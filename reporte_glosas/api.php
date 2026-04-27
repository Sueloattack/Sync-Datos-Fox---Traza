<?php
/**
 * reporte_glosas/api.php
 * Endpoint AJAX: fetch_invoice (Malla Original por ítem)
 * Produccion: conexion directa ODBC a FoxPro/GEMA via conectFox.php
 */

require_once __DIR__ . '/config/conectFox.php';

header('Content-Type: application/json');

if (!isset($_GET['action']) || $_GET['action'] !== 'fetch_invoice') {
    echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
    exit;
}

try {
    $pdo = ConnectionFox::con();

    $serie = strtoupper($_GET['serie'] ?? '');
    $numero = (int)($_GET['numero'] ?? 0);
    $solo_activas = ($_GET['solo_activas'] ?? 'false') === 'true';

    if (empty($serie) || empty($numero)) {
        echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros']);
        exit;
    }

    $sql_cab = "SELECT gl_docn, vr_fra, gl_fecha, radicacion FROM glo_cab WHERE fc_serie = '$serie' AND fc_docn = $numero";
    $cab = $pdo->query($sql_cab)->fetch(PDO::FETCH_ASSOC);

    if (!$cab) {
        echo json_encode(['status' => 'ok', 'found' => false]);
        exit;
    }

    $gl_docn = (int)$cab['gl_docn'];
    $sql_det = "SELECT codigo, marca, referencia, estatus1, motivo_res, vr_glosa, gr_docn, fecha_rep FROM glo_det WHERE gl_docn = $gl_docn";
    $detalles = $pdo->query($sql_det)->fetchAll(PDO::FETCH_ASSOC);

    // Procesamiento de malla por item
    $malla_por_item = [];
    foreach ($detalles as $d) {
        $cod = trim($d['codigo']);
        $mar = trim($d['marca'] ?? '');
        $ref = trim($d['referencia'] ?? '');
        $val = (float)$d['vr_glosa'];

        $item_key = "{$cod}_{$mar}_{$ref}_{$val}";

        if (!isset($malla_por_item[$item_key])) {
            $malla_por_item[$item_key] = [
                'codigo' => $cod,
                'marca' => $mar,
                'referencia' => $ref,
                'valor_glosa' => $val,
                'historia' => [],
                'statuses' => []
            ];
        }

        $cc = trim($d['gr_docn'] ?? '');
        $fr = trim($d['fecha_rep'] ?? '');
        if (empty($fr) || strpos($fr, '1899-12-30') !== false) $fr = 'No tiene';
        if (empty($cc)) $cc = 'No tiene';

        $st = strtoupper(trim($d['estatus1']));

        $malla_por_item[$item_key]['historia'][$st] = [
            'motivo_texto' => trim($d['motivo_res']),
            'gr_docn' => $cc,
            'fecha_rep' => $fr
        ];
        $malla_por_item[$item_key]['statuses'][] = $st;
    }

    // Filtrar y Formatear filas finales
    $filas_finales = [];
    foreach ($malla_por_item as $item) {
        if ($solo_activas) {
            $ha_llegado_final = in_array('CO', $item['statuses']) || in_array('C3', $item['statuses']);
            if (!$ha_llegado_final && (in_array('AI', $item['statuses']) || in_array('AE', $item['statuses']))) {
                continue;
            }
        }

        $row = [
            'factura' => $serie . $numero,
            'v_fra' => (float)($cab['vr_fra'] ?? 0),
            'radicacion' => ($cab['gl_fecha'] ?? '') ?: ($cab['radicacion'] ?? ''),
            'item' => $item['codigo'],
            'v_glosa' => (float)$item['valor_glosa'],
            'NU' => $item['historia']['NU']['motivo_texto'] ?? '',
            'C1' => $item['historia']['C1']['motivo_texto'] ?? '',
            'C1_CC' => $item['historia']['C1']['gr_docn'] ?? 'No tiene',
            'C1_FR' => $item['historia']['C1']['fecha_rep'] ?? 'No tiene',
            'R2' => $item['historia']['R2']['motivo_texto'] ?? '',
            'C2' => $item['historia']['C2']['motivo_texto'] ?? '',
            'C2_CC' => $item['historia']['C2']['gr_docn'] ?? 'No tiene',
            'C2_FR' => $item['historia']['C2']['fecha_rep'] ?? 'No tiene',
            'R3' => $item['historia']['R3']['motivo_texto'] ?? '',
            'C3' => ($item['historia']['C3']['motivo_texto'] ?? '') ?: ($item['historia']['CO']['motivo_texto'] ?? ''),
            'C3_CC' => ($item['historia']['C3']['gr_docn'] ?? '') ?: ($item['historia']['CO']['gr_docn'] ?? 'No tiene'),
            'C3_FR' => ($item['historia']['C3']['fecha_rep'] ?? '') ?: ($item['historia']['CO']['fecha_rep'] ?? 'No tiene'),
            'R4' => $item['historia']['R4']['motivo_texto'] ?? '',
            'CO' => $item['historia']['CO']['motivo_texto'] ?? ''
        ];
        $filas_finales[] = $row;
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
        'cab' => $cab,
        'items' => $filas_finales
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
