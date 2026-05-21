import { Search, X } from 'lucide-react';
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
import type { User } from '@/types/user';
import { debounce } from '@/utils/helpers';

interface SalesTableFiltersProps {
    mode: string;
    is_payment_view: boolean;
    filters: {
        search?: string;
        date?: string;
        status?: string;
        branch_id?: string;
        staff_id?: string;
        payment_type?: string;
    };
    handleFilterChange: (
        value: string,
        type:
            | 'mode'
            | 'date'
            | 'status'
            | 'branch_id'
            | 'staff_id'
            | 'payment_type'
            | 'search',
    ) => void;
    clearFilters: () => void;
    branches: Branch[];
    types_of_payment: { key: string; value: string }[];
    users: User[];
}

const SalesTableFilters = React.memo(
    ({
        mode,
        is_payment_view,
        filters,
        handleFilterChange,
        clearFilters,
        branches,
        types_of_payment,
        users,
    }: SalesTableFiltersProps) => {
        const [searchValue, setSearchValue] = React.useState(
            filters.search || '',
        );

        React.useEffect(() => {
            setSearchValue(filters.search || '');
        }, [filters.search]);

        const handleFilterChangeRef = React.useRef(handleFilterChange);

        React.useEffect(() => {
            handleFilterChangeRef.current = handleFilterChange;
        });

        /* eslint-disable react-hooks/refs */
        const debouncedSearch = React.useMemo(
            () =>
                debounce((val: string) => {
                    handleFilterChangeRef.current(val, 'search');
                }, 400),
            [],
        );
        /* eslint-enable react-hooks/refs */

        return (
            <div className="mb-6 flex flex-wrap items-end gap-3 rounded-lg bg-slate-50/50 p-4">
                <div className="min-w-[250px] flex-1 space-y-1.5">
                    <label className="ml-1 text-xs font-semibold text-muted-foreground uppercase">
                        Search
                    </label>
                    <div className="relative">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search customer..."
                            value={searchValue}
                            onChange={(e) => {
                                const val = e.target.value;
                                setSearchValue(val);
                                debouncedSearch(val);
                            }}
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
                        onValueChange={(v) =>
                            handleFilterChange(v, 'branch_id')
                        }
                    >
                        <SelectTrigger className="w-[140px] bg-white text-sm">
                            <SelectValue placeholder="All Branch" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Branch</SelectItem>
                            {branches.map((branch) => (
                                <SelectItem
                                    key={branch.id}
                                    value={String(branch.id)}
                                >
                                    {branch.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Staff Filter — only for superadmin when a specific branch is selected */}
                {users.length > 0 &&
                    filters.branch_id &&
                    filters.branch_id !== 'all' && (
                        <div className="space-y-1.5">
                            <label className="ml-1 text-xs font-semibold text-muted-foreground uppercase">
                                Staff
                            </label>
                            <Select
                                value={filters.staff_id || 'all'}
                                onValueChange={(v) =>
                                    handleFilterChange(
                                        v === 'all' ? '' : v,
                                        'staff_id',
                                    )
                                }
                            >
                                <SelectTrigger className="w-[160px] bg-white text-sm">
                                    <SelectValue placeholder="All Staff" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Staff
                                    </SelectItem>
                                    {users
                                        .filter(
                                            (u) =>
                                                String(u.branch_id) ===
                                                filters.branch_id,
                                        )
                                        .map((u) => (
                                            <SelectItem
                                                key={u.id}
                                                value={String(u.id)}
                                            >
                                                {u.fullname}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                {/* Payment Type Filter — only on payments tab */}
                {is_payment_view && (
                    <div className="space-y-1.5">
                        <label className="ml-1 text-xs font-semibold text-muted-foreground uppercase">
                            Payment
                        </label>
                        <Select
                            value={filters.payment_type || 'all'}
                            onValueChange={(v) =>
                                handleFilterChange(v, 'payment_type')
                            }
                        >
                            <SelectTrigger className="w-[140px] bg-white text-sm">
                                <SelectValue placeholder="All Types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Types</SelectItem>
                                {types_of_payment.map((type) => (
                                    <SelectItem key={type.key} value={type.key}>
                                        {type.value}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}

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
    },
);

SalesTableFilters.displayName = 'SalesTableFilters';

export default SalesTableFilters;
