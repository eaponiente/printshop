import { X } from 'lucide-react';
import React from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Branch } from '@/types/branches';

interface ReportFiltersProps {
    mode: string;
    filters: {
        date?: string;
        branch_id?: string;
        mode?: string;
    };
    handleFilterChange: (value: string, type: 'mode' | 'date' | 'branch_id') => void;
    clearFilters: () => void;
    branches: Branch[];
}

const ReportFilters = React.memo(({
    mode,
    filters,
    handleFilterChange,
    clearFilters,
    branches,
}: ReportFiltersProps) => {
    return (
        <div className="flex flex-wrap items-end gap-3 rounded-lg bg-slate-50/50 p-4">
            <div className="space-y-1.5">
                <label className="ml-1 text-xs font-semibold text-muted-foreground uppercase">
                    Frequency
                </label>
                <Select
                    value={mode}
                    onValueChange={(v) => handleFilterChange(v, 'mode')}
                >
                    <SelectTrigger className="w-[140px] bg-white">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="daily">Daily</SelectItem>
                        <SelectItem value="weekly">Weekly</SelectItem>
                        <SelectItem value="monthly">Monthly</SelectItem>
                        <SelectItem value="yearly">Yearly</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-1.5">
                <label className="ml-1 text-xs font-semibold text-muted-foreground uppercase">
                    Select {mode}
                </label>
                <div className="flex items-center gap-2">
                    {mode === 'daily' && (
                        <Input
                            type="date"
                            value={filters.date || ''}
                            onChange={(e) => handleFilterChange(e.target.value, 'date')}
                            className="w-[180px] bg-white"
                        />
                    )}
                    {mode === 'weekly' && (
                        <Input
                            type="week"
                            value={filters.date || ''}
                            onChange={(e) => handleFilterChange(e.target.value, 'date')}
                            className="w-[200px] bg-white"
                        />
                    )}
                    {mode === 'monthly' && (
                        <Input
                            type="month"
                            value={filters.date || ''}
                            onChange={(e) => handleFilterChange(e.target.value, 'date')}
                            className="w-[180px] bg-white"
                        />
                    )}
                    {mode === 'yearly' && (
                        <select
                            value={filters.date ? filters.date.substring(0, 4) : new Date().getFullYear()}
                            onChange={(e) => handleFilterChange(e.target.value, 'date')}
                            className="h-10 w-[180px] rounded-md border bg-white px-3 py-2 shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                        >
                            {Array.from({ length: 6 }, (_, i) => {
                                const year = new Date().getFullYear() - i;
                                return (
                                    <option key={year} value={String(year)}>
                                        {year}
                                    </option>
                                );
                            })}
                        </select>
                    )}
                </div>
            </div>

            <div className="space-y-1.5">
                <label className="ml-1 text-xs font-semibold text-muted-foreground uppercase">
                    Branch
                </label>
                <Select
                    value={filters.branch_id || 'all'}
                    onValueChange={(v) => handleFilterChange(v, 'branch_id')}
                >
                    <SelectTrigger className="w-[140px] bg-white text-sm">
                        <SelectValue placeholder="All Branch" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Branch</SelectItem>
                        {branches.map((branch) => (
                            <SelectItem key={branch.id} value={String(branch.id)}>
                                {branch.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <Button
                variant="ghost"
                size="sm"
                onClick={clearFilters}
                className="h-10 px-2 text-muted-foreground hover:text-destructive"
            >
                <X className="mr-1 h-4 w-4" />
                Clear
            </Button>
        </div>
    );
});

ReportFilters.displayName = 'ReportFilters';

export default ReportFilters;
