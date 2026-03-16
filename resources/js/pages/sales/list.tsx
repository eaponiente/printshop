import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus, X } from 'lucide-react';
import { toast } from 'sonner';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { useState } from 'react';
import UserDialog from '@/pages/users/users-dialog';
import SaleDialog from '@/pages/sales/sales-dialog';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales', href: '/sales' },
];

export default function SaleIndex({ transactions, filters, branches }) {
    const openEditForm = () => {
        setIsDialogOpen(true);
    };

    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const [mode, setMode] = useState(filters.mode || "daily")

    const handleFilterChange = (value: string, type: 'mode' | 'date') => {
        const params = { ...filters };

        if (type === 'mode') {
            setMode(value);
            params.mode = value;
            // Reset date when switching modes to avoid invalid matches
            params.date = "";
        } else {
            params.date = value;
        }

        router.get(`/sales`, params, { preserveState: true, replace: true });
    }

    const clearFilters = () => {
        setMode("daily");

        router.get(`/sales`, {}, { replace: true });
    };

    const columns: ColumnDef<unknown, any>[] = [
        {
            accessorKey: 'invoice_number',
            header: 'Invoice Number',
        },
        {
            accessorKey: 'guest_name',
            header: 'Customer Name',
        },
        {
            accessorKey: 'status',
            header: 'Status',
        },
        {
            accessorKey: 'user.fullname',
            header: 'Staff',
        },
        {
            accessorKey: 'transaction_date',
            header: 'Date',
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                console.log(row);
            }
        }
    ]

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sales" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Sale Management</h1>
                        <p className="text-sm text-muted-foreground">Manage your sale.</p>
                    </div>

                    {/* Create Staff Button */}
                    <Button onClick={() => openEditForm(null)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Transaction
                    </Button>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar">

                    <div className="flex flex-wrap items-end gap-3 mb-6 p-4 rounded-lg bg-slate-50/50">
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

                                {/* Clear Button - Only show if a date is picked */}
                                {filters.date && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={clearFilters}
                                        className="h-10 px-2 text-muted-foreground hover:text-destructive"
                                    >
                                        <X className="h-4 w-4 mr-1" />
                                        Clear
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    <DataTable columns={columns} pagination={transactions} />
                </div>
            </div>

            {isDialogOpen && (
                <SaleDialog open={isDialogOpen} setOpen={setIsDialogOpen} branches={branches} />
            )}

        </AppLayout>
    );
}
