import { router } from '@inertiajs/react';
import { Check, Printer, X } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toManilaTime } from '@/utils/dateHelper';

interface TagEntry {
    id: number;
    name: string;
    color: string;
    price_per_piece?: string;
    pivot: { quantity: number; price_per_piece?: string | null };
}

interface PayslipItem {
    id: number;
    amount: number;
    quantity: number;
    notes: string | null;
    sewed_date: string;
    created_at: string;
    tags: TagEntry[];
    sublimation: { description: string } | null;
}

interface PayslipDialogProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    items: PayslipItem[];
    generatedBy: string;
    payslipId: number | null;
}

const peso = (value: number) =>
    `₱${value.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const tagPrice = (tag: TagEntry) =>
    Number(tag.pivot.price_per_piece ?? tag.price_per_piece ?? 0);

// Escape values before injecting into the print document's HTML string.
const esc = (value: string) =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

export default function PayslipDialog({
    open,
    setOpen,
    items,
    generatedBy,
    payslipId,
}: PayslipDialogProps) {
    const grandTotal = items.reduce((sum, i) => sum + Number(i.amount), 0);
    const generatedAt = new Date().toLocaleString('en-PH', {
        dateStyle: 'long',
        timeStyle: 'short',
    });

    // Build a clean, self-contained document and print it from a dedicated
    // window. This avoids fighting the transformed dialog / app DOM with
    // print-only CSS overrides, which is what kept breaking the old layout.
    const handlePrint = () => {
        const rows = items
            .map((item) => {
                const tags = item.tags ?? [];
                const description = esc(item.sublimation?.description ?? '—');
                const date = toManilaTime(item.created_at, 'MMM DD, YYYY');

                const tagRows = tags
                    .map((tag, index) => {
                        const qty = tag.pivot.quantity;
                        const price = tagPrice(tag);
                        const amount = qty * price;

                        return `
                            <tr>
                                <td>${index === 0 ? esc(date) : ''}</td>
                                <td>${index === 0 ? description : ''}</td>
                                <td>
                                    <span class="dot" style="background:${esc(tag.color)}"></span>${esc(tag.name)}
                                </td>
                                <td class="num">${qty}</td>
                                <td class="num">${peso(price)}</td>
                                <td class="num">${peso(amount)}</td>
                            </tr>`;
                    })
                    .join('');

                return `
                    ${tagRows}
                    <tr class="subtotal">
                        <td colspan="3"></td>
                        <td class="num">${item.quantity}</td>
                        <td></td>
                        <td class="num">${peso(Number(item.amount))}</td>
                    </tr>`;
            })
            .join('');

        const html = `<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Sewed Items Payslip</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            font-size: 11px;
            margin: 0;
        }
        h1 { font-size: 16px; text-align: center; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td {
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        thead th { background: #f4f4f5; border-bottom: 1px solid #d4d4d8; }
        tbody td { border-bottom: 1px solid #ececee; }
        col.date { width: 13%; }
        col.desc { width: 27%; }
        col.cat { width: 25%; }
        col.qty { width: 9%; }
        col.price { width: 13%; }
        col.amt { width: 13%; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            margin-right: 5px;
            vertical-align: middle;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        tr.subtotal td {
            background: #fafafa;
            border-top: 1px solid #d4d4d8;
            border-bottom: 1px solid #d4d4d8;
            font-weight: 600;
        }
        tfoot td {
            border-top: 2px solid #111;
            font-weight: 700;
            padding-top: 6px;
        }
        .footer {
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-top: 1px solid #d4d4d8;
            padding-top: 10px;
        }
        .footer .muted { color: #52525b; font-size: 10px; }
        .footer .total { font-size: 16px; font-weight: 700; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
    </style>
</head>
<body>
    <h1>SEWED ITEMS PAYSLIP</h1>
    <table>
        <colgroup>
            <col class="date" /><col class="desc" /><col class="cat" />
            <col class="qty" /><col class="price" /><col class="amt" />
        </colgroup>
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Category</th>
                <th class="num">Qty</th>
                <th class="num">Price/piece</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>${rows}</tbody>
        <tfoot>
            <tr>
                <td colspan="5">Grand Total</td>
                <td class="num">${peso(grandTotal)}</td>
            </tr>
        </tfoot>
    </table>
    <div class="footer">
        <div>
            <div>Generated by: <strong>${esc(generatedBy)}</strong></div>
            <div class="muted">${esc(generatedAt)}</div>
        </div>
        <div style="text-align:right">
            <div class="muted">Total Amount</div>
            <div class="total">${peso(grandTotal)}</div>
        </div>
    </div>
</body>
</html>`;

        const win = window.open('', '_blank', 'width=900,height=1000');

        if (!win) {
            toast.error('Unable to open print window. Please allow pop-ups.');

            return;
        }

        win.document.write(html);
        win.document.close();
        win.focus();

        // Give the new document a tick to lay out before invoking print.
        win.onload = () => {
            win.print();
        };
        setTimeout(() => {
            try {
                win.print();
            } catch {
                // onload already handled it.
            }
        }, 300);
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <DialogHeader>
                            <DialogTitle>Sewed Items Payslip</DialogTitle>
                        </DialogHeader>
                        <div className="flex items-center gap-2">
                            {payslipId && (
                                <>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            router.post(
                                                `/payroll/sewed-items/payslip/${payslipId}/approve`,
                                                {},
                                                {
                                                    onSuccess: () => {
                                                        toast.success(
                                                            'Payslip approved. Items marked as completed.',
                                                        );
                                                        setOpen(false);
                                                    },
                                                    onError: (err: any) =>
                                                        toast.error(
                                                            err.error ??
                                                                'Approval failed.',
                                                        ),
                                                },
                                            );
                                        }}
                                    >
                                        <Check className="mr-1.5 h-4 w-4 text-green-600" />
                                        Approve
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            router.post(
                                                `/payroll/sewed-items/payslip/${payslipId}/cancel`,
                                                {},
                                                {
                                                    onSuccess: () => {
                                                        toast.success(
                                                            'Payslip cancelled.',
                                                        );
                                                        setOpen(false);
                                                    },
                                                    onError: (err: any) =>
                                                        toast.error(
                                                            err.error ??
                                                                'Cancel failed.',
                                                        ),
                                                },
                                            );
                                        }}
                                    >
                                        <X className="mr-1.5 h-4 w-4 text-red-600" />
                                        Cancel
                                    </Button>
                                </>
                            )}
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handlePrint}
                            >
                                <Printer className="mr-1.5 h-4 w-4" />
                                Print
                            </Button>
                        </div>
                    </div>

                    {/* Screen preview: one card per item */}
                    <div className="space-y-4">
                        {items.map((item) => (
                            <div
                                key={item.id}
                                className="break-inside-avoid rounded-md border bg-sidebar p-3"
                            >
                                <h3 className="mb-2 text-sm font-semibold">
                                    {item.sublimation?.description ?? '—'}
                                    <span className="ml-1.5 text-[11px] font-normal text-muted-foreground">
                                        {toManilaTime(
                                            item.created_at,
                                            'MMM DD, YYYY',
                                        )}
                                    </span>
                                </h3>

                                <div className="overflow-hidden rounded-md border">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/50">
                                                <th className="px-3 py-1.5 text-left font-medium">
                                                    Category
                                                </th>
                                                <th className="w-16 px-3 py-1.5 text-right font-medium">
                                                    Qty
                                                </th>
                                                <th className="w-28 px-3 py-1.5 text-right font-medium">
                                                    Price/piece
                                                </th>
                                                <th className="w-24 px-3 py-1.5 text-right font-medium">
                                                    Amount
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(item.tags ?? []).map((tag) => {
                                                const qty = tag.pivot.quantity;
                                                const price = tagPrice(tag);
                                                const amount = qty * price;

                                                return (
                                                    <tr
                                                        key={tag.id}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="px-3 py-1.5">
                                                            <span className="inline-flex items-center gap-1.5">
                                                                <span
                                                                    className="inline-block h-2.5 w-2.5 rounded-full"
                                                                    style={{
                                                                        backgroundColor:
                                                                            tag.color,
                                                                    }}
                                                                />
                                                                {tag.name}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-1.5 text-right tabular-nums">
                                                            {qty}
                                                        </td>
                                                        <td className="px-3 py-1.5 text-right tabular-nums">
                                                            {peso(price)}
                                                        </td>
                                                        <td className="px-3 py-1.5 text-right tabular-nums">
                                                            {peso(amount)}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                        <tfoot>
                                            <tr className="border-t bg-muted/30 text-sm font-medium">
                                                <td className="px-3 py-1.5" />
                                                <td className="px-3 py-1.5 text-right tabular-nums">
                                                    {item.quantity}
                                                </td>
                                                <td className="px-3 py-1.5" />
                                                <td className="px-3 py-1.5 text-right tabular-nums">
                                                    {peso(Number(item.amount))}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="flex items-center justify-between rounded-md border bg-sidebar px-4 py-3">
                        <div className="text-sm">
                            <p>
                                Generated by:{' '}
                                <span className="font-semibold">
                                    {generatedBy}
                                </span>
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {generatedAt}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-xs text-muted-foreground">
                                Total Amount
                            </p>
                            <p className="text-lg font-bold tabular-nums">
                                {peso(grandTotal)}
                            </p>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
