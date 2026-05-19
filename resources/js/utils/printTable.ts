interface PrintData {
    headers: string[];
    rows: string[][];
}

function buildPrintHtml(
    headers: string[],
    rows: string[][],
    title: string,
): string {
    return `<!DOCTYPE html>
<html>
<head>
    <title>${title}</title>
    <style>
        body { font-family: -apple-system, sans-serif; padding: 24px; color: #1a1a1a; }
        h1 { font-size: 18px; margin-bottom: 8px; }
        .date { font-size: 12px; color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: #f3f4f6; text-align: left; padding: 8px 6px; border-bottom: 2px solid #d1d5db; font-weight: 600; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f9fafb; }
        @media print {
            body { padding: 0; }
            @page { margin: 12mm; }
        }
    </style>
</head>
<body>
    <h1>${title}</h1>
    <div class="date">Printed on ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
    <table>
        <thead><tr>${headers.map((h) => `<th>${h}</th>`).join('')}</tr></thead>
        <tbody>${rows.map((cells) => `<tr>${cells.map((c) => `<td>${c}</td>`).join('')}</tr>`).join('')}</tbody>
    </table>
</body>
</html>`;
}

function openPrintWindow(html: string) {
    const printWindow = window.open('', '_blank', 'width=900,height=700');

    if (!printWindow) {
        return;
    }

    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}

/** Clone a table element's visible content into a print-friendly window and trigger print. */
export function printTableData(tableSelector: string, title: string) {
    const table = document.querySelector(
        tableSelector,
    ) as HTMLTableElement | null;

    if (!table) {
        return;
    }

    const headers: string[] = [];
    const rows: string[][] = [];

    table.querySelectorAll('thead th').forEach((th) => {
        const text = (th.textContent ?? '').trim();

        if (text) {
            headers.push(text);
        }
    });

    table.querySelectorAll('tbody tr').forEach((tr) => {
        const cells: string[] = [];
        tr.querySelectorAll('td').forEach((td) => {
            cells.push((td.textContent ?? '').trim());
        });

        if (cells.length > 0) {
            rows.push(cells);
        }
    });

    openPrintWindow(buildPrintHtml(headers, rows, title));
}

/** Fetch all matching records from a backend print endpoint and trigger a print dialog. */
export async function printAllTableData(title: string, dataUrl: string) {
    try {
        const response = await fetch(dataUrl);
        const { headers, rows }: PrintData = await response.json();

        openPrintWindow(buildPrintHtml(headers, rows, title));
    } catch {
        // Silently fail
    }
}
