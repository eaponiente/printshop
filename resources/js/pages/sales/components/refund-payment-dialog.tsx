import { useForm } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import type { TypeOfPayment } from '@/types/settings';
import type { Transaction } from '@/types/transaction';
import { formatCurrency } from '@/utils/formatters';

interface RefundPaymentDialogProps {
    transaction: Transaction | null;
    open: boolean;
    setOpen: (open: boolean) => void;
    typesOfPayment: TypeOfPayment[];
}

export default function RefundPaymentDialog({
    open,
    setOpen,
    transaction,
    typesOfPayment,
}: RefundPaymentDialogProps) {
    const { data, setData, patch, processing, errors, reset } = useForm({
        payment_type: '',
    });

    if (!transaction) {
        return null;
    }

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        patch(route('sales.refund-payment', transaction.id), {
            onSuccess: () => {
                toast.success('Full refund successfully recorded');
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                setOpen(nextOpen);

                if (!nextOpen) {
                    reset();
                }
            }}
        >
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <RotateCcw className="h-5 w-5 text-primary" />
                        Process Refund
                    </DialogTitle>
                    <DialogDescription>
                        Refund from invoice{' '}
                        <span className="font-bold text-foreground">
                            #{transaction.invoice_number}
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <div className="my-2 space-y-4 rounded-xl border border-border/50 bg-secondary/40 p-4">
                    <div>
                        <Label className="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                            Full Refund Amount
                        </Label>
                        <div className="text-3xl font-black text-primary">
                            {formatCurrency(transaction.amount_paid)}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4 border-t border-border/30 pt-2">
                        <div>
                            <Label className="text-[10px] tracking-widest text-muted-foreground uppercase">
                                Status
                            </Label>
                            <div className="text-sm font-semibold capitalize text-foreground/80">
                                {transaction.status}
                            </div>
                        </div>
                        <div>
                            <Label className="text-[10px] tracking-widest text-muted-foreground uppercase">
                                Current Balance
                            </Label>
                            <div className="text-sm font-semibold text-foreground/80">
                                {formatCurrency(transaction.balance)}
                            </div>
                        </div>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-3">
                        <Label htmlFor="payment_type">Refund Method</Label>
                        <NativeSelect
                            value={data.payment_type}
                            onChange={(e) => setData('payment_type', e.target.value)}
                        >
                            <NativeSelectOption value="">
                                Select type
                            </NativeSelectOption>
                            {typesOfPayment.map((payment) => (
                                <NativeSelectOption
                                    key={payment.key}
                                    value={payment.key}
                                >
                                    {payment.value}
                                </NativeSelectOption>
                            ))}
                        </NativeSelect>
                        <InputError message={errors.payment_type} />
                    </div>

                    <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        This action refunds the full collected amount and resets the transaction back to pending.
                    </div>

                    <DialogFooter className="pt-2">
                        <Button
                            type="submit"
                            className="h-11 w-full bg-amber-600 font-bold text-white hover:bg-amber-700"
                            disabled={processing}
                        >
                            {processing ? (
                                <Spinner className="mr-2 h-4 w-4" />
                            ) : (
                                <RotateCcw className="mr-2 h-4 w-4" />
                            )}
                            Confirm Full Refund
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
