/**
 * reporte_glosas/js/malla.js
 * Lógica del reporte MALLA (original, por ítem individual)
 */

async function processMalla(item, source, estados) {
    const soloActivas = document.getElementById('check-solo-activas').checked;
    const params = new URLSearchParams({
        action: 'fetch_invoice',
        source: source,
        serie: item.serie,
        numero: item.numero,
        estados: estados,
        solo_activas: soloActivas
    });

    const resp = await fetch(`api.php?${params.toString()}`, { signal: abortController.signal });
    if (!resp.ok) throw new Error("Error HTTP " + resp.status);

    const data = await resp.json();

    if (data.status === 'ok' && data.found) {
        if (data.items.length > 0) {
            data.items.forEach(row => {
                addRowMalla(row);
                allResultsData.push(row);
            });
            counts.success++;
            log(`✅ ${item.full}: ${data.items.length} ítems agregados.`);
        } else {
            counts.success++;
            log(`⚠️ ${item.full}: Encontrada pero sin glosas según filtros.`);
        }
    } else {
        counts.failed++;
        failedInvoices.push(item.full);
        log(`❌ ${item.full}: No encontrada.`);
    }
}

function addRowMalla(r) {
    const tbody = document.querySelector('#tabla-resultados tbody');
    const tr = document.createElement('tr');
    const fmt = (v) => v || '';
    const num = (v) => typeof v === 'number' ? v.toLocaleString('es-CO') : v;

    tr.innerHTML = `
        <td class="fw-bold">${r.factura}</td>
        <td>${num(r.v_fra)}</td>
        <td>${fmt(r.radicacion)}</td>
        <td>${fmt(r.item)}</td>
        <td class="text-danger fw-bold">${num(r.v_glosa)}</td>
        <td class="small">${fmt(r.NU)}</td>
        <td class="small">${fmt(r.C1)}</td>
        <td>${fmt(r.C1_CC)}</td>
        <td>${fmt(r.C1_FR)}</td>
        <td>${fmt(r.R2)}</td>
        <td class="small">${fmt(r.C2)}</td>
        <td>${fmt(r.C2_CC)}</td>
        <td>${fmt(r.C2_FR)}</td>
        <td>${fmt(r.R3)}</td>
        <td class="small">${fmt(r.C3)}</td>
        <td>${fmt(r.C3_CC)}</td>
        <td>${fmt(r.C3_FR)}</td>
        <td>${fmt(r.R4)}</td>
        <td class="small">${fmt(r.CO)}</td>
    `;
    tbody.appendChild(tr);
}
