import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, Pencil, Search, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import EditSewedItemDialog from '@/pages/payroll/sewed-items/components/edit-sewed-item-dialog';
import PayslipDialog from '@/pages/payroll/sewed-items/components/payslip-dialog';
import type { BreadcrumbItem } from '@/types';
import { toManilaTime } from '@/utils/dateHelper';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sewed Items', href: '/payroll/sewed-items' },
];

type TagPivot = {
    id: number;
    name: string;
    color: string;
    price_per_piece?: string;
    pivot: { quantity: number; price_per_piece?: string | null };
};

type SewedItem = {
    id: number;
    quantity: number;
    amount: number;
    notes: string | null;
    sewed_date: string;
    completed_at: string | null;
    tags: TagPivot[];
    sublimation: {
        id: number;
        description: string;
        quantity: number;
        due_at: string;
        status: string;
        user: { id: number; first_name: string; last_name: string };
        tags: { id: number; name: string }[];
    };
    branch: { id: number; name: string };
    user: { id: number; first_name: string; last_name: string };
};

type Filters = {
    date_from?: string;
    date_to?: string;
    branch_id?: string;
    user_id?: string;
    include_completed?: boolean;
};

type Props = {
    sewedItems: {
        data: SewedItem[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: Filters;
    branches: { id: number; name: string }[];
    staff: { id: number; first_name: string; last_name: string }[];
};

export default function SewedItemsIndex({
    sewedItems,
    filters,
    branches,
    staff,
}: Props) {
    const { auth } = usePage().props as any;
    const flash = (usePage().props as any).flash;
    const isSuperAdmin = auth?.user?.role === 'superadmin';
    const isAdmin = auth?.user?.role === 'admin';
    const canFilter = isSuperAdmin || isAdmin;

    const canEditSewedItems = !!auth?.user?.employee?.can_edit_sewed_items;

    const userCanEdit = (item: SewedItem) => {
        if (isSuperAdmin) {
            return !item.completed_at;
        }

        if (isAdmin || canEditSewedItems) {
            return (
                !item.completed_at && item.branch?.id === +auth?.user?.branch_id
            );
        }

        return !item.completed_at && item.user?.id === auth?.user?.id;
    };

    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [branchId, setBranchId] = useState(filters.branch_id ?? '');
    const [userId, setUserId] = useState(filters.user_id ?? '');
    const [includeCompleted, setIncludeCompleted] = useState(
        filters.include_completed ?? false,
    );
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [selectedItem, setSelectedItem] = useState<SewedItem | null>(null);
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [payslipDialogOpen, setPayslipDialogOpen] = useState(false);
    const [payslipItems, setPayslipItems] = useState<SewedItem[]>([]);

    const toggleSelect = (id: number) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const toggleSelectAll = () => {
        const selectable = sewedItems.data.filter((i) => !i.completed_at);

        if (selectedIds.size === selectable.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(selectable.map((i) => i.id)));
        }
    };

    const generatePayslip = () => {
        const items = sewedItems.data.filter((i) => selectedIds.has(i.id));
        const ids = items.map((i) => i.id);

        router.post(
            '/payroll/sewed-items/payslip',
            { sewed_item_ids: ids },
            {
                onSuccess: () => {
                    setPayslipItems(items);
                    setPayslipDialogOpen(true);
                },
                onError: () => toast.error('Failed to generate payslip.'),
            },
        );
    };

    const openEditDialog = (item: SewedItem) => {
        setSelectedItem(item);
        setEditDialogOpen(true);
    };

    const applyFilters = () => {
        const params: Record<string, string> = {};

        if (dateFrom) {
            params.date_from = dateFrom;
        }

        if (dateTo) {
            params.date_to = dateTo;
        }

        if (canFilter && branchId) {
            params.branch_id = branchId;
        }

        if (isSuperAdmin && userId) {
            params.user_id = userId;
        }

        if (includeCompleted) {
            params.include_completed = '1';
        }

        router.get('/payroll/sewed-items', params, {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setDateFrom('');
        setDateTo('');
        setBranchId('');
        setUserId('');
        setIncludeCompleted(false);
        router.get(
            '/payroll/sewed-items',
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Sewed Items" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Sewed Items</h1>
                </div>

                <div className="flex flex-wrap items-end gap-2">
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">
                            Date From
                        </label>
                        <Input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="h-8 w-36"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">
                            Date To
                        </label>
                        <Input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="h-8 w-36"
                        />
                    </div>
                    {canFilter && (
                        <div className="flex flex-col gap-1">
                            <label className="text-xs text-muted-foreground">
                                Branch
                            </label>
                            <Select
                                value={branchId}
                                onValueChange={setBranchId}
                            >
                                <SelectTrigger className="h-8 w-40">
                                    <SelectValue placeholder="All branches" />
                                </SelectTrigger>
                                <SelectContent>
                                    {branches.map((b) => (
                                        <SelectItem
                                            key={b.id}
                                            value={String(b.id)}
                                        >
                                            {b.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                    {isSuperAdmin && (
                        <div className="flex flex-col gap-1">
                            <label className="text-xs text-muted-foreground">
                                Staff
                            </label>
                            <Select value={userId} onValueChange={setUserId}>
                                <SelectTrigger className="h-8 w-44">
                                    <SelectValue placeholder="All staff" />
                                </SelectTrigger>
                                <SelectContent>
                                    {staff.map((s) => (
                                        <SelectItem
                                            key={s.id}
                                            value={String(s.id)}
                                        >
                                            {s.first_name} {s.last_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                    <div className="flex h-8 items-center space-x-2 px-2">
                        <Checkbox
                            id="include_completed"
                            checked={includeCompleted}
                            onCheckedChange={(checked) => {
                                setIncludeCompleted(checked === true);
                            }}
                        />
                        <label
                            htmlFor="include_completed"
                            className="cursor-pointer text-sm leading-none font-medium text-muted-foreground transition-colors select-none hover:text-foreground"
                        >
                            Include Completed
                        </label>
                    </div>
                    <Button size="sm" onClick={applyFilters} className="h-8">
                        <Search className="mr-1 h-3.5 w-3.5" />
                        Filter
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={resetFilters}
                        className="h-8"
                    >
                        <X className="mr-1 h-3.5 w-3.5" />
                        Reset
                    </Button>
                </div>

                {selectedIds.size > 0 && (
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            {selectedIds.size} selected
                        </span>
                        <Button size="sm" onClick={generatePayslip}>
                            <FileText className="mr-1.5 h-4 w-4" />
                            Generate Payslip
                        </Button>
                    </div>
                )}

                <div className="overflow-x-auto rounded-md border bg-sidebar">
                    <table className="min-w-full table-fixed text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="w-10 px-3 py-2">
                                    <Checkbox
                                        checked={
                                            sewedItems.data.filter(
                                                (i) => !i.completed_at,
                                            ).length > 0 &&
                                            selectedIds.size ===
                                            sewedItems.data.filter(
                                                (i) => !i.completed_at,
                                            ).length
                                        }
                                        onCheckedChange={toggleSelectAll}
                                    />
                                </th>
                                <th className="w-[160px] px-3 py-2 text-left font-medium">
                                    Sublimation
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Categories
                                </th>
                                <th className="w-[120px] px-3 py-2 text-right font-medium">
                                    Amount
                                </th>
                                <th className="w-[100px] px-3 py-2 text-left font-medium">
                                    Sewed Date
                                </th>
                                <th className="w-[90px] px-3 py-2 text-left font-medium">
                                    Branch
                                </th>
                                <th className="w-[110px] px-3 py-2 text-left font-medium">
                                    Created By
                                </th>
                                <th className="w-[160px] px-3 py-2 text-left font-medium">
                                    Notes
                                </th>
                                <th className="w-[90px] px-3 py-2 text-center font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {sewedItems.data.map((item) => (
                                <tr
                                    key={item.id}
                                    className={`border-b ${item.completed_at ? 'line-through opacity-60' : ''}`}
                                >
                                    <td className="w-10 px-3 py-2">
                                        {!item.completed_at && (
                                            <Checkbox
                                                checked={selectedIds.has(
                                                    item.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleSelect(item.id)
                                                }
                                            />
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Popover>
                                            <PopoverTrigger className="cursor-pointer text-left hover:text-primary hover:underline">
                                                {item.sublimation
                                                    ?.description ?? '—'}
                                            </PopoverTrigger>
                                            <PopoverContent
                                                className="w-64 p-3"
                                                align="start"
                                                sideOffset={4}
                                            >
                                                <div className="space-y-2">
                                                    <h4 className="text-xs font-medium text-muted-foreground uppercase">
                                                        Sublimation Details
                                                    </h4>
                                                    <div className="space-y-1.5 text-sm">
                                                        <div className="flex justify-between gap-2">
                                                            <span className="shrink-0 text-muted-foreground">
                                                                Description
                                                            </span>
                                                            <span className="truncate text-right">
                                                                {
                                                                    item
                                                                        .sublimation
                                                                        ?.description
                                                                }
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between gap-2">
                                                            <span className="shrink-0 text-muted-foreground">
                                                                Quantity
                                                            </span>
                                                            <span className="text-right">
                                                                {
                                                                    item
                                                                        .sublimation
                                                                        ?.quantity
                                                                }
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between gap-2">
                                                            <span className="shrink-0 text-muted-foreground">
                                                                Due Date
                                                            </span>
                                                            <span className="text-right">
                                                                {toManilaTime(
                                                                    item
                                                                        .sublimation
                                                                        ?.due_at,
                                                                    'MMM D, YYYY',
                                                                )}
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between gap-2">
                                                            <span className="shrink-0 text-muted-foreground">
                                                                Assigned
                                                            </span>
                                                            <span className="truncate text-right">
                                                                {
                                                                    item
                                                                        .sublimation
                                                                        ?.user
                                                                        ?.first_name
                                                                }{' '}
                                                                {
                                                                    item
                                                                        .sublimation
                                                                        ?.user
                                                                        ?.last_name
                                                                }
                                                            </span>
                                                        </div>
                                                        <div className="border-t pt-1.5">
                                                            <p className="mb-1.5 text-xs text-muted-foreground">
                                                                Sewed Breakdown
                                                            </p>
                                                            {(
                                                                item.tags ?? []
                                                            ).map((tag) => (
                                                                <div
                                                                    key={tag.id}
                                                                    className="flex items-center justify-between text-xs"
                                                                >
                                                                    <span className="flex items-center gap-1">
                                                                        <span
                                                                            className="inline-block h-2.5 w-2.5 rounded-full"
                                                                            style={{
                                                                                backgroundColor:
                                                                                    tag.color,
                                                                            }}
                                                                        />
                                                                        {
                                                                            tag.name
                                                                        }
                                                                    </span>
                                                                    <span className="font-mono">
                                                                        {
                                                                            tag
                                                                                .pivot
                                                                                .quantity
                                                                        }
                                                                    </span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                    <div className="border-t pt-1.5">
                                                        <Link
                                                            href={route(
                                                                'sublimations.show',
                                                                item.sublimation
                                                                    ?.id,
                                                            )}
                                                            className="text-xs text-primary hover:underline"
                                                        >
                                                            View Sublimation
                                                        </Link>
                                                    </div>
                                                </div>
                                            </PopoverContent>
                                        </Popover>
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex flex-wrap gap-1">
                                            {(item.tags ?? []).map((tag) => (
                                                <span
                                                    key={tag.id}
                                                    className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-sm"
                                                    style={{
                                                        backgroundColor:
                                                            tag.color + '20',
                                                    }}
                                                >
                                                    {tag.name}
                                                    <span className="font-medium">
                                                        ({tag.pivot.quantity})
                                                    </span>
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2 text-right font-mono">
                                        {formatCurrency(item.amount)}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {item.sewed_date}
                                    </td>
                                    <td className="truncate px-3 py-2 text-muted-foreground">
                                        {item.branch?.name ?? '—'}
                                    </td>
                                    <td className="truncate px-3 py-2 text-muted-foreground">
                                        {item.user?.first_name}{' '}
                                        {item.user?.last_name}
                                    </td>
                                    <td className="truncate px-3 py-2 text-muted-foreground">
                                        {item.notes || '—'}
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        <div className="flex items-center justify-center gap-1">
                                            {userCanEdit(item) && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        openEditDialog(item)
                                                    }
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            )}
                                            {(isSuperAdmin || isAdmin) && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => {
                                                        if (!confirm('Delete this sewed item? This cannot be undone.')) return;
                                                        router.delete(route('payroll.sewed-items.destroy', item.id), { preserveScroll: true });
                                                    }}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {sewedItems.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No sewed items yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {(sewedItems.prev_page_url || sewedItems.next_page_url) && (
                    <div className="flex justify-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!sewedItems.prev_page_url}
                            onClick={() =>
                                router.get(sewedItems.prev_page_url!)
                            }
                        >
                            Prev
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!sewedItems.next_page_url}
                            onClick={() =>
                                router.get(sewedItems.next_page_url!)
                            }
                        >
                            Next
                        </Button>
                    </div>
                )}
            </div>

            {editDialogOpen && selectedItem && (
                <EditSewedItemDialog
                    open={editDialogOpen}
                    setOpen={setEditDialogOpen}
                    item={selectedItem}
                />
            )}

            <PayslipDialog
                open={payslipDialogOpen}
                setOpen={setPayslipDialogOpen}
                items={payslipItems}
                generatedBy={auth?.user?.fullname ?? '—'}
                payslipId={flash?.payslip_id ?? null}
            />
        </PayrollLayout>
    );
}
