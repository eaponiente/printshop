import { Printer } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    // Native date inputs work in YYYY-MM-DD strings, so there is no Date/UTC
    // conversion to shift the day. Local state lets the user set both ends
    // before applying a single reload.
    const [from, setFrom] = useState(startDate);
    const [to, setTo] = useState(endDate);

    const isInvalid = !from || !to || to < from;
    const isUnchanged = from === startDate && to === endDate;

    return (
        <div className="flex flex-wrap items-end gap-2">
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

            <div className="flex flex-col gap-1">
                <Label htmlFor="work-week-from" className="text-xs">
                    From
                </Label>
                <Input
                    id="work-week-from"
                    type="date"
                    value={from}
                    max={to || undefined}
                    onChange={(e) => setFrom(e.target.value)}
                    className="h-9 w-[150px] text-xs"
                />
            </div>

            <div className="flex flex-col gap-1">
                <Label htmlFor="work-week-to" className="text-xs">
                    To
                </Label>
                <Input
                    id="work-week-to"
                    type="date"
                    value={to}
                    min={from || undefined}
                    onChange={(e) => setTo(e.target.value)}
                    className="h-9 w-[150px] text-xs"
                />
            </div>

            <Button
                variant="outline"
                size="sm"
                disabled={isInvalid || isUnchanged}
                onClick={() => onDateRangeChange(from, to)}
            >
                Apply
            </Button>

            <Button variant="outline" size="sm" onClick={onPrint}>
                <Printer className="mr-1 h-4 w-4" />
                Print Payroll
            </Button>
        </div>
    );
}
