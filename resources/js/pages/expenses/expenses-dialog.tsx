import { useForm, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { TypeOfPayment } from '@/types/settings'; // Assuming you have this Shadcn component

interface ExpenseDialogProps {
    branches: { id: number; name: string }[];
    paymentMethods: TypeOfPayment[]; // Pass these from your config
    expense?: any; // Replace with your Expense type
    open: boolean;
    setOpen: (open: boolean) => void;
}

export default function ExpenseDialog({
                                          open,
                                          setOpen,
                                          branches,
                                          paymentMethods,
                                          expense,
                                      }: ExpenseDialogProps) {
    const isEdit = !!expense;
    const { auth } = usePage().props as any;

    console.log('aa', paymentMethods);
    const { data, setData, post, put, processing, errors, reset } = useForm({
        description: expense?.description ?? '',
        vendor_name: expense?.vendor_name ?? '',
        amount: expense?.amount ?? '',
        payment_type: expense?.payment_type ?? '',
        user_id: expense?.user_id ?? auth.user.id,
        branch_id: expense?.branch_id ?? auth.user.branch_id,
        status: expense?.status ?? 'pending',
        expense_date: expense?.expense_date
            ? new Date(expense.expense_date).toISOString().split('T')[0]
            : new Date().toISOString().split('T')[0],
        receipt: null as File | null, // Used for the file upload
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            onSuccess: () => {
                toast.success(isEdit ? 'Expense updated' : 'Expense recorded');
                setOpen(false);

                if (!isEdit) {
                    reset();
                }
            },
            onError: () => toast.error('Please check the form for errors.'),
        };

        if (isEdit) {
            // Note: In Laravel, multipart/form-data with PUT requires _method: 'PUT'
            // or using post() with a spoofed method.
            put(route('expenses.update', expense.id), {
                ...options,
                forceFormData: true,
                data: { ...data, _method: 'PUT' }
            } as any);
        } else {
            post(route('expenses.store'), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-[700px]">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit' : 'Add New'} Expense
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-5">
                    {/* Description - Full Width */}
                    <div className="grid gap-2">
                        <Label htmlFor="description">
                            Particulars / Description
                        </Label>
                        <Textarea
                            id="description"
                            className="min-h-[120px] resize-none"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            placeholder="Describe the expense details..."
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid grid-cols-1 gap-4">
                        {/* Vendor & Amount */}
                        <div className="grid gap-2">
                            <Label htmlFor="vendor_name">Vendor Name</Label>
                            <Input
                                id="vendor_name"
                                value={data.vendor_name}
                                onChange={(e) =>
                                    setData('vendor_name', e.target.value)
                                }
                            />
                            <InputError message={errors.vendor_name} />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        {/* Branch & User */}
                        <div className="grid gap-2">
                            <Label htmlFor="branch_id">Branch</Label>
                            <NativeSelect
                                value={data.branch_id}
                                onChange={(e) =>
                                    setData('branch_id', e.target.value)
                                }
                            >
                                {branches.map((b) => (
                                    <NativeSelectOption key={b.id} value={b.id}>
                                        {b.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="amount">Amount</Label>
                            <Input
                                id="amount"
                                type="number"
                                step="0.01"
                                value={data.amount}
                                onChange={(e) =>
                                    setData('amount', e.target.value)
                                }
                            />
                            <InputError message={errors.amount} />
                        </div>

                        {/* Payment Method & Date */}
                        <div className="grid gap-2">
                            <Label htmlFor="payment_type">Payment Type</Label>
                            <NativeSelect
                                id="payment_type"
                                value={data.payment_type}
                                onChange={(e) =>
                                    setData('payment_type', e.target.value)
                                }
                            >
                                <NativeSelectOption value="">
                                    Select Method
                                </NativeSelectOption>
                                {paymentMethods.map((method) => (
                                    <NativeSelectOption
                                        key={method.key}
                                        value={method.key}
                                    >
                                        {method.value}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <InputError message={errors.payment_type} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="expense_date">Date Purchased</Label>
                            <Input
                                id="expense_date"
                                type="date"
                                value={data.expense_date}
                                onChange={(e) =>
                                    setData('expense_date', e.target.value)
                                }
                            />
                            <InputError message={errors.expense_date} />
                        </div>
                    </div>

                    {/* Status & Receipt Upload */}
                    <div className="grid grid-cols-2 gap-4">

                        <div className="grid gap-2">
                            <Label htmlFor="receipt">Receipt (Image/PDF)</Label>
                            <Input
                                id="receipt"
                                type="file"
                                onChange={(e) =>
                                    setData(
                                        'receipt',
                                        e.target.files
                                            ? e.target.files[0]
                                            : null,
                                    )
                                }
                            />
                            <InputError message={errors.receipt} />
                        </div>
                    </div>

                    <Button
                        type="submit"
                        className="mt-2"
                        disabled={processing}
                    >
                        {processing && <Spinner className="mr-2" />}
                        {isEdit ? 'Update Expense' : 'Save Expense'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
