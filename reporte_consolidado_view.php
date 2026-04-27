<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Consolidado de Glosas</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <!-- ExcelJS for Styled Export -->
    <script src="assets/lib/exceljs.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        montserrat: ['Montserrat', 'sans-serif'],
                        lato: ['Lato', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="font-lato bg-gray-100 text-gray-800 p-4 sm:p-8">

    <div class="max-w-7xl mx-auto bg-white shadow-xl rounded-xl overflow-hidden">
        
        <!-- Header -->
        <header class="bg-blue-600 text-white p-6 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-montserrat font-bold">Consolidado de Glosas</h1>
                <p class="text-blue-100 mt-1">Ingreso, Respuesta y Radicación Mensual</p>
            </div>
            <a href="reporte.php" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-lg transition text-sm font-semibold">
                &larr; Volver
            </a>
        </header>

        <!-- Filters -->
        <div class="p-6 bg-gray-50 border-b border-gray-200">
            <form id="consolidado-form" class="flex flex-col md:flex-row gap-4 items-end justify-center">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Fecha Inicio</label>
                    <input type="date" id="fecha_inicio" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Fecha Fin</label>
                    <input type="date" id="fecha_fin" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded font-bold transition shadow-lg flex items-center gap-2">
                    <span>Generar Reporte</span>
                </button>
            </form>
        </div>

        <!-- Results Area -->
        <div id="results-area" class="p-6 hidden">
            
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-700">Resultados</h2>
                <button id="btn-export-excel" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-semibold text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Exportar a Excel
                </button>
            </div>

            <div class="overflow-x-auto border rounded-lg shadow-sm">
                <table id="tabla-consolidado" class="w-full text-sm text-left text-gray-600">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-100 border-b">
                        <tr id="header-months-row">
                            <th class="px-6 py-3 border-r bg-gray-200">Concepto</th>
                            <!-- Month headers generated dynamically -->
                        </tr>
                        <tr id="header-columns-row">
                            <th class="px-6 py-3 border-r"></th>
                            <!-- Subheaders (Cant/Monto) generated dynamically -->
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr id="row-ingreso" class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-bold text-gray-900 border-r">Ingreso</td>
                        </tr>
                        <tr id="row-respuesta" class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-bold text-gray-900 border-r">Respuesta</td>
                        </tr>
                        <tr id="row-radicacion" class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-bold text-gray-900 border-r">Radicación</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
        
        <!-- Loading State -->
        <div id="loader" class="hidden p-12 text-center text-gray-500">
            <svg class="animate-spin h-10 w-10 text-blue-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p>Procesando datos... Por favor espere.</p>
        </div>

    </div>

    <script>
        const form = document.getElementById('consolidado-form');
        const resultsArea = document.getElementById('results-area');
        const loader = document.getElementById('loader');
        
        // Rows
        const rowIngreso = document.getElementById('row-ingreso');
        const rowRespuesta = document.getElementById('row-respuesta');
        const rowRadicacion = document.getElementById('row-radicacion');
        
        // Headers
        const headerMonthsRow = document.getElementById('header-months-row');
        const headerColumnsRow = document.getElementById('header-columns-row');

        // Defined breakdown keys for ordering
        const BREAKDOWN_KEYS = {
            'ingreso': ['NU', 'R2', 'R3', 'R4', 'AE'],
            'respuesta': ['C1', 'C2', 'C3', 'CO', 'AI']
        };

        // Helper to generate list of monthly ranges
        function getMonthlyRanges(startDate, endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const ranges = [];
            
            let current = new Date(start);
            
            while (current <= end) {
                const year = current.getFullYear();
                const month = current.getMonth();
                const lastDayOfMonth = new Date(year, month + 1, 0); 
                
                let chunkEnd = lastDayOfMonth;
                if (chunkEnd > end) {
                    chunkEnd = end;
                }
                
                ranges.push({
                    start: current.toISOString().split('T')[0],
                    end: chunkEnd.toISOString().split('T')[0]
                });
                
                current = new Date(year, month + 1, 1);
            }
            return ranges;
        }

        // Helper: Fetch with Retry
        async function fetchWithRetry(url, retries = 2) {
            for (let i = 0; i <= retries; i++) {
                try {
                    const res = await fetch(url);
                    if (!res.ok) {
                        const text = await res.text();
                        throw new Error(`Error HTTP ${res.status}: ${text}`);
                    }
                    const json = await res.json();
                    if (json.status === 'error') throw new Error(json.message);
                    return json.data;
                } catch (err) {
                    if (i === retries) throw err;
                    // Exponential backoff: 500ms, 1000ms...
                    await new Promise(r => setTimeout(r, 500 * Math.pow(2, i)));
                }
            }
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const f_ini = document.getElementById('fecha_inicio').value;
            const f_fin = document.getElementById('fecha_fin').value;
            
            if (!f_ini || !f_fin) {
                alert("Por favor seleccione ambas fechas.");
                return;
            }

            resultsArea.classList.add('hidden');
            loader.classList.remove('hidden');
            cleanTable();

            try {
                // 1. Generate Ranges
                const ranges = getMonthlyRanges(f_ini, f_fin);
                
                // 2. Create Fetch Promises (All at once, with retry)
                const fetchPromises = ranges.map(range => {
                    const url = `api/reporte_consolidado.php?fecha_inicio=${range.start}&fecha_fin=${range.end}`;
                    return fetchWithRetry(url)
                        .then(data => ({ status: 'ok', data: data }))
                        .catch(err => ({ status: 'error', message: err.message, range: range }));
                });

                // 3. Execute All in Parallel
                const responses = await Promise.all(fetchPromises);
                
                // 4. Merge Results
                const combinedData = {};
                let hasError = false;
                let errorMessage = "";

                responses.forEach(result => {
                    if (result.status === 'ok') {
                        Object.keys(result.data).forEach(key => {
                            // Ensure key structure exists
                            if (!combinedData[key]) combinedData[key] = {
                                ingreso: { cantidad: 0, monto: 0, breakdown: {} },
                                respuesta: { cantidad: 0, monto: 0, breakdown: {} },
                                radicacion: { cantidad: 0, monto: 0, breakdown: {} }
                            };

                            // Merge Logic (Always merge, even if new key, to handle structure consistently)
                            ['ingreso', 'respuesta', 'radicacion'].forEach(type => {
                                const source = result.data[key][type];
                                const target = combinedData[key][type];

                                target.cantidad += Number(source.cantidad || 0);
                                target.monto += Number(source.monto || 0);
                                
                                const sourceBreakdown = source.breakdown || {};
                                const targetBreakdown = target.breakdown;
                                
                                Object.keys(sourceBreakdown).forEach(code => {
                                    if (!targetBreakdown[code]) {
                                        // Clone to avoid reference issues
                                        targetBreakdown[code] = { ...sourceBreakdown[code] };
                                        // Ensure numbers
                                        targetBreakdown[code].cantidad = Number(targetBreakdown[code].cantidad);
                                        targetBreakdown[code].monto = Number(targetBreakdown[code].monto);
                                    } else {
                                        targetBreakdown[code].cantidad += Number(sourceBreakdown[code].cantidad || 0);
                                        targetBreakdown[code].monto += Number(sourceBreakdown[code].monto || 0);
                                    }
                                });
                            });
                        });
                    } else {
                        hasError = true;
                        errorMessage = result.message || "Error desconocido";
                        console.error("Sub-consulta fallida:", result);
                    }
                });

                if (hasError) {
                    alert('Atención: Algunos periodos no se pudieron cargar. ' + errorMessage);
                }

                if (Object.keys(combinedData).length === 0) {
                     alert("No se encontraron datos en el rango seleccionado (o falló la carga).");
                } else {
                     renderTable(combinedData);
                     resultsArea.classList.remove('hidden');
                }

            } catch (error) {
                console.error(error);
                alert('Ocurrió un error general: ' + error.message);
            } finally {
                loader.classList.add('hidden');
            }
        });

        function cleanTable() {
            // Remove all cells except the first one (Title)
            while (headerMonthsRow.children.length > 1) headerMonthsRow.removeChild(headerMonthsRow.lastChild);
            while (headerColumnsRow.children.length > 1) headerColumnsRow.removeChild(headerColumnsRow.lastChild);
        }

        function renderTable(data) {
            const months = Object.keys(data).sort();
            
            if (months.length === 0) {
                alert("No se encontraron datos en este rango.");
                return;
            }

            // 1. Build Headers
            months.forEach(mesKey => {
                const thMonth = document.createElement('th');
                thMonth.colSpan = 2;
                thMonth.className = "px-6 py-3 border-b border-r text-center bg-blue-50 text-blue-800 font-bold border-gray-300";
                thMonth.innerText = formatMonth(mesKey);
                headerMonthsRow.appendChild(thMonth);

                const thCant = document.createElement('th');
                thCant.innerText = "No. Glosas";
                thCant.className = "px-3 py-2 border-r bg-gray-50 font-semibold text-center text-xs";
                
                const thMonto = document.createElement('th');
                thMonto.innerText = "Monto";
                thMonto.className = "px-3 py-2 border-r bg-gray-50 font-semibold text-center text-xs";

                headerColumnsRow.appendChild(thCant);
                headerColumnsRow.appendChild(thMonto);
            });

            // Add Header for TOTAL
            const thTotalMonth = document.createElement('th');
            thTotalMonth.colSpan = 2;
            thTotalMonth.className = "px-6 py-3 border-b border-r text-center bg-gray-600 text-white font-bold border-gray-500";
            thTotalMonth.innerText = "TOTAL";
            headerMonthsRow.appendChild(thTotalMonth);

            const thCantTotal = document.createElement('th');
            thCantTotal.innerText = "No. Glosas";
            thCantTotal.className = "px-3 py-2 border-r bg-gray-100 font-bold text-center text-xs";
            
            const thMontoTotal = document.createElement('th');
            thMontoTotal.innerText = "Monto";
            thMontoTotal.className = "px-3 py-2 border-r bg-gray-100 font-bold text-center text-xs";

            headerColumnsRow.appendChild(thCantTotal);
            headerColumnsRow.appendChild(thMontoTotal);

            // 2. Build Main Rows & Breakdown Rows
            const tbody = document.getElementById('tabla-consolidado').querySelector('tbody');
            tbody.innerHTML = ''; // Full clear

            // --- INGRESO ---
            createMainRow(tbody, 'Ingreso', 'ingreso', months, data, true);

            // --- RESPUESTA ---
            createMainRow(tbody, 'Respuesta', 'respuesta', months, data, true);

            // --- RADICACION ---
            // User requested NO Breakdown for Radicacion (pass false)
            createMainRow(tbody, 'Radicación', 'radicacion', months, data, false);
        }

        function createMainRow(tbody, title, typeKey, months, allData, hasBreakdown) {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-gray-50 cursor-pointer transition-colors";
            
            // Title Cell
            const tdTitle = document.createElement('td');
            tdTitle.className = "px-6 py-4 font-bold text-gray-900 border-r flex items-center gap-2";
            
            if (hasBreakdown) {
                const icon = document.createElement('span');
                icon.className = "text-blue-500 font-bold text-lg leading-none w-4 h-4 flex items-center justify-center transform transition-transform";
                icon.innerText = "+";
                icon.dataset.iconFor = typeKey;
                tdTitle.appendChild(icon);
            }
            
            const spanTitle = document.createElement('span');
            spanTitle.innerText = title;
            tdTitle.appendChild(spanTitle);
            tr.appendChild(tdTitle);

            let rowTotalCant = 0;
            let rowTotalMonto = 0;

            // Data Cells for each month
            months.forEach(mes => {
                const monthData = allData[mes][typeKey];
                appendDataCells(tr, monthData, false);
                rowTotalCant += Number(monthData.cantidad || 0);
                rowTotalMonto += Number(monthData.monto || 0);
            });

            // Append Total Cells
            appendDataCells(tr, { cantidad: rowTotalCant, monto: rowTotalMonto }, false, true);

            tbody.appendChild(tr);

            if (hasBreakdown) {
                // Toggle Logic
                tr.addEventListener('click', () => {
                    const rows = document.querySelectorAll(`.breakdown-row-${typeKey}`);
                    const icon = tr.querySelector(`[data-icon-for="${typeKey}"]`);
                    let isHidden = true;
                    if (rows.length > 0) {
                         // Check status of first one
                         isHidden = rows[0].classList.contains('hidden');
                         rows.forEach(r => {
                             if (isHidden) r.classList.remove('hidden');
                             else r.classList.add('hidden');
                         });
                         icon.innerText = isHidden ? "-" : "+"; // Fix logic: if was hidden, now visible (-)
                    }
                });

                // Determine Breakdown Keys
                let breakdownCodes = [];
                if (BREAKDOWN_KEYS[typeKey]) {
                    breakdownCodes = BREAKDOWN_KEYS[typeKey];
                } else {
                    const allKeys = new Set();
                    months.forEach(m => {
                        if (allData[m][typeKey].breakdown) {
                             Object.keys(allData[m][typeKey].breakdown).forEach(k => allKeys.add(k));
                        }
                    });
                    breakdownCodes = Array.from(allKeys).sort();
                }
                
                breakdownCodes.forEach(code => {
                    const subTr = document.createElement('tr');
                    subTr.className = `breakdown-row-${typeKey} hidden bg-gray-50 text-xs text-gray-500`;
                    
                    const tdSubTitle = document.createElement('td');
                    tdSubTitle.className = "px-8 py-2 border-r font-mono font-semibold truncate max-w-xs";
                    tdSubTitle.innerText = code; // Removed title attribute for cleaner HTML, code is short
                    subTr.appendChild(tdSubTitle);

                    let subTotalCant = 0;
                    let subTotalMonto = 0;

                    months.forEach(mes => {
                        const monthData = allData[mes][typeKey];
                        const subData = monthData.breakdown && monthData.breakdown[code] 
                            ? monthData.breakdown[code] 
                            : { cantidad: 0, monto: 0 };
                        
                        appendDataCells(subTr, subData, true);
                        subTotalCant += subData.cantidad || 0;
                        subTotalMonto += subData.monto || 0;
                    });

                    // Append Subtotal Cells
                    appendDataCells(subTr, { cantidad: subTotalCant, monto: subTotalMonto }, true, true);

                    tbody.appendChild(subTr);
                });
            }
        }

        function appendDataCells(row, dataObj, isSmall, isTotal = false) {
            const tdCant = document.createElement('td');
            const bgClass = isTotal ? (isSmall ? 'bg-gray-100' : 'bg-gray-200 font-bold') : '';
            tdCant.className = `px-4 ${isSmall?'py-2':'py-3'} border-r text-center ${bgClass}`;
            tdCant.innerText = dataObj.cantidad > 0 ? new Intl.NumberFormat('es-CO').format(dataObj.cantidad) : '-';
            
            const tdMonto = document.createElement('td');
            tdMonto.className = `px-4 ${isSmall?'py-2':'py-3'} border-r text-right whitespace-nowrap ${bgClass}`;
            tdMonto.innerText = dataObj.monto > 0 ? formatCurrency(dataObj.monto) : '-';

            row.appendChild(tdCant);
            row.appendChild(tdMonto);
        }

        function formatMonth(yyyy_mm) {
            const [y, m] = yyyy_mm.split('-');
            const date = new Date(y, m - 1); 
            return date.toLocaleString('es-ES', { month: 'long', year: 'numeric' }).toUpperCase();
        }

        function formatCurrency(val) {
            return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(val);
        }
        
        // GLOBAL DATA STORAGE for Export (Cheap way to access data without passing it everywhere)
        let URL_LAST_DATA = null;
        
        // Export to Excel Custom Function (ExcelJS)
        document.getElementById('btn-export-excel').addEventListener('click', async () => {
             if (!URL_LAST_DATA) { alert("No hay datos para exportar."); return; }
             
             const data = URL_LAST_DATA;
             const months = Object.keys(data).sort();
             
             // Create Workbook & Sheet
             const workbook = new ExcelJS.Workbook();
             const msg = "Consolidado";
             const worksheet = workbook.addWorksheet(msg);

             // Styles
             const fontBold = { name: 'Calibri', size: 11, bold: true };
             const fontNormal = { name: 'Calibri', size: 11, bold: false };
             const alignCenter = { vertical: 'middle', horizontal: 'center' };
             const alignLeft = { vertical: 'middle', horizontal: 'left' };
             const borderStyle = { style: 'thin', color: { argb: '000000' } };
             const borders = { top: borderStyle, left: borderStyle, bottom: borderStyle, right: borderStyle };
             
             // Fills (ARGB)
             const fillHeader = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFFFFF' } }; // White/None
             const fillIngreso = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFDDEBF7' } };
             const fillRespuesta = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFCE4D6' } };
             const fillRadicacion = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE2EFDA' } };
             
             // Accounting Format
             const numFmtAccounting = '_("$"* #,##0_);_("$"* (#,##0);_("$"* "-"_);_(@_)';

             // -- 1. Setup Columns --
             const columns = [{ header: 'Concepto', key: 'concepto', width: 30 }];
             months.forEach(m => {
                 columns.push({ header: '', key: `cant_${m}`, width: 15 });
                 columns.push({ header: '', key: `monto_${m}`, width: 22 });
             });
             columns.push({ header: 'Total Cant', key: 'total_cant', width: 15 });
             columns.push({ header: 'Total Monto', key: 'total_monto', width: 22 });
             worksheet.columns = columns;

             // -- 2. Headers Management (Manual Row Construction for Merges) --
             
             // Row 1: Months
             const row1 = worksheet.getRow(1);
             row1.getCell(1).value = "Concepto";
             row1.getCell(1).style = { font: fontBold, alignment: alignCenter, border: borders };
             
             let colIdx = 2;
             months.forEach(m => {
                 const cell = row1.getCell(colIdx);
                 cell.value = formatMonth(m);
                 cell.style = { font: fontBold, alignment: alignCenter, border: borders };
                 
                 // Merge next cell
                 worksheet.mergeCells(1, colIdx, 1, colIdx + 1);
                 
                 // Apply border to merged cell placeholder too
                 row1.getCell(colIdx+1).style = { border: borders };
                 
                 colIdx += 2;
             });
             
             // Total Header
             const cellTotal = row1.getCell(colIdx);
             cellTotal.value = "TOTAL";
             cellTotal.style = { font: fontBold, alignment: alignCenter, border: borders };
             worksheet.mergeCells(1, colIdx, 1, colIdx + 1);
             row1.getCell(colIdx+1).style = { border: borders };
             
             // Row 2: Sub-headers
             const row2 = worksheet.getRow(2);
             row2.getCell(1).style = { border: borders }; // Empty below Concepto match
             
             colIdx = 2;
             const addSubHeaders = () => {
                 const c1 = row2.getCell(colIdx);
                 c1.value = "No. Glosas";
                 c1.style = { font: fontBold, alignment: alignCenter, border: borders };
                 
                 const c2 = row2.getCell(colIdx + 1);
                 c2.value = "Monto";
                 c2.style = { font: fontBold, alignment: alignCenter, border: borders };
                 colIdx += 2;
             };
             
             months.forEach(() => addSubHeaders());
             addSubHeaders(); // For Total
             
             // -- 3. Data Rows --
             const addDataRow = (title, typeKey, isBreakdown) => {
                 const rowValues = [];
                 // Indent title
                 rowValues[1] = isBreakdown ? "   " + title : title;
                 
                 let totalC = 0, totalM = 0;
                 let idx = 2;
                 
                 months.forEach(mes => {
                    const monthData = data[mes][typeKey];
                    let c = 0, m = 0;
                    if (isBreakdown) {
                         if (monthData.breakdown && monthData.breakdown[title]) {
                             c = Number(monthData.breakdown[title].cantidad || 0);
                             m = Number(monthData.breakdown[title].monto || 0);
                         }
                    } else {
                         c = Number(monthData.cantidad || 0);
                         m = Number(monthData.monto || 0);
                    }
                    totalC += c;
                    totalM += m;
                    
                    rowValues[idx] = c;
                    rowValues[idx+1] = m;
                    idx += 2;
                 });
                 
                 rowValues[idx] = totalC;
                 rowValues[idx+1] = totalM;
                 
                 const row = worksheet.addRow(rowValues); // Needs dense array or object? Sparse array works with getCell
                 // Fix array to be 1-based logic compatible? addRow takes standard array [col1, col2...]
                 // Let's reset and pass clean array
                 
                 const cleanValues = [];
                 cleanValues.push(isBreakdown ? "   " + title : title);
                 months.forEach(mes => {
                    const monthData = data[mes][typeKey];
                    let c = 0, m = 0;
                    if (isBreakdown) {
                         if (monthData.breakdown && monthData.breakdown[title]) {
                             c = monthData.breakdown[title].cantidad;
                             m = monthData.breakdown[title].monto;
                         }
                    } else {
                         c = monthData.cantidad;
                         m = monthData.monto;
                    }
                    cleanValues.push(c || 0);
                    cleanValues.push(m || 0);
                 });
                 cleanValues.push(totalC, totalM);
                 
                 // Commit row values
                 row.values = cleanValues;

                 // Styling
                 let fill = null;
                 if (typeKey === 'ingreso') fill = fillIngreso;
                 else if (typeKey === 'respuesta') fill = fillRespuesta;
                 else if (typeKey === 'radicacion') fill = fillRadicacion;
                 
                 const font = isBreakdown ? fontNormal : fontBold;
                 
                 row.eachCell({ includeEmpty: true }, (cell, colNumber) => {
                     cell.border = borders;
                     cell.font = font;
                     if (fill) cell.fill = fill;
                     
                     if (colNumber === 1) {
                         cell.alignment = alignLeft;
                     } else {
                         cell.alignment = alignCenter;
                         // Check if this is a Monto column (Even index in cleanValues? No.)
                         // Col 1 = Title. Col 2 = Qty. Col 3 = Monto. Col 4 = Qty. Col 5 = Monto.
                         // So odd numbers > 1 are Amounts (3, 5, 7...)
                         if (colNumber > 1 && colNumber % 2 !== 0) {
                             cell.numFmt = numFmtAccounting;
                         }
                     }
                 });
             };

            // INGRESO
            addDataRow("Ingreso", "ingreso", false);
            if (BREAKDOWN_KEYS['ingreso']) {
                BREAKDOWN_KEYS['ingreso'].forEach(code => addDataRow(code, "ingreso", true));
            }
            
            // RESPUESTA
            addDataRow("Respuesta", "respuesta", false);
             if (BREAKDOWN_KEYS['respuesta']) {
                BREAKDOWN_KEYS['respuesta'].forEach(code => addDataRow(code, "respuesta", true));
            }
            
            // RADICACION
            addDataRow("Radicación", "radicacion", false);
            
            // Download
            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            
            const url = window.URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = `Consolidado_Glosas_${new Date().toISOString().slice(0,10)}.xlsx`;
            anchor.click();
            window.URL.revokeObjectURL(url);
        });

        // Store data globally when rendering
        const originalRender = renderTable;
        renderTable = function(data) {
             URL_LAST_DATA = data; // Catch data here
             originalRender(data);
        };

        // Set default dates (Current Month)
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        document.getElementById('fecha_inicio').valueAsDate = firstDay;
        document.getElementById('fecha_fin').valueAsDate = today;

    </script>
</body>
</html>
