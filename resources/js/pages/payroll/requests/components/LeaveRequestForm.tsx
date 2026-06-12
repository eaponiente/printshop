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

type Props = {
    onClose: () => void;
};

export default function LeaveRequestForm({ onClose }: Props) {
    const [date, setDate] = useState('');
    const [leaveType, setLeaveType] = useState('vacation');
    const [duration, setDuration] = useState('full_day');
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const canSubmit = date && reason;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!canSubmit) return;

        setSubmitting(true);

        router.post(
            '/payroll/leave-requests',
            {
                date,
                leave_type: leaveType,
                duration,
                reason,
                is_paid: leaveType !== 'unpaid',
            },
            {
                onSuccess: () => {
                    toast.success('Leave request submitted.');
                    setDate('');
                    setLeaveType('vacation');
                    setDuration('full_day');
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
                <Label htmlFor="lv_date">Date *</Label>
                <Input
                    id="lv_date"
                    type="date"
                    value={date}
                    onChange={(e) => setDate(e.target.value)}
                    required
                />
            </div>

            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                    <Label htmlFor="lv_type">Type *</Label>
                    <Select
                        value={leaveType}
                        onValueChange={(v) => setLeaveType(v)}
                    >
                        <SelectTrigger id="lv_type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="vacation">Vacation</SelectItem>
                            <SelectItem value="sick">Sick</SelectItem>
                            <SelectItem value="emergency">Emergency</SelectItem>
                            <SelectItem value="maternity">Maternity</SelectItem>
                            <SelectItem value="paternity">Paternity</SelectItem>
                            <SelectItem value="bereavement">
                                Bereavement
                            </SelectItem>
                            <SelectItem value="unpaid">Unpaid</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-1">
                    <Label htmlFor="lv_duration">Duration *</Label>
                    <Select
                        value={duration}
                        onValueChange={(v) => setDuration(v)}
                    >
                        <SelectTrigger id="lv_duration">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="full_day">Full Day</SelectItem>
                            <SelectItem value="half_day_morning">
                                Half Day (AM)
                            </SelectItem>
                            <SelectItem value="half_day_afternoon">
                                Half Day (PM)
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="space-y-1">
                <Label htmlFor="lv_reason">Reason *</Label>
                <Textarea
                    id="lv_reason"
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
