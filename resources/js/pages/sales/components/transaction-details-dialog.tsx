import { Banknote, FileText, Calendar, User, MapPin } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { toManilaTime } from '@/utils/dateHelper';
import { formatCurrency } from '@/utils/formatters';

interface TransactionDetailsDialogProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    transaction: any | null;
}

export default function TransactionDetailsDialog({
    open,
    setOpen,
    transaction,
}: TransactionDetailsDialogProps) {
    if (!transaction) return null;

    const statusConfig = {
        paid: 'bg-green-100 border-green-200 text-green-700',
        pending: 'bg-yellow-100 border-yellow-200 text-yellow-700',
        partial: 'bg-blue-100 border-blue-200 text-blue-700',
    };

    const badgeStyle =
        statusConfig[
            transaction.status.toLowerCase() as keyof typeof statusConfig
        ] || 'bg-gray-100 text-gray-700';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-[550px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl">
                        <FileText className="h-5 w-5 text-primary" />
                        Transaction Details
                    </DialogTitle>
                    <DialogDescription>
                        Invoice{' '}
                        <span className="font-bold text-foreground">
                            #{transaction.invoice_number}
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 pt-2">
                    {/* Header Info */}
                    <div className="flex items-center justify-between border-b border-border/50 pb-4">
                        <div className="space-y-1">
                            <Label className="text-xs tracking-widest text-muted-foreground uppercase">
                                Status
                            </Label>
                            <div>
                                <Badge
                                    className={`border shadow-none capitalize ${badgeStyle}`}
                                >
                                    {transaction.status}
                                </Badge>
                            </div>
                        </div>
                        <div className="space-y-1 text-right">
                            <Label className="text-xs tracking-widest text-muted-foreground uppercase">
                                Transaction Date
                            </Label>
                            <div className="flex items-center justify-end gap-1.5 text-sm font-medium">
                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                {toManilaTime(
                                    transaction.transaction_date,
                                    'MMM DD, YYYY hh:mm A',
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Meta Data */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <Label className="text-xs tracking-widest text-muted-foreground uppercase">
                                Customer
                            </Label>
                            <div className="flex flex-col gap-0.5">
                                <div className="flex items-center gap-1.5 text-sm font-medium">
                                    <User className="h-4 w-4 text-muted-foreground" />
                                    {transaction.customer?.full_name ||
                                        'Unknown'}
                                </div>
                                {transaction.customer?.company && (
                                    <span className="text-xs text-muted-foreground pl-5.5">
                                        {transaction.customer.company}
                                    </span>
                                )}
                            </div>
                        </div>
                        <div className="space-y-1 text-right">
                            <Label className="text-xs tracking-widest text-muted-foreground uppercase">
                                Branch
                            </Label>
                            <div className="flex items-center justify-end gap-1.5 text-sm font-medium">
                                <MapPin className="h-4 w-4 text-muted-foreground" />
                                {transaction.branch?.name || '-'}
                            </div>
                        </div>
                    </div>

                    {/* Particular & Description */}
                    <div className="space-y-1 rounded-md border border-border/50 bg-secondary/30 p-3">
                        <Label className="text-xs tracking-widest text-muted-foreground uppercase">
                            Particular
                        </Label>
                        <p className="text-sm font-medium">
                            {transaction.particular}
                        </p>
                        {transaction.description && (
                            <p className="mt-1.5 border-t border-border/50 pt-1 text-sm text-muted-foreground">
                                {transaction.description}
                            </p>
                        )}
                    </div>

                    {/* Financial Summary */}
                    <div className="grid grid-cols-3 gap-2 border-y border-border/50 py-3">
                        <div className="space-y-1">
                            <Label className="text-xs tracking-widest text-muted-foreground uppercase">
                                Total Amount
                            </Label>
                            <div className="text-sm font-bold">
                                {formatCurrency(transaction.amount_total)}
                            </div>
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs tracking-widest text-muted-foreground uppercase">
                                Amount Paid
                            </Label>
                            <div className="text-sm font-bold text-green-600 dark:text-green-500">
                                {formatCurrency(transaction.amount_paid)}
                            </div>
                        </div>
                        <div className="space-y-1 text-right">
                            <Label className="text-xs tracking-widest text-red-500 uppercase">
                                Balance
                            </Label>
                            <div className="text-lg font-black text-primary">
                                {formatCurrency(transaction.balance)}
                            </div>
                        </div>
                    </div>

                    {/* Payments History */}
                    <div className="space-y-2 pt-2">
                        <div className="flex items-center gap-2">
                            <Banknote className="h-4 w-4 text-muted-foreground" />
                            <h4 className="text-[11px] font-bold tracking-widest text-muted-foreground uppercase">
                                Payment History
                            </h4>
                        </div>

                        {transaction.payments &&
                        transaction.payments.length > 0 ? (
                            <div className="max-h-[140px] space-y-2 overflow-y-auto rounded-md border border-border/50 bg-secondary/10 p-2 pr-1">
                                {transaction.payments.map((payment: any) => (
                                    <div
                                        key={payment.id}
                                        className="flex items-center justify-between rounded-md border bg-background p-2 shadow-sm"
                                    >
                                        <div className="flex flex-col">
                                            <span className="text-xs font-semibold capitalize">
                                                {payment.payment_type ||
                                                    'Unknown Method'}
                                            </span>
                                            <span className="text-[10px] text-muted-foreground">
                                                {toManilaTime(
                                                    payment.created_at,
                                                    'MMM DD, YYYY hh:mm A',
                                                )}
                                            </span>
                                        </div>
                                        <div className="font-mono text-sm font-bold text-green-600 dark:text-green-500">
                                            {formatCurrency(payment.amount)}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-md border border-border/50 bg-secondary/10 p-4 text-center text-sm italic text-muted-foreground">
                                No payments recorded yet.
                            </div>
                        )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
