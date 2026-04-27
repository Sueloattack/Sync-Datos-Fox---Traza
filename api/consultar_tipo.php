<?php
/**
 * api/consultar_tipo.php
 * Endpoint para consultar el tipo de glosa (GT, GP, DV, etc.) en GEMA.
 * Soporta consultas individuales y por lotes.
 */

require_once __DIR__ . '/../helpers/gema_api_client.php';

header('Content-Type: application/json');

/**
 * Separa el prefijo (letras) del número de la factura.
 * Ej: "FCR123456" -> ["FCR", "123456"]
 */
function extraerPrefijoNumero($factura) {
    if (preg_match('/^([A-Za-z]+)(\d+)$/', trim($factura), $matches)) {
        return [strtoupper($matches[1]), $matches[2]];
    }
    return [null, null];
}

try {
    // Aceptar datos tanto por GET (individual) como por POST (lote)
    $invoices = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['invoices']) && is_array($input['invoices'])) {
            $invoices = $input['invoices'];
        }
    } else {
        if (isset($_GET['invoice'])) {
            $invoices = [$_GET['invoice']];
        }
    }

    if (empty($invoices)) {
        echo json_encode(['status' => 'error', 'message' => 'No se proporcionaron facturas']);
        exit;
    }

    $resultados = [];
    foreach ($invoices as $factura) {
        $factura = trim($factura);
        list($prefijo, $numero) = extraerPrefijoNumero($factura);

        if (!$prefijo || !$numero) {
            $resultados[] = [
                'factura' => $factura,
                'tipo' => 'ERROR_FORMATO',
                'success' => false
            ];
            continue;
        }

        try {
            // Consultamos la glosa más reciente para esta factura
            $sql = "tipo FROM [gema10.d/salud/datos/glo_cab] WHERE fc_serie = '$prefijo' AND fc_docn = $numero ORDER BY freg DESC";
            $data = queryApiGema($sql);

            if ($data && count($data) > 0) {
                $resultados[] = [
                    'factura' => $factura,
                    'tipo' => trim($data[0]['tipo'] ?? 'S/T'),
                    'success' => true
                ];
            } else {
                $resultados[] = [
                    'factura' => $factura,
                    'tipo' => 'NO_ENCONTRADA',
                    'success' => false
                ];
            }
        } catch (Exception $e) {
            $resultados[] = [
                'factura' => $factura,
                'tipo' => 'ERROR_API',
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => $resultados
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
