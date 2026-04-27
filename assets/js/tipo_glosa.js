/**
 * assets/js/tipo_glosa.js
 * Lógica para la herramienta de consulta masiva de tipo de glosa.
 */

document.addEventListener('DOMContentLoaded', () => {
    // --- SELECTORES ---
    const btnOpenModal = document.getElementById('btn-open-tipo-tool');
    const modal = document.getElementById('modal-tipo-tool');
    const btnCloseModal = document.getElementById('btn-close-tipo-modal');

    const txtInput = document.getElementById('txt-invoices-input');
    const dropZone = document.getElementById('drop-zone-excel');
    const fileInput = document.getElementById('file-excel-input');

    const btnProcess = document.getElementById('btn-process-tipo');
    const btnClear = document.getElementById('btn-clear-tipo');

    const progressContainer = document.getElementById('tipo-progress-container');
    const progressText = document.getElementById('tipo-progress-text');
    const progressPercent = document.getElementById('tipo-progress-percent');
    const progressBar = document.getElementById('tipo-progress-bar');

    const resultsContainer = document.getElementById('tipo-results-container');
    const resultsTableBody = document.querySelector('#tbl-tipo-results tbody');
    const btnExport = document.getElementById('btn-export-tipo-excel');

    let processedData = [];

    // --- EVENTOS DE APERTURA/CIERRE ---
    btnOpenModal?.addEventListener('click', () => {
        modal.classList.remove('hidden');
    });

    btnCloseModal?.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    // Cerrar al hacer clic fuera del contenido
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });

    // --- MANEJO DE EXCEL (DROP ZONE) ---
    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('bg-orange-200', 'border-orange-500');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('bg-orange-200', 'border-orange-500');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('bg-orange-200', 'border-orange-500');
        const files = e.dataTransfer.files;
        if (files.length) handleExcelFile(files[0]);
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length) handleExcelFile(e.target.files[0]);
    });

    function handleExcelFile(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const json = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });

            // Extraer facturas (primera columna, omitir encabezado si parece ser uno)
            const facturas = json
                .map(row => row[0])
                .filter(val => val && String(val).trim() !== '')
                .map(val => String(val).trim());

            // Detectar encabezados comunes para eliminarlos
            const commonHeaders = ['FACTURA', 'CODIGO', 'FACTURAS', 'INVOICE', 'DOCUMENTO'];
            if (facturas.length > 0 && commonHeaders.includes(facturas[0].toUpperCase())) {
                facturas.shift();
            }

            if (facturas.length > 0) {
                txtInput.value = facturas.join('\n');
                showNotification(`✅ Se cargaron ${facturas.length} facturas del Excel.`, 'success');
            } else {
                showNotification('❌ No se encontraron facturas en el archivo.', 'error');
            }
        };
        reader.readAsArrayBuffer(file);
    }

    // --- PROCESAMIENTO ---
    btnProcess.addEventListener('click', async () => {
        const text = txtInput.value.trim();
        if (!text) {
            showNotification('⚠️ Por favor ingresa al menos una factura.', 'warning');
            return;
        }

        const invoices = text.split('\n')
            .map(f => f.trim())
            .filter(f => f !== '');

        if (invoices.length === 0) return;

        // Reset UI
        processedData = [];
        resultsTableBody.innerHTML = '';
        resultsContainer.classList.add('hidden');
        progressContainer.classList.remove('hidden');
        updateProgress(0, invoices.length);

        btnProcess.disabled = true;
        btnProcess.classList.add('opacity-50', 'cursor-not-allowed');

        // Procesar en lotes de 50 para evitar sobrecargar la API y dar feedback visual
        const batchSize = 50;
        for (let i = 0; i < invoices.length; i += batchSize) {
            const batch = invoices.slice(i, i + batchSize);
            try {
                const response = await fetch('api/consultar_tipo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ invoices: batch })
                });

                const result = await response.json();
                if (result.status === 'success') {
                    processedData.push(...result.data);
                    renderBatchResults(result.data);
                } else {
                    console.error('Error en lote:', result.message);
                }
            } catch (err) {
                console.error('Error de red:', err);
            }
            updateProgress(Math.min(i + batchSize, invoices.length), invoices.length);
        }

        btnProcess.disabled = false;
        btnProcess.classList.remove('opacity-50', 'cursor-not-allowed');
        resultsContainer.classList.remove('hidden');
        showNotification(`✅ Proceso finalizado. ${processedData.length} facturas procesadas.`, 'success');
    });

    btnClear.addEventListener('click', () => {
        txtInput.value = '';
        progressContainer.classList.add('hidden');
        resultsContainer.classList.add('hidden');
        resultsTableBody.innerHTML = '';
        processedData = [];
    });

    function updateProgress(current, total) {
        const percent = Math.round((current / total) * 100);
        progressText.textContent = `Procesando: ${current} / ${total}`;
        progressPercent.textContent = `${percent}%`;
        progressBar.style.width = `${percent}%`;
    }

    function renderBatchResults(data) {
        data.forEach(item => {
            const tr = document.createElement('tr');
            let badgeClass = 'bg-gray-100 text-gray-800';
            if (item.success) {
                badgeClass = 'bg-green-100 text-green-800';
            } else if (item.tipo === 'NO_ENCONTRADA') {
                badgeClass = 'bg-yellow-100 text-yellow-800';
            } else {
                badgeClass = 'bg-red-100 text-red-800';
            }

            tr.innerHTML = `
                <td class="px-4 py-2 whitespace-nowrap font-medium">${item.factura}</td>
                <td class="px-4 py-2 whitespace-nowrap">
                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800">${item.tipo}</span>
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${badgeClass}">
                        ${item.success ? 'Encontrada' : item.tipo}
                    </span>
                </td>
            `;
            resultsTableBody.appendChild(tr);
        });
    }

    // --- EXPORTAR ---
    btnExport.addEventListener('click', () => {
        if (!processedData.length) return;

        const worksheetData = processedData.map(item => ({
            'Factura Original': item.factura,
            'Tipo GEMA': item.tipo,
            'Resultado': item.success ? 'Encontrada' : (item.error || item.tipo)
        }));

        const ws = XLSX.utils.json_to_sheet(worksheetData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Resultados");
        XLSX.writeFile(wb, "resultados_tipos_glosa.xlsx");
    });

    // --- UTILIDADES ---
    function showNotification(msg, type = 'info') {
        // Podríamos usar un toast, por ahora console y alert si es grave
        console.log(`[TipoTool] ${type.toUpperCase()}: ${msg}`);
        if (type === 'error' || type === 'warning') {
            alert(msg);
        }
    }
});
