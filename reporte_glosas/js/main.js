/**
 * reporte_glosas/js/main.js
 * Lógica compartida: parseo de facturas, progreso, logs, UI helpers
 */

// ===== DOM ELEMENTS =====
const inputArea = document.getElementById('input-facturas');
const btnProcesar = document.getElementById('btn-procesar');
const btnCancelar = document.getElementById('btn-cancelar');
const btnDescargar = document.getElementById('btn-descargar');
const btnCopyFailed = document.getElementById('btn-copy-failed');
const progressBar = document.getElementById('progress-bar');
const progressContainer = document.querySelector('.progress');
const statusText = document.getElementById('status-text');
const tableContainer = document.querySelector('.table-container');
const logArea = document.getElementById('log-area');
const checkUnificado = document.getElementById('check-unificado');

// ===== STATE =====
let facturasAProcesar = [];
let abortController = null;
let allResultsData = [];
let allResultsUnificado = [];
let counts = { success: 0, failed: 0 };
let failedInvoices = [];

// ===== HELPERS =====

function getEstadosSeleccionados() {
    const checks = document.querySelectorAll('.check-estado:checked');
    return Array.from(checks).map(c => c.value).join(',');
}

function getSource() {
    return document.querySelector('input[name="data-source"]:checked').value;
}

function isUnificado() {
    return checkUnificado && checkUnificado.checked;
}

function parseFacturas(text) {
    const lineas = text.split('\n');
    const result = [];
    lineas.forEach(linea => {
        linea = linea.trim().toUpperCase();
        if (!linea) return;
        const match = linea.match(/^([A-Z]+)\s*(\d+)$/);
        if (match) {
            result.push({
                full: match[1] + match[2],
                serie: match[1],
                numero: match[2]
            });
        }
    });
    return result;
}

function updateProgress(current, total, text) {
    const porc = Math.round((current / total) * 100);
    progressBar.style.width = porc + "%";
    statusText.innerText = text;
}

function log(msg) {
    const div = document.createElement('div');
    const now = new Date().toLocaleTimeString();
    div.innerHTML = `<span class="text-muted">[${now}]</span> ${msg}`;
    logArea.appendChild(div);
    logArea.scrollTop = logArea.scrollHeight;
}

function finishUI() {
    updateProgress(1, 1, "Proceso Finalizado");
    btnDescargar.disabled = (allResultsData.length === 0 && allResultsUnificado.length === 0);
    btnProcesar.disabled = false;
    btnCancelar.classList.add('d-none');
    inputArea.disabled = false;
    progressBar.classList.remove('progress-bar-animated');
    progressBar.classList.add('bg-success');

    const summaryArea = document.getElementById('summary-area');
    summaryArea.classList.remove('d-none');
    document.getElementById('count-success').innerText = counts.success;
    document.getElementById('count-failed').innerText = counts.failed;

    if (failedInvoices.length > 0) {
        document.getElementById('failed-list').classList.remove('d-none');
        const container = document.getElementById('failed-items');
        failedInvoices.forEach(inv => {
            const span = document.createElement('span');
            span.className = 'badge bg-danger p-1';
            span.innerText = inv;
            container.appendChild(span);
        });
    }
}

function resetUI() {
    allResultsData = [];
    allResultsUnificado = [];
    counts = { success: 0, failed: 0 };
    failedInvoices = [];
    document.getElementById('summary-area').classList.add('d-none');
    document.getElementById('failed-list').classList.add('d-none');
    document.getElementById('failed-items').innerHTML = '';
    progressBar.classList.add('progress-bar-animated');
    progressBar.classList.remove('bg-success');
}

// ===== EVENT LISTENERS =====

// Checkbox "Todos"
document.getElementById('check-all-states').addEventListener('change', (e) => {
    document.querySelectorAll('.check-estado').forEach(c => c.checked = e.target.checked);
});

// Mostrar hint de path local
document.querySelectorAll('input[name="data-source"]').forEach(r => {
    r.addEventListener('change', (e) => {
        document.getElementById('local-path-hint').classList.toggle('d-none', e.target.value !== 'local');
    });
});

// Toggle reporte mode - when unified, disable/ignore other filters
if (checkUnificado) {
    checkUnificado.addEventListener('change', () => {
        const mallaTable = document.getElementById('tabla-malla-container');
        const unificadoTable = document.getElementById('tabla-unificado-container');
        const filtrosEstados = document.querySelectorAll('.check-estado, #check-all-states, #check-solo-activas');
        const searchModePanel = document.getElementById('search-mode-container');

        if (checkUnificado.checked) {
            if (mallaTable) mallaTable.classList.add('d-none');
            if (unificadoTable) unificadoTable.classList.remove('d-none');
            searchModePanel?.classList.remove('d-none');
            // Disable filters visually - unified mode ignores them
            filtrosEstados.forEach(el => { el.disabled = true; el.closest('.form-check')?.classList.add('opacity-50'); });

            // Trigger layout refresh for modes
            const activeMode = document.querySelector('input[name="report-search-mode"]:checked')?.value || 'list';
            toggleSearchModeUI(activeMode);
        } else {
            if (mallaTable) mallaTable.classList.remove('d-none');
            if (unificadoTable) unificadoTable.classList.add('d-none');
            searchModePanel?.classList.add('d-none');
            // Re-enable filters
            filtrosEstados.forEach(el => { el.disabled = false; el.closest('.form-check')?.classList.remove('opacity-50'); });

            // Reset to list mode appearance when unificado is off
            toggleSearchModeUI('list');
        }
    });
}

// NUEVO: Toggle entre Lista y Entidad
function toggleSearchModeUI(mode) {
    const listContainer = document.getElementById('list-facturas-container');
    const nitContainer = document.getElementById('filter-unificado');

    if (mode === 'nit') {
        listContainer?.classList.add('d-none');
        nitContainer?.classList.remove('d-none');
    } else {
        listContainer?.classList.remove('d-none');
        nitContainer?.classList.add('d-none');
    }
}

document.querySelectorAll('input[name="report-search-mode"]').forEach(radio => {
    radio.addEventListener('change', (e) => toggleSearchModeUI(e.target.value));
});

// Copy failed invoices
if (btnCopyFailed) {
    btnCopyFailed.addEventListener('click', () => {
        if (failedInvoices.length === 0) return;
        const text = failedInvoices.join('\n');

        const onSuccess = () => {
            const originalText = btnCopyFailed.innerHTML;
            btnCopyFailed.innerHTML = '<i class="fas fa-check me-1"></i>¡Copiado!';
            btnCopyFailed.classList.replace('btn-outline-danger', 'btn-success');
            setTimeout(() => {
                btnCopyFailed.innerHTML = originalText;
                btnCopyFailed.classList.replace('btn-success', 'btn-outline-danger');
            }, 2000);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(onSuccess).catch(() => alert('No se pudo copiar.'));
        } else {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-9999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try { document.execCommand('copy'); onSuccess(); } catch (e) { alert('No se pudo copiar.'); }
            document.body.removeChild(textArea);
        }
    });
}

// ===== MAIN PROCESS BUTTON =====
btnProcesar.addEventListener('click', async () => {
    const source = getSource();
    const estados = getEstadosSeleccionados();
    const unified = isUnificado();

    // Prepare UI
    btnProcesar.disabled = true;
    btnCancelar.classList.remove('d-none');
    inputArea.disabled = true;
    progressContainer.style.display = 'flex';
    logArea.style.display = 'block';
    tableContainer.style.display = 'block';
    resetUI();

    // Clear tables
    const mallaBody = document.querySelector('#tabla-resultados tbody');
    const unificadoBody = document.querySelector('#tabla-unificado tbody');
    if (mallaBody) mallaBody.innerHTML = '';
    if (unificadoBody) unificadoBody.innerHTML = '';

    // Toggle correct table visibility based on mode
    const mallaContainer = document.getElementById('tabla-malla-container');
    const unificadoContainer = document.getElementById('tabla-unificado-container');
    if (unified) {
        if (mallaContainer) mallaContainer.classList.add('d-none');
        if (unificadoContainer) unificadoContainer.classList.remove('d-none');
    } else {
        if (mallaContainer) mallaContainer.classList.remove('d-none');
        if (unificadoContainer) unificadoContainer.classList.add('d-none');
    }

    let modoBusqueda = 'lista';
    if (unified) {
        modoBusqueda = document.querySelector('input[name="report-search-mode"]:checked')?.value || 'list';
    }

    if (modoBusqueda === 'nit') {
        const nitValue = document.getElementById('input-nit')?.value.trim();
        const fechaDesde = document.getElementById('input-fecha-desde')?.value;
        const fechaHasta = document.getElementById('input-fecha-hasta')?.value;

        if (!nitValue || !fechaDesde || !fechaHasta) {
            alert("Para buscar por Entidad, el NIT y el rango de fechas son obligatorios.");
            btnProcesar.disabled = false;
            return;
        }
        modoBusqueda = 'filtro'; // semantic change for logic below
        // we'll use these in the if block below
        var filterNit = nitValue;
        var filterDesde = fechaDesde;
        var filterHasta = fechaHasta;
    } else {
        facturasAProcesar = parseFacturas(inputArea.value);
        if (facturasAProcesar.length === 0) {
            alert("No se detectaron facturas válidas. Revisa el formato (Ej: FCR247579).");
            btnProcesar.disabled = false;
            return;
        }
        modoBusqueda = 'lista';
    }

    abortController = new AbortController();

    if (modoBusqueda === 'filtro') {
        const nit = document.getElementById('input-nit')?.value.trim();
        const desde = document.getElementById('input-fecha-desde')?.value;
        const hasta = document.getElementById('input-fecha-hasta')?.value;
        log(`🚀 Buscando por Nit [${nit}] y fechas [${desde} a ${hasta}]...`);
        updateProgress(0, 1, "Buscando...");
        try {
            await searchUnifiedByFilter(nit, desde, hasta, source);
        } catch (err) {
            log(`🚨 Error en búsqueda: ${err.message}`);
        }
        updateProgress(1, 1, "Búsqueda finalizada");
    } else {
        log(`🚀 Iniciando [${source.toUpperCase()}] ${unified ? '(UNIFICADO - ignora filtros)' : '(MALLA)'} para ${facturasAProcesar.length} facturas...`);
        for (let i = 0; i < facturasAProcesar.length; i++) {
            if (abortController.signal.aborted) break;
            const item = facturasAProcesar[i];
            updateProgress(i, facturasAProcesar.length, `Procesando ${item.full}...`);
            try {
                if (unified) {
                    await processUnified(item, source);
                } else {
                    await processMalla(item, source, estados);
                }
            } catch (err) {
                counts.failed++;
                failedInvoices.push(item.full);
                if (err.name === 'AbortError') {
                    log(`🛑 Proceso detenido por el usuario.`);
                } else {
                    log(`🚨 ${item.full}: Error - ${err.message}`);
                }
            }
        }
    }

    finishUI();
});

// Listener para el check de unificado - mostrar filtros
if (checkUnificado) {
    checkUnificado.addEventListener('change', () => {
        // Handled in the mode logic above
    });
}

btnCancelar.addEventListener('click', () => {
    if (abortController) abortController.abort();
    finishUI();
});
