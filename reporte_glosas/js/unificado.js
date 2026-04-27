/**
 * reporte_glosas/js/unificado.js
 * Lógica del reporte UNIFICADO (compresión horizontal, 1 fila por factura)
 * En este modo los AI se cuentan como valor aceptado.
 */

async function processUnified(item, source) {
    const params = new URLSearchParams({
        action: 'fetch_unified',
        source: source,
        serie: item.serie,
        numero: item.numero
    });

    const resp = await fetch(`api_unificado.php?${params.toString()}`, { signal: abortController.signal });
    if (!resp.ok) throw new Error("Error HTTP " + resp.status);

    const data = await resp.json();

    if (data.status === 'ok' && data.found) {
        data.filas.forEach(f => {
            addRowUnificado(f);
            allResultsUnificado.push(f);
        });
        counts.success++;
        log(`✅ ${item.full}: Fila(s) unificada(s) generada(s).`);
    } else {
        counts.failed++;
        failedInvoices.push(item.full);
        log(`❌ ${item.full}: No encontrada.`);
    }
}

/**
 * Splits a date range into monthly chunks.
 * Returns array of { desde, hasta } objects.
 */
function splitIntoMonths(desde, hasta) {
    const chunks = [];
    let current = new Date(desde + 'T00:00:00');
    const end = new Date(hasta + 'T00:00:00');

    while (current <= end) {
        const chunkStart = current.toISOString().slice(0, 10);
        // Last day of current month
        const lastOfMonth = new Date(current.getFullYear(), current.getMonth() + 1, 0);
        const chunkEnd = (lastOfMonth <= end ? lastOfMonth : end).toISOString().slice(0, 10);
        chunks.push({ desde: chunkStart, hasta: chunkEnd });
        // Move to first day of next month
        current = new Date(current.getFullYear(), current.getMonth() + 1, 1);
    }
    return chunks;
}

/**
 * Fetch one chunk with automatic retry on 429.
 */
async function fetchUnifiedChunkWithRetry(nit, desde, hasta, source, maxRetries = 3) {
    for (let attempt = 0; attempt <= maxRetries; attempt++) {
        const params = new URLSearchParams({ action: 'search_unified', source, nit, fecha_desde: desde, fecha_hasta: hasta });
        const resp = await fetch(`api_unificado.php?${params.toString()}`, { signal: abortController.signal });

        if (resp.status === 429) {
            if (attempt < maxRetries) {
                const wait = 1000 * (attempt + 1); // 1s, 2s, 3s
                log(`Rate limit en ${desde}->${hasta}. Esperando ${wait / 1000}s antes de reintentar...`);
                await new Promise(r => setTimeout(r, wait));
                continue;
            }
            throw new Error('Rate limit persistente en chunk ' + desde + '->' + hasta);
        }

        if (!resp.ok) throw new Error('Error HTTP ' + resp.status);
        const data = await resp.json();
        if (data.status === 'ok' && data.found) return data.filas;
        return [];
    }
    return [];
}

/**
 * Runs an array of async tasks with a max concurrency of `limit` at a time.
 */
async function runWithConcurrency(tasks, limit) {
    const results = new Array(tasks.length);
    let index = 0;

    async function worker() {
        while (index < tasks.length) {
            const i = index++;
            results[i] = await tasks[i]();
        }
    }

    const workers = Array.from({ length: Math.min(limit, tasks.length) }, () => worker());
    await Promise.all(workers);
    return results;
}

/**
 * BUSQUEDA MASIVA por NIT y Rango de Fechas.
 * Si el rango supera 30 dias, divide en chunks mensuales.
 * Los procesa con concurrencia controlada (max 2 simultaneos) + retry en 429.
 */
async function searchUnifiedByFilter(nit, desde, hasta, source) {
    const CONCURRENCY = 2;
    const msPerDay = 86400000;
    const daysDiff = (new Date(hasta) - new Date(desde)) / msPerDay;

    let chunks;
    if (daysDiff > 30) {
        chunks = splitIntoMonths(desde, hasta);
        log(`Rango de ${Math.ceil(daysDiff)} dias -> ${chunks.length} chunks (concurrencia: ${CONCURRENCY})`);
    } else {
        chunks = [{ desde, hasta }];
    }

    updateProgress(0, chunks.length, `Ejecutando ${chunks.length} consulta(s)...`);
    let completed = 0;

    const tasks = chunks.map((c) => async () => {
        try {
            const filas = await fetchUnifiedChunkWithRetry(nit, c.desde, c.hasta, source);
            completed++;
            updateProgress(completed, chunks.length, `${completed}/${chunks.length} completados`);
            log(`${c.desde} al ${c.hasta}: ${filas.length} filas.`);
            return filas;
        } catch (err) {
            completed++;
            updateProgress(completed, chunks.length, `${completed}/${chunks.length} completados`);
            log(`Error en ${c.desde}->${c.hasta}: ${err.message}`);
            return [];
        }
    });

    const results = await runWithConcurrency(tasks, CONCURRENCY);

    // Merge + de-duplicate
    const seen = new Set();
    const allFilas = [];
    for (const chunk of results) {
        for (const f of chunk) {
            const key = (f.factura || '') + '|' + (f.tipo || '');
            if (!seen.has(key)) { seen.add(key); allFilas.push(f); }
        }
    }

    if (allFilas.length > 0) {
        allFilas.forEach(f => { addRowUnificado(f); allResultsUnificado.push(f); });
        const facturasUnicas = [...new Set(allFilas.map(f => f.factura))].length;
        counts.success = facturasUnicas;
        log(`Busqueda completada: ${allFilas.length} filas (${facturasUnicas} facturas).`);
    } else {
        log(`No se encontraron resultados para los filtros aplicados.`);
    }
}

function addRowUnificado(r) {
    const tbody = document.querySelector('#tabla-unificado tbody');
    if (!tbody) return;

    const tr = document.createElement('tr');
    const fmt = (v) => v || '';
    const num = (v) => {
        if (typeof v === 'number' && v !== 0) return v.toLocaleString('es-CO');
        if (v === 0) return '0';
        return v || '';
    };

    tr.innerHTML = `
        <td class="fw-bold text-nowrap">${fmt(r.factura)}</td>
        <td class="text-nowrap">${fmt(r.nit)}</td>
        <td>${fmt(r.tercero_nombre)}</td>
        <td class="text-nowrap">${fmt(r.codigo)}</td>
        <td>${fmt(r.concepto)}</td>
        <td class="text-center">${fmt(r.tipo)}</td>
        <td class="text-end fw-bold">${num(r.valor_factura)}</td>

        <!-- NU -->
        <td class="text-nowrap">${fmt(r.nu_fecha_ingreso)}</td>
        <td class="text-end text-danger fw-bold">${num(r.nu_valor_glosa)}</td>
        <td class="small">${fmt(r.nu_motivo)}</td>
        <td class="text-nowrap">${fmt(r.nu_fecha_resp)}</td>
        <td class="text-end text-success">${num(r.nu_valor_acep)}</td>
        <td class="small">${fmt(r.nu_motivo_acep)}</td>
        <td class="text-nowrap">${fmt(r.nu_cod_resp)}</td>

        <!-- C1 -->
        <td class="small">${fmt(r.c1_motivo)}</td>
        <td class="text-nowrap">${fmt(r.c1_cuenta_cobro)}</td>
        <td class="text-nowrap">${fmt(r.c1_fecha_radicado)}</td>

        <!-- R2 -->
        <td class="text-nowrap">${fmt(r.r2_fecha_ingreso)}</td>
        <td class="text-end text-danger fw-bold">${num(r.r2_valor_glosa)}</td>
        <td class="small">${fmt(r.r2_motivo)}</td>
        <td class="text-nowrap">${fmt(r.r2_fecha_resp)}</td>
        <td class="text-end text-success">${num(r.r2_valor_acep)}</td>
        <td class="small">${fmt(r.r2_motivo_acep)}</td>
        <td class="text-nowrap">${fmt(r.r2_cod_resp)}</td>

        <!-- C2 -->
        <td class="small">${fmt(r.c2_motivo)}</td>
        <td class="text-nowrap">${fmt(r.c2_cuenta_cobro)}</td>
        <td class="text-nowrap">${fmt(r.c2_fecha_radicado)}</td>

        <!-- R3 -->
        <td class="text-nowrap">${fmt(r.r3_fecha_ingreso)}</td>
        <td class="text-end text-danger fw-bold">${num(r.r3_valor_glosa)}</td>
        <td class="small">${fmt(r.r3_motivo)}</td>
        <td class="text-nowrap">${fmt(r.r3_fecha_resp)}</td>
        <td class="text-end text-success">${num(r.r3_valor_acep)}</td>
        <td class="small">${fmt(r.r3_motivo_acep)}</td>
        <td class="text-nowrap">${fmt(r.r3_cod_resp)}</td>

        <!-- C3/CO -->
        <td class="small">${fmt(r.c3_motivo)}</td>
        <td class="text-nowrap">${fmt(r.c3_cuenta_cobro)}</td>
        <td class="text-nowrap">${fmt(r.c3_fecha_radicado)}</td>

        <!-- R4 -->
        <td class="text-nowrap">${fmt(r.r4_fecha_ingreso)}</td>
        <td class="text-end text-danger fw-bold">${num(r.r4_valor_glosa)}</td>
        <td class="small">${fmt(r.r4_motivo)}</td>
        <td class="text-nowrap">${fmt(r.r4_fecha_resp)}</td>
        <td class="text-end text-success">${num(r.r4_valor_acep)}</td>
        <td class="small">${fmt(r.r4_motivo_acep)}</td>
        <td class="text-nowrap">${fmt(r.r4_cod_resp)}</td>

        <!-- CO -->
        <td class="small">${fmt(r.co_motivo)}</td>
    `;
    tbody.appendChild(tr);
}
