<?php
require 'config/conectFox.php';
try {
    $pdo = ConnectionFox::con();
    echo "Connected locally!\n";
    $stmt = $pdo->query("SELECT TOP 1 * FROM glo_det ORDER BY gl_docn");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "Columns in glo_det:\n";
        print_r(array_keys($row));
    } else {
        echo "No records in glo_det\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
