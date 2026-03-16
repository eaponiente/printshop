import { Form } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { toast } from "sonner"
import InputError from '@/components/input-error';
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/sales';
import type { Branch } from '@/types/user';
import { Textarea } from "@/components/ui/textarea"
import { useEffect } from 'react';

interface SaleDialogProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    branches: Branch[];
}

export default function SaleDialog({ open, setOpen, branches }: SaleDialogProps) {

    const { auth } = usePage().props;

    const form = store.form();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-[825px]">
                <DialogHeader>
                    <DialogTitle>Create Transaction</DialogTitle>
                </DialogHeader>

                <Form
                    {...form}
                    className="flex flex-col gap-6"
                    setDefaultsOnSuccess={true}
                    onSuccess={() => {
                        toast.success('Sale saved successfully', { position: 'top-center' });
                        setOpen(false);
                    }}
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Guest Information */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-3">
                                    <Label htmlFor="guest_name">Guest name</Label>
                                    <Input id="guest_name" name="guest_name" tabIndex={1} />
                                    <div className="min-h-[20px]">
                                        <InputError message={errors.guest_name} />
                                    </div>
                                </div>

                                <div className="grid gap-3">
                                    <Label htmlFor="last_name">Last name</Label>
                                    <Input id="last_name" name="last_name" tabIndex={2} />
                                    <div className="min-h-[20px]">
                                        <InputError message={errors.last_name} />
                                    </div>
                                </div>
                            </div>

                            {/* Branch and Particular */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-3">
                                    <Label htmlFor="branch_id">Branch</Label>
                                    <NativeSelect name="branch_id" defaultValue={auth.user.branch_id}>
                                        <NativeSelectOption value="">Select branch</NativeSelectOption>
                                        {branches.map((branch) => (
                                            <NativeSelectOption key={branch.id} value={branch.id}>
                                                {branch.name}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <div className="min-h-[20px]">
                                        <InputError message={errors.branch_id} />
                                    </div>
                                </div>

                                <div className="grid gap-3">
                                    <Label htmlFor="particular">Particular</Label>
                                    <NativeSelect name="particular">
                                        <NativeSelectOption value="">Select particular</NativeSelectOption>
                                        <NativeSelectOption value="service">Service</NativeSelectOption>
                                        <NativeSelectOption value="product">Product</NativeSelectOption>
                                    </NativeSelect>
                                    <div className="min-h-[20px]">
                                        <InputError message={errors.particular} />
                                    </div>
                                </div>
                            </div>

                            {/* Description - Full Width */}
                            <div className="grid gap-3">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    placeholder="Enter details..."
                                />
                                <div className="min-h-[20px]">
                                    <InputError message={errors.description} />
                                </div>
                            </div>

                            {/* Payment Details */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-3">
                                    <Label htmlFor="payment_type">Type of Payment</Label>
                                    <NativeSelect name="payment_type">
                                        <NativeSelectOption value="">Select type</NativeSelectOption>
                                        <NativeSelectOption value="cash">Cash</NativeSelectOption>
                                        <NativeSelectOption value="card">Card</NativeSelectOption>
                                        <NativeSelectOption value="transfer">Bank Transfer</NativeSelectOption>
                                    </NativeSelect>
                                    <div className="min-h-[20px]">
                                        <InputError message={errors.payment_type} />
                                    </div>
                                </div>

                                <div className="grid gap-3">
                                    <Label htmlFor="status">Status</Label>
                                    <NativeSelect name="status">
                                        <NativeSelectOption value="">Select status</NativeSelectOption>
                                        <NativeSelectOption value="pending">Pending</NativeSelectOption>
                                        <NativeSelectOption value="paid">Paid</NativeSelectOption>
                                        <NativeSelectOption value="partial">Partial</NativeSelectOption>
                                    </NativeSelect>
                                    <div className="min-h-[20px]">
                                        <InputError message={errors.status} />
                                    </div>
                                </div>
                            </div>

                            {/* Financials */}
                            <div className="grid grid-cols-3 gap-4">
                                <div className="grid gap-3">
                                    <Label htmlFor="amount_to_pay">Amount to Pay</Label>
                                    <Input id="amount_to_pay" name="amount_to_pay" type="number" />
                                    <div className="min-h-[20px]">
                                        <InputError message={errors.amount_to_pay} />
                                    </div>
                                </div>

                                <div className="grid gap-3">
                                    <Label htmlFor="amount_paid">Amount Paid</Label>
                                    <Input id="amount_paid" name="amount_paid" type="number" />
                                    <div className="min-h-[20px]">
                                        <InputError message={errors.amount_paid} />
                                    </div>
                                </div>

                                <div className="grid gap-3">
                                    <Label htmlFor="balance">Balance</Label>
                                    <Input id="balance" name="balance" type="number" />
                                    <div className="min-h-[20px]">
                                        <InputError message={errors.balance} />
                                    </div>
                                </div>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Save Sale
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
