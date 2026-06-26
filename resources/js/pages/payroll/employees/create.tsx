import { Head, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { EmployeesList } from '@/types/employee';
import { EmployeeForm } from './components/employee-form';
import type { EmployeeFormData } from './components/employee-form';

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
    const form = useForm<EmployeeFormData>({
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
        default_paid_leave_days: 5,
        paid_leave_balance: 5,
        can_edit_sewed_items: false,
        sss_number: '',
        philhealth_number: '',
        pagibig_number: '',
        tin_number: '',
        notes: '',
    });

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post(route('payroll.employees.store'), {
            onSuccess: () =>
                toast.success('Employee created successfully.', {
                    position: 'top-center',
                }),
        });
    };

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Employee" />
            <div className="mx-auto w-full max-w-4xl flex-1 px-4 py-6 sm:px-6">
                <header className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Create Employee
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Add a new employee to the payroll system.
                    </p>
                </header>

                <EmployeeForm
                    form={form}
                    statuses={statuses}
                    positions={positions}
                    branches={branches}
                    onSubmit={submit}
                    submitLabel="Save Employee"
                    cancelHref={route('payroll.employees.index')}
                    includeBranchPlaceholder
                />
            </div>
        </PayrollLayout>
    );
}
