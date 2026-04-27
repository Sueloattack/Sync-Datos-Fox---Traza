<?php
// api/reporte_consolidado.php
// Endpoint para generar el reporte consolidado mensual (Ingreso, Respuesta, Radicación)

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once '../helpers/gema_api_client.php';

try {
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

    // Validar formato de fechas
    if (!$fecha_inicio || !$fecha_fin) {
        throw new Exception("Fechas inválidas.");
    }

    $f_ini_vfp = "{^{$fecha_inicio}}";
    $f_fin_vfp = "{^{$fecha_fin}}";

    // Estructura de respuesta: [ 'Enero 2024' => ['ingreso' => [...], 'respuesta' => [...], 'radicacion' => [...]]]
    $consolidado = [];

    // Helper para obtener llave de mes (Ej: "2024-01") dada una fecha Y-m-d
    function getMonthKey($dateStr) {
        if (!$dateStr) return 'Sin Fecha';
        $time = strtotime($dateStr);
        return date('Y-m', $time); 
    }


    // Helper for Event Logic
    function processEvents($rawData, &$consolidado, $type, $primaryStatuses) {
        // 1. Group by Month -> Docn -> Date
        $grouped = [];
        
        foreach ($rawData as $row) {
            $y = $row['anio']; 
            $m = str_pad($row['mes'], 2, '0', STR_PAD_LEFT);
            $mesKey = "{$y}-{$m}";
            
            $docn = trim($row['gl_docn']);
            $fecha_gl = trim($row['fecha_gl']);
            $status = trim($row['estatus1']);
            $cantItems = (int)$row['cantidad'];
            $monto = (float)$row['monto'];

            if (!isset($consolidado[$mesKey])) initMonth($consolidado, $mesKey);

            // Accumulate Total Amount (Sum of all items)
            $consolidado[$mesKey][$type]['monto'] += $monto;

            // Group for Event Counting
            $grouped[$mesKey][$docn][$fecha_gl][] = ['status' => $status, 'monto' => $monto];
        }

        // 2. Count Events & Populate Breakdown
        foreach ($grouped as $mesKey => $docs) {
            foreach ($docs as $docn => $fechas) {
                foreach ($fechas as $date => $items) {
                    // Extract unique statutes for this Doc+Date
                    $uniquePrimaryStatuses = [];
                    $secondaryItems = [];
                    // Keep track of secondary amount to distribute
                    $secondaryMonto = 0;

                    foreach ($items as $item) {
                        if (in_array($item['status'], $primaryStatuses)) {
                            $uniquePrimaryStatuses[$item['status']] = true;
                        } else {
                            $secondaryItems[] = $item;
                            $secondaryMonto += $item['monto'];
                        }
                    }

                    if (!empty($uniquePrimaryStatuses)) {
                        // Found Primary Events.
                        // We need to distribute the Secondary Amount (e.g. AE accepted parts) 
                        // to the Primary Statuses so the totals match.
                        // Strategy: Add Secondary Amount to the FIRST Primary Status found.
                        // This assumes the "Event" is defined by the Primary Status.
                        
                        $primaryKeys = array_keys($uniquePrimaryStatuses);
                        $firstPrimary = $primaryKeys[0];

                        foreach ($primaryKeys as $pStatus) {
                            $consolidado[$mesKey][$type]['cantidad']++; // Total Event Count
                            
                            if (!isset($consolidado[$mesKey][$type]['breakdown'][$pStatus])) {
                                $consolidado[$mesKey][$type]['breakdown'][$pStatus] = ['cantidad' => 0, 'monto' => 0];
                            }
                            $consolidado[$mesKey][$type]['breakdown'][$pStatus]['cantidad']++;
                            
                            // Add amounts of items belonging specifically to this Primary Status
                            foreach ($items as $itm) {
                                if ($itm['status'] === $pStatus) {
                                    $consolidado[$mesKey][$type]['breakdown'][$pStatus]['monto'] += $itm['monto'];
                                }
                            }
                            
                            // If this is the "Main" (first) primary status, add the secondary amount here
                            // so it is not lost.
                            if ($pStatus === $firstPrimary) {
                                $consolidado[$mesKey][$type]['breakdown'][$pStatus]['monto'] += $secondaryMonto;
                            }
                        }
                    } else {
                        // Orphan Event (Only Secondaries). Counts as 1 Event.
                        $consolidado[$mesKey][$type]['cantidad']++;
                        
                        // Assign entire amount to the "Orphan" status (e.g. first found, or 'ACEPTADA')
                        $orphanStatus = !empty($secondaryItems) ? $secondaryItems[0]['status'] : 'ACEPTADA';
                        
                        if (!isset($consolidado[$mesKey][$type]['breakdown'][$orphanStatus])) {
                            $consolidado[$mesKey][$type]['breakdown'][$orphanStatus] = ['cantidad' => 0, 'monto' => 0];
                        }
                        $consolidado[$mesKey][$type]['breakdown'][$orphanStatus]['cantidad']++;
                        
                        // Sum of all items (they are all secondary here)
                        $totalGroupMonto = 0;
                        foreach($items as $itm) $totalGroupMonto += $itm['monto'];
                        
                        $consolidado[$mesKey][$type]['breakdown'][$orphanStatus]['monto'] += $totalGroupMonto;
                    }
                }
            }
        }
    }

    // Helper: Split date range into chunks of N days
    function getChunks($startDate, $endDate, $days = 10) {
        $chunks = [];
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        while ($start <= $end) {
            $chunkEnd = clone $start;
            $chunkEnd->modify("+{$days} days");
            
            if ($chunkEnd > $end) {
                $chunkEnd = $end;
            }
            
            $chunks[] = [
                'start' => $start->format('Y-m-d'),
                'end' => $chunkEnd->format('Y-m-d')
            ];
            
            $start = clone $chunkEnd;
            $start->modify('+1 day');
        }
        return $chunks;
    }

    // Helper: Execute Query in Chunks OR Single Shot
    function chunkedQuery($sqlTemplate, $startStr, $endStr) {
        $allData = [];
        
        $start = new DateTime($startStr);
        $end = new DateTime($endStr);
        $diff = $start->diff($end)->days;

        // If range is reasonable (<= 45 days), execute in one go to preserve Event integrity (grouping)
        // and avoid splitting invoices that might span a chunk boundary (e.g. at day 15).
        // Since frontend now requests Month-by-Month, this will typically trigger single-shot.
        if ($diff <= 45) {
             $cStart = "{^{$startStr}}";
             $cEnd = "{^{$endStr}}";
             $sql = str_replace(['{START_DATE}', '{END_DATE}'], [$cStart, $cEnd], $sqlTemplate);
             return queryApiGema($sql) ?: [];
        }

        // Otherwise, use chunks (fallback for large ranges if called directly)
        $chunks = getChunks($startStr, $endStr, 20); // Increased chunk size slightly
        
        foreach ($chunks as $chunk) {
            $cStart = "{^{$chunk['start']}}";
            $cEnd = "{^{$chunk['end']}}";
            
            // Replace placeholders in SQL
            $sql = str_replace(['{START_DATE}', '{END_DATE}'], [$cStart, $cEnd], $sqlTemplate);
            
            $chunkData = queryApiGema($sql);
            if ($chunkData) {
                $allData = array_merge($allData, $chunkData);
            }
        }
        return $allData;
    }

    // --- 1. INGRESO ---
    // Estatus Primarios
    $primariosIngreso = ['NU', 'R2', 'R3', 'R4']; 
    
    // SQL Template for Chunking
    // Removed GROUP BY: We need individual rows for Event logic in PHP. 
    // Sending ~15k rows per chunk is faster than VFP trying to group 72k rows.
    $sql_ingreso_tpl = "
        YEAR(freg) as anio, MONTH(freg) as mes, gl_docn, estatus1, fecha_gl, 1 as cantidad, vr_glosa as monto
        FROM gema10.d/salud/datos/glo_det 
        WHERE (estatus1 = 'NU' OR estatus1 = 'R2' OR estatus1 = 'R3' OR estatus1 = 'R4' OR estatus1 = 'AE') 
        AND freg BETWEEN {START_DATE} AND {END_DATE}
    ";
    
    // Chunked Execution
    $data_ingreso = chunkedQuery($sql_ingreso_tpl, $fecha_inicio, $fecha_fin);
    processEvents($data_ingreso, $consolidado, 'ingreso', $primariosIngreso);


    // --- 2. RESPUESTA ---
    $primariosRespuesta = ['C1', 'C2', 'C3', 'CO'];

    $sql_respuesta_tpl = "
        YEAR(freg) as anio, MONTH(freg) as mes, gl_docn, estatus1, fecha_gl, 1 as cantidad, vr_glosa as monto
        FROM gema10.d/salud/datos/glo_det 
        WHERE (estatus1 = 'C1' OR estatus1 = 'C2' OR estatus1 = 'C3' OR estatus1 = 'CO' OR estatus1 = 'AI') 
        AND freg BETWEEN {START_DATE} AND {END_DATE}
    ";

    $data_respuesta = chunkedQuery($sql_respuesta_tpl, $fecha_inicio, $fecha_fin);
    processEvents($data_respuesta, $consolidado, 'respuesta', $primariosRespuesta);



    // 3. RADICACION ---
    // Lógica similar a reporte_erp.php
    
    $sql_radicacion_tpl = "
        rec.gr_docn, rec.freg, rec.vr_tace, rec.vr_tref, rec.vr_tcon, rec.observac, 
        red.fc_serie, red.fc_docn, red.estatus1 
        FROM gema10.d/salud/datos/glo_rec rec 
        LEFT JOIN gema10.d/salud/datos/glo_red red ON rec.gr_docn = red.gr_docn 
        WHERE BETWEEN(rec.freg, {START_DATE}, {END_DATE}) 
        AND NOT ('ANULADO' $ UPPER(rec.observac) OR 'ANULADA' $ UPPER(rec.observac)) 
        AND !EMPTY(rec.fecha_rep)
    ";

    $data_radicacion = chunkedQuery($sql_radicacion_tpl, $fecha_inicio, $fecha_fin);

    $seen_rad_headers = []; // Para no sumar montos doble vez al total general (Header)
    $seen_rad_invoices = []; // Para no contar facturas doble vez (Detail)

    foreach ($data_radicacion as $row) {
        $gr_docn = trim($row['gr_docn']);
        if (empty($gr_docn)) continue;

        $mes = getMonthKey($row['freg']);
        if (!isset($consolidado[$mes])) initMonth($consolidado, $mes);
        
        // Inicializar breakdown para este Cta Cobro si no existe
        if (!isset($consolidado[$mes]['radicacion']['breakdown'][$gr_docn])) {
            $consolidado[$mes]['radicacion']['breakdown'][$gr_docn] = ['cantidad' => 0, 'monto' => 0];
        }

        // Sumar Montos (Solo una vez por Documento de Radicacion - Header)
        if (!isset($seen_rad_headers[$gr_docn])) {
            $seen_rad_headers[$gr_docn] = true;
            $monto_total = (float)$row['vr_tace'] + (float)$row['vr_tref'] + (float)$row['vr_tcon'];
            
            // Total Vertical (Mes) - Suma de cuentas de cobro
            $consolidado[$mes]['radicacion']['monto'] += $monto_total;
            
            // Item Breakdown (Cta Cobro)
            $consolidado[$mes]['radicacion']['breakdown'][$gr_docn]['monto'] += $monto_total;
        }

        // Contar Facturas (Unicas - Detail)
        // Match logic from reporte_erp.php: fc_serie + fc_docn + estatus1
        if (!empty($row['fc_serie'])) {
            $factura_key = trim($row['fc_serie']) . trim($row['fc_docn']) . trim($row['estatus1']);
            // Uniqueness is per Account (gr_docn) + Invoice Key
            $unique_key = $gr_docn . '_' . $factura_key;
            
            if (!isset($seen_rad_invoices[$unique_key])) {
                $seen_rad_invoices[$unique_key] = true;
                
                // Total Vertical (Mes)
                $consolidado[$mes]['radicacion']['cantidad']++;
                
                // Item Breakdown (Cta Cobro)
                $consolidado[$mes]['radicacion']['breakdown'][$gr_docn]['cantidad']++;
            }
        }
    }

    // --- ORDENAR POR MES ---
    ksort($consolidado);

    echo json_encode(['status' => 'ok', 'data' => $consolidado]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Función auxiliar para inicializar estructura del mes
function initMonth(&$array, $mesKey) {
    $array[$mesKey] = [
        'nombre_mes' => $mesKey,
        'ingreso' => ['cantidad' => 0, 'monto' => 0, 'breakdown' => []],
        'respuesta' => ['cantidad' => 0, 'monto' => 0, 'breakdown' => []],
        'radicacion' => ['cantidad' => 0, 'monto' => 0, 'breakdown' => []] // Radicacion breakdown might be empty for now
    ];
}
?>
