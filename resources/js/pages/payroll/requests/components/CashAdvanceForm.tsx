import { router } from '@inertiajs/react';
import { useState } from 'react';
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

type Employee = {
    id: number;
    first_name: string;
    last_name: string;
    branch?: { name: string };
};

type Props = {
    employees: Employee[];
    onClose: () => void;
};

export default function CashAdvanceForm({ employees, onClose }: Props) {
    const [employeeId, setEmployeeId] = useState('');
    const [amount, setAmount] = useState('');
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const canSubmit = employeeId && amount && Number(amount) >= 1 && reason;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!canSubmit) {
return;
}

        setSubmitting(true);

        router.post(
            '/payroll/cash-advances',
            { employee_id: Number(employeeId), amount: Number(amount), reason },
            {
                onSuccess: () => {
                    toast.success('Cash advance created.');
                    setEmployeeId('');
                    setAmount('');
                    setReason('');
                    onClose();
                },
                onError: (err: any) =>
                    toast.error(err.error ?? err.message ?? 'Failed to create.'),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-1">
                <Label htmlFor="ca_employee">Employee *</Label>
                <Select value={employeeId} onValueChange={setEmployeeId}>
                    <SelectTrigger id="ca_employee">
                        <SelectValue placeholder="Select employee…" />
                    </SelectTrigger>
                    <SelectContent>
                        {employees.map((emp) => (
                            <SelectItem key={emp.id} value={String(emp.id)}>
                                {emp.last_name}, {emp.first_name}
                                {emp.branch ? ` — ${emp.branch.name}` : ''}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-1">
                <Label htmlFor="ca_amount">Amount (PHP) *</Label>
                <Input
                    id="ca_amount"
                    type="number"
                    step="0.01"
                    min="1"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                    required
                />
            </div>

            <div className="space-y-1">
                <Label htmlFor="ca_reason">Reason *</Label>
                <Textarea
                    id="ca_reason"
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    required
                    rows={2}
                />
            </div>

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onClose}>
                    Cancel
                </Button>
                <Button type="submit" disabled={!canSubmit || submitting}>
                    {submitting ? 'Creating…' : 'Create'}
                </Button>
            </div>
        </form>
    );
}
