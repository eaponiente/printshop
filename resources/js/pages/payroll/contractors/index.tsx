import { Head, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contractors', href: '/payroll/contractors' },
];

type Contractor = {
    id: number;
    name: string;
    branch: { id: number; name: string };
    status: string;
    active_projects_count: number;
    notes: string | null;
};

type PaginatedContractors = {
    data: Contractor[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    contractors: PaginatedContractors;
    branches: { id: number; name: string }[];
};

export default function ContractorIndex({ contractors, branches }: Props) {
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Contractors" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Contractors</h1>
                    <Button size="sm" onClick={() => setShowForm(!showForm)}>
                        {showForm ? 'Cancel' : 'Add Contractor'}
                    </Button>
                </div>

                {showForm && (
                    <form
                        className="rounded-md border bg-sidebar p-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const form = e.currentTarget as HTMLFormElement;
                            const data = new FormData(form);
                            router.post('/payroll/contractors', data, {
                                onSuccess: () => {
                                    form.reset();
                                    setShowForm(false);
                                },
                            });
                        }}
                    >
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    Name
                                </label>
                                <input
                                    name="name"
                                    required
                                    className="w-full rounded border px-2 py-1 text-sm"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    Branch
                                </label>
                                <select
                                    name="branch_id"
                                    required
                                    className="w-full rounded border px-2 py-1 text-sm"
                                >
                                    <option value="">Select...</option>
                                    {branches.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="col-span-2">
                                <label className="mb-1 block text-xs font-medium">
                                    Notes
                                </label>
                                <input
                                    name="notes"
                                    className="w-full rounded border px-2 py-1 text-sm"
                                />
                            </div>
                        </div>
                        <div className="mt-3 flex gap-2">
                            <Button type="submit" size="sm">
                                Save
                            </Button>
                        </div>
                    </form>
                )}

                <div className="overflow-x-auto rounded-md border bg-sidebar">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="px-3 py-2 text-left font-medium">
                                    Name
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Branch
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Active Projects
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {contractors.data.map((c) => (
                                <tr key={c.id} className="border-b">
                                    <td className="px-3 py-2 font-medium">
                                        <button
                                            type="button"
                                            className="text-left hover:underline"
                                            onClick={() =>
                                                router.get(
                                                    `/payroll/contractors/${c.id}/projects`,
                                                )
                                            }
                                        >
                                            {c.name}
                                        </button>
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {c.branch.name}
                                    </td>
                                    <td className="px-3 py-2">
                                        {c.active_projects_count}
                                    </td>
                                    <td className="px-3 py-2">
                                        <span
                                            className={
                                                c.status === 'active'
                                                    ? 'text-green-600'
                                                    : 'text-red-500'
                                            }
                                        >
                                            {c.status}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        <div className="flex justify-center gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setEditingId(c.id)
                                                }
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => {
                                                    if (
                                                        confirm(
                                                            'Delete this contractor?',
                                                        )
                                                    ) {
                                                        router.delete(
                                                            `/payroll/contractors/${c.id}`,
                                                        );
                                                    }
                                                }}
                                            >
                                                <Trash2 className="h-4 w-4 text-red-500" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {(contractors.prev_page_url || contractors.next_page_url) && (
                    <div className="flex justify-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!contractors.prev_page_url}
                            onClick={() =>
                                router.get(contractors.prev_page_url!)
                            }
                        >
                            Prev
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!contractors.next_page_url}
                            onClick={() =>
                                router.get(contractors.next_page_url!)
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
