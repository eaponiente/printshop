import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    onClose: () => void;
};

export default function CashAdvanceForm({ onClose }: Props) {
    const [amount, setAmount] = useState('');
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const canSubmit = amount && Number(amount) >= 1 && reason;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!canSubmit) {
return;
}

        setSubmitting(true);

        router.post(
            '/payroll/cash-advances',
            { amount: Number(amount), reason },
            {
                onSuccess: () => {
                    toast.success('Cash advance request submitted.');
                    setAmount('');
                    setReason('');
                    onClose();
                },
                onError: (err: any) =>
                    toast.error(err.message ?? 'Failed to submit.'),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
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
                    {submitting ? 'Submitting...' : 'Submit'}
                </Button>
            </div>
        </form>
    );
}
