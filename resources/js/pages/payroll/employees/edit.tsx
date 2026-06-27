import { Head, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { Employee } from '@/types/employee';
import { toDateInput } from '@/utils/dateHelper';
import { EmployeeForm } from './components/employee-form';
import type { EmployeeFormData } from './components/employee-form';

type EditProps = {
    employee: Employee;
    statuses: Array<{ key: string; value: string }>;
    positions: Array<{ key: string; value: string }>;
    branches: Array<{ id: number; name: string }>;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Employees', href: '/payroll/employees' },
    { title: 'Edit', href: '#' },
];

export default function EmployeeEdit({
    employee,
    statuses,
    positions,
    branches,
}: EditProps) {
    const form = useForm<EmployeeFormData>({
        first_name: employee.first_name,
        last_name: employee.last_name,
        middle_name: employee.middle_name ?? '',
        email: employee.email ?? '',
        phone: employee.phone ?? '',
        address: employee.address ?? '',
        birth_date: toDateInput(employee.birth_date),
        hire_date: toDateInput(employee.hire_date),
        end_date: toDateInput(employee.end_date),
        branch_id: String(employee.branch_id),
        position: employee.position,
        status: employee.status,
        daily_rate: Number(employee.current_daily_rate),
        default_paid_leave_days: Number(employee.default_paid_leave_days ?? 5),
        paid_leave_balance: Number(employee.paid_leave_balance ?? 5),
        can_edit_sewed_items: !!employee.can_edit_sewed_items,
        sss_number: employee.sss_number ?? '',
        philhealth_number: employee.philhealth_number ?? '',
        pagibig_number: employee.pagibig_number ?? '',
        tin_number: employee.tin_number ?? '',
        notes: employee.notes ?? '',
    });

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.put(route('payroll.employees.update', employee.id), {
            onSuccess: () =>
                toast.success('Employee updated successfully.', {
                    position: 'top-center',
                }),
        });
    };

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${employee.full_name}`} />
            <div className="mx-auto w-full max-w-4xl flex-1 px-4 py-6 sm:px-6">
                <header className="mb-6">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Employee #{employee.employee_number}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {employee.full_name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Update employee information.
                    </p>
                </header>

                <EmployeeForm
                    form={form}
                    statuses={statuses}
                    positions={positions}
                    branches={branches}
                    onSubmit={submit}
                    submitLabel="Save Changes"
                    cancelHref={route('payroll.employees.show', employee.id)}
                />
            </div>
        </PayrollLayout>
    );
}
