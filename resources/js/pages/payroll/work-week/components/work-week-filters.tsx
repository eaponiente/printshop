import { Printer } from 'lucide-react';
import { DateRangePicker } from '@/components/date-range-picker';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BranchOption } from '../types';

type WorkWeekFiltersProps = {
    isSuperAdmin: boolean;
    branches: BranchOption[];
    branchId: number;
    startDate: string;
    endDate: string;
    onBranchChange: (branchId: number) => void;
    onDateRangeChange: (startDate: string, endDate: string) => void;
    onPrint: () => void;
};

export function WorkWeekFilters({
    isSuperAdmin,
    branches,
    branchId,
    startDate,
    endDate,
    onBranchChange,
    onDateRangeChange,
    onPrint,
}: WorkWeekFiltersProps) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            {isSuperAdmin && branches.length > 0 && (
                <Select
                    value={String(branchId)}
                    onValueChange={(val) => onBranchChange(Number(val))}
                >
                    <SelectTrigger className="h-9 w-[180px] text-xs">
                        <SelectValue placeholder="Branch" />
                    </SelectTrigger>
                    <SelectContent>
                        {branches.map((b) => (
                            <SelectItem key={b.id} value={String(b.id)}>
                                {b.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}

            <DateRangePicker
                startDate={startDate}
                endDate={endDate}
                onChange={onDateRangeChange}
            />

            <Button variant="outline" size="sm" onClick={onPrint}>
                <Printer className="mr-1 h-4 w-4" />
                Print Payroll
            </Button>
        </div>
    );
}
