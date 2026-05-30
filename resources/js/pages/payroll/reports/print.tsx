import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import { formatCurrency } from '@/utils/formatters';

type EmployeeInfo = {
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

type ItemData = {
    id: number;
    employee: EmployeeInfo;
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

type Props = {
    period: {
        id: number;
        branch: string;
        period_start: string;
        period_end: string;
        status: string;
    };
    items: ItemData[];
};

const monthlySalary = (rate: number) => rate * 26;

export default function ReportsPrint({ period, items }: Props) {
    useEffect(() => {
        window.print();
    }, []);

    return (
        <>
            <Head title="Print Payslips" />

            <style>{`
                @media print {
                    @page { margin: 10mm; size: A4; }
                    .payslip-card { page-break-after: always; }
                    .payslip-card:last-child { page-break-after: auto; }
                    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                }
                @media screen {
                    body { background: #f5f5f5; padding: 20px; }
                    .payslip-card { margin-bottom: 24px; }
                }
            `}</style>

            <div className="mx-auto max-w-2xl">
                {items.length === 0 && (
                    <div className="rounded-md border bg-white p-8 text-center text-muted-foreground">
                        <p className="text-lg font-medium">No payslips found</p>
                        <p className="mt-1 text-sm">
                            No payroll period items exist for this branch and
                            period.
                        </p>
                    </div>
                )}

                {items.map((item) => (
                    <PayslipCard key={item.id} period={period} item={item} />
                ))}
            </div>
        </>
    );
}

function PayslipCard({
    period,
    item,
}: {
    period: Props['period'];
    item: ItemData;
}) {
    const emp = item.employee;
    const monthly = monthlySalary(item.daily_rate);

    const totalDeductions =
        (item.late_deduction || 0) +
        (item.undertime_deduction || 0) +
        (item.fine_deduction || 0) +
        (item.sss_deduction || 0) +
        (item.philhealth_deduction || 0) +
        (item.pagibig_deduction || 0) +
        (item.ca_deduction || 0);

    const grossPay =
        (item.gross_pay || 0) +
        (item.overtime_pay || 0) +
        (item.holiday_pay || 0) +
        (item.deminimis_earnings || 0);

    const now = new Date().toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

    return (
        <div className="payslip-card rounded-md border bg-white text-sm shadow">
            {/* Header */}
            <div className="p-5">
                <div className="text-center font-bold">
                    PRINTING SHOP MANAGEMENT
                </div>
                <div className="text-center text-xs text-muted-foreground">
                    Branch: {period.branch}
                </div>
                <div className="mt-1 text-center text-sm font-semibold">
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
                        <span className="text-muted-foreground">Emp #: </span>
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
                    <span className="text-muted-foreground">Attendance: </span>
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
                            · OT {(item.total_overtime_minutes / 60).toFixed(1)}
                            h
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
            <div className="grid grid-cols-2 gap-0 border-t">
                {/* Earnings */}
                <div className="border-r p-4">
                    <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                        Earnings
                    </h3>
                    <div className="space-y-1.5 text-xs">
                        <PayslipRow label="Basic Pay" value={item.gross_pay} />
                        {item.total_overtime_minutes > 0 && (
                            <PayslipRow
                                label={`Overtime (${(item.total_overtime_minutes / 60).toFixed(1)}h)`}
                                value={item.overtime_pay}
                            />
                        )}
                        {item.holiday_pay > 0 && (
                            <PayslipRow
                                label={`Holiday Pay (${item.holiday_pay_days}d)`}
                                value={item.holiday_pay}
                            />
                        )}
                        {item.deminimis_earnings > 0 && (
                            <>
                                <div className="text-[10px] text-muted-foreground">
                                    * De Minimis:
                                </div>
                                <PayslipRow
                                    label="  Perks"
                                    value={item.deminimis_earnings}
                                />
                            </>
                        )}
                        <div className="border-t pt-1.5 font-semibold">
                            <PayslipRow
                                label="GROSS PAY"
                                value={grossPay}
                                bold
                            />
                        </div>
                    </div>
                </div>

                {/* Deductions */}
                <div className="p-4">
                    <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                        Deductions
                    </h3>
                    <div className="space-y-1.5 text-xs">
                        {item.late_deduction > 0 && (
                            <PayslipRow
                                label={`Late (${item.total_late_minutes}min)`}
                                value={-item.late_deduction}
                                red
                            />
                        )}
                        {item.undertime_deduction > 0 && (
                            <PayslipRow
                                label={`Undertime (${item.total_undertime_minutes}min)`}
                                value={-item.undertime_deduction}
                                red
                            />
                        )}
                        {item.fine_deduction > 0 && (
                            <PayslipRow
                                label="Fines"
                                value={-item.fine_deduction}
                                red
                            />
                        )}
                        {item.sss_deduction > 0 && (
                            <PayslipRow
                                label="SSS (5%)"
                                value={-item.sss_deduction}
                                red
                            />
                        )}
                        {item.philhealth_deduction > 0 && (
                            <PayslipRow
                                label="PhilHealth (2.50%)"
                                value={-item.philhealth_deduction}
                                red
                            />
                        )}
                        {item.pagibig_deduction > 0 && (
                            <PayslipRow
                                label="Pag-IBIG"
                                value={-item.pagibig_deduction}
                                red
                            />
                        )}
                        {item.ca_deduction > 0 && (
                            <PayslipRow
                                label="Cash Advance"
                                value={-item.ca_deduction}
                                red
                            />
                        )}
                        <div className="border-t pt-1.5 font-semibold">
                            <PayslipRow
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
            <div className="border-t bg-green-50 p-4 text-center">
                <div className="text-[10px] text-green-700">NET PAY</div>
                <div className="text-xl font-bold text-green-800">
                    {formatCurrency(item.net_pay)}
                </div>
            </div>

            {/* Footer */}
            <div className="border-t p-4 text-[10px] text-muted-foreground">
                <div className="grid grid-cols-3 gap-3">
                    <div className="space-y-0.5">
                        <div className="border-b pb-0.5">Employee:</div>
                        <div className="pt-4">Signature & Date</div>
                    </div>
                    <div className="space-y-0.5">
                        <div className="border-b pb-0.5">Prepared by:</div>
                        <div className="pt-4">Signature & Date</div>
                    </div>
                    <div className="space-y-0.5">
                        <div className="border-b pb-0.5">Approved by:</div>
                        <div className="pt-4">Signature & Date</div>
                    </div>
                </div>
                <div className="mt-3 text-center">
                    Generated: {now} · Status: {period.status}
                </div>
            </div>
        </div>
    );
}

function PayslipRow({
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
