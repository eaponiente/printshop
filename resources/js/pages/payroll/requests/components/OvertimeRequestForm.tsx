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

export default function OvertimeRequestForm({ onClose }: Props) {
    const [date, setDate] = useState('');
    const [startTime, setStartTime] = useState('17:00');
    const [endTime, setEndTime] = useState('');
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const computedMinutes = (() => {
        if (!startTime || !endTime) {
return 0;
}

        const [sh, sm] = startTime.split(':').map(Number);
        const [eh, em] = endTime.split(':').map(Number);
        const total = eh * 60 + em - (sh * 60 + sm);

        return Math.max(0, total);
    })();

    const computedHours = Math.round((computedMinutes / 60) * 10) / 10;

    const canSubmit =
        date && startTime && endTime && computedMinutes > 0 && reason;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!canSubmit) {
return;
}

        setSubmitting(true);

        router.post(
            '/payroll/overtime-requests',
            { date, start_time: startTime, end_time: endTime, reason },
            {
                onSuccess: () => {
                    toast.success('Overtime request submitted.');
                    setDate('');
                    setStartTime('17:00');
                    setEndTime('');
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
                <Label htmlFor="ot_date">Date *</Label>
                <Input
                    id="ot_date"
                    type="date"
                    value={date}
                    onChange={(e) => setDate(e.target.value)}
                    required
                />
            </div>

            <div className="space-y-1">
                <Label htmlFor="ot_start_time">Start Time *</Label>
                <Input
                    id="ot_start_time"
                    type="time"
                    value={startTime}
                    onChange={(e) => setStartTime(e.target.value)}
                    required
                />
            </div>

            <div className="space-y-1">
                <Label htmlFor="ot_end_time">End Time *</Label>
                <Input
                    id="ot_end_time"
                    type="time"
                    value={endTime}
                    onChange={(e) => setEndTime(e.target.value)}
                    required
                />
            </div>

            {computedMinutes > 0 && (
                <p className="rounded bg-accent/50 px-3 py-2 text-sm">
                    {computedHours}h ({computedMinutes} min) of overtime
                </p>
            )}

            <div className="space-y-1">
                <Label htmlFor="ot_reason">Reason *</Label>
                <Textarea
                    id="ot_reason"
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
