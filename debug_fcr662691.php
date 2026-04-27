<?php
require_once 'config/conectFox.php';

$serie = 'FCR';
$numero = 662691;
$pdo = ConnectionFox::con();

$sql_cab = "SELECT gl_docn, vr_fra, gl_fecha, radicacion FROM glo_cab WHERE fc_serie = '$serie' AND fc_docn = $numero";
$cab = $pdo->query($sql_cab)->fetch(PDO::FETCH_ASSOC);

if (!$cab) {
    echo "Factura no encontrada en glo_cab.\n";
    exit;
}

echo "CAB Encontrada: gl_docn = {$cab['gl_docn']}, vr_fra = {$cab['vr_fra']}\n";

$gl_docn = (int)$cab['gl_docn'];
$sql_det = "SELECT codigo, marca, referencia, estatus1, motivo_res, vr_glosa, gr_docn, fecha_rep FROM glo_det WHERE gl_docn = $gl_docn";
$res = $pdo->query($sql_det)->fetchAll(PDO::FETCH_ASSOC);

echo "Total Detalle: " . count($res) . "\n\n";

foreach ($res as $row) {
    echo "COD: [{$row['codigo']}] | ST: [{$row['estatus1']}] | VR: [{$row['vr_glosa']}] | CC: [{$row['gr_docn']}]\n";
}
