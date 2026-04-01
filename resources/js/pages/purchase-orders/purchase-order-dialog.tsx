import { useForm, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { Trash2, Plus } from 'lucide-react';
import { toast } from "sonner";
import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import type { Branch } from '@/types/branches';
import type {
    PurchaseOrder,
    PurchaseOrderDetail,
    PurchaseOrderStatus,
} from '@/types/purchase-order';
import { capitalizeFirstLetter } from '@/utils/formatters';

interface PODialogProps {
    order?: PurchaseOrder | null;
    open: boolean;
    setOpen: (open: boolean) => void;
    branches: Branch[];
    statuses: PurchaseOrderStatus[];
}

export default function PurchaseOrderDialog({ open, setOpen, order, branches, statuses }: PODialogProps) {
    const isEdit = !!order;

    const { auth } = usePage().props;

    // 1. Initialize useForm with v2.0 standards
    const { data, setData, post, put, processing, errors, reset } = useForm({
        po_number: order?.po_number ?? '',
        branch_id: order?.branch_id ?? auth.user.branch_id,
        status: order?.status ?? '',
        due_at: order?.due_at ?? '',
        received_at: order?.received_at ?? format(new Date(), 'yyyy-MM-dd'),
        details: order?.details ?? [
            { item_name: '', quantity: 1, unit_price: 0 },
        ],
    });

    // 2. Handle Submission using Promises (New in v2.0)
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            onSuccess: () => {
                toast.success(isEdit ? 'Order updated' : 'Order created');
                setOpen(false);

                if (!isEdit) {
                    reset();
                }
            },
            onError: () => {
                // Optional: specifically handle validation failures
                toast.error("Please check the form for errors.");
            },
            // This runs whether it failed or succeeded
            onFinish: () => {
                // Useful for toggling manual loading states if not using {processing}
            }
        };

        if (isEdit) {
            put(route('purchase-orders.update', order.id), options);
        } else {
            post(route('purchase-orders.store'), options);
        }
    };

    // Helper to update specific item in the array
    const updateItem = (index: number, field: keyof PurchaseOrderDetail, value: any) => {
        const newItems = [...data.details];
        newItems[index] = { ...newItems[index], [field]: value };
        setData('details', newItems);
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[700px]">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit' : 'Create'} Purchase Order
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    {/* --- Header Fields --- */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="branch_id">Branch</Label>
                            <NativeSelect
                                name="branch_id"
                                onChange={(e) =>
                                    setData('branch_id', +e.target.value)
                                }
                                value={data.branch_id}
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

                        <div className="grid gap-2">
                            <Label htmlFor="supplier_name">PO No.</Label>
                            <Input
                                id="supplier_name"
                                value={data.po_number}
                                onChange={(e) =>
                                    setData('po_number', e.target.value)
                                }
                            />
                            <InputError message={errors.po_number} />
                        </div>
                    </div>

                    {isEdit && (
                        <div className="grid grid-cols-1 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="status">Status</Label>
                                <NativeSelect
                                    name="status"
                                    onChange={(e) =>
                                        setData('status', e.target.value)
                                    }
                                    value={data.status}
                                >
                                    <NativeSelectOption value="">
                                        Select status
                                    </NativeSelectOption>
                                    {statuses.map((status: PurchaseOrderStatus) => (
                                        <NativeSelectOption
                                            key={status.key}
                                            value={status.key}
                                        >
                                            {capitalizeFirstLetter(status.value)}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.status} />
                            </div>
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="received_at">Received At</Label>
                            <Input
                                id="received_at"
                                type="date"
                                value={data.received_at}
                                onChange={(e) =>
                                    setData('received_at', e.target.value)
                                }
                            />
                            <InputError message={errors.due_at} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="due_at">Due</Label>
                            <Input
                                id="due_at"
                                type="date"
                                value={data.due_at}
                                onChange={(e) =>
                                    setData('due_at', e.target.value)
                                }
                            />
                            <InputError message={errors.due_at} />
                        </div>
                    </div>

                    <hr />

                    {/* --- Items Table --- */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <Label className="text-lg font-bold">
                                Line Items
                            </Label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setData('details', [
                                        ...data.details,
                                        {
                                            item_name: '',
                                            quantity: 1,
                                            unit_price: 0,
                                        },
                                    ])
                                }
                            >
                                <Plus className="mr-2 h-4 w-4" /> Add Item
                            </Button>
                        </div>

                        {data.details.map((item, index) => (
                            <div
                                key={index}
                                className="group relative flex items-end gap-3 rounded-md border bg-slate-50/50 p-3"
                            >
                                <div className="grid flex-1 grid-cols-12 gap-3">
                                    <div className="col-span-6 grid gap-1.5">
                                        <Label className="text-xs">
                                            Item Description
                                        </Label>
                                        <Input
                                            value={item.item_name}
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'item_name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="col-span-3 grid gap-1.5">
                                        <Label className="text-xs">Qty</Label>
                                        <Input
                                            type="number"
                                            className={
                                                errors[
                                                    `details.${index}.quantity` as keyof typeof errors
                                                ]
                                                    ? 'border-destructive focus-visible:ring-destructive'
                                                    : ''
                                            }
                                            value={item.quantity}
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'quantity',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="col-span-3 grid gap-1.5">
                                        <Label className="text-xs">Price</Label>
                                        <Input
                                            type="number"
                                            className={
                                                errors[
                                                    `details.${index}.unit_price` as keyof typeof errors
                                                ]
                                                    ? 'border-destructive focus-visible:ring-destructive'
                                                    : ''
                                            }
                                            value={item.unit_price}
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'unit_price',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="mb-0.5 text-destructive"
                                    onClick={() =>
                                        setData(
                                            'details',
                                            data.details.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        ))}

                        {/* Find the first error that starts with "details" */}
                        {Object.keys(errors).some((key) =>
                            key.startsWith('details'),
                        ) && (
                            <p className="text-sm font-medium text-destructive">
                                There are errors in your line items. Please
                                check the quantities and prices.
                            </p>
                        )}
                    </div>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={processing}
                    >
                        {processing && <Spinner className="mr-2" />}
                        {isEdit
                            ? 'Update Purchase Order'
                            : 'Submit Purchase Order'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
