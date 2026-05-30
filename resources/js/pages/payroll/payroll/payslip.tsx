import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Payslip', href: '#' },
];

type Props = {
    period: {
        id: number;
        branch: { name: string };
        period_start: string;
        period_end: string;
        status: string;
    };
    item: {
        id: number;
        employee: {
            id: number;
            first_name: string;
            last_name: string;
            employee_number: string;
            current_daily_rate: number;
            sss_number: string | null;
            philhealth_number: string | null;
            pagibig_number: string | null;
            tin_number: string | null;
            position: string;
        };
        total_regular_days: number;
        absent_days: number;
        total_late_minutes: number;
        late_deduction: number;
        total_undertime_minutes: number;
        undertime_deduction: number;
        total_overtime_minutes: number;
        overtime_pay: number;
        holiday_pay_days: number;
        holiday_pay: number;
        leave_paid_days: number;
        fine_deduction: number;
        gross_pay: number;
        deminimis_earnings: number;
        sss_deduction: number;
        philhealth_deduction: number;
        pagibig_deduction: number;
        ca_deduction: number;
        net_pay: number;
        daily_rate: number;
        sss_bracket: number | null;
    };
};

const monthlySalary = (rate: number) => rate * 26;

const sssLabel = (item: Props['item']) => {
    if (!item.employee.sss_number) {
        return null;
    }

    return `${item.employee.sss_number} · Bracket #${item.sss_bracket ?? '—'}`;
};

export default function Payslip({ period, item }: Props) {
    const emp = item.employee;
    const monthly = monthlySalary(item.daily_rate);

    const totalDeductions =
        (Number(item.late_deduction) || 0) +
        (Number(item.undertime_deduction) || 0) +
        (Number(item.fine_deduction) || 0) +
        (Number(item.sss_deduction) || 0) +
        (Number(item.philhealth_deduction) || 0) +
        (Number(item.pagibig_deduction) || 0) +
        (Number(item.ca_deduction) || 0);

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Payslip" />
            <div className="mx-auto flex h-full max-w-2xl flex-col gap-4 p-4">
                <Button
                    variant="ghost"
                    size="sm"
                    className="w-fit"
                    onClick={() => router.get(`/payroll/periods/${period.id}`)}
                >
                    <ArrowLeft className="mr-1 h-4 w-4" /> Back to Period
                </Button>

                {/* Header */}
                <div className="rounded-md border bg-sidebar p-6 text-sm">
                    <div className="text-center font-bold">
                        PRINTING SHOP MANAGEMENT
                    </div>
                    <div className="text-center text-xs text-muted-foreground">
                        Branch: {period.branch.name}
                    </div>
                    <div className="mt-2 text-center text-sm font-semibold">
                        PAYSLIP — Weekly · {period.period_start} to{' '}
                        {period.period_end}
                    </div>

                    <div className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                        <div>
                            <span className="text-muted-foreground">
                                Employee:{' '}
                            </span>
                            <span className="font-medium">
                                {emp.first_name} {emp.last_name}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Position:{' '}
                            </span>
                            <span className="font-medium capitalize">
                                {emp.position}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Emp #:{' '}
                            </span>
                            <span className="font-medium">
                                {emp.employee_number}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Daily Rate:{' '}
                            </span>
                            <span className="font-medium">
                                {formatCurrency(item.daily_rate)}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">SSS: </span>
                            <span className="font-medium">
                                {emp.sss_number || '—'}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                PhilHealth:{' '}
                            </span>
                            <span className="font-medium">
                                {emp.philhealth_number || '—'}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Pag-IBIG:{' '}
                            </span>
                            <span className="font-medium">
                                {emp.pagibig_number || '—'}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">TIN: </span>
                            <span className="font-medium">
                                {emp.tin_number || '—'}
                            </span>
                        </div>
                        <div className="col-span-2">
                            <span className="text-muted-foreground">
                                Monthly Salary:{' '}
                            </span>
                            <span className="font-medium">
                                {formatCurrency(monthly)} (daily × 26)
                                {item.sss_bracket
                                    ? ` · SSS Bracket #${item.sss_bracket}`
                                    : ''}
                            </span>
                        </div>
                    </div>

                    <div className="mt-2 border-t pt-2 text-xs">
                        <span className="text-muted-foreground">
                            Attendance:{' '}
                        </span>
                        <span className="font-medium">
                            Present {item.total_regular_days}d
                        </span>
                        {item.total_late_minutes > 0 && (
                            <span className="font-medium">
                                {' '}
                                · Late {item.total_late_minutes}min
                            </span>
                        )}
                        {item.total_overtime_minutes > 0 && (
                            <span className="font-medium">
                                {' '}
                                · OT{' '}
                                {(item.total_overtime_minutes / 60).toFixed(1)}h
                            </span>
                        )}
                        {item.absent_days > 0 && (
                            <span className="font-medium">
                                {' '}
                                · Absent {item.absent_days}d
                            </span>
                        )}
                        {item.holiday_pay_days > 0 && (
                            <span className="font-medium">
                                {' '}
                                · Holiday {item.holiday_pay_days}d
                            </span>
                        )}
                    </div>
                </div>

                {/* Two-Column Body */}
                <div className="grid grid-cols-2 gap-4">
                    {/* Earnings */}
                    <div className="rounded-md border bg-sidebar p-4">
                        <h3 className="mb-3 text-xs font-semibold text-muted-foreground uppercase">
                            Earnings
                        </h3>
                        <div className="space-y-2 text-sm">
                            <Row label="Basic Pay" value={item.gross_pay} />
                            {item.total_overtime_minutes > 0 && (
                                <Row
                                    label={`Overtime (${(item.total_overtime_minutes / 60).toFixed(1)}h)`}
                                    value={item.overtime_pay}
                                />
                            )}
                            {item.holiday_pay > 0 && (
                                <Row
                                    label={`Holiday Pay (${item.holiday_pay_days}d)`}
                                    value={item.holiday_pay}
                                />
                            )}
                            {item.deminimis_earnings > 0 && (
                                <>
                                    <div className="text-xs text-muted-foreground">
                                        * De Minimis:
                                    </div>
                                    <Row
                                        label="  Perks"
                                        value={item.deminimis_earnings}
                                    />
                                </>
                            )}
                            <div className="border-t pt-2 font-semibold">
                                <Row
                                    label="GROSS PAY"
                                    value={
                                        (Number(item.gross_pay) || 0) +
                                        (Number(item.overtime_pay) || 0) +
                                        (Number(item.holiday_pay) || 0) +
                                        (Number(item.deminimis_earnings) || 0)
                                    }
                                    bold
                                />
                            </div>
                        </div>
                    </div>

                    {/* Deductions */}
                    <div className="rounded-md border bg-sidebar p-4">
                        <h3 className="mb-3 text-xs font-semibold text-muted-foreground uppercase">
                            Deductions
                        </h3>
                        <div className="space-y-2 text-sm">
                            {item.late_deduction > 0 && (
                                <Row
                                    label={`Late (${item.total_late_minutes}min)`}
                                    value={-item.late_deduction}
                                    red
                                />
                            )}
                            {item.undertime_deduction > 0 && (
                                <Row
                                    label={`Undertime (${item.total_undertime_minutes}min)`}
                                    value={-item.undertime_deduction}
                                    red
                                />
                            )}
                            {item.fine_deduction > 0 && (
                                <Row
                                    label="Fines"
                                    value={-item.fine_deduction}
                                    red
                                />
                            )}
                            {item.sss_deduction > 0 && (
                                <Row
                                    label="SSS (5%)"
                                    value={-item.sss_deduction}
                                    red
                                />
                            )}
                            {item.philhealth_deduction > 0 && (
                                <Row
                                    label="PhilHealth (2.50%)"
                                    value={-item.philhealth_deduction}
                                    red
                                />
                            )}
                            {item.pagibig_deduction > 0 && (
                                <Row
                                    label="Pag-IBIG"
                                    value={-item.pagibig_deduction}
                                    red
                                />
                            )}
                            {item.ca_deduction > 0 && (
                                <Row
                                    label="Cash Advance"
                                    value={-item.ca_deduction}
                                    red
                                />
                            )}
                            <div className="border-t pt-2 font-semibold">
                                <Row
                                    label="TOTAL DEDUCTIONS"
                                    value={-totalDeductions}
                                    bold
                                    red
                                />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Net Pay */}
                <div className="rounded-md border border-green-200 bg-green-50 p-4 text-center">
                    <div className="text-xs text-green-700">NET PAY</div>
                    <div className="text-2xl font-bold text-green-800">
                        {formatCurrency(item.net_pay)}
                    </div>
                </div>

                {/* Footer */}
                <div className="rounded-md border bg-sidebar p-4 text-xs text-muted-foreground">
                    <div className="grid grid-cols-3 gap-4">
                        <div className="space-y-1">
                            <div className="border-b pb-1">Employee:</div>
                            <div className="pb-4">Signature & Date</div>
                        </div>
                        <div className="space-y-1">
                            <div className="border-b pb-1">Prepared by:</div>
                            <div className="pb-4">Signature & Date</div>
                        </div>
                        <div className="space-y-1">
                            <div className="border-b pb-1">Approved by:</div>
                            <div className="pb-4">Signature & Date</div>
                        </div>
                    </div>
                    <div className="mt-3 text-center">
                        Generated:{' '}
                        {new Date().toLocaleDateString('en-PH', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                        })}{' '}
                        · Status: {period.status}
                    </div>
                </div>
            </div>
        </PayrollLayout>
    );
}

function Row({
    label,
    value,
    bold,
    red,
}: {
    label: string;
    value: number;
    bold?: boolean;
    red?: boolean;
}) {
    return (
        <div className={`flex justify-between ${bold ? 'font-semibold' : ''}`}>
            <span>{label}</span>
            <span className={`font-mono ${red ? 'text-red-600' : ''}`}>
                {formatCurrency(value)}
            </span>
        </div>
    );
}
