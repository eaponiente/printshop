import type { InertiaFormProps } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

export type EmployeeFormData = {
    first_name: string;
    last_name: string;
    middle_name: string;
    email: string;
    phone: string;
    address: string;
    birth_date: string;
    hire_date: string;
    end_date: string;
    branch_id: string;
    position: string;
    status: string;
    daily_rate: number;
    default_paid_leave_days: number;
    paid_leave_balance: number;
    can_edit_sewed_items: boolean;
    sss_number: string;
    philhealth_number: string;
    pagibig_number: string;
    tin_number: string;
    notes: string;
};

type Option = { key: string; value: string };

type Props = {
    form: InertiaFormProps<EmployeeFormData>;
    statuses: Option[];
    positions: Option[];
    branches: Array<{ id: number; name: string }>;
    onSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    submitLabel: string;
    cancelHref?: string;
    includeBranchPlaceholder?: boolean;
};

export function EmployeeForm({
    form,
    statuses,
    positions,
    branches,
    onSubmit,
    submitLabel,
    cancelHref,
    includeBranchPlaceholder = false,
}: Props) {
    const { data, setData, processing, errors } = form;

    return (
        <form onSubmit={onSubmit} className="space-y-6 pb-24">
            <Card>
                <CardHeader>
                    <CardTitle>Personal Information</CardTitle>
                    <CardDescription>
                        Name and contact details.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <Field label="First Name" htmlFor="first_name" required>
                            <Input
                                id="first_name"
                                value={data.first_name}
                                onChange={(e) =>
                                    setData('first_name', e.target.value)
                                }
                            />
                            <InputError message={errors.first_name} />
                        </Field>
                        <Field label="Middle Name" htmlFor="middle_name">
                            <Input
                                id="middle_name"
                                value={data.middle_name}
                                onChange={(e) =>
                                    setData('middle_name', e.target.value)
                                }
                            />
                            <InputError message={errors.middle_name} />
                        </Field>
                        <Field label="Last Name" htmlFor="last_name" required>
                            <Input
                                id="last_name"
                                value={data.last_name}
                                onChange={(e) =>
                                    setData('last_name', e.target.value)
                                }
                            />
                            <InputError message={errors.last_name} />
                        </Field>
                    </div>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <Field label="Email" htmlFor="email">
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                            />
                            <InputError message={errors.email} />
                        </Field>
                        <Field label="Phone" htmlFor="phone">
                            <Input
                                id="phone"
                                value={data.phone}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                            />
                            <InputError message={errors.phone} />
                        </Field>
                        <Field label="Birth Date" htmlFor="birth_date">
                            <Input
                                id="birth_date"
                                type="date"
                                value={data.birth_date}
                                onChange={(e) =>
                                    setData('birth_date', e.target.value)
                                }
                            />
                            <InputError message={errors.birth_date} />
                        </Field>
                    </div>
                    <Field label="Address" htmlFor="address">
                        <Textarea
                            id="address"
                            value={data.address}
                            onChange={(e) =>
                                setData('address', e.target.value)
                            }
                            rows={2}
                        />
                        <InputError message={errors.address} />
                    </Field>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Employment</CardTitle>
                    <CardDescription>
                        Branch assignment, position, and compensation.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <Field label="Hire Date" htmlFor="hire_date" required>
                            <Input
                                id="hire_date"
                                type="date"
                                value={data.hire_date}
                                onChange={(e) =>
                                    setData('hire_date', e.target.value)
                                }
                            />
                            <InputError message={errors.hire_date} />
                        </Field>
                        <Field label="End Date" htmlFor="end_date">
                            <Input
                                id="end_date"
                                type="date"
                                value={data.end_date}
                                onChange={(e) =>
                                    setData('end_date', e.target.value)
                                }
                            />
                            <InputError message={errors.end_date} />
                        </Field>
                    </div>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <Field label="Branch" htmlFor="branch_id" required>
                            <NativeSelect
                                id="branch_id"
                                value={data.branch_id}
                                onChange={(e) =>
                                    setData('branch_id', e.target.value)
                                }
                            >
                                {includeBranchPlaceholder && (
                                    <NativeSelectOption value="">
                                        Select branch
                                    </NativeSelectOption>
                                )}
                                {branches.map((b) => (
                                    <NativeSelectOption
                                        key={b.id}
                                        value={String(b.id)}
                                    >
                                        {b.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <InputError message={errors.branch_id} />
                        </Field>
                        <Field label="Position" htmlFor="position" required>
                            <NativeSelect
                                id="position"
                                value={data.position}
                                onChange={(e) =>
                                    setData('position', e.target.value)
                                }
                            >
                                {positions.map((p) => (
                                    <NativeSelectOption
                                        key={p.key}
                                        value={p.key}
                                    >
                                        {p.value}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <InputError message={errors.position} />
                        </Field>
                        <Field label="Status" htmlFor="status" required>
                            <NativeSelect
                                id="status"
                                value={data.status}
                                onChange={(e) =>
                                    setData('status', e.target.value)
                                }
                            >
                                {statuses.map((s) => (
                                    <NativeSelectOption
                                        key={s.key}
                                        value={s.key}
                                    >
                                        {s.value}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <InputError message={errors.status} />
                        </Field>
                    </div>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <Field label="Daily Rate (PHP)" htmlFor="daily_rate" required>
                            <Input
                                id="daily_rate"
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.daily_rate}
                                onChange={(e) =>
                                    setData(
                                        'daily_rate',
                                        parseFloat(e.target.value) || 0,
                                    )
                                }
                            />
                            <InputError message={errors.daily_rate} />
                        </Field>
                        <Field
                            label="Default Paid Leave Days"
                            htmlFor="default_paid_leave_days"
                            hint="Allocated per year."
                        >
                            <Input
                                id="default_paid_leave_days"
                                type="number"
                                step="0.5"
                                min="0"
                                value={data.default_paid_leave_days}
                                onChange={(e) =>
                                    setData(
                                        'default_paid_leave_days',
                                        Number(e.target.value),
                                    )
                                }
                            />
                            <InputError
                                message={errors.default_paid_leave_days}
                            />
                        </Field>
                        <Field
                            label="Current Leave Balance"
                            htmlFor="paid_leave_balance"
                            hint="Remaining paid leave days."
                        >
                            <Input
                                id="paid_leave_balance"
                                type="number"
                                step="0.5"
                                min="0"
                                value={data.paid_leave_balance}
                                onChange={(e) =>
                                    setData(
                                        'paid_leave_balance',
                                        Number(e.target.value),
                                    )
                                }
                            />
                            <InputError message={errors.paid_leave_balance} />
                        </Field>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Government IDs</CardTitle>
                    <CardDescription>
                        Statutory contribution identifiers.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Field label="SSS" htmlFor="sss_number">
                            <Input
                                id="sss_number"
                                value={data.sss_number}
                                onChange={(e) =>
                                    setData('sss_number', e.target.value)
                                }
                            />
                            <InputError message={errors.sss_number} />
                        </Field>
                        <Field label="PhilHealth" htmlFor="philhealth_number">
                            <Input
                                id="philhealth_number"
                                value={data.philhealth_number}
                                onChange={(e) =>
                                    setData(
                                        'philhealth_number',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.philhealth_number} />
                        </Field>
                        <Field label="Pag-IBIG" htmlFor="pagibig_number">
                            <Input
                                id="pagibig_number"
                                value={data.pagibig_number}
                                onChange={(e) =>
                                    setData('pagibig_number', e.target.value)
                                }
                            />
                            <InputError message={errors.pagibig_number} />
                        </Field>
                        <Field label="TIN" htmlFor="tin_number">
                            <Input
                                id="tin_number"
                                value={data.tin_number}
                                onChange={(e) =>
                                    setData('tin_number', e.target.value)
                                }
                            />
                            <InputError message={errors.tin_number} />
                        </Field>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Permissions & Notes</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <label
                        htmlFor="can_edit_sewed_items"
                        className="flex cursor-pointer items-start gap-3 rounded-md border p-3 hover:bg-accent/40"
                    >
                        <Checkbox
                            id="can_edit_sewed_items"
                            checked={data.can_edit_sewed_items}
                            onCheckedChange={(checked) =>
                                setData(
                                    'can_edit_sewed_items',
                                    checked === true,
                                )
                            }
                            className="mt-0.5"
                        />
                        <div className="space-y-0.5">
                            <p className="text-sm font-medium">
                                Can edit sewed items
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Allows this staff to edit all sewed items in
                                their branch.
                            </p>
                        </div>
                    </label>
                    <InputError message={errors.can_edit_sewed_items} />

                    <Field label="Notes" htmlFor="notes">
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={3}
                        />
                        <InputError message={errors.notes} />
                    </Field>
                </CardContent>
            </Card>

            <div className="sticky bottom-0 -mx-6 border-t bg-background/95 px-6 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/75">
                <div className="mx-auto flex max-w-4xl items-center justify-end gap-2">
                    {cancelHref && (
                        <Button
                            asChild
                            variant="outline"
                            type="button"
                            disabled={processing}
                        >
                            <Link href={cancelHref}>
                                <ArrowLeft className="h-4 w-4" />
                                Cancel
                            </Link>
                        </Button>
                    )}
                    <Button type="submit" disabled={processing}>
                        {processing ? (
                            <Spinner className="h-4 w-4" />
                        ) : (
                            <Save className="h-4 w-4" />
                        )}
                        {submitLabel}
                    </Button>
                </div>
            </div>
        </form>
    );
}

function Field({
    label,
    htmlFor,
    required,
    hint,
    children,
}: {
    label: string;
    htmlFor: string;
    required?: boolean;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={htmlFor}>
                {label}
                {required && (
                    <span className="ml-0.5 text-destructive">*</span>
                )}
            </Label>
            {children}
            {hint && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}
