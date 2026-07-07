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

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="payslip-dialog max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <style>{`
                    @media print {
                        @page { size: A4 portrait; margin: 8mm; }

                        /* Remove the page behind the dialog entirely —
                           visibility:hidden would keep its layout height and
                           spill trailing blank pages after the payslip. */
                        body > #app { display: none !important; }
                        [data-slot="dialog-overlay"] { display: none !important; }

                        /* Safety net for other portals (toasts, tooltips). */
                        body * { visibility: hidden; }
                        .payslip-print-area,
                        .payslip-print-area * { visibility: visible; }

                        /* The dialog is fixed + transformed, and fixed elements
                           don't paginate in print — content past page 1 gets
                           clipped. Reposition it as a plain block at the page
                           origin so long payslips flow across pages. */
                        .payslip-dialog {
                            position: absolute !important;
                            top: 0 !important;
                            left: 0 !important;
                            transform: none !important;
                            width: 100% !important;
                            max-width: none !important;
                            max-height: none !important;
                            overflow: visible !important;
                            border: 0 !important;
                            box-shadow: none !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            background: #fff !important;
                        }

                        /* Printable container — fixed to page width, no overflow */
                        .payslip-print-area {
                            position: static !important;
                            width: 100% !important;
                            max-width: 100% !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            color: #111 !important;
                            font-size: 9pt !important;
                            line-height: 1.3 !important;
                            box-sizing: border-box !important;
                            overflow-x: hidden !important;
                        }
                        .payslip-print-area .no-print { display: none !important; }

                        /* Hide the card layout when printing */
                        .payslip-print-area .screen-only { display: none !important; }

                        /* Global text wrapping and overflow prevention */
                        .payslip-print-area * {
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                            color: #111 !important;
                            border-color: #d4d4d8 !important;
                            word-break: break-word !important;
                            overflow-wrap: break-word !important;
                            white-space: normal !important;
                            max-width: 100% !important;
                        }
                        .payslip-print-area .text-muted-foreground { color: #52525b !important; }
                        .payslip-print-area .bg-sidebar { background: #fff !important; }

                        /* Single consolidated print table */
                        .payslip-print-area .payslip-print-table {
                            display: table !important;
                            width: 100% !important;
                            max-width: 100% !important;
                            table-layout: fixed !important;
                            font-size: 8.5pt !important;
                            border-collapse: collapse !important;
                        }

                        .payslip-print-area .payslip-print-table th,
                        .payslip-print-area .payslip-print-table td {
                            padding: 2px 4px !important;
                            word-break: break-word !important;
                            overflow-wrap: break-word !important;
                            white-space: normal !important;
                            vertical-align: top !important;
                        }

                        /* Proportional column widths (total 100%) */
                        .payslip-print-area .payslip-print-table th:nth-child(1),
                        .payslip-print-area .payslip-print-table td:nth-child(1) { width: 12% !important; min-width: 0 !important; }
                        .payslip-print-area .payslip-print-table th:nth-child(2),
                        .payslip-print-area .payslip-print-table td:nth-child(2) { width: 25% !important; min-width: 0 !important; }
                        .payslip-print-area .payslip-print-table th:nth-child(3),
                        .payslip-print-area .payslip-print-table td:nth-child(3) { width: 23% !important; min-width: 0 !important; }
                        .payslip-print-area .payslip-print-table th:nth-child(4),
                        .payslip-print-area .payslip-print-table td:nth-child(4) { width: 10% !important; min-width: 0 !important; }
                        .payslip-print-area .payslip-print-table th:nth-child(5),
                        .payslip-print-area .payslip-print-table td:nth-child(5) { width: 15% !important; min-width: 0 !important; }
                        .payslip-print-area .payslip-print-table th:nth-child(6),
                        .payslip-print-area .payslip-print-table td:nth-child(6) { width: 15% !important; min-width: 0 !important; }

                        /* Inline-flex tag rows should wrap like normal text */
                        .payslip-print-area .payslip-print-table td .inline-flex {
                            display: inline !important;
                        }

                        /* Subtotal and total rows */
                        .payslip-print-area .payslip-print-table .item-subtotal td,
                        .payslip-print-area .payslip-print-table .grand-total td {
                            border-top: 1px solid #d4d4d8 !important;
                        }

                        /* Keep header / footer band backgrounds */
                        .payslip-print-area thead tr,
                        .payslip-print-area tfoot tr { background: #f4f4f5 !important; }

                        /* overflow-hidden wrappers can clip rows at page-break edges */
                        .payslip-print-area .overflow-hidden { overflow: visible !important; }

                        /* Grand-total / generated-by block */
                        .payslip-print-area .flex.break-inside-avoid {
                            width: 100% !important;
                            max-width: 100% !important;
                            box-sizing: border-box !important;
                            padding: 6px 8px !important;
                        }

                        /* Reduce heading sizes */
                        .payslip-print-area h2 { font-size: 12pt !important; }
                    }
                `}</style>

                <div className="payslip-print-area space-y-4">
                    <div className="no-print flex items-center justify-between">
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
                                onClick={() => window.print()}
                            >
                                <Printer className="mr-1.5 h-4 w-4" />
                                Print
                            </Button>
                        </div>
                    </div>

                    <div className="hidden text-center print:block">
                        <h2 className="text-lg font-bold">
                            SEWED ITEMS PAYSLIP
                        </h2>
                    </div>

                    {/* Screen layout: cards with per-item tables */}
                    <div className="screen-only space-y-4">
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
                                                const price = Number(
                                                    tag.pivot.price_per_piece ??
                                                        tag.price_per_piece ??
                                                        0,
                                                );
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
                                                            ₱{price.toFixed(2)}
                                                        </td>
                                                        <td className="px-3 py-1.5 text-right tabular-nums">
                                                            ₱{amount.toFixed(2)}
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
                                                    ₱
                                                    {Number(
                                                        item.amount,
                                                    ).toFixed(2)}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Print layout: single consolidated table */}
                    <table className="payslip-print-table hidden w-full border-collapse text-sm print:table">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="px-2 py-1 text-left font-medium">
                                    Date
                                </th>
                                <th className="px-2 py-1 text-left font-medium">
                                    Description
                                </th>
                                <th className="px-2 py-1 text-left font-medium">
                                    Category
                                </th>
                                <th className="px-2 py-1 text-right font-medium">
                                    Qty
                                </th>
                                <th className="px-2 py-1 text-right font-medium">
                                    Price/piece
                                </th>
                                <th className="px-2 py-1 text-right font-medium">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <>
                                    {(item.tags ?? []).map((tag, index) => {
                                        const qty = tag.pivot.quantity;
                                        const price = Number(
                                            tag.pivot.price_per_piece ??
                                                tag.price_per_piece ??
                                                0,
                                        );
                                        const amount = qty * price;

                                        return (
                                            <tr
                                                key={`${item.id}-${tag.id}`}
                                                className="border-b"
                                            >
                                                <td className="px-2 py-1">
                                                    {index === 0
                                                        ? toManilaTime(
                                                              item.created_at,
                                                              'MMM DD, YYYY',
                                                          )
                                                        : ''}
                                                </td>
                                                <td className="px-2 py-1">
                                                    {index === 0
                                                        ? (item.sublimation
                                                              ?.description ??
                                                          '—')
                                                        : ''}
                                                </td>
                                                <td className="px-2 py-1">
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
                                                <td className="px-2 py-1 text-right tabular-nums">
                                                    {qty}
                                                </td>
                                                <td className="px-2 py-1 text-right tabular-nums">
                                                    ₱{price.toFixed(2)}
                                                </td>
                                                <td className="px-2 py-1 text-right tabular-nums">
                                                    ₱{amount.toFixed(2)}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    <tr className="item-subtotal border-b bg-muted/30 font-medium">
                                        <td className="px-2 py-1" colSpan={3} />
                                        <td className="px-2 py-1 text-right tabular-nums">
                                            {item.quantity}
                                        </td>
                                        <td className="px-2 py-1" />
                                        <td className="px-2 py-1 text-right tabular-nums">
                                            ₱{Number(item.amount).toFixed(2)}
                                        </td>
                                    </tr>
                                </>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="grand-total font-bold">
                                <td className="px-2 py-1" colSpan={5}>
                                    Grand Total
                                </td>
                                <td className="px-2 py-1 text-right tabular-nums">
                                    ₱{grandTotal.toFixed(2)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <div className="flex break-inside-avoid items-center justify-between rounded-md border bg-sidebar px-4 py-3">
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
                                ₱{grandTotal.toFixed(2)}
                            </p>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
