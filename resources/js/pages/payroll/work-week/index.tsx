import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { TotalsFooter } from './components/totals-footer';
import { WorkWeekFilters } from './components/work-week-filters';
import { WorkWeekRow } from './components/work-week-row';
import type {
    BranchOption,
    DayCellData,
    EmployeeRowTotals,
    FooterTotals,
} from './types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Work Week Table', href: '/payroll/work-week' },
];

const dayLabels = ['Sat', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

type EmployeeListEntry = {
    id: number;
};

type Props = {
    branch: { id: number; name: string };
    branches: BranchOption[];
    isSuperAdmin: boolean;
    startDate: string;
    endDate: string;
    dayColumns: string[];
    employees: {
        data: EmployeeListEntry[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    cells: Record<string, DayCellData>;
    rowTotals: Record<number, EmployeeRowTotals>;
    footerTotals: FooterTotals;
};

export default function WorkWeekTable({
    branch,
    branches,
    isSuperAdmin,
    startDate,
    endDate,
    dayColumns,
    employees,
    cells,
    rowTotals,
    footerTotals,
}: Props) {
    const navigate = (params: Record<string, string | number | undefined>) => {
        router.get(route('payroll.work-week.index'), {
            branch_id: branch.id,
            start_date: startDate,
            end_date: endDate,
            ...params,
        });
    };

    const goToPage = (url: string | null) => {
        if (!url) {
            return;
        }

        const parsed = new URL(url, window.location.origin);
        navigate({ page: parsed.searchParams.get('page') ?? undefined });
    };

    const handlePrint = () => {
        window.open(
            route('payroll.work-week.print', {
                branch_id: branch.id,
                start_date: startDate,
                end_date: endDate,
            }),
            '_blank',
        );
    };

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Work Week Payroll Table" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Work Week Payroll Table
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {branch.name} · {startDate} to {endDate} — live
                            preview, not a generated payroll period
                        </p>
                    </div>
                    <WorkWeekFilters
                        isSuperAdmin={isSuperAdmin}
                        branches={branches}
                        branchId={branch.id}
                        startDate={startDate}
                        endDate={endDate}
                        onBranchChange={(branchId) =>
                            navigate({ branch_id: branchId })
                        }
                        onDateRangeChange={(start, end) =>
                            navigate({ start_date: start, end_date: end })
                        }
                        onPrint={handlePrint}
                    />
                </div>

                <div className="overflow-x-auto rounded-md border border-sidebar-border bg-sidebar">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="sticky left-0 bg-muted px-3 py-2 text-left font-medium">
                                    Employee
                                </th>
                                <th className="px-2 py-2 text-right font-medium">
                                    Daily Rate
                                </th>
                                {dayColumns.map((date, i) => (
                                    <th
                                        key={date}
                                        className="px-2 py-2 text-center font-medium"
                                    >
                                        <div>{dayLabels[i]}</div>
                                        <div className="text-xs font-normal text-muted-foreground">
                                            {date.substring(5)}
                                        </div>
                                    </th>
                                ))}
                                <th className="px-2 py-2 text-right font-medium">
                                    Total Fines
                                </th>
                                <th className="px-2 py-2 text-right font-medium">
                                    Late (mins)
                                </th>
                                <th className="px-2 py-2 text-right font-medium">
                                    Total OT
                                </th>
                                <th className="px-2 py-2 text-center font-medium">
                                    Holidays
                                </th>
                                <th className="px-2 py-2 text-right font-medium">
                                    Cash Advance
                                </th>
                                <th className="px-2 py-2 text-right font-medium">
                                    SSS
                                </th>
                                <th className="px-2 py-2 text-right font-medium">
                                    PhilHealth
                                </th>
                                <th className="px-2 py-2 text-right font-medium">
                                    Pag-IBIG
                                </th>
                                <th className="px-2 py-2 text-right font-medium">
                                    Total Deductions
                                </th>
                                <th className="px-2 py-2 text-right font-medium">
                                    Gross Salary
                                </th>
                                <th className="px-2 py-2 text-right font-medium">
                                    Net Salary
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {employees.data.map((emp) => {
                                const totals = rowTotals[emp.id];

                                if (!totals) {
                                    return null;
                                }

                                return (
                                    <WorkWeekRow
                                        key={emp.id}
                                        totals={totals}
                                        dayColumns={dayColumns}
                                        cells={cells}
                                    />
                                );
                            })}
                        </tbody>
                        <TotalsFooter
                            totals={footerTotals}
                            dayColumnCount={dayColumns.length}
                        />
                    </table>
                </div>

                {(employees.prev_page_url || employees.next_page_url) && (
                    <div className="flex items-center justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!employees.prev_page_url}
                            onClick={() => goToPage(employees.prev_page_url)}
                        >
                            <ArrowLeft className="mr-1 h-3.5 w-3.5" /> Prev
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!employees.next_page_url}
                            onClick={() => goToPage(employees.next_page_url)}
                        >
                            Next <ArrowRight className="ml-1 h-3.5 w-3.5" />
                        </Button>
                    </div>
                )}
            </div>
        </PayrollLayout>
    );
}
