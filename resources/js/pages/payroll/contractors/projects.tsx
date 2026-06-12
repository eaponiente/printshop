import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Play, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { formatCurrency } from '@/utils/formatters';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contractors', href: '/payroll/contractors' },
    { title: 'Projects', href: '#' },
];

type Project = {
    id: number;
    name: string;
    contract_amount: number;
    total_installments: number;
    remaining_installments: number;
    installment_amount: number;
    start_date: string;
    end_date: string;
    status: string;
};

type Props = {
    contractor: { id: number; name: string };
    projects: Project[];
};

const statusBadge = (status: string) => {
    switch (status) {
        case 'draft':
            return { text: 'Draft', className: 'text-gray-400' };
        case 'active':
            return { text: 'Active', className: 'text-green-600' };
        case 'completed':
            return { text: 'Completed', className: 'text-blue-600' };
        default:
            return { text: status, className: 'text-muted-foreground' };
    }
};

export default function ContractorProjects({ contractor, projects }: Props) {
    const [showForm, setShowForm] = useState(false);

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title={`${contractor.name} — Projects`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="mb-1"
                            onClick={() => router.get('/payroll/contractors')}
                        >
                            <ArrowLeft className="mr-1 h-4 w-4" /> Back
                        </Button>
                        <h1 className="text-xl font-semibold">
                            {contractor.name} — Projects
                        </h1>
                    </div>
                    <Button size="sm" onClick={() => setShowForm(!showForm)}>
                        {showForm ? 'Cancel' : 'Add Project'}
                    </Button>
                </div>

                {showForm && (
                    <form
                        className="rounded-md border bg-sidebar p-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const form = e.currentTarget as HTMLFormElement;
                            const data = new FormData(form);
                            router.post(
                                `/payroll/contractors/${contractor.id}/projects`,
                                data,
                                {
                                    onSuccess: () => {
                                        form.reset();
                                        setShowForm(false);
                                    },
                                },
                            );
                        }}
                    >
                        <div className="grid grid-cols-2 gap-3">
                            <div className="col-span-2">
                                <label className="mb-1 block text-xs font-medium">
                                    Project Name
                                </label>
                                <input
                                    name="name"
                                    required
                                    className="w-full rounded border px-2 py-1 text-sm"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    Contract Amount
                                </label>
                                <input
                                    name="contract_amount"
                                    type="number"
                                    min="1"
                                    required
                                    className="w-full rounded border px-2 py-1 text-sm"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    Installments
                                </label>
                                <input
                                    name="total_installments"
                                    type="number"
                                    min="1"
                                    defaultValue="1"
                                    required
                                    className="w-full rounded border px-2 py-1 text-sm"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    Start Date
                                </label>
                                <input
                                    name="start_date"
                                    type="date"
                                    required
                                    className="w-full rounded border px-2 py-1 text-sm"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    End Date
                                </label>
                                <input
                                    name="end_date"
                                    type="date"
                                    required
                                    className="w-full rounded border px-2 py-1 text-sm"
                                />
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
                        <div className="mt-3">
                            <Button type="submit" size="sm">
                                Create Project
                            </Button>
                        </div>
                    </form>
                )}

                <div className="overflow-x-auto rounded-md border bg-sidebar">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="px-3 py-2 text-left font-medium">
                                    Project
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Amount
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Per Installment
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Progress
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Dates
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 text-center font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {projects.map((p) => {
                                const status = statusBadge(p.status);
                                const paid =
                                    p.total_installments -
                                    p.remaining_installments;
                                return (
                                    <tr key={p.id} className="border-b">
                                        <td className="px-3 py-2 font-medium">
                                            {p.name}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono">
                                            {formatCurrency(p.contract_amount)}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono text-muted-foreground">
                                            {formatCurrency(
                                                p.installment_amount,
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-center">
                                            {paid}/{p.total_installments}
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                            {p.start_date} to {p.end_date}
                                        </td>
                                        <td className="px-3 py-2 text-center">
                                            <span
                                                className={`text-xs ${status.className}`}
                                            >
                                                {status.text}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-center">
                                            {p.status === 'draft' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            `/payroll/projects/${p.id}/activate`,
                                                        )
                                                    }
                                                >
                                                    <Play className="mr-1 h-3.5 w-3.5" />{' '}
                                                    Activate
                                                </Button>
                                            )}
                                            {p.status !== 'active' && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => {
                                                        if (
                                                            confirm('Delete?')
                                                        ) {
                                                            router.delete(
                                                                `/payroll/projects/${p.id}`,
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <Trash2 className="h-4 w-4 text-red-500" />
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                            {projects.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No projects yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </PayrollLayout>
    );
}
