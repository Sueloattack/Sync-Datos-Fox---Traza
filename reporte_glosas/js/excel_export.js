/**
 * reporte_glosas/js/excel_export.js
 * Exportación Excel para ambos reportes (Malla y Unificado)
 */

const btnDescargarUnificado = document.getElementById('btn-descargar-unificado');

// Download button for malla
btnDescargar.addEventListener('click', async () => {
    try {
        if (isUnificado()) {
            await exportUnificadoExcel();
        } else {
            await exportMallaExcel();
        }
    } catch (err) {
        console.error('Error al exportar Excel:', err);
        alert('Error al exportar Excel: ' + err.message);
    }
});

// Download button for unified (separate button inside unified container)
if (btnDescargarUnificado) {
    btnDescargarUnificado.addEventListener('click', async () => {
        try {
            await exportUnificadoExcel();
        } catch (err) {
            console.error('Error al exportar Excel Unificado:', err);
            alert('Error al exportar Excel: ' + err.message);
        }
    });
}

// Override finishUI to also enable unified download button
const originalFinishUI = finishUI;
finishUI = function () {
    originalFinishUI();
    if (btnDescargarUnificado) {
        btnDescargarUnificado.disabled = (allResultsUnificado.length === 0);
    }
};

// =============================================
// EXPORT MALLA (Original)
// =============================================
async function exportMallaExcel() {
    if (allResultsData.length === 0) return;

    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Glosas', {
        views: [{ state: 'frozen', ySplit: 1 }]
    });

    worksheet.columns = [
        { header: 'Factura', key: 'factura', width: 15 },
        { header: 'VALOR FACTURA', key: 'v_fra', width: 18 },
        { header: 'RADICACION', key: 'radicacion', width: 15 },
        { header: 'Item', key: 'item', width: 10 },
        { header: 'VALOR GLOSA', key: 'v_glosa', width: 18 },
        { header: 'NU', key: 'NU', width: 40 },
        { header: 'C1', key: 'C1', width: 40 },
        { header: 'CC', key: 'C1_CC', width: 10 },
        { header: 'fecha rad', key: 'C1_FR', width: 15 },
        { header: 'R2', key: 'R2', width: 40 },
        { header: 'C2', key: 'C2', width: 40 },
        { header: 'CC', key: 'C2_CC', width: 10 },
        { header: 'fecha rad', key: 'C2_FR', width: 15 },
        { header: 'R3', key: 'R3', width: 40 },
        { header: 'C3/CO', key: 'C3', width: 40 },
        { header: 'CC', key: 'C3_CC', width: 10 },
        { header: 'fecha rad', key: 'C3_FR', width: 15 },
        { header: 'R4', key: 'R4', width: 40 },
        { header: 'Final', key: 'CO', width: 20 }
    ];

    // Header style
    const headerRow = worksheet.getRow(1);
    headerRow.height = 35;
    headerRow.eachCell((cell, colNumber) => {
        let fillColor = 'D9EAD3';
        if (colNumber <= 5) fillColor = 'CFE2F3';
        if (colNumber > 5) fillColor = 'F4CCCC';

        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF' + fillColor } };
        cell.font = { bold: true, size: 12 };
        cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
        cell.border = {
            top: { style: 'thin' }, left: { style: 'thin' },
            bottom: { style: 'thin' }, right: { style: 'thin' }
        };
    });

    allResultsData.forEach(r => {
        const row = worksheet.addRow({
            factura: r.factura, v_fra: r.v_fra, radicacion: r.radicacion,
            item: r.item, v_glosa: r.v_glosa, NU: r.NU,
            C1: r.C1, C1_CC: r.C1_CC, C1_FR: r.C1_FR,
            R2: r.R2, C2: r.C2, C2_CC: r.C2_CC, C2_FR: r.C2_FR,
            R3: r.R3, C3: r.C3, C3_CC: r.C3_CC, C3_FR: r.C3_FR,
            R4: r.R4, CO: r.CO && r.C3 ? 'CONCILIADO' : (r.CO || '')
        });

        row.eachCell({ includeEmpty: true }, (cell, colNumber) => {
            cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
            cell.border = {
                top: { style: 'thin' }, left: { style: 'thin' },
                bottom: { style: 'thin' }, right: { style: 'thin' }
            };
            if (colNumber === 2 || colNumber === 5) cell.numFmt = '#,##0';
        });
        row.height = 100;
    });

    // Merging
    let currentFactura = "";
    let startRow = 2;
    for (let i = 0; i <= allResultsData.length; i++) {
        const rowFactura = allResultsData[i] ? allResultsData[i].factura : null;
        if (rowFactura !== currentFactura || i === allResultsData.length) {
            if (currentFactura !== "" && (i + 1) > startRow) {
                const endRow = i + 1;
                worksheet.mergeCells(`A${startRow}:A${endRow}`);
                worksheet.mergeCells(`B${startRow}:B${endRow}`);
                worksheet.mergeCells(`C${startRow}:C${endRow}`);
            }
            currentFactura = rowFactura;
            startRow = i + 2;
        }
    }

    downloadWorkbook(workbook, `Malla_Glosas_Pro_${new Date().toISOString().split('T')[0]}.xlsx`);
}

// =============================================
// EXPORT UNIFICADO (Nuevo)
// =============================================
async function exportUnificadoExcel() {
    if (allResultsUnificado.length === 0) return;

    const workbook = new ExcelJS.Workbook();
    const ws = workbook.addWorksheet('Reporte Unificado', {
        views: [{ state: 'frozen', ySplit: 1 }]
    });

    // Definir columnas según especificación del usuario
    ws.columns = [
        { header: 'Factura', key: 'factura', width: 16 },
        { header: 'NIT', key: 'nit', width: 14 },
        { header: 'TERCERO', key: 'tercero_nombre', width: 30 },
        { header: 'CODIGO', key: 'codigo', width: 18 },
        { header: 'CONCEPTO', key: 'concepto', width: 30 },
        { header: 'TIPO', key: 'tipo', width: 8 },
        { header: 'VALOR FACTURA', key: 'valor_factura', width: 18 },
        // NU
        { header: 'FECHA INGRESO', key: 'nu_fecha_ingreso', width: 14 },
        { header: 'VALOR GLOSA', key: 'nu_valor_glosa', width: 16 },
        { header: 'NU', key: 'nu_motivo', width: 50 },
        { header: 'FECHA RESP GLOSA', key: 'nu_fecha_resp', width: 14 },
        { header: 'VALOR ACEPTADO', key: 'nu_valor_acep', width: 16 },
        { header: 'MOTIVO ACEPTACION', key: 'nu_motivo_acep', width: 50 },
        { header: 'COD RESPUESTA', key: 'nu_cod_resp', width: 14 },
        // C1
        { header: 'C1', key: 'c1_motivo', width: 50 },
        { header: 'Cuenta de cobro', key: 'c1_cuenta_cobro', width: 14 },
        { header: 'Fecha radicado', key: 'c1_fecha_radicado', width: 14 },
        // R2
        { header: 'FECHA INGRESO', key: 'r2_fecha_ingreso', width: 14 },
        { header: 'VALOR GLOSA', key: 'r2_valor_glosa', width: 16 },
        { header: 'R2', key: 'r2_motivo', width: 50 },
        { header: 'FECHA RESP GLOSA', key: 'r2_fecha_resp', width: 14 },
        { header: 'VALOR ACEPTADO', key: 'r2_valor_acep', width: 16 },
        { header: 'MOTIVO ACEPTACION', key: 'r2_motivo_acep', width: 50 },
        { header: 'COD RESPUESTA', key: 'r2_cod_resp', width: 14 },
        // C2
        { header: 'C2', key: 'c2_motivo', width: 50 },
        { header: 'Cuenta de cobro', key: 'c2_cuenta_cobro', width: 14 },
        { header: 'Fecha radicado', key: 'c2_fecha_radicado', width: 14 },
        // R3
        { header: 'FECHA INGRESO', key: 'r3_fecha_ingreso', width: 14 },
        { header: 'VALOR GLOSA', key: 'r3_valor_glosa', width: 16 },
        { header: 'R3', key: 'r3_motivo', width: 50 },
        { header: 'FECHA RESP GLOSA', key: 'r3_fecha_resp', width: 14 },
        { header: 'VALOR ACEPTADO', key: 'r3_valor_acep', width: 16 },
        { header: 'MOTIVO ACEPTACION', key: 'r3_motivo_acep', width: 50 },
        { header: 'COD RESPUESTA', key: 'r3_cod_resp', width: 14 },
        // C3/CO
        { header: 'C3/CO', key: 'c3_motivo', width: 50 },
        { header: 'Cuenta de cobro', key: 'c3_cuenta_cobro', width: 14 },
        { header: 'Fecha radicado', key: 'c3_fecha_radicado', width: 14 },
        // R4
        { header: 'FECHA INGRESO', key: 'r4_fecha_ingreso', width: 14 },
        { header: 'VALOR GLOSA', key: 'r4_valor_glosa', width: 16 },
        { header: 'R4', key: 'r4_motivo', width: 50 },
        { header: 'FECHA RESP GLOSA', key: 'r4_fecha_resp', width: 14 },
        { header: 'VALOR ACEPTADO', key: 'r4_valor_acep', width: 16 },
        { header: 'MOTIVO ACEPTACION', key: 'r4_motivo_acep', width: 50 },
        { header: 'COD RESPUESTA', key: 'r4_cod_resp', width: 14 },
        // CO
        { header: 'CO', key: 'co_motivo', width: 30 },
    ];

    // Header colors by section
    const colorMap = {
        base: 'FFCFE2F3',    // azul claro
        nu: 'FFD5A6BD',      // rosa
        c1: 'FFF4CCCC',      // rojo claro
        r2: 'FFFCE5CD',      // naranja claro
        c2: 'FFFFF2CC',      // amarillo claro
        r3: 'FFD9EAD3',      // verde claro
        c3: 'FFD0E0E3',      // teal claro
        r4: 'FFCFE2F3',      // azul claro
        co: 'FFEAD1DC',      // rosa
    };

    function getHeaderColor(colNum) {
        if (colNum <= 7) return colorMap.base;
        if (colNum <= 14) return colorMap.nu;
        if (colNum <= 17) return colorMap.c1;
        if (colNum <= 24) return colorMap.r2;
        if (colNum <= 27) return colorMap.c2;
        if (colNum <= 34) return colorMap.r3;
        if (colNum <= 37) return colorMap.c3;
        if (colNum <= 44) return colorMap.r4;
        return colorMap.co;
    }

    // Style header row
    const headerRow = ws.getRow(1);
    headerRow.height = 40;
    headerRow.eachCell((cell, colNumber) => {
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: getHeaderColor(colNumber) } };
        cell.font = { bold: true, size: 10, name: 'Calibri' };
        cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
        cell.border = {
            top: { style: 'thin' }, left: { style: 'thin' },
            bottom: { style: 'medium' }, right: { style: 'thin' }
        };
    });

    // Add data rows
    const moneyColumns = [7, 9, 12, 19, 22, 29, 32, 39, 42]; // VALOR columns (1-indexed)

    allResultsUnificado.forEach(r => {
        const row = ws.addRow(r);
        row.eachCell({ includeEmpty: true }, (cell, colNumber) => {
            cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
            cell.border = {
                top: { style: 'thin' }, left: { style: 'thin' },
                bottom: { style: 'thin' }, right: { style: 'thin' }
            };
            cell.font = { size: 9, name: 'Calibri' };

            if (moneyColumns.includes(colNumber)) {
                cell.numFmt = '#,##0';
                cell.alignment = { vertical: 'middle', horizontal: 'right' };
            }

            // Light tint for status sections
            const color = getHeaderColor(colNumber);
            // Make data lighter (add some white)
            cell.fill = {
                type: 'pattern',
                pattern: 'solid',
                fgColor: { argb: 'FFFFFFFF' }  // white for data rows
            };
        });
        row.height = 80;
    });

    downloadWorkbook(workbook, `Reporte_Unificado_Glosas_${new Date().toISOString().split('T')[0]}.xlsx`);
}

// =============================================
// Helper: Download
// =============================================
async function downloadWorkbook(workbook, filename) {
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}
