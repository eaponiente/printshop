import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
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
    return (
        <>
            <Head title="View Payslips" />

            <style>{`
                @media print {
                    @page { margin: 6mm; size: A4; }
                    .payslip-card {
                        break-inside: avoid;
                        page-break-inside: avoid;
                    }
                    .payslip-grid {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 4mm;
                    }
                    .payslip-card .print-hide { display: none !important; }
                    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; font-size: 7px; }
                    .no-print { display: none !important; }
                }
                @media screen {
                    body { background: #f5f5f5; padding: 0; }
                    .payslip-grid {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 12px;
                    }
                }
            `}</style>

            <div className="no-print sticky top-0 z-10 flex items-center justify-between border-b bg-white px-4 py-3 shadow-sm">
                <div>
                    <h1 className="text-sm font-semibold">
                        View Payslips — {period.branch}
                    </h1>
                    <p className="text-xs text-muted-foreground">
                        {period.period_start} to {period.period_end} · Status:{' '}
                        {period.status}
                    </p>
                </div>
                <Button size="sm" onClick={() => window.print()}>
                    <Printer className="mr-2 h-4 w-4" />
                    Print
                </Button>
            </div>

            <div className="mx-auto max-w-5xl px-4 py-4">
                {items.length === 0 && (
                    <div className="rounded-md border bg-white p-6 text-center text-muted-foreground">
                        <p className="text-sm font-medium">No payslips found</p>
                        <p className="mt-1 text-xs">
                            No payroll period items exist for this branch and
                            period.
                        </p>
                    </div>
                )}

                <div className="payslip-grid">
                    {items.map((item) => (
                        <PayslipCard
                            key={item.id}
                            period={period}
                            item={item}
                        />
                    ))}
                </div>
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

    // gross_pay (= SUM of daily_wage) already includes overtime_pay and
    // holiday_pay (see AttendanceService::processDailyAttendance). So the true
    // basic is gross_pay minus those, and GROSS must not re-add them — doing so
    // double-counts OT/holiday. Only de-minimis perks sit outside gross_pay.
    const basicPay =
        (item.gross_pay || 0) -
        (item.overtime_pay || 0) -
        (item.holiday_pay || 0);

    const grossPay = (item.gross_pay || 0) + (item.deminimis_earnings || 0);

    const now = new Date().toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

    return (
        <div className="payslip-card rounded border bg-white text-[8px] shadow-sm">
            {/* Header */}
            <div className="p-1.5">
                <div className="text-center text-[9px] font-bold">
                    PRINTING SHOP MANAGEMENT
                    <span className="font-normal text-muted-foreground">
                        {' '}
                        — {period.branch}
                    </span>
                </div>
                <div className="text-center text-[7px] font-semibold">
                    PAYSLIP · {period.period_start} to {period.period_end}
                </div>

                <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0 text-[7px]">
                    <span>
                        <span className="text-muted-foreground">Emp: </span>
                        <span className="font-medium">
                            {emp.first_name} {emp.last_name}
                        </span>
                    </span>
                    <span>
                        <span className="text-muted-foreground">#: </span>
                        <span className="font-medium">
                            {emp.employee_number}
                        </span>
                    </span>
                    <span>
                        <span className="text-muted-foreground">Rate: </span>
                        <span className="font-medium">
                            {formatCurrency(item.daily_rate)}
                        </span>
                    </span>
                    <span>
                        <span className="text-muted-foreground">Monthly: </span>
                        <span className="font-medium">
                            {formatCurrency(monthly)}
                            {item.sss_bracket ? ` (B${item.sss_bracket})` : ''}
                        </span>
                    </span>
                </div>

                <div className="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0 text-[7px]">
                    <span>P:{item.total_regular_days}</span>
                    {item.absent_days > 0 && <span>A:{item.absent_days}</span>}
                    {item.total_late_minutes > 0 && (
                        <span>L:{item.total_late_minutes}m</span>
                    )}
                    {item.total_undertime_minutes > 0 && (
                        <span>UT:{item.total_undertime_minutes}m</span>
                    )}
                    {item.total_overtime_minutes > 0 && (
                        <span>
                            OT:{(item.total_overtime_minutes / 60).toFixed(1)}h
                        </span>
                    )}
                    {item.holiday_pay_days > 0 && (
                        <span>HD:{item.holiday_pay_days}</span>
                    )}
                </div>
            </div>

            {/* Two-Column Body */}
            <div className="grid grid-cols-2 border-t">
                {/* Earnings */}
                <div className="border-r p-1.5">
                    <h3 className="mb-0.5 text-[7px] font-semibold text-muted-foreground uppercase">
                        Earnings
                    </h3>
                    <div className="space-y-0 text-[7px]">
                        <PayslipRow label="Basic" value={basicPay} />
                        {item.total_overtime_minutes > 0 && (
                            <PayslipRow label="OT" value={item.overtime_pay} />
                        )}
                        {item.holiday_pay > 0 && (
                            <PayslipRow
                                label="Holiday"
                                value={item.holiday_pay}
                            />
                        )}
                        {item.deminimis_earnings > 0 && (
                            <PayslipRow
                                label="Perks"
                                value={item.deminimis_earnings}
                            />
                        )}
                        <div className="mt-0.5 border-t pt-0.5 font-semibold">
                            <PayslipRow label="GROSS" value={grossPay} bold />
                        </div>
                    </div>
                </div>

                {/* Deductions */}
                <div className="p-1.5">
                    <h3 className="mb-0.5 text-[7px] font-semibold text-muted-foreground uppercase">
                        Deductions
                    </h3>
                    <div className="space-y-0 text-[7px]">
                        {item.late_deduction > 0 && (
                            <PayslipRow
                                label="Late"
                                value={-item.late_deduction}
                                red
                            />
                        )}
                        {item.undertime_deduction > 0 && (
                            <PayslipRow
                                label="UT"
                                value={-item.undertime_deduction}
                                red
                            />
                        )}
                        {item.fine_deduction > 0 && (
                            <PayslipRow
                                label="Fine"
                                value={-item.fine_deduction}
                                red
                            />
                        )}
                        {item.sss_deduction > 0 && (
                            <PayslipRow
                                label="SSS"
                                value={-item.sss_deduction}
                                red
                            />
                        )}
                        {item.philhealth_deduction > 0 && (
                            <PayslipRow
                                label="PHIC"
                                value={-item.philhealth_deduction}
                                red
                            />
                        )}
                        {item.pagibig_deduction > 0 && (
                            <PayslipRow
                                label="PAG"
                                value={-item.pagibig_deduction}
                                red
                            />
                        )}
                        {item.ca_deduction > 0 && (
                            <PayslipRow
                                label="CA"
                                value={-item.ca_deduction}
                                red
                            />
                        )}
                        <div className="mt-0.5 border-t pt-0.5 font-semibold">
                            <PayslipRow
                                label="TOTAL"
                                value={-totalDeductions}
                                bold
                                red
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* Net Pay */}
            <div className="border-t bg-green-50 p-1 text-center">
                <span className="text-[7px] font-medium text-green-700">
                    NET PAY{' '}
                </span>
                <span className="text-xs font-bold text-green-800">
                    {formatCurrency(item.net_pay)}
                </span>
            </div>

            {/* Footer — hidden when printing */}
            <div className="print-hide border-t p-1.5 text-[7px] text-muted-foreground">
                <div className="grid grid-cols-3 gap-1.5">
                    <div>
                        <div className="border-b">Employee</div>
                        <div className="pt-1.5">Signature</div>
                    </div>
                    <div>
                        <div className="border-b">Prepared by</div>
                        <div className="pt-1.5">Signature</div>
                    </div>
                    <div>
                        <div className="border-b">Approved by</div>
                        <div className="pt-1.5">Signature</div>
                    </div>
                </div>
                <div className="mt-1 text-center">
                    {now} · {period.status}
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
