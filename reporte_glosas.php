<?php
/**
 * reporte_glosas.php - Redirect
 * 
 * La lógica ha sido reorganizada en la carpeta reporte_glosas/
 * Este archivo redirige para mantener compatibilidad con enlaces existentes.
 */

// Si viene un request AJAX con action, redirigir al API correspondiente
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'fetch_invoice') {
        require_once __DIR__ . '/reporte_glosas/api.php';
        exit;
    }
    if ($_GET['action'] === 'fetch_unified') {
        require_once __DIR__ . '/reporte_glosas/api_unificado.php';
        exit;
    }
}

// Si es acceso normal, redirigir a la nueva vista
header('Location: reporte_glosas/index.php');
exit;
