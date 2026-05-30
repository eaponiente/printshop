import { router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Plus, X } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/utils/formatters';

const FINE_TYPES: Record<string, number> = {
    'No Uniform': 20,
    'Tardiness Report': 50,
    Other: 0,
};

type FineRow = {
    id: number;
    fine_type: string;
    amount: number;
    note: string | null;
};

type Props = {
    employeeId: number;
    date: string;
    fines: FineRow[];
};

export default function SheetFinesCard({ employeeId, date, fines }: Props) {
    const [showForm, setShowForm] = useState(false);
    const [fineType, setFineType] = useState('No Uniform');
    const [amount, setAmount] = useState(String(FINE_TYPES['No Uniform']));

    const handleTypeChange = (val: string) => {
        setFineType(val);
        setAmount(String(FINE_TYPES[val] ?? 0));
    };

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const fd = new FormData(e.currentTarget);
        router.post('/payroll/fines', fd, {
            onSuccess: () => {
                toast.success('Fine marked.');
                setShowForm(false);
            },
            onError: () => toast.error('Failed to mark fine.'),
        });
    };

    const removeFine = (fineId: number) => {
        router.delete(`/payroll/fines/${fineId}`, {
            onSuccess: () => toast.success('Fine removed.'),
            onError: () => toast.error('Failed to remove fine.'),
        });
    };

    return (
        <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
            <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                Fines
            </h3>
            {fines.length > 0 ? (
                <div className="mb-3 space-y-1.5">
                    {fines.map((f) => (
                        <div
                            key={f.id}
                            className="flex items-center justify-between rounded border bg-background/50 px-3 py-1.5"
                        >
                            <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                                <span className="font-medium">
                                    {f.fine_type}:
                                </span>
                                <span className="font-mono text-red-600">
                                    −{formatCurrency(f.amount)}
                                </span>
                                {f.note && (
                                    <span className="text-muted-foreground">
                                        — {f.note}
                                    </span>
                                )}
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-6 px-1 text-red-500"
                                onClick={() => removeFine(f.id)}
                            >
                                <X className="h-3 w-3" />
                            </Button>
                        </div>
                    ))}
                </div>
            ) : (
                <p className="mb-3 text-xs text-muted-foreground">
                    No fines for this day.
                </p>
            )}

            {!showForm && (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setShowForm(true)}
                >
                    <Plus className="mr-1 h-3.5 w-3.5" />
                    Mark Fine
                </Button>
            )}

            {showForm && (
                <form
                    onSubmit={handleSubmit}
                    className="space-y-3 rounded border bg-background/50 p-3"
                >
                    <input
                        type="hidden"
                        name="employee_id"
                        value={employeeId}
                    />
                    <input type="hidden" name="date" value={date} />
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label className="text-xs">Type</Label>
                            <Select
                                name="fine_type"
                                value={fineType}
                                onValueChange={handleTypeChange}
                            >
                                <SelectTrigger className="h-8 text-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.keys(FINE_TYPES).map((t) => (
                                        <SelectItem key={t} value={t}>
                                            {t}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">Amount (PHP)</Label>
                            <Input
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0"
                                value={amount}
                                onChange={(e) => setAmount(e.target.value)}
                                className="h-8 text-xs"
                                required
                            />
                        </div>
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Note</Label>
                        <Textarea
                            name="note"
                            rows={2}
                            className="text-xs"
                            placeholder="Reason for fine..."
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" size="sm" className="h-7 text-xs">
                            Mark Fine
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs"
                            onClick={() => setShowForm(false)}
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            )}
        </div>
    );
}
