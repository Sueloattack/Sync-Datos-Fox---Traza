<?php
/**
 * debug_listado_entidades.php
 * Script aislado para traer el nombre de la entidad (tercero) 
 * y los códigos de respuesta (cod_resp) de un listado específico.
 */

require_once __DIR__ . '/helpers/gema_api_client.php';

$facturas = [
    'FECR344568', 'COEX28768', 'COEX28896', 'COEX29020', 'FECR328785',
    'FECR329834', 'COEX29020', 'FECR330997', 'FECR348913', 'FECR349884',
    'COEX31571', 'COEX32178', 'FECR357906', 'FECR358284', 'FECR358579',
    'FECR358582', 'FECR358992', 'COEX28862', 'FECR345981', 'FECR346974',
    'FECR347069', 'FECR345446'
];

echo "<!DOCTYPE html><html><head><title>Debug Entidades</title>";
echo "<style>table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ccc;padding:8px;text-align:left;} th{background:#f4f4f4;}</style>";
echo "</head><body>";
echo "<h1>Resultados de Consulta GEMA</h1>";
echo "<table><thead><tr><th>Factura</th><th>NIT</th><th>Entidad (Tercero)</th><th>Codigos Resp (cod_resp)</th></tr></thead><tbody>";

foreach ($facturas as $f) {
    preg_match('/^([A-Z]+)(\d+)$/', strtoupper($f), $matches);
    if (!$matches) {
        echo "<tr><td>$f</td><td colspan='3' style='color:red'>Formato inválido</td></tr>";
        continue;
    }

    $serie = $matches[1];
    $numero = $matches[2];

    // 1. Buscar en glo_cab
    $sql_cab = "gl_docn, tercero FROM gema10.d/salud/datos/glo_cab WHERE fc_serie = '$serie' AND fc_docn = $numero";
    $res_cab = queryApiGema($sql_cab);

    if (empty($res_cab)) {
        echo "<tr><td>$f</td><td colspan='3' style='color:orange'>No encontrada en glo_cab</td></tr>";
        continue;
    }

    $cab = $res_cab[0];
    $gl_docn = $cab['gl_docn'];
    $nit = trim($cab['tercero']);
    $entidad = "N/A";

    // 2. Buscar Entidad con Fallback (codigo suele ser NUMERICO en GEMA)
    $nit_numeric = (int)$nit;
    $entidad = "N/A";
    
    if ($nit_numeric > 0) {
        $intentos = [
            "nombre FROM gema10.d/salud/datos/terceros WHERE codigo = $nit_numeric",
            "nombre FROM gema10.d/dgen/datos/terceros WHERE codigo = $nit_numeric",
            "nom_terce FROM gema10.d/salud/datos/terceros WHERE codigo = $nit_numeric"
        ];
        
        foreach ($intentos as $sql) {
            try {
                $res_terc = queryApiGema($sql);
                if (!empty($res_terc)) {
                    $key = isset($res_terc[0]['nombre']) ? 'nombre' : 'nom_terce';
                    $entidad = $res_terc[0][$key];
                    if (!empty(trim($entidad))) break;
                }
            } catch (Exception $e) {}
        }
    }
    
    // Si sigue N/A, intentar como string por si acaso (algunos NITs tienen letras o guiones)
    if ($entidad === "N/A" || empty(trim($entidad))) {
         try {
            $sql_terc = "nombre FROM gema10.d/dgen/datos/terceros WHERE codigo = '$nit'";
            $res_terc = queryApiGema($sql_terc);
            if (!empty($res_terc)) $entidad = $res_terc[0]['nombre'];
        } catch (Exception $e) {}
    }

    // 3. Buscar cod_resp en glo_det
    $sql_det = "cod_resp FROM gema10.d/salud/datos/glo_det WHERE gl_docn = $gl_docn";
    $res_det = queryApiGema($sql_det);
    $codigos = [];
    if (!empty($res_det)) {
        foreach ($res_det as $rd) {
            $c = trim($rd['cod_resp']);
            if ($c !== "" && $c !== "0") {
                $codigos[] = (int)$c;
            }
        }
    }
    $codigos_unicos = array_unique($codigos);
    sort($codigos_unicos);

    echo "<tr>";
    echo "<td>$f</td>";
    echo "<td>$nit</td>";
    echo "<td>" . mb_convert_encoding($entidad, "UTF-8", "ISO-8859-1") . "</td>";
    echo "<td>" . implode(', ', $codigos_unicos) . "</td>";
    echo "</tr>";
}

echo "</tbody></table></body></html>";
