import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import { BranchMultiSelect } from '@/components/shared/branch-multi-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Holiday, HolidayBranch, Props } from '../list';

type HolidayFormData = {
    name: string;
    date: string;
    type: string;
    recurring: boolean;
    branch_ids: number[];
};

const emptyFormData = (
    holiday: Holiday | undefined,
    types: Props['types'],
): HolidayFormData => ({
    name: holiday?.name ?? '',
    date: holiday?.date ?? '',
    type: holiday?.type ?? types[0]?.key ?? '',
    recurring: holiday?.recurring ?? true,
    branch_ids: holiday?.branches.map((b) => b.id) ?? [],
});

export function HolidayDialog({
    holiday,
    types,
    branches,
    children,
}: {
    holiday?: Holiday;
    types: Props['types'];
    branches: HolidayBranch[];
    children: React.ReactNode;
}) {
    const isEdit = !!holiday;
    const [open, setOpen] = useState(false);

    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm<HolidayFormData>(emptyFormData(holiday, types));

    // Reset local form state whenever the dialog opens (or closes), so
    // editing one holiday and then another doesn't leak the previous
    // selection, and reopening "Add" starts blank.
    const handleOpenChange = (next: boolean) => {
        setOpen(next);

        if (next) {
            setData(emptyFormData(holiday, types));
            clearErrors();
        }
    };

    const handleTypeChange = (value: string) => {
        setData((prev) => ({
            ...prev,
            type: value,
            // Only `special` holidays may be branch-scoped — clear any
            // selection in the same update so a stale array never survives
            // a switch away from `special`.
            branch_ids: value === 'special' ? prev.branch_ids : [],
        }));
    };

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        const options = {
            onSuccess: () => {
                toast.success(isEdit ? 'Updated.' : 'Added.');
                setOpen(false);
            },
            onError: () => toast.error('Failed.'),
        };

        if (isEdit && holiday) {
            put(route('payroll.holidays.update', holiday.id), options);
        } else {
            post(route('payroll.holidays.store'), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit Holiday' : 'Add Holiday'}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            required
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        {errors.name && (
                            <p className="text-sm text-destructive">
                                {errors.name}
                            </p>
                        )}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="date">Date</Label>
                        <Input
                            id="date"
                            type="date"
                            required
                            value={data.date}
                            onChange={(e) => setData('date', e.target.value)}
                        />
                        {errors.date && (
                            <p className="text-sm text-destructive">
                                {errors.date}
                            </p>
                        )}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="type">Type</Label>
                        <Select
                            value={data.type}
                            onValueChange={handleTypeChange}
                        >
                            <SelectTrigger id="type">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                {types.map((t) => (
                                    <SelectItem key={t.key} value={t.key}>
                                        {t.value}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.type && (
                            <p className="text-sm text-destructive">
                                {errors.type}
                            </p>
                        )}
                    </div>
                    {data.type === 'special' && (
                        <div className="space-y-2">
                            <Label>Applies to</Label>
                            <p className="text-xs text-muted-foreground">
                                Leave all unchecked to apply to every branch.
                            </p>
                            <BranchMultiSelect
                                branches={branches}
                                value={data.branch_ids}
                                onChange={(branchIds) =>
                                    setData('branch_ids', branchIds)
                                }
                            />
                            {errors.branch_ids && (
                                <p className="text-sm text-destructive">
                                    {errors.branch_ids}
                                </p>
                            )}
                        </div>
                    )}
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="recurring"
                            checked={data.recurring}
                            onCheckedChange={(checked) =>
                                setData('recurring', checked === true)
                            }
                        />
                        <Label
                            htmlFor="recurring"
                            className="cursor-pointer text-sm"
                        >
                            Recurring (repeat every year)
                        </Label>
                    </div>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={processing}
                    >
                        {isEdit ? 'Update' : 'Add'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
