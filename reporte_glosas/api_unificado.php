<?php
/**
 * reporte_glosas/api_unificado.php
 * Endpoint AJAX: Reporte Unificado (comprime todos los items en una sola fila por factura)
 * En este modo, los AI SE CUENTAN como valor aceptado.
 * Produccion: conexion directa ODBC a FoxPro/GEMA via conectFox.php
 */

require_once __DIR__ . '/config/conectFox.php';

header('Content-Type: application/json');

if (!isset($_GET['action']) || !in_array($_GET['action'], ['fetch_unified', 'search_unified'])) {
    echo json_encode(['status' => 'error', 'message' => 'Accion no valida']);
    exit;
}

/**
 * Mapear codigo numerico a concepto segun rangos
 */
function mapCodigoToConcepto($codigo) {
    $num = (int)$codigo;
    if ($num >= 100 && $num <= 199) return 'FACTURACION';
    if ($num >= 200 && $num <= 299) return 'TARIFAS';
    if ($num >= 300 && $num <= 399) return 'SOPORTES';
    if ($num >= 400 && $num <= 499) return 'AUTORIZACION';
    if ($num >= 500 && $num <= 599) return 'COBERTURA';
    if ($num >= 600 && $num <= 699) return 'PERTINENCIA';
    if ($num >= 800 && $num <= 899) return 'DEVOLUCION';
    return 'OTRO (' . $codigo . ')';
}

/**
 * Limpia fecha FoxPro nula
 */
function cleanDate($fecha) {
    $f = trim($fecha ?? '');
    if (empty($f) || strpos($f, '1899-12-30') !== false) return '';
    return $f;
}

/**
 * Combina motivos: si hay textos diferentes, los separa por ;
 */
function mergeMotivos($motivos_arr) {
    $unicos = array_unique(array_filter(array_map('trim', $motivos_arr)));
    return implode('; ', $unicos);
}

try {
    $pdo = ConnectionFox::con();
    $action = $_GET['action'] ?? '';
    $cabs = [];

    if ($action === 'fetch_unified') {
        $serie = strtoupper($_GET['serie'] ?? '');
        $numero = (int)($_GET['numero'] ?? 0);

        if (empty($serie) || empty($numero)) {
            echo json_encode(['status' => 'error', 'message' => 'Faltan parametros']);
            exit;
        }

        $sql_cab = "SELECT gl_docn, fc_serie, fc_docn, tercero, tipo, freg, vr_fra FROM glo_cab WHERE fc_serie = '$serie' AND fc_docn = $numero ORDER BY freg DESC";
        $cabs = $pdo->query($sql_cab)->fetchAll(PDO::FETCH_ASSOC);

    } else if ($action === 'search_unified') {
        $nit = trim($_GET['nit'] ?? '');
        $fecha_desde = trim($_GET['fecha_desde'] ?? '');
        $fecha_hasta = trim($_GET['fecha_hasta'] ?? '');

        if (empty($nit) || empty($fecha_desde) || empty($fecha_hasta)) {
            echo json_encode(['status' => 'error', 'message' => 'NIT y Rango de fechas son obligatorios']);
            exit;
        }

        // tercero es campo NUMERICO en glo_cab, sin comillas.
        // Fechas con literales {^YYYY-MM-DD} para compatibilidad con FoxPro ODBC.
        $nit_num = intval($nit);
        $sql_cab = "SELECT gl_docn, fc_serie, fc_docn, tercero, tipo, freg, vr_fra FROM glo_cab WHERE tercero = $nit_num AND gl_fecha >= {^$fecha_desde} AND gl_fecha <= {^$fecha_hasta} ORDER BY freg DESC";
        $cabs = $pdo->query($sql_cab)->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($cabs)) {
        echo json_encode(['status' => 'ok', 'found' => false]);
        exit;
    }

    // --- Aplicar jerarquia/filtrado por FACTURA ---
    // Agrupamos por factura (serie+docn) para aplicar la logica GP > GT > DV
    $grupos_factura = [];
    foreach ($cabs as $c) {
        $key = trim($c['fc_serie']) . trim($c['fc_docn']);
        if (!isset($grupos_factura[$key])) $grupos_factura[$key] = [];
        $grupos_factura[$key][] = $c;
    }

    $selected_cabs = [];
    foreach ($grupos_factura as $factura_items) {
        $gps = array_filter($factura_items, fn($c) => strtoupper(trim($c['tipo'])) === 'GP');
        $gts = array_filter($factura_items, fn($c) => strtoupper(trim($c['tipo'])) === 'GT');
        $dvs = array_filter($factura_items, fn($c) => strtoupper(trim($c['tipo'])) === 'DV');
        $otros = array_filter($factura_items, fn($c) => !in_array(strtoupper(trim($c['tipo'])), ['GP', 'GT', 'DV']));

        // REGLA: Mostrar TODAS las devoluciones
        if (!empty($dvs)) {
            $dvs_ordered = array_reverse(array_values($dvs));
            $total_dvs = count($dvs_ordered);
            foreach ($dvs_ordered as $i => $dv) {
                $dv['tipo_unificado'] = ($total_dvs > 1) ? "DV-" . ($i + 1) : "DV";
                $selected_cabs[] = $dv;
            }
        }

        // REGLA: Mostrar el "Principal" (GP > GT > Otros)
        if (!empty($gps)) {
            $selected_cabs[] = reset($gps);
        } elseif (!empty($gts)) {
            $selected_cabs[] = reset($gts);
        } elseif (!empty($otros)) {
            $selected_cabs[] = reset($otros);
        }
    }

    $filas = [];
    foreach ($selected_cabs as $cab) {
        $gl_docn = (int)$cab['gl_docn'];

        // Detalles directos por ODBC
        $sql_det = "SELECT codigo, estatus1, vr_glosa, vr_acep, motivo_res, fecha_gl, gr_docn, fecha_rep, cod_resp FROM glo_det WHERE gl_docn = $gl_docn ORDER BY freg, fecha_gl";
        $detalles = $pdo->query($sql_det)->fetchAll(PDO::FETCH_ASSOC);

        // Si vr_fra no vino en el cab (posible en search_unified), lo buscamos
        if (!isset($cab['vr_fra']) || $cab['vr_fra'] == 0) {
            $sql_fra = "SELECT vr_fra FROM glo_cab WHERE gl_docn = $gl_docn";
            $res_fra = $pdo->query($sql_fra)->fetch(PDO::FETCH_ASSOC);
            if ($res_fra) $cab['vr_fra'] = $res_fra['vr_fra'];
        }

        // Nombre del tercero
        if (!isset($cab['nom_terce'])) {
            $tercero_nit = trim($cab['tercero'] ?? '');
            $cab['nom_terce'] = '';
            if (!empty($tercero_nit)) {
                $nit_numeric = (int)$tercero_nit;
                if ($nit_numeric > 0) {
                    $rutas = [
                        "terceros",
                        "terceros"
                    ];
                    foreach ($rutas as $ruta) {
                        try {
                            $sql_t = "SELECT nombre FROM $ruta WHERE codigo = $nit_numeric";
                            $terc = $pdo->query($sql_t)->fetch(PDO::FETCH_ASSOC);
                            if ($terc && !empty(trim($terc['nombre'] ?? ''))) {
                                $cab['nom_terce'] = $terc['nombre'];
                                break;
                            }
                        } catch (Exception $e) {}
                    }
                }
            }
        }

        // --- Logica de compresion para este gl_docn ---
        $statuses_interes = ['NU', 'R2', 'R3', 'R4', 'C1', 'C2', 'C3', 'CO', 'AI', 'AE'];
        $por_status = [];
        foreach ($statuses_interes as $st) {
            $por_status[$st] = [
                'codigos' => [], 'vr_glosa' => 0, 'vr_acep' => 0, 'motivos' => [],
                'fecha_gl' => '', 'fecha_rep' => '', 'cod_resp' => [], 'gr_docn' => '',
                'vr_acep_etapa' => 0, 'motivos_acep_etapa' => [],
            ];
        }

        $current_stage = 'NU';
        foreach ($detalles as $d) {
            $st = strtoupper(trim($d['estatus1'] ?? ''));
            if (!in_array($st, $statuses_interes)) continue;
            if ($st === 'R2') $current_stage = 'R2';
            if ($st === 'R3') $current_stage = 'R3';
            if ($st === 'R4') $current_stage = 'R4';

            $codigo = trim($d['codigo'] ?? '');
            if (!empty($codigo) && !in_array($codigo, $por_status[$st]['codigos'])) {
                $por_status[$st]['codigos'][] = $codigo;
            }
            $por_status[$st]['vr_glosa'] += (float)($d['vr_glosa'] ?? 0);
            $por_status[$st]['vr_acep']  += (float)($d['vr_acep'] ?? 0);
            if ($st === 'AI') {
                $por_status[$current_stage]['vr_acep_etapa'] += (float)($d['vr_acep'] ?? 0);
                $ma = trim($d['motivo_res'] ?? '');
                if (!empty($ma)) $por_status[$current_stage]['motivos_acep_etapa'][] = $ma;
            }
            $motivo = trim($d['motivo_res'] ?? '');
            if (!empty($motivo)) $por_status[$st]['motivos'][] = $motivo;
            $fecha_gl = cleanDate($d['fecha_gl'] ?? '');
            if (!empty($fecha_gl) && empty($por_status[$st]['fecha_gl'])) $por_status[$st]['fecha_gl'] = $fecha_gl;
            $fecha_rep = cleanDate($d['fecha_rep'] ?? '');
            if (!empty($fecha_rep) && empty($por_status[$st]['fecha_rep'])) $por_status[$st]['fecha_rep'] = $fecha_rep;
            $cod_resp_val = $d['cod_resp'] ?? '';
            $cod_resp_str = is_numeric($cod_resp_val) ? (string)(int)$cod_resp_val : trim($cod_resp_val);
            if (!empty($cod_resp_str) && $cod_resp_str !== '0' && !in_array($cod_resp_str, $por_status[$st]['cod_resp'])) {
                $por_status[$st]['cod_resp'][] = $cod_resp_str;
            }
            $gr_docn = trim($d['gr_docn'] ?? '');
            if (!empty($gr_docn) && empty($por_status[$st]['gr_docn'])) $por_status[$st]['gr_docn'] = $gr_docn;
        }

        $all_codigos = [];
        foreach ($por_status as $data) {
            foreach ($data['codigos'] as $c) if (!in_array($c, $all_codigos)) $all_codigos[] = $c;
        }
        $conceptos = [];
        foreach ($all_codigos as $c) {
            $concepto = mapCodigoToConcepto($c);
            if (!in_array($concepto, $conceptos)) $conceptos[] = $concepto;
        }

        foreach ($por_status as $st => &$data) {
            $data['motivo_merged'] = mergeMotivos($data['motivos']);
            $data['motivo_acep_merged'] = mergeMotivos($data['motivos_acep_etapa']);
            $data['cod_resp_merged'] = implode(', ', $data['cod_resp']);
        }
        unset($data);

        $has_r4 = !empty($por_status['R4']['fecha_gl']);

        $filas[] = [
            'factura' => trim($cab['fc_serie'] ?? '') . trim($cab['fc_docn'] ?? ''),
            'nit' => trim($cab['tercero'] ?? ''),
            'tercero_nombre' => trim($cab['nom_terce'] ?? ''),
            'codigo' => implode(', ', $all_codigos),
            'concepto' => implode(', ', $conceptos),
            'tipo' => $cab['tipo_unificado'] ?? trim($cab['tipo'] ?? ''),
            'valor_factura' => (float)($cab['vr_fra'] ?? 0),

            'nu_fecha_ingreso' => $por_status['NU']['fecha_gl'],
            'nu_valor_glosa' => $por_status['NU']['vr_glosa'],
            'nu_motivo' => $por_status['NU']['motivo_merged'],
            'nu_fecha_resp' => $por_status['C1']['fecha_gl'] ?: $por_status['AI']['fecha_gl'],
            'nu_valor_acep' => $por_status['NU']['vr_acep_etapa'],
            'nu_motivo_acep' => $por_status['NU']['motivo_acep_merged'],
            'nu_cod_resp' => $por_status['C1']['cod_resp_merged'] ?: $por_status['AI']['cod_resp_merged'],

            'c1_motivo' => $por_status['C1']['motivo_merged'],
            'c1_cuenta_cobro' => $por_status['C1']['gr_docn'],
            'c1_fecha_radicado' => $por_status['C1']['fecha_rep'],

            'r2_fecha_ingreso' => $por_status['R2']['fecha_gl'],
            'r2_valor_glosa' => $por_status['R2']['vr_glosa'],
            'r2_motivo' => $por_status['R2']['motivo_merged'],
            'r2_fecha_resp' => !empty($por_status['R2']['fecha_gl']) ? $por_status['C2']['fecha_gl'] : '',
            'r2_valor_acep' => $por_status['R2']['vr_acep_etapa'],
            'r2_motivo_acep' => $por_status['R2']['motivo_acep_merged'],
            'r2_cod_resp' => !empty($por_status['R2']['fecha_gl']) ? $por_status['C2']['cod_resp_merged'] : '',

            'c2_motivo' => $por_status['C2']['motivo_merged'],
            'c2_cuenta_cobro' => $por_status['C2']['gr_docn'],
            'c2_fecha_radicado' => $por_status['C2']['fecha_rep'],

            'r3_fecha_ingreso' => $por_status['R3']['fecha_gl'],
            'r3_valor_glosa' => $por_status['R3']['vr_glosa'],
            'r3_motivo' => $por_status['R3']['motivo_merged'],
            'r3_fecha_resp' => !empty($por_status['R3']['fecha_gl']) ? ($por_status['C3']['fecha_gl'] ?: $por_status['CO']['fecha_gl']) : '',
            'r3_valor_acep' => $por_status['R3']['vr_acep_etapa'],
            'r3_motivo_acep' => $por_status['R3']['motivo_acep_merged'],
            'r3_cod_resp' => !empty($por_status['R3']['fecha_gl']) ? ($por_status['C3']['cod_resp_merged'] ?: $por_status['CO']['cod_resp_merged']) : '',

            'c3_motivo' => mergeMotivos(array_merge($por_status['C3']['motivos'], (!$has_r4 ? $por_status['CO']['motivos'] : []))),
            'c3_cuenta_cobro' => $por_status['C3']['gr_docn'] ?: (!$has_r4 ? $por_status['CO']['gr_docn'] : ''),
            'c3_fecha_radicado' => $por_status['C3']['fecha_rep'] ?: (!$has_r4 ? $por_status['CO']['fecha_rep'] : ''),

            'r4_fecha_ingreso' => $por_status['R4']['fecha_gl'],
            'r4_valor_glosa' => $por_status['R4']['vr_glosa'],
            'r4_motivo' => $por_status['R4']['motivo_merged'],
            'r4_fecha_resp' => !empty($por_status['R4']['fecha_gl']) ? $por_status['CO']['fecha_gl'] : '',
            'r4_valor_acep' => $por_status['R4']['vr_acep_etapa'],
            'r4_motivo_acep' => $por_status['R4']['motivo_acep_merged'],
            'r4_cod_resp' => !empty($por_status['R4']['fecha_gl']) ? $por_status['CO']['cod_resp_merged'] : '',

            'co_motivo' => $has_r4 ? $por_status['CO']['motivo_merged'] : '',
        ];
    }

    // Limpiar caracteres no UTF-8
    $utf8ize = function($mixed) use (&$utf8ize) {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = $utf8ize($value);
            }
        } elseif (is_string($mixed)) {
            return mb_convert_encoding($mixed, "UTF-8", "ISO-8859-1");
        }
        return $mixed;
    };

    echo json_encode($utf8ize([
        'status' => 'ok',
        'found' => true,
        'filas' => $filas
    ]));

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
exit;
