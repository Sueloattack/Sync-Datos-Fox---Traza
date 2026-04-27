<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportador Avanzado de Glosas - Asotrauma</title>
    <link rel="icon" href="data:,">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>

<div class="container-fluid px-4">
    <div class="row">
        <!-- ============================================= -->
        <!-- PANEL IZQUIERDO: CONFIGURACIÓN                -->
        <!-- ============================================= -->
        <div class="col-lg-4">
            <div class="card shadow sticky-summary mb-4">
                <div class="card-header text-center py-3">
                    <h4 class="mb-0"><i class="fas fa-file-export me-2"></i>Configuración</h4>
                </div>
                <div class="card-body">
                    <!-- ORIGEN DE DATOS -->
                    <div class="source-selector mb-4">
                        <label class="form-label fw-bold d-block mb-2"><i class="fas fa-database me-2"></i>Origen de Datos:</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="data-source" id="source-api" value="api" checked>
                            <label class="form-check-label" for="source-api">API GEMA (Nube)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="data-source" id="source-local" value="local">
                            <label class="form-check-label" for="source-local">PC Local (FoxPro)</label>
                        </div>
                        <div id="local-path-hint" class="small text-muted mt-2 d-none">Usa la conexión de <code>c:\gl\glo_cab.DBF</code></div>
                    </div>

                    <!-- ESTADOS -->
                    <div class="mb-4">
                        <label class="form-label fw-bold d-block mb-2"><i class="fas fa-filter me-2"></i>Estados a incluir:</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input check-estado" type="checkbox" value="NU" id="st-nu" checked>
                                    <label class="form-check-label" for="st-nu">NU (Inicial)</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input check-estado" type="checkbox" value="C1" id="st-c1" checked>
                                    <label class="form-check-label" for="st-c1">C1 (Respuesta 1)</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input check-estado" type="checkbox" value="C2" id="st-c2" checked>
                                    <label class="form-check-label" for="st-c2">C2 (Réplica)</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input check-estado" type="checkbox" value="C3" id="st-c3" checked>
                                    <label class="form-check-label" for="st-c3">C3 (Respuesta 2)</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input check-estado" type="checkbox" value="CO" id="st-co" checked>
                                    <label class="form-check-label" for="st-co">CO (Final)</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="check-all-states">
                                    <label class="form-check-label text-primary" for="check-all-states">Todos</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FILTROS EXTRA -->
                    <div class="mb-4">
                        <label class="form-label fw-bold d-block mb-2"><i class="fas fa-cog me-2"></i>Opciones Extra:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="check-solo-activas" checked>
                            <label class="form-check-label text-danger" for="check-solo-activas">
                                Solo Glosas Activas (Excluye aceptadas AI/AE)
                            </label>
                        </div>
                        <!-- NUEVO: Checkbox Reporte Unificado -->
                        <div class="form-check form-check-unified mt-3">
                            <input class="form-check-input" type="checkbox" id="check-unificado">
                            <label class="form-check-label" for="check-unificado">
                                <i class="fas fa-compress-arrows-alt me-1"></i>Reporte Unificado
                            </label>
                            <div class="small text-muted mt-1">
                                Comprime todos los ítems en una sola fila por factura. Los AI se cuentan como valor aceptado.
                            </div>
                        </div>
                    </div>

                    <!-- MODO DE SELECCIÓN -->
                    <div id="search-mode-container" class="mb-4 d-none">
                        <label class="form-label fw-bold d-block mb-2"><i class="fas fa-search-plus me-2"></i>Modo de Selección:</label>
                        <div class="btn-group w-100 mb-3" role="group">
                            <input type="radio" class="btn-check" name="report-search-mode" id="search-mode-list" value="list" checked>
                            <label class="btn btn-outline-primary" for="search-mode-list"><i class="fas fa-list-ul me-1"></i>Por Lista</label>

                            <input type="radio" class="btn-check" name="report-search-mode" id="search-mode-nit" value="nit">
                            <label class="btn btn-outline-primary" for="search-mode-nit"><i class="fas fa-building me-1"></i>Por Entidad</label>
                        </div>
                    </div>

                    <!-- BÚSQUEDA POR NIT (UNIFICADO) -->
                    <div id="filter-unificado" class="mb-4 d-none border p-3 rounded bg-light section-search-mode">
                        <label class="form-label fw-bold mb-2 text-purple"><i class="fas fa-filter me-2"></i>Filtros de Entidad:</label>
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="small text-muted mb-1">NIT de la Entidad:</label>
                                <input type="text" id="input-nit" class="form-control" placeholder="Ej: 890903790">
                            </div>
                            <div class="col-6">
                                <label class="small text-muted mb-1">Fecha Desde:</label>
                                <input type="date" id="input-fecha-desde" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="small text-muted mb-1">Fecha Hasta:</label>
                                <input type="date" id="input-fecha-hasta" class="form-control">
                            </div>
                        </div>
                        <div class="mt-2 small text-muted">
                            <i class="fas fa-info-circle me-1"></i>Debe coincidir NIT y rango de fechas.
                        </div>
                    </div>

                    <!-- LISTADO -->
                    <div id="list-facturas-container" class="mb-4 section-search-mode">
                        <label class="form-label fw-bold mb-2"><i class="fas fa-list-ol me-2"></i>Listado de Facturas:</label>
                        <textarea id="input-facturas" class="form-control" rows="10" placeholder="Pega aquí las facturas, una por línea..."></textarea>
                    </div>

                    <button id="btn-procesar" class="btn btn-primary w-100 py-3 fw-bold rounded-pill">
                        <i class="fas fa-play me-2"></i>INICIAR PROCESAMIENTO
                    </button>
                    
                    <button id="btn-cancelar" class="btn btn-outline-danger w-100 mt-2 d-none rounded-pill">
                        <i class="fas fa-stop me-2"></i>Detener
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================= -->
        <!-- PANEL DERECHO: RESULTADOS                     -->
        <!-- ============================================= -->
        <div class="col-lg-8">
            <!-- PROGRESO Y LOGS -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="progress mb-2">
                        <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div id="status-text" class="text-center text-muted mb-3 small fw-bold">Esperando inicio...</div>
                    <div id="log-area" placeholder="Logs de actividad..."></div>
                </div>
            </div>

            <!-- RESUMEN DE PROCESAMIENTO -->
            <div id="summary-area" class="card shadow mb-4 d-none">
                <div class="card-body py-2">
                    <div class="row text-center">
                        <div class="col-6">
                            <h6 class="text-success mb-0"><i class="fas fa-check-circle me-1"></i>EXITOSOS: <span id="count-success" class="fw-bold">0</span></h6>
                        </div>
                        <div class="col-6 border-start">
                            <h6 class="text-danger mb-0"><i class="fas fa-times-circle me-1"></i>FALLIDOS: <span id="count-failed" class="fw-bold">0</span></h6>
                        </div>
                    </div>
                    <div id="failed-list" class="mt-2 small text-danger d-none border-top pt-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <b>Facturas no encontradas / con error:</b>
                            <button id="btn-copy-failed" class="btn btn-outline-danger btn-sm py-0 px-2" title="Copiar lista de fallos">
                                <i class="fas fa-copy me-1"></i>Copiar
                            </button>
                        </div>
                        <div id="failed-items" class="d-flex flex-wrap gap-2 mt-1"></div>
                    </div>
                </div>
            </div>

            <!-- ========================================= -->
            <!-- TABLA MALLA (Reporte Original)            -->
            <!-- ========================================= -->
            <div class="table-container">
                <div id="tabla-malla-container" class="card shadow mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center py-2 bg-success">
                        <h5 class="mb-0"><i class="fas fa-table me-2"></i>Malla de Glosas Generada</h5>
                        <button id="btn-descargar" class="btn btn-light btn-sm fw-bold" disabled>
                            <i class="fas fa-file-excel text-success me-1"></i>Descargar Excel
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 600px;">
                            <table class="table table-bordered table-sm table-striped mb-0" id="tabla-resultados" style="font-size: 0.85em;">
                                <thead class="table-light sticky-top">
                                    <tr id="header-malla">
                                        <th>Factura</th>
                                        <th>Valor Fac</th>
                                        <th>Radicación</th>
                                        <th>Item</th>
                                        <th>Valor Glosa</th>
                                        <th>NU</th>
                                        <th>C1</th>
                                        <th>CC</th>
                                        <th>Fecha Rad</th>
                                        <th>R2</th>
                                        <th>C2</th>
                                        <th>CC</th>
                                        <th>Fecha Rad</th>
                                        <th>R3</th>
                                        <th>C3/CO</th>
                                        <th>CC</th>
                                        <th>Fecha Rad</th>
                                        <th>R4</th>
                                        <th>Final</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ========================================= -->
                <!-- TABLA UNIFICADA (Reporte Nuevo)            -->
                <!-- ========================================= -->
                <div id="tabla-unificado-container" class="card shadow mb-4 d-none">
                    <div class="card-header d-flex justify-content-between align-items-center py-2" style="background-color: #6f42c1 !important;">
                        <h5 class="mb-0 text-white"><i class="fas fa-compress-arrows-alt me-2"></i>Reporte Unificado de Glosas</h5>
                        <button id="btn-descargar-unificado" class="btn btn-light btn-sm fw-bold" disabled>
                            <i class="fas fa-file-excel text-success me-1"></i>Descargar Excel
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 600px;">
                            <table class="table table-bordered table-sm table-striped mb-0 table-unificado" id="tabla-unificado">
                                <thead class="sticky-top">
                                    <tr>
                                        <!-- Base Info -->
                                        <th class="th-info-base">Factura</th>
                                        <th class="th-info-base">NIT</th>
                                        <th class="th-info-base">TERCERO</th>
                                        <th class="th-info-base">CODIGO</th>
                                        <th class="th-info-base">CONCEPTO</th>
                                        <th class="th-info-base">TIPO</th>
                                        <th class="th-info-base">VALOR FACTURA</th>
                                        <!-- NU -->
                                        <th class="th-nu">FECHA INGRESO</th>
                                        <th class="th-nu">VALOR GLOSA</th>
                                        <th class="th-nu">NU</th>
                                        <th class="th-nu">FECHA RESP</th>
                                        <th class="th-nu">VALOR ACEPTADO</th>
                                        <th class="th-nu">MOTIVO ACEPTACION</th>
                                        <th class="th-nu">COD RESP</th>
                                        <!-- C1 -->
                                        <th class="th-c1">C1</th>
                                        <th class="th-c1">Cuenta cobro</th>
                                        <th class="th-c1">Fecha radicado</th>
                                        <!-- R2 -->
                                        <th class="th-r2">FECHA INGRESO</th>
                                        <th class="th-r2">VALOR GLOSA</th>
                                        <th class="th-r2">R2</th>
                                        <th class="th-r2">FECHA RESP</th>
                                        <th class="th-r2">VALOR ACEPTADO</th>
                                        <th class="th-r2">MOTIVO ACEPTACION</th>
                                        <th class="th-r2">COD RESP</th>
                                        <!-- C2 -->
                                        <th class="th-c2">C2</th>
                                        <th class="th-c2">Cuenta cobro</th>
                                        <th class="th-c2">Fecha radicado</th>
                                        <!-- R3 -->
                                        <th class="th-r3">FECHA INGRESO</th>
                                        <th class="th-r3">VALOR GLOSA</th>
                                        <th class="th-r3">R3</th>
                                        <th class="th-r3">FECHA RESP</th>
                                        <th class="th-r3">VALOR ACEPTADO</th>
                                        <th class="th-r3">MOTIVO ACEPTACION</th>
                                        <th class="th-r3">COD RESP</th>
                                        <!-- C3/CO -->
                                        <th class="th-c3">C3/CO</th>
                                        <th class="th-c3">Cuenta cobro</th>
                                        <th class="th-c3">Fecha radicado</th>
                                        <!-- R4 -->
                                        <th class="th-r4">FECHA INGRESO</th>
                                        <th class="th-r4">VALOR GLOSA</th>
                                        <th class="th-r4">R4</th>
                                        <th class="th-r4">FECHA RESP</th>
                                        <th class="th-r4">VALOR ACEPTADO</th>
                                        <th class="th-r4">MOTIVO ACEPTACION</th>
                                        <th class="th-r4">COD RESP</th>
                                        <!-- CO -->
                                        <th class="th-co">CO</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="../assets/lib/exceljs.min.js"></script>
<script src="js/main.js"></script>
<script src="js/malla.js"></script>
<script src="js/unificado.js"></script>
<script src="js/excel_export.js"></script>

</body>
</html>
