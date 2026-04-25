import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { route } from 'ziggy-js';
import type { PurchaseOrder } from '@/types/purchase-order';
import { toast } from 'sonner';

export default function CreatePoTransactionDialog({ open, setOpen, purchaseOrder }: { open: boolean, setOpen: (open: boolean) => void, purchaseOrder: PurchaseOrder }) {

    const { data, setData, post, processing, errors, reset } = useForm({
        amount_total: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('purchase-orders.transactions.store', purchaseOrder.id), {
            onSuccess: () => {
                setOpen(false);
                toast.success('Transaction created successfully');
                reset();
            },
        });
    };

    return (
        <>
            {/* Transaction Modal */}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Transaction for {purchaseOrder?.po_number}</DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-6">
                        {/* Amount Field Group */}
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="amount_total" className="font-medium">
                                Net Amount (Current Total Amount: {purchaseOrder.grand_total})
                            </Label>
                            <div className="relative">
                                <Input
                                    id="amount_total"
                                    type="text"
                                    placeholder="0.00"
                                    value={data.amount_total || ''}
                                    onChange={(e) =>
                                        setData('amount_total', e.target.value)
                                    }
                                    className={`focus-visible:ring-indigo-500 ${errors.amount_total ? 'border-red-500' : ''
                                        }`}
                                />
                            </div>
                            {errors.amount_total && (
                                <span className="text-sm font-medium text-red-500">
                                    {errors.amount_total}
                                </span>
                            )}
                        </div>

                        {/* Action Buttons */}
                        <div className="flex items-center justify-end gap-3 pt-2">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Confirm Transaction
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}