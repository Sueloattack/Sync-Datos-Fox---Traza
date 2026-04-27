<?php
require 'config/conectFox.php';
try {
    $pdo = ConnectionFox::con();
    $q = $pdo->query("SELECT TOP 1 * FROM glo_det ORDER BY gl_docn DESC");
    if (!$q) {
        echo "Query failed.\n";
        exit;
    }
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "Columns:\n";
        echo implode(", ", array_keys($row));
    } else {
        echo "No data found in glo_det";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
