import '@/../css/print-payslip.css';

import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { formatCurrency, numberToWords } from '@/utils/formatters';

type Employee = {
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

type Item = {
    id: number;
    employee: Employee;
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
    incentive: number;
    leave_paid_days: number;
    fine_deduction: number;
    gross_pay: number;
    deminimis_earnings: number;
    sss_deduction: number;
    sss_employer: number | null;
    philhealth_deduction: number;
    philhealth_employer: number | null;
    pagibig_deduction: number;
    pagibig_employer: number | null;
    ca_deduction: number;
    net_pay: number;
    daily_rate: number;
    sss_bracket: number | null;
    cash_advance_deductions?: CashAdvanceDeduction[];
};

type CashAdvanceDeduction = {
    id: number;
    amount: number;
    cash_advance: {
        id: number;
        reason: string | null;
        remaining_balance: number;
        status: string;
    } | null;
};

type Period = {
    id: number;
    branch: { name: string };
    period_start: string;
    period_end: string;
    status: 'draft' | 'approved' | 'paid' | 'voided';
    approved_at: string | null;
    approved_by: number | null;
    approved_by_user?: { first_name: string; last_name: string } | null;
};

type Props = {
    period: Period;
    item: Item;
    companyName: string;
    viewerCanSeeEmployer: boolean;
    basicPayDays: number;
};

const STATUS_STYLES: Record<Period['status'], string> = {
    draft: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    approved: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    paid: 'bg-blue-100 text-blue-800 border-blue-200',
    voided: 'bg-red-100 text-red-800 border-red-200',
};

const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

export default function Payslip({
    period,
    item,
    companyName,
    viewerCanSeeEmployer,
    basicPayDays,
}: Props) {
    const { auth } = usePage().props as { auth: { user: { role: string } } };
    const isStaff = auth?.user?.role === 'staff';

    const breadcrumbs: BreadcrumbItem[] = isStaff
        ? [
              { title: 'Dashboard', href: '/dashboard' },
              { title: 'Payroll', href: '/payroll' },
              {
                  title: 'My Payslips',
                  href: route('payroll.my-payslip'),
              },
              {
                  title: 'Payslip',
                  href: route('payroll.payslip', [period.id, item.id]),
              },
          ]
        : [
              { title: 'Dashboard', href: '/dashboard' },
              { title: 'Payroll', href: '/payroll' },
              {
                  title: 'Payroll Periods',
                  href: route('payroll.periods.index'),
              },
              {
                  title: `${period.period_start} – ${period.period_end}`,
                  href: route('payroll.periods.show', period.id),
              },
              {
                  title: 'Payslip',
                  href: route('payroll.payslip', [period.id, item.id]),
              },
          ];

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Payslip" />
            <div className="payslip-page mx-auto flex w-full max-w-3xl flex-col gap-4 p-4 sm:p-6">
                <ActionBar isStaff={isStaff} periodId={period.id} />

                <DocumentHeader companyName={companyName} period={period} />

                <EmployeeCard item={item} />

                <AttendanceStrip item={item} basicPayDays={basicPayDays} />

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <EarningsCard item={item} basicPayDays={basicPayDays} />
                    <DeductionsCard
                        item={item}
                        showEmployer={viewerCanSeeEmployer}
                    />
                </div>

                <NetPayCard amount={Number(item.net_pay) || 0} />

                <SignatureFooter period={period} />
            </div>
        </PayrollLayout>
    );
}

function ActionBar({
    isStaff,
    periodId,
}: {
    isStaff: boolean;
    periodId: number;
}) {
    return (
        <div className="no-print flex items-center justify-between gap-2">
            <Button
                variant="ghost"
                size="sm"
                onClick={() =>
                    router.get(
                        isStaff
                            ? route('payroll.my-payslip')
                            : route('payroll.periods.show', periodId),
                    )
                }
            >
                <ArrowLeft className="mr-1 h-4 w-4" />
                <span className="hidden sm:inline">
                    {isStaff ? 'Back to My Payslips' : 'Back to Period'}
                </span>
                <span className="sm:hidden">Back</span>
            </Button>
            <Button variant="outline" size="sm" onClick={() => window.print()}>
                <Printer className="mr-1 h-4 w-4" />
                Print
            </Button>
        </div>
    );
}

function DocumentHeader({
    companyName,
    period,
}: {
    companyName: string;
    period: Period;
}) {
    return (
        <Card data-payslip-card>
            <CardContent className="space-y-1 py-5 text-center">
                <div className="text-base font-bold tracking-wide uppercase">
                    {companyName}
                </div>
                <div className="text-xs text-muted-foreground">
                    Branch: {period.branch.name}
                </div>
                <div className="pt-2 text-sm font-semibold">PAYSLIP</div>
                <div className="text-xs text-muted-foreground">
                    Pay Period: {formatDate(period.period_start)} —{' '}
                    {formatDate(period.period_end)}
                </div>
                <div className="pt-2">
                    <span
                        className={`inline-block rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase ${STATUS_STYLES[period.status]}`}
                    >
                        {period.status}
                    </span>
                </div>
            </CardContent>
        </Card>
    );
}

function EmployeeCard({ item }: { item: Item }) {
    const emp = item.employee;

    return (
        <Card data-payslip-card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base">
                    {emp.first_name} {emp.last_name}
                    <span className="ml-2 text-xs font-normal text-muted-foreground capitalize">
                        · {emp.position}
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                <InfoRow label="Employee #" value={emp.employee_number} />
                <InfoRow
                    label="Daily Rate"
                    value={formatCurrency(item.daily_rate)}
                />
                <InfoRow
                    label="Monthly Salary"
                    value={`${formatCurrency(item.daily_rate * 26)} (daily × 26)`}
                />
                <InfoRow
                    label="SSS Bracket"
                    value={item.sss_bracket ? `#${item.sss_bracket}` : '—'}
                />
                <InfoRow label="SSS" value={emp.sss_number || '—'} mono />
                <InfoRow
                    label="PhilHealth"
                    value={emp.philhealth_number || '—'}
                    mono
                />
                <InfoRow
                    label="Pag-IBIG"
                    value={emp.pagibig_number || '—'}
                    mono
                />
                <InfoRow label="TIN" value={emp.tin_number || '—'} mono />
            </CardContent>
        </Card>
    );
}

function InfoRow({
    label,
    value,
    mono,
}: {
    label: string;
    value: string;
    mono?: boolean;
}) {
    return (
        <div className="flex items-baseline gap-2">
            <span className="text-muted-foreground">{label}:</span>
            <span className={`font-medium ${mono ? 'font-mono' : ''}`}>
                {value}
            </span>
        </div>
    );
}

function AttendanceStrip({
    item,
    basicPayDays,
}: {
    item: Item;
    basicPayDays: number;
}) {
    const cells: Array<{ label: string; value: string }> = [
        // `total_regular_days` deliberately excludes holiday and rest days
        // (see PayrollPeriodService::generateItemForEmployee), so it under-
        // counts days actually worked. Kept here as "Regular" for reference;
        // "Days Paid" (basicPayDays) is the true count of base-pay days.
        { label: 'Regular', value: `${item.total_regular_days}d` },
        { label: 'Days Paid', value: `${basicPayDays}d` },
        ...(item.total_late_minutes > 0
            ? [{ label: 'Late', value: `${item.total_late_minutes}m` }]
            : []),
        ...(item.total_undertime_minutes > 0
            ? [
                  {
                      label: 'Undertime',
                      value: `${item.total_undertime_minutes}m`,
                  },
              ]
            : []),
        ...(item.total_overtime_minutes > 0
            ? [
                  {
                      label: 'Overtime',
                      value: `${(item.total_overtime_minutes / 60).toFixed(1)}h`,
                  },
              ]
            : []),
        ...(item.absent_days > 0
            ? [{ label: 'Absent', value: `${item.absent_days}d` }]
            : []),
        ...(item.holiday_pay_days > 0
            ? [{ label: 'Holidays', value: `${item.holiday_pay_days}d` }]
            : []),
        ...(item.leave_paid_days > 0
            ? [{ label: 'Paid Leave', value: `${item.leave_paid_days}d` }]
            : []),
    ];

    return (
        <Card data-payslip-card>
            <CardContent className="flex flex-wrap items-center gap-x-6 gap-y-2 py-4 text-xs">
                {cells.map((c) => (
                    <div key={c.label} className="flex flex-col">
                        <span className="text-[10px] tracking-wide text-muted-foreground uppercase">
                            {c.label}
                        </span>
                        <span className="font-mono text-sm font-medium">
                            {c.value}
                        </span>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function EarningsCard({
    item,
    basicPayDays,
}: {
    item: Item;
    basicPayDays: number;
}) {
    const leavePay =
        (Number(item.daily_rate) || 0) * (item.leave_paid_days || 0);
    // gross_pay (= SUM of daily_wage) already contains base pay, overtime,
    // the holiday premium and incentive, net of late/undertime/fine. Those
    // penalties render as their own negative lines below, so add them back
    // here or they are charged twice. Back-solving from gross keeps the line
    // items reconciling to GROSS PAY on holiday and rest-day weeks, where
    // total_regular_days deliberately excludes the day (see
    // PayrollPeriodService::generateItemForEmployee).
    const basicPay =
        (Number(item.gross_pay) || 0) -
        (Number(item.overtime_pay) || 0) -
        (Number(item.holiday_pay) || 0) -
        (Number(item.incentive) || 0) +
        (Number(item.late_deduction) || 0) +
        (Number(item.undertime_deduction) || 0) +
        (Number(item.fine_deduction) || 0) -
        leavePay;
    const grossWithPerks =
        (Number(item.gross_pay) || 0) + (Number(item.deminimis_earnings) || 0);

    return (
        <Card data-payslip-card>
            <CardHeader className="pb-2">
                <CardTitle className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Earnings
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1.5 text-sm">
                {basicPayDays > 0 && (
                    <LineItem
                        label={`Basic Pay (${basicPayDays}d)`}
                        value={basicPay}
                    />
                )}
                {item.total_overtime_minutes > 0 && (
                    <LineItem
                        label={`Overtime (${(item.total_overtime_minutes / 60).toFixed(1)}h)`}
                        value={item.overtime_pay}
                    />
                )}
                {item.holiday_pay > 0 && (
                    <LineItem
                        label={`Holiday Pay (${item.holiday_pay_days}d)`}
                        value={item.holiday_pay}
                    />
                )}
                {item.leave_paid_days > 0 && (
                    <LineItem
                        label={`Paid Leave (${item.leave_paid_days}d)`}
                        value={leavePay}
                    />
                )}
                {item.incentive > 0 && (
                    <LineItem label="Incentive" value={item.incentive} />
                )}
                {(item.late_deduction > 0 ||
                    item.undertime_deduction > 0 ||
                    item.fine_deduction > 0) && (
                    <div className="border-t pt-1.5 text-[10px] tracking-wide text-muted-foreground uppercase">
                        Less: attendance penalties
                    </div>
                )}
                {item.late_deduction > 0 && (
                    <LineItem
                        label={`Late (${item.total_late_minutes}m)`}
                        value={-item.late_deduction}
                        muted
                    />
                )}
                {item.undertime_deduction > 0 && (
                    <LineItem
                        label={`Undertime (${item.total_undertime_minutes}m)`}
                        value={-item.undertime_deduction}
                        muted
                    />
                )}
                {item.fine_deduction > 0 && (
                    <LineItem
                        label="Fines"
                        value={-item.fine_deduction}
                        muted
                    />
                )}
                {item.deminimis_earnings > 0 && (
                    <LineItem
                        label="De Minimis Perks"
                        value={item.deminimis_earnings}
                    />
                )}
                <div className="mt-1 border-t pt-2">
                    <LineItem label="GROSS PAY" value={grossWithPerks} bold />
                </div>
            </CardContent>
        </Card>
    );
}

function DeductionsCard({
    item,
    showEmployer,
}: {
    item: Item;
    showEmployer: boolean;
}) {
    const statutoryAndCa =
        (Number(item.sss_deduction) || 0) +
        (Number(item.philhealth_deduction) || 0) +
        (Number(item.pagibig_deduction) || 0) +
        (Number(item.ca_deduction) || 0);

    const caDeductions = item.cash_advance_deductions ?? [];
    const outstandingCa = caDeductions.reduce(
        (sum, d) => sum + (Number(d.cash_advance?.remaining_balance) || 0),
        0,
    );

    const employerTotal =
        (Number(item.sss_employer) || 0) +
        (Number(item.philhealth_employer) || 0) +
        (Number(item.pagibig_employer) || 0);

    return (
        <Card data-payslip-card>
            <CardHeader className="pb-2">
                <CardTitle className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Deductions
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1.5 text-sm">
                {item.sss_deduction > 0 && (
                    <LineItem label="SSS" value={-item.sss_deduction} red />
                )}
                {item.philhealth_deduction > 0 && (
                    <LineItem
                        label="PhilHealth"
                        value={-item.philhealth_deduction}
                        red
                    />
                )}
                {item.pagibig_deduction > 0 && (
                    <LineItem
                        label="Pag-IBIG"
                        value={-item.pagibig_deduction}
                        red
                    />
                )}
                {caDeductions.length > 0
                    ? caDeductions.map((d) => (
                          <LineItem
                              key={d.id}
                              label={
                                  d.cash_advance?.reason
                                      ? `Cash Advance — ${d.cash_advance.reason}`
                                      : 'Cash Advance'
                              }
                              value={-d.amount}
                              red
                          />
                      ))
                    : item.ca_deduction > 0 && (
                          <LineItem
                              label="Cash Advance"
                              value={-item.ca_deduction}
                              red
                          />
                      )}
                {outstandingCa > 0 && (
                    <p className="text-[10px] tracking-wide text-muted-foreground">
                        Outstanding cash advance balance after this period:{' '}
                        <span className="font-mono font-medium">
                            {formatCurrency(outstandingCa)}
                        </span>
                    </p>
                )}
                {statutoryAndCa === 0 && (
                    <p className="text-xs text-muted-foreground">
                        No statutory or cash-advance deductions.
                    </p>
                )}
                <div className="mt-1 border-t pt-2">
                    <LineItem
                        label="TOTAL DEDUCTIONS"
                        value={-statutoryAndCa}
                        bold
                        red
                    />
                </div>

                {showEmployer && employerTotal > 0 && (
                    <div className="mt-3 rounded-md border bg-muted/40 p-2 text-xs">
                        <div className="mb-1 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                            Employer Contributions
                        </div>
                        <div className="space-y-1">
                            {(item.sss_employer ?? 0) > 0 && (
                                <LineItem
                                    label="SSS (employer)"
                                    value={item.sss_employer ?? 0}
                                    muted
                                />
                            )}
                            {(item.philhealth_employer ?? 0) > 0 && (
                                <LineItem
                                    label="PhilHealth (employer)"
                                    value={item.philhealth_employer ?? 0}
                                    muted
                                />
                            )}
                            {(item.pagibig_employer ?? 0) > 0 && (
                                <LineItem
                                    label="Pag-IBIG (employer)"
                                    value={item.pagibig_employer ?? 0}
                                    muted
                                />
                            )}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function NetPayCard({ amount }: { amount: number }) {
    return (
        <Card
            data-payslip-card
            data-payslip-net
            className="border-emerald-200 bg-emerald-50"
        >
            <CardContent className="space-y-1 py-5 text-center">
                <div className="text-[10px] font-semibold tracking-widest text-emerald-700 uppercase">
                    Take-Home Net Pay
                </div>
                <div className="font-mono text-4xl font-bold text-emerald-800 tabular-nums">
                    {formatCurrency(amount)}
                </div>
                <div className="text-[10px] tracking-wide text-emerald-700/80 uppercase">
                    {numberToWords(amount)}
                </div>
            </CardContent>
        </Card>
    );
}

function SignatureFooter({ period }: { period: Period }) {
    const approver = period.approved_by_user;

    return (
        <Card data-payslip-card data-payslip-signatures>
            <CardContent className="space-y-4 py-5 text-xs text-muted-foreground">
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <SignatureBlock label="Received by Employee" />
                    <SignatureBlock label="Prepared by" />
                    <SignatureBlock
                        label="Approved by"
                        line={
                            approver
                                ? `${approver.first_name} ${approver.last_name}`
                                : undefined
                        }
                    />
                </div>
                <div className="border-t pt-2 text-center text-[10px]">
                    Generated{' '}
                    {new Date().toLocaleDateString('en-PH', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                    })}
                    {period.approved_at &&
                        ` · Approved ${formatDate(period.approved_at)}`}{' '}
                    · Status: {period.status.toUpperCase()}
                </div>
            </CardContent>
        </Card>
    );
}

function SignatureBlock({ label, line }: { label: string; line?: string }) {
    return (
        <div className="space-y-1">
            <div data-signature-line className="h-8 border-b border-border" />
            <div className="text-[10px] tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            {line && (
                <div className="text-xs font-medium text-foreground">
                    {line}
                </div>
            )}
        </div>
    );
}

function LineItem({
    label,
    value,
    bold,
    red,
    muted,
}: {
    label: string;
    value: number;
    bold?: boolean;
    red?: boolean;
    muted?: boolean;
}) {
    return (
        <div
            className={`flex items-baseline justify-between ${bold ? 'font-semibold' : ''}`}
        >
            <span className={muted ? 'text-muted-foreground' : ''}>
                {label}
            </span>
            <span
                className={`font-mono tabular-nums ${
                    red ? 'text-red-600' : muted ? 'text-muted-foreground' : ''
                }`}
            >
                {formatCurrency(value)}
            </span>
        </div>
    );
}
