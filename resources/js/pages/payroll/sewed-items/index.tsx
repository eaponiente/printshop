import { Head, Link, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { Pencil } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { formatCurrency } from '@/utils/formatters';
import { toManilaTime } from '@/utils/dateHelper';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sewed Items', href: '/payroll/sewed-items' },
];

type SewedItem = {
    id: number;
    quantity: number;
    unit_price: number;
    amount: number;
    notes: string | null;
    sewed_date: string;
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

type Props = {
    sewedItems: {
        data: SewedItem[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export default function SewedItemsIndex({ sewedItems }: Props) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editQuantity, setEditQuantity] = useState('');
    const [editUnitPrice, setEditUnitPrice] = useState('');
    const [editNotes, setEditNotes] = useState('');
    const [notesError, setNotesError] = useState('');

    const startEdit = (item: SewedItem) => {
        setEditingId(item.id);
        setEditQuantity(String(item.quantity));
        setEditUnitPrice(String(item.unit_price));
        setEditNotes(item.notes ?? '');
        setNotesError('');
    };

    const cancelEdit = () => {
        setEditingId(null);
        setNotesError('');
    };

    const submitEdit = (id: number) => {
        if (!editNotes.trim()) {
            setNotesError('Notes is required.');
            return;
        }

        router.put(
            `/payroll/sewed-items/${id}`,
            {
                quantity: editQuantity,
                unit_price: editUnitPrice,
                notes: editNotes,
            },
            {
                onSuccess: () => setEditingId(null),
                onError: (err) => {
                    if (err.notes) setNotesError(err.notes);
                },
            },
        );
    };

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Sewed Items" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Sewed Items</h1>
                </div>

                <div className="overflow-x-auto rounded-md border bg-sidebar">
                    <table className="min-w-full table-fixed text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="w-[160px] px-3 py-2 text-left font-medium">
                                    Sublimation
                                </th>
                                <th className="w-[100px] px-3 py-2 text-right font-medium">
                                    Quantity
                                </th>
                                <th className="w-[120px] px-3 py-2 text-right font-medium">
                                    Unit Price
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
                                <tr key={item.id} className="border-b">
                                    <td className="px-3 py-2">
                                        <Popover>
                                            <PopoverTrigger className="cursor-pointer text-left hover:text-primary hover:underline">
                                                {item.sublimation
                                                    ?.description ?? '—'}
                                            </PopoverTrigger>
                                            <PopoverContent
                                                className="w-56 p-3"
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
                                                                Category
                                                            </span>
                                                            <span className="truncate text-right">
                                                                {item.sublimation?.tags
                                                                    ?.map(
                                                                        (t) =>
                                                                            t.name,
                                                                    )
                                                                    .join(
                                                                        ', ',
                                                                    ) || '—'}
                                                            </span>
                                                        </div>
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
                                    <td className="px-3 py-2 text-right font-mono">
                                        {editingId === item.id ? (
                                            <Input
                                                type="number"
                                                min="1"
                                                value={editQuantity}
                                                onChange={(e) =>
                                                    setEditQuantity(
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-7 w-full text-right"
                                            />
                                        ) : (
                                            item.quantity
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-right font-mono">
                                        {editingId === item.id ? (
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={editUnitPrice}
                                                onChange={(e) =>
                                                    setEditUnitPrice(
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-7 w-full text-right"
                                            />
                                        ) : (
                                            formatCurrency(item.unit_price)
                                        )}
                                    </td>
                                    <td className="truncate px-3 py-2 text-right font-mono">
                                        {editingId === item.id
                                            ? formatCurrency(
                                                  Number(editQuantity) *
                                                      Number(editUnitPrice) ||
                                                      0,
                                              )
                                            : formatCurrency(item.amount)}
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
                                        {editingId === item.id ? (
                                            <div>
                                                <Input
                                                    value={editNotes}
                                                    onChange={(e) => {
                                                        setEditNotes(
                                                            e.target.value,
                                                        );
                                                        if (notesError)
                                                            setNotesError('');
                                                    }}
                                                    className="h-7 w-full"
                                                />
                                                {notesError && (
                                                    <p className="mt-1 text-xs text-red-500">
                                                        {notesError}
                                                    </p>
                                                )}
                                            </div>
                                        ) : (
                                            item.notes || '—'
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        {editingId === item.id ? (
                                            <div className="flex justify-center gap-1">
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        submitEdit(item.id)
                                                    }
                                                >
                                                    Save
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={cancelEdit}
                                                >
                                                    Cancel
                                                </Button>
                                            </div>
                                        ) : (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => startEdit(item)}
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                        )}
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
        </PayrollLayout>
    );
}
