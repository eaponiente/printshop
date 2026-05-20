import { Head, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { EmployeesList } from '@/types/employee';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Employees', href: '/payroll/employees' },
    { title: 'Create', href: '/payroll/employees/create' },
];

export default function EmployeeCreate({
    statuses,
    positions,
    branches,
}: EmployeesList) {
    const { data, setData, post, processing, errors } = useForm({
        first_name: '',
        last_name: '',
        middle_name: '',
        email: '',
        phone: '',
        address: '',
        birth_date: '',
        hire_date: new Date().toISOString().slice(0, 10),
        end_date: '',
        branch_id: '',
        position: 'regular',
        status: 'active',
        daily_rate: 0,
        sss_number: '',
        philhealth_number: '',
        pagibig_number: '',
        tin_number: '',
        notes: '',
    });

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post('/payroll/employees', {
            onSuccess: () => {
                toast.success('Employee created successfully.', {
                    position: 'top-center',
                });
            },
        });
    };

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Employee" />
            <div className="flex h-full flex-1 flex-col gap-4 p-6">
                <div>
                    <h1 className="text-xl font-semibold">Create Employee</h1>
                    <p className="text-sm text-muted-foreground">
                        Add a new employee to the payroll system.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-md border border-sidebar-border bg-sidebar p-6"
                >
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="first_name">First Name *</Label>
                            <Input
                                id="first_name"
                                value={data.first_name}
                                onChange={(e) =>
                                    setData('first_name', e.target.value)
                                }
                            />
                            <InputError message={errors.first_name} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="last_name">Last Name *</Label>
                            <Input
                                id="last_name"
                                value={data.last_name}
                                onChange={(e) =>
                                    setData('last_name', e.target.value)
                                }
                            />
                            <InputError message={errors.last_name} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="middle_name">Middle Name</Label>
                            <Input
                                id="middle_name"
                                value={data.middle_name}
                                onChange={(e) =>
                                    setData('middle_name', e.target.value)
                                }
                            />
                            <InputError message={errors.middle_name} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="phone">Phone</Label>
                            <Input
                                id="phone"
                                value={data.phone}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                            />
                            <InputError message={errors.phone} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="birth_date">Birth Date</Label>
                            <Input
                                id="birth_date"
                                type="date"
                                value={data.birth_date}
                                onChange={(e) =>
                                    setData('birth_date', e.target.value)
                                }
                            />
                            <InputError message={errors.birth_date} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="hire_date">Hire Date *</Label>
                            <Input
                                id="hire_date"
                                type="date"
                                value={data.hire_date}
                                onChange={(e) =>
                                    setData('hire_date', e.target.value)
                                }
                            />
                            <InputError message={errors.hire_date} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="branch_id">Branch *</Label>
                            <NativeSelect
                                id="branch_id"
                                value={data.branch_id}
                                onChange={(e) =>
                                    setData('branch_id', e.target.value)
                                }
                            >
                                <NativeSelectOption value="">
                                    Select branch
                                </NativeSelectOption>
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
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="position">Position *</Label>
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
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="status">Status *</Label>
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
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="daily_rate">
                                Daily Rate (PHP) *
                            </Label>
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
                        </div>
                    </div>

                    <div className="border-t pt-4">
                        <h3 className="mb-3 text-sm font-semibold">
                            Government IDs
                        </h3>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="sss_number">SSS Number</Label>
                                <Input
                                    id="sss_number"
                                    value={data.sss_number}
                                    onChange={(e) =>
                                        setData('sss_number', e.target.value)
                                    }
                                />
                                <InputError message={errors.sss_number} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="philhealth_number">
                                    PhilHealth Number
                                </Label>
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
                                <InputError
                                    message={errors.philhealth_number}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="pagibig_number">
                                    Pag-IBIG Number
                                </Label>
                                <Input
                                    id="pagibig_number"
                                    value={data.pagibig_number}
                                    onChange={(e) =>
                                        setData(
                                            'pagibig_number',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError message={errors.pagibig_number} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="tin_number">TIN Number</Label>
                                <Input
                                    id="tin_number"
                                    value={data.tin_number}
                                    onChange={(e) =>
                                        setData('tin_number', e.target.value)
                                    }
                                />
                                <InputError message={errors.tin_number} />
                            </div>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="address">Address</Label>
                        <Textarea
                            id="address"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                            rows={2}
                        />
                        <InputError message={errors.address} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="notes">Notes</Label>
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={3}
                        />
                        <InputError message={errors.notes} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner className="mr-2" />}
                        Save Employee
                    </Button>
                </form>
            </div>
        </PayrollLayout>
    );
}
