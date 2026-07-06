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
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl print:max-h-none print:overflow-visible">
                <style>{`
                    @media print {
                        body * { visibility: hidden; }
                        .payslip-print-area,
                        .payslip-print-area * { visibility: visible; }
                        .payslip-print-area {
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            padding: 16px;
                        }
                        .payslip-print-area .no-print { display: none !important; }
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

                    {items.map((item) => (
                        <div
                            key={item.id}
                            className="rounded-md border bg-sidebar p-3"
                        >
                            <h3 className="mb-2 text-sm font-semibold">
                                {item.sublimation?.description ?? '—'}
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
                                                            <span className="text-[11px] text-muted-foreground">
                                                                {toManilaTime(
                                                                    item.created_at,
                                                                    'MMM DD, YYYY',
                                                                )}
                                                            </span>
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
                                                {Number(item.amount).toFixed(2)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    ))}

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
                                ₱{grandTotal.toFixed(2)}
                            </p>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
