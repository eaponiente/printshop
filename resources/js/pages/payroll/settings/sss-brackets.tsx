import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'SSS Brackets', href: '/payroll/sss-brackets' },
];

type Bracket = {
    id: number;
    salary_min: number;
    salary_max: number | null;
    employee_percentage: number;
    employer_percentage: number;
    effective_from: string;
};

export default function SssBrackets({ brackets }: { brackets: Bracket[] }) {
    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="SSS Contribution Brackets" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            SSS Contribution Brackets
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Configure SSS contribution brackets per salary
                            range.
                        </p>
                    </div>
                    <BracketDialog>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Bracket
                        </Button>
                    </BracketDialog>
                </div>

                <div className="overflow-x-auto rounded-md border border-sidebar-border bg-sidebar">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="px-3 py-2 text-left">#</th>
                                <th className="px-3 py-2 text-left">
                                    Salary Min
                                </th>
                                <th className="px-3 py-2 text-left">
                                    Salary Max
                                </th>
                                <th className="px-3 py-2 text-left">
                                    Employee %
                                </th>
                                <th className="px-3 py-2 text-left">
                                    Employer %
                                </th>
                                <th className="px-3 py-2 text-left">
                                    Effective From
                                </th>
                                <th className="px-3 py-2 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {brackets.map((b, i) => (
                                <tr key={b.id} className="border-b">
                                    <td className="px-3 py-2 font-medium">
                                        {i + 1}
                                    </td>
                                    <td className="px-3 py-2 font-mono">
                                        ₱
                                        {Number(b.salary_min).toLocaleString(
                                            undefined,
                                            { minimumFractionDigits: 2 },
                                        )}
                                    </td>
                                    <td className="px-3 py-2 font-mono">
                                        {b.salary_max
                                            ? `₱${Number(b.salary_max).toLocaleString(undefined, { minimumFractionDigits: 2 })}`
                                            : 'No limit'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {b.employee_percentage}%
                                    </td>
                                    <td className="px-3 py-2">
                                        {b.employer_percentage}%
                                    </td>
                                    <td className="px-3 py-2">
                                        {b.effective_from}
                                    </td>
                                    <td className="flex justify-end gap-1 px-3 py-2">
                                        <BracketDialog bracket={b}>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                title="Edit"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                        </BracketDialog>
                                        <AlertDialog>
                                            <AlertDialogTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    title="Delete"
                                                >
                                                    <Trash2 className="h-4 w-4 text-red-500" />
                                                </Button>
                                            </AlertDialogTrigger>
                                            <AlertDialogContent>
                                                <AlertDialogHeader>
                                                    <AlertDialogTitle>
                                                        Delete bracket #{i + 1}?
                                                    </AlertDialogTitle>
                                                </AlertDialogHeader>
                                                <AlertDialogFooter>
                                                    <AlertDialogCancel>
                                                        Cancel
                                                    </AlertDialogCancel>
                                                    <AlertDialogAction
                                                        onClick={() =>
                                                            router.delete(
                                                                `/payroll/sss-brackets/${b.id}`,
                                                                {
                                                                    onSuccess:
                                                                        () =>
                                                                            toast.success(
                                                                                'Deleted.',
                                                                            ),
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Delete
                                                    </AlertDialogAction>
                                                </AlertDialogFooter>
                                            </AlertDialogContent>
                                        </AlertDialog>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </PayrollLayout>
    );
}

function BracketDialog({
    bracket,
    children,
}: {
    bracket?: Bracket;
    children: React.ReactNode;
}) {
    const isEdit = !!bracket;

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);

        if (isEdit && bracket) {
            router.put(`/payroll/sss-brackets/${bracket.id}`, formData, {
                onSuccess: () => toast.success('Updated.'),
            });
        } else {
            router.post('/payroll/sss-brackets', formData, {
                onSuccess: () => toast.success('Added.'),
            });
        }
    };

    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit Bracket' : 'Add Bracket'}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-3">
                    <div className="space-y-1">
                        <Label htmlFor="salary_min">Salary Min</Label>
                        <Input
                            id="salary_min"
                            name="salary_min"
                            type="number"
                            step="0.01"
                            required
                            defaultValue={bracket?.salary_min}
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="salary_max">
                            Salary Max (leave empty for no limit)
                        </Label>
                        <Input
                            id="salary_max"
                            name="salary_max"
                            type="number"
                            step="0.01"
                            defaultValue={bracket?.salary_max ?? ''}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="employee_percentage">
                                Employee %
                            </Label>
                            <Input
                                id="employee_percentage"
                                name="employee_percentage"
                                type="number"
                                step="0.01"
                                required
                                defaultValue={bracket?.employee_percentage ?? 5}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="employer_percentage">
                                Employer %
                            </Label>
                            <Input
                                id="employer_percentage"
                                name="employer_percentage"
                                type="number"
                                step="0.01"
                                required
                                defaultValue={
                                    bracket?.employer_percentage ?? 10
                                }
                            />
                        </div>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="effective_from">Effective From</Label>
                        <Input
                            id="effective_from"
                            name="effective_from"
                            type="date"
                            required
                            defaultValue={bracket?.effective_from}
                        />
                    </div>
                    <Button type="submit" className="w-full">
                        {isEdit ? 'Update' : 'Add'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
