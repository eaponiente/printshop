import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import SearchCustomersField from '@/components/shared/search-customers-field';
import { submitFormOptions } from '@/components/shared/submit-form-options';
import TagSelector from '@/components/shared/tag-selector';
import { tagsLocked } from '@/pages/sublimations/tag-locked-statuses';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { Branch } from '@/types/branches';
import type { Tag } from '@/types/settings';
import type { Sublimation, SublimationStatus } from '@/types/sublimations';
import type { User } from '@/types/user';

interface SublimationDialogProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    branches: Branch[];
    users: User[];
    statuses: SublimationStatus[];
    availableTags: Tag[];
    sublimation?: Sublimation | null;
}

export default function SublimationDialog({
    open,
    setOpen,
    branches,
    users,
    availableTags,
    sublimation,
}: SublimationDialogProps) {
    const isEdit = !!sublimation;
    const { auth } = usePage().props;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        status: sublimation?.status ?? 'for_approval',
        transaction_type: sublimation?.transaction_type ?? 'retail',
        production_authorized: sublimation?.production_authorized ?? false,
        amount_total: sublimation?.amount_total ?? '',
        amount_paid: sublimation?.amount_paid ?? '',
        description: sublimation?.description ?? '',
        notes: sublimation?.notes ?? '',
        branch_id: sublimation?.branch_id ?? (auth.user as any).branch_id ?? '',
        customer_id: sublimation?.customer_id ?? '',
        tag_ids: (sublimation?.tags?.map((t) => ({
            id: t.id,
            quantity: t.pivot?.quantity ?? 1,
        })) ?? []) as { id: number; quantity: number }[],
        user_id: sublimation?.user_id ?? '',
    });

    // Automatically uncheck/reset if the transaction type changes to PO
    useEffect(() => {
        if (data.transaction_type === 'purchase_order') {
            setData('production_authorized', false);
        }
    }, [data.transaction_type, setData]);

    const prePaymentKeys = ['for_approval', 'done_layout', 'waiting_for_dp'];

    const [availableTagsState, setAvailableTagsState] =
        useState<Tag[]>(availableTags);

    const addTag = (tagId: number) => {
        if (!data.tag_ids.some((t) => t.id === tagId)) {
            setData('tag_ids', [...data.tag_ids, { id: tagId, quantity: 1 }]);
        }
    };

    const removeTag = (tagId: number) => {
        setData(
            'tag_ids',
            data.tag_ids.filter((t) => t.id !== tagId),
        );
    };

    const updateTagQuantity = (tagId: number, qty: number) => {
        setData(
            'tag_ids',
            data.tag_ids.map((t) => (t.id === tagId ? { ...t, quantity: qty } : t)),
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = submitFormOptions({
            isEdit,
            resourceName: 'Sublimation',
            onSuccess: () => setOpen(false),
            reset,
        });

        if (isEdit) {
            put(route('sublimations.update', sublimation.id), options);
        } else {
            post(route('sublimations.store'), options);
        }
    };

    // Define the visibility logic
    const isManagerOrAdmin = ['admin', 'superadmin'].includes(auth.user.role);
    const showAuthorization =
        isEdit && data.transaction_type === 'retail' && isManagerOrAdmin;

    return (
        <>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-175">
                    <DialogHeader>
                        <DialogTitle>
                            {isEdit ? 'Edit' : 'Add'} Sublimation
                        </DialogTitle>
                    </DialogHeader>

                    <form
                        onSubmit={handleSubmit}
                        className="flex flex-col gap-5"
                    >
                        {/* Description */}
                        <div className="grid gap-2">
                            <Label htmlFor="description">Team / Subject</Label>
                            <Textarea
                                id="description"
                                className="min-h-[80px] resize-none"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                            <InputError message={errors.description} />
                        </div>

                        {/* Notes */}
                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                className="min-h-[80px] resize-none"
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                            />
                            <InputError message={errors.notes} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            {/* Customer Search */}
                            <SearchCustomersField
                                field={sublimation}
                                selectCustomer={(id: any) =>
                                    setData('customer_id', id)
                                }
                                errors={errors}
                            />

                            {/* Branch */}
                            <div className="grid gap-2">
                                <Label htmlFor="branch_id">Branch</Label>
                                <NativeSelect
                                    value={data.branch_id}
                                    onChange={(e) => {
                                        setData({
                                            ...data,
                                            branch_id: e.target.value,
                                        });
                                    }}
                                >
                                    <NativeSelectOption value="">
                                        Select branch
                                    </NativeSelectOption>
                                    {branches.map((b) => (
                                        <NativeSelectOption
                                            key={b.id}
                                            value={b.id}
                                        >
                                            {b.name}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.branch_id} />
                            </div>
                        </div>

                        {/* Assigned To */}
                        <div className="grid gap-2">
                            <Label htmlFor="user_id">Assigned To</Label>
                            <NativeSelect
                                id="user_id"
                                value={data.user_id}
                                onChange={(e) =>
                                    setData('user_id', e.target.value)
                                }
                            >
                                <NativeSelectOption value="">
                                    Select staff
                                </NativeSelectOption>
                                {users.map((u) => (
                                    <NativeSelectOption key={u.id} value={u.id}>
                                        {u.fullname}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <InputError message={errors.user_id} />
                        </div>

                        {/* Transaction Type & Production Authorization */}
                        <div className="grid grid-cols-2 gap-4">
                            {/* Transaction Type */}
                            <div className="grid gap-2">
                                <Label htmlFor="transaction_type">
                                    Transaction Type
                                </Label>
                                <NativeSelect
                                    id="transaction_type"
                                    value={data.transaction_type}
                                    onChange={(e) =>
                                        setData(
                                            'transaction_type',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <NativeSelectOption value="retail">
                                        Retail (Standard)
                                    </NativeSelectOption>
                                    <NativeSelectOption value="purchase_order">
                                        Purchase Order (Corporate)
                                    </NativeSelectOption>
                                </NativeSelect>
                                <InputError message={errors.transaction_type} />
                            </div>

                            {/* Production Authorized (Manual Override) */}
                            {showAuthorization && (
                                <div className="flex items-end pb-2">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="production_authorized"
                                            checked={data.production_authorized}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'production_authorized',
                                                    checked as boolean,
                                                )
                                            }
                                        />
                                        <div className="grid gap-1.5 leading-none">
                                            <Label
                                                htmlFor="production_authorized"
                                                className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                            >
                                                Authorize Production
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                Bypass payment requirements.
                                            </p>
                                        </div>
                                    </div>
                                    <InputError
                                        message={errors.production_authorized}
                                    />
                                </div>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="amount_total">Total Amount</Label>
                            <Input
                                disabled={
                                    isEdit &&
                                    !prePaymentKeys.includes(data.status)
                                }
                                id="amount_total"
                                value={data.amount_total}
                                onChange={(e) =>
                                    setData('amount_total', e.target.value)
                                }
                            />
                            <InputError message={errors.amount_total} />
                        </div>

                        {/* Tags */}
                        <div className="grid gap-2">
                            <Label>Tags</Label>
                            <div className="rounded-md border p-3">
                                <TagSelector
                                    selectedTagIds={data.tag_ids.map((t) => t.id)}
                                    availableTags={availableTagsState}
                                    onAdd={addTag}
                                    onRemove={removeTag}
                                    layout="row"
                                    quantities={Object.fromEntries(
                                        data.tag_ids.map((t) => [t.id, t.quantity]),
                                    )}
                                    onQuantityChange={updateTagQuantity}
                                    onTagCreated={(tag) =>
                                        setAvailableTagsState((prev) => [
                                            ...prev,
                                            tag,
                                        ])
                                    }
                                    readOnly={tagsLocked(sublimation?.status)}
                                />
                            </div>
                            <InputError message={errors.tag_ids} />
                        </div>

                        <Button
                            type="submit"
                            className="mt-2"
                            disabled={processing}
                        >
                            {processing && <Spinner className="mr-2" />}
                            {isEdit
                                ? 'Update Sublimation'
                                : 'Create Sublimation'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
