"use client"

import { useForm } from '@inertiajs/react';
import { Banknote, CreditCard, Receipt } from "lucide-react";
import { toast } from "sonner";

import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter
} from '@/components/ui/dialog';
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import type { TypeOfPayment } from '@/types/settings';
import { formatCurrency } from '@/utils/formatters';

interface PaymentDialogProps {
    transaction: any;
    open: boolean;
    setOpen: (open: boolean) => void;
    typesOfPayment: TypeOfPayment[];
}

export default function PaymentDialog({ open, setOpen, transaction, typesOfPayment }: PaymentDialogProps) {

    // Standard Inertia useForm hook
    const { data, setData, patch, processing, errors, reset } = useForm({
        amount_paid: 0,
        status: transaction.status ?? 'pending',
        payment_type: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        // Replace 'sales.update' with your actual route name
        patch(route('sales.update-payment', transaction.id), {
            onSuccess: () => {
                toast.success('Payment successfully recorded');
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Receipt className="h-5 w-5 text-primary" />
                        Process Payment
                    </DialogTitle>
                    <DialogDescription>
                        Updating invoice{' '}
                        <span className="font-bold text-foreground">
                            #{transaction.invoice_number}
                        </span>
                    </DialogDescription>
                </DialogHeader>

                {/* Visual Summary for the Cashier */}
                <div className="my-2 space-y-4 rounded-xl border border-border/50 bg-secondary/40 p-4">
                    {/* The Hero: Remaining Balance */}
                    <div>
                        <Label className="text-[10px] font-bold tracking-widest text-red-500 uppercase dark:text-red-400">
                            Remaining Balance
                        </Label>
                        <div className="text-3xl font-black text-primary">
                            {formatCurrency(transaction.balance)}
                        </div>
                    </div>

                    {/* Secondary Info: Breakdown */}
                    <div className="grid grid-cols-2 gap-4 border-t border-border/30 pt-2">
                        <div>
                            <Label className="text-[10px] tracking-widest text-muted-foreground uppercase">
                                Total Amount
                            </Label>
                            <div className="text-sm font-semibold text-foreground/80">
                                {formatCurrency(transaction.amount_total)}
                            </div>
                        </div>
                        <div>
                            <Label className="text-[10px] tracking-widest text-muted-foreground uppercase">
                                Amount Paid
                            </Label>
                            <div className="text-sm font-semibold text-green-600 dark:text-green-400">
                                {formatCurrency(transaction.amount_paid)}
                            </div>
                        </div>
                    </div>

                    {/* Show payment history if any exists */}
                    {transaction.payments && transaction.payments.length > 0 && (
                        <div className="border-t border-border/30 pt-3">
                            <Label className="text-[10px] tracking-widest text-muted-foreground uppercase">
                                Payment History
                            </Label>
                            <div className="mt-1.5 space-y-1.5">
                                {transaction.payments.map((payment: any) => (
                                    <div key={payment.id} className="flex justify-between text-xs items-center bg-background/50 p-1.5 rounded-md border border-border/40">
                                        <div className="flex gap-2">
                                            <span className="font-medium text-foreground capitalize">{payment.payment_type || 'Unknown'}</span>
                                        </div>
                                        <div className="font-mono text-green-600 dark:text-green-400 font-medium">
                                            {formatCurrency(payment.amount)}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                <form onSubmit={submit} className="space-y-5">
                    <div className="grid grid-cols-1 gap-4">
                        {/* Status Field */}
                        <div className="grid gap-3">
                            <Label htmlFor="payment_type">
                                Type of Payment
                            </Label>
                            <NativeSelect
                                value={data.payment_type}
                                onChange={(e) =>
                                    setData('payment_type', e.target.value)
                                }
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
                    </div>

                    {/* Amount Field */}
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="amount_paid">Amount Received</Label>
                            <button
                                type="button"
                                className="text-xs font-medium text-indigo-600 underline-offset-4 hover:text-indigo-500 hover:underline"
                                onClick={() =>
                                    setData(
                                        'amount_paid',
                                        transaction.balance,
                                    )
                                }
                            >
                                Pay Full Balance
                            </button>
                        </div>
                        <div className="relative">
                            <Banknote className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                            <Input
                                id="amount_paid"
                                type="number"
                                step="0.01"
                                className="pl-9 focus-visible:ring-indigo-500"
                                value={data.amount_paid}
                                onChange={(e) =>
                                    setData('amount_paid', +e.target.value)
                                }
                            />
                        </div>
                        {errors.amount_paid && (
                            <p className="text-sm font-medium text-destructive">
                                {errors.amount_paid}
                            </p>
                        )}
                    </div>

                    <DialogFooter className="pt-2">
                        <Button
                            type="submit"
                            className="h-11 w-full bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                            disabled={processing}
                        >
                            {processing ? (
                                <Spinner className="mr-2 h-4 w-4" />
                            ) : (
                                <CreditCard className="mr-2 h-4 w-4" />
                            )}
                            Confirm Payment
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
