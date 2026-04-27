<?php
require_once 'config/conectFox.php';

$serie = 'FCR';
$numero = 247579;
$pdo = ConnectionFox::con();

$sql_cab = "SELECT gl_docn, vr_fra, gl_fecha, radicacion FROM glo_cab WHERE fc_serie = '$serie' AND fc_docn = $numero";
$cab = $pdo->query($sql_cab)->fetch(PDO::FETCH_ASSOC);

echo "CAB: vr_fra = {$cab['vr_fra']}\n";

$gl_docn = (int)$cab['gl_docn'];
$sql_det = "SELECT codigo, vr_glosa FROM glo_det WHERE gl_docn = $gl_docn";
$res = $pdo->query($sql_det)->fetchAll(PDO::FETCH_ASSOC);

foreach ($res as $row) {
    echo "COD: [{$row['codigo']}] | VR: [{$row['vr_glosa']}]\n";
}
