import { Search, X } from 'lucide-react';
import React, { useState, useEffect } from 'react';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import type { Branch } from '@/types/branches';

interface SalesTableFiltersProps {
    mode: string;
    filters: {
        search?: string;
        date?: string;
        status?: string;
        branch_id?: string;
        payment_type?: string;
    };
    handleFilterChange: (
        value: string,
        type: 'mode' | 'date' | 'status' | 'branch_id' | 'payment_type' | 'search',
    ) => void;
    clearFilters: () => void;
    branches: Branch[];
}

const SalesTableFilters = React.memo(({
    mode,
    filters,
    handleFilterChange,
    clearFilters,
    branches
}: SalesTableFiltersProps) => {
    // 1. Local state for search to prevent re-rendering the whole page
    const [localSearch, setLocalSearch] = useState(filters.search || '');

    // 2. Debounce effect inside the filter component
    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (localSearch !== (filters.search || '')) {
                handleFilterChange(localSearch, 'search');
            }
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [localSearch, filters.search, handleFilterChange]);

    // Sync local search if filters are cleared externally
    useEffect(() => {
        if (!filters.search && localSearch) {
            setLocalSearch('');
        }
    }, [filters.search]);

    // Map mode to HTML input types
    return (
        <div className="mb-6 flex flex-wrap items-end gap-3 rounded-lg bg-slate-50/50 p-4">
            {/* 3. The New Search Input */}
            <div className="min-w-[250px] flex-1 space-y-1.5">
                <label className="ml-1 text-xs font-semibold text-muted-foreground uppercase">
                    Search
                </label>
                <div className="relative">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search invoice or guest..."
                        value={localSearch}
                        onChange={(e) => setLocalSearch(e.target.value)}
                        className="bg-white pl-9"
                    />
                </div>
            </div>

            {/* Mode Selection */}
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

            {/* Date Selection - Placed directly beside */}
            <div className="space-y-1.5">
                <label className="ml-1 text-xs font-semibold text-muted-foreground uppercase">
                    Select {mode}
                </label>
                <div className="flex items-center gap-2">
                    {mode === 'daily' && (
                        <Input
                            type="date"
                            value={filters.date || ''}
                            onChange={(e) =>
                                handleFilterChange(e.target.value, 'date')
                            }
                            className="w-[180px] bg-white"
                        />
                    )}
                    {mode === 'weekly' && (
                        <Input
                            type="week"
                            value={filters.date || ''}
                            onChange={(e) =>
                                handleFilterChange(e.target.value, 'date')
                            }
                            className="w-[200px] bg-white"
                        />
                    )}
                    {mode === 'monthly' && (
                        <Input
                            type="month"
                            value={filters.date || ''}
                            onChange={(e) =>
                                handleFilterChange(e.target.value, 'date')
                            }
                            className="w-[180px] bg-white"
                        />
                    )}
                    {mode === 'yearly' && (
                        <select
                            value={
                                filters.date
                                    ? filters.date.substring(0, 4)
                                    : new Date().getFullYear()
                            }
                            onChange={(e) =>
                                handleFilterChange(e.target.value, 'date')
                            }
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

            {/* Status Filter */}
            <div className="space-y-1.5">
                <label className="ml-1 text-xs font-semibold text-muted-foreground uppercase">
                    Status
                </label>
                <Select
                    value={filters.status || 'all'}
                    onValueChange={(v) => handleFilterChange(v, 'status')}
                >
                    <SelectTrigger className="w-[140px] bg-white text-sm">
                        <SelectValue placeholder="All Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Status</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="paid">Paid</SelectItem>
                        <SelectItem value="partial">Partial</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            {/* Branch Filter */}
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

SalesTableFilters.displayName = 'SalesTableFilters';

export default SalesTableFilters;
