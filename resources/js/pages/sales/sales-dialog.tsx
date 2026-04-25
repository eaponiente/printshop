import { useForm } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import SearchCustomersField from '@/components/shared/search-customers-field';
import { submitFormOptions } from '@/components/shared/submit-form-options';
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
import { Textarea } from "@/components/ui/textarea"
import type { Branch } from '@/types/branches';
import type { Transaction } from '@/types/transaction';

interface SaleDialogProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    branches: Branch[];
    transaction?: Transaction | null;
}

export default function SaleDialog({ open, setOpen, transaction, branches }: SaleDialogProps) {

    const isEdit = !!transaction;
    const { auth } = usePage().props;

    // 1. Initialize useForm with conditional data
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        customer_id: transaction?.customer_id || '',
        branch_id: transaction?.branch_id ?? auth.user?.branch_id ?? '',
        particular: transaction?.particular || '',
        description: transaction?.description || '',
        status: transaction?.status || 'pending',
        amount_total: transaction?.amount_total || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = submitFormOptions({
            isEdit,
            resourceName: 'Sale',
            onSuccess: () => setOpen(false),
            reset,
        });

        if (isEdit) {
            patch(route('sales.update', transaction.id), options);
        } else {
            post(route('sales.store'), options);
        }
    };

    return (
        <>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-[825px]">
                    <DialogHeader>
                        <DialogTitle>
                            {isEdit ? 'Update' : 'Create'} Transaction
                        </DialogTitle>
                    </DialogHeader>

                    <form
                        onSubmit={handleSubmit}
                        className="flex flex-col gap-6"
                    >
                        <div className="grid grid-cols-2 gap-4">
                            <SearchCustomersField
                                field={transaction}
                                selectCustomer={(id: any) =>
                                    setData('customer_id', id)
                                }
                                errors={errors}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-3">
                                <Label htmlFor="branch_id">Branch</Label>
                                <NativeSelect
                                    value={data.branch_id}
                                    onChange={(e) =>
                                        setData('branch_id', +e.target.value)
                                    }
                                >
                                    <NativeSelectOption value="">
                                        Select branch
                                    </NativeSelectOption>
                                    {branches.map((branch) => (
                                        <NativeSelectOption
                                            key={branch.id}
                                            value={branch.id}
                                        >
                                            {branch.name}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.branch_id} />
                            </div>

                            <div className="grid gap-3">
                                <Label htmlFor="particular">Particular</Label>
                                <Input
                                    type="string"
                                    value={data.particular}
                                    onChange={(e) =>
                                        setData('particular', e.target.value)
                                    }
                                />
                                <InputError message={errors.particular} />
                            </div>
                        </div>

                        <div className="grid gap-3">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid grid-cols-1 gap-4">
                            <div className="grid gap-3">
                                <Label htmlFor="amount_total">
                                    Total Amount
                                </Label>
                                <Input
                                    disabled={isEdit && data.status !== 'pending'}
                                    type="number"
                                    value={data.amount_total}
                                    onChange={(e) =>
                                        setData('amount_total', e.target.value)
                                    }
                                />
                                <InputError message={errors.amount_total} />
                            </div>
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            {isEdit ? 'Update Sale' : 'Save Sale'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
