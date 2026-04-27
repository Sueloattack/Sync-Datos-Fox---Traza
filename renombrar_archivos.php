<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renombrar Archivos JSON</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding-top: 50px; }
        .container { max-width: 800px; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .log-box { background: #1e1e1e; color: #00ff00; padding: 15px; border-radius: 5px; font-family: monospace; max-height: 400px; overflow-y: auto; margin-top: 20px; }
        .log-error { color: #ff3333; }
        .log-info { color: #00ccff; }
    </style>
</head>
<body>

<div class="container">
    <h2 class="mb-4 text-center">Renombrado Masivo de JSONs</h2>
    
    <div class="alert alert-warning">
        <strong>¡Atención!</strong> Esta herramienta modificará los nombres de los archivos en la carpeta especificada. Asegúrese de tener una copia de seguridad.
    </div>

    <form method="POST">
        <div class="mb-3">
            <label for="ruta" class="form-label">Ruta de la Carpeta (Ruta Absoluta):</label>
            <input type="text" class="form-control" id="ruta" name="ruta" placeholder="C:\laragon\www\archivos_json" required value="<?php echo isset($_POST['ruta']) ? htmlspecialchars($_POST['ruta']) : ''; ?>">
            <div class="form-text">Ingrese la ruta completa donde se encuentran los archivos JSON.</div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Procesar Renombrado</button>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ruta'])) {
        $ruta = $_POST['ruta'];
        
        echo '<div class="log-box">';
        echo "<div>Iniciando proceso en: " . htmlspecialchars($ruta) . "</div><br>";

        if (is_dir($ruta)) {
            $archivos = scandir($ruta);
            $contador = 0;

            foreach ($archivos as $archivo) {
                if ($archivo === '.' || $archivo === '..') continue;

                $pathCompleto = $ruta . DIRECTORY_SEPARATOR . $archivo;
                
                if (!is_file($pathCompleto)) continue;

                $nuevoNombre = null;
                $nit = "";
                $identificador = "";
                $esCoex = false;

                // Patrón 1: resultadosmsps_IDENTIFICADOR_id..._a_cuv.json
                if (preg_match('/^resultadosmsps_([^_]+)_id.*_a_cuv\.json$/i', $archivo, $matches)) {
                    $identificadorOriginal = $matches[1];
                    
                    // Verificar si es COEX
                    if (strpos(strtolower($identificadorOriginal), 'coex') !== false) {
                        $esCoex = true;
                        $nit = "730010082602";
                        $identificador = strtoupper($identificadorOriginal);
                    } else {
                        // Cualesquier otra serie (fcr, ferr, ferd, fecr, etc)
                        $nit = "730010082601";
                        $identificador = strtoupper($identificadorOriginal); 
                    }

                    $nuevoNombre = "{$nit}_{$identificador}_CUV.json";
                }
                // Patrón 2: IDENTIFICADOR.json (donde no empiece por resultadosmsps)
                elseif (preg_match('/^([a-zA-Z0-9]+)\.json$/i', $archivo, $matches)) {
                    // Evitar procesar archivos que ya tengan el formato de salida o empiecen con resultadosmsps si regex falla
                    if (strpos($archivo, 'resultadosmsps') === 0) continue;
                    
                    $identificadorOriginal = $matches[1];

                    // Verificar si es COEX
                    if (strpos(strtolower($identificadorOriginal), 'coex') !== false) {
                        $esCoex = true;
                        $nit = "730010082602";
                        $identificador = strtoupper($identificadorOriginal);
                    } else {
                        // Cualesquier otra serie
                        $nit = "730010082601";
                        $identificador = strtoupper($identificadorOriginal);
                    }

                    $nuevoNombre = "{$nit}_{$identificador}_RIP.json";
                }

                // Ejecutar renombrado si hubo coincidencia
                if ($nuevoNombre) {
                    // Evitar renombrar si el nombre ya es el correcto (aunque el regex original filtra muchos casos)
                    if ($archivo !== $nuevoNombre) {
                        $pathNuevo = $ruta . DIRECTORY_SEPARATOR . $nuevoNombre;
                        
                        if (file_exists($pathNuevo)) {
                            echo "<div class='log-error'>[OMITIDO] El destino ya existe: $nuevoNombre</div>";
                        } else {
                            if (rename($pathCompleto, $pathNuevo)) {
                                echo "<div>[OK] $archivo &rarr; <span class='log-info'>$nuevoNombre</span></div>";
                                $contador++;
                            } else {
                                echo "<div class='log-error'>[ERROR] No se pudo renombrar: $archivo</div>";
                            }
                        }
                    }
                }
            }
            echo "<br><div>----------------------------------------</div>";
            echo "<div>Proceso finalizado. Total renombrados: $contador</div>";

        } else {
            echo "<div class='log-error'>ERROR: La ruta especificada no existe o no es un directorio.</div>";
        }
        echo '</div>';
    }
    ?>
</div>

</body>
</html>
