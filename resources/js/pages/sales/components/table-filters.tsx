import { Search, X } from 'lucide-react';
import React from 'react';
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
    searchTerm: string;
    setSearchTerm: (value: string) => void;
    mode: string;
    filters: {
        date?: string;
        status?: string;
        branch_id?: string;
    };
    handleFilterChange: (value: string, key: string) => void;
    clearFilters: () => void;
    branches: Branch[]
}

const SalesTableFilters = ({
                               searchTerm,
                               setSearchTerm,
                               mode,
                               filters,
                               handleFilterChange,
                               clearFilters,
    branches
                           }: SalesTableFiltersProps) => {

    // Map mode to HTML input types
    return (
        <div className="flex flex-wrap items-end gap-3 mb-6 p-4 rounded-lg bg-slate-50/50">

            {/* 3. The New Search Input */}
            <div className="space-y-1.5 flex-1 min-w-[250px]">
                <label className="text-xs font-semibold uppercase text-muted-foreground ml-1">
                    Search
                </label>
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        placeholder="Search invoice or guest..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="pl-9 bg-white"
                    />
                </div>
            </div>

            {/* Mode Selection */}
            <div className="space-y-1.5">
                <label className="text-xs font-semibold uppercase text-muted-foreground ml-1">
                    Frequency
                </label>
                <Select value={mode} onValueChange={(v) => handleFilterChange(v, 'mode')}>
                    <SelectTrigger className="w-[140px] bg-white">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="daily">Daily</SelectItem>
                        <SelectItem value="weekly">Weekly</SelectItem>
                        <SelectItem value="monthly">Monthly</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            {/* Date Selection - Placed directly beside */}
            <div className="space-y-1.5">
                <label className="text-xs font-semibold uppercase text-muted-foreground ml-1">
                    Select {mode}
                </label>
                <div className="flex items-center gap-2">
                    {mode === "daily" && (
                        <Input
                            type="date"
                            value={filters.date || ""}
                            onChange={(e) => handleFilterChange(e.target.value, 'date')}
                            className="w-[180px] bg-white"
                        />
                    )}
                    {mode === "weekly" && (
                        <Input
                            type="week"
                            value={filters.date || ""}
                            onChange={(e) => handleFilterChange(e.target.value, 'date')}
                            className="w-[200px] bg-white"
                        />
                    )}
                    {mode === "monthly" && (
                        <Input
                            type="month"
                            value={filters.date || ""}
                            onChange={(e) => handleFilterChange(e.target.value, 'date')}
                            className="w-[180px] bg-white"
                        />
                    )}
                </div>
            </div>

            {/* Status Filter */}
            <div className="space-y-1.5">
                <label className="text-xs font-semibold uppercase text-muted-foreground ml-1">
                    Status
                </label>
                <Select
                    value={filters.status || "all"}
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

            {/* Status Filter */}
            <div className="space-y-1.5">
                <label className="text-xs font-semibold uppercase text-muted-foreground ml-1">
                    Branch
                </label>
                <Select
                    value={filters.branch_id || "all"}
                    onValueChange={(v) => handleFilterChange(v, 'branch_id')}
                >
                    <SelectTrigger className="w-[140px] bg-white text-sm">
                        <SelectValue placeholder="All Branch" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Branch</SelectItem>
                        {branches.map((branch) => (
                            <SelectItem value={String(branch.id)}>{branch.name}</SelectItem>
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
                <X className="h-4 w-4 mr-1" />
                Clear
            </Button>
        </div>
    );
};

export default SalesTableFilters;
