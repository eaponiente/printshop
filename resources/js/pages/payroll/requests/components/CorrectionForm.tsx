import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
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

type CorrectionType =
    | 'missed_punch_in'
    | 'missed_punch_out'
    | 'time_adjustment'
    | 'absent_to_present';

type AdjustmentEntry = {
    key: number;
    punch_type: 'in' | 'out';
    requested_time: string;
};

let nextKey = 0;

function makeEntry(punch_type: 'in' | 'out' = 'in'): AdjustmentEntry {
    return {
        key: nextKey++,
        punch_type,
        requested_time: punch_type === 'in' ? '08:00' : '17:00',
    };
}

type Props = {
    onClose: () => void;
};

export default function CorrectionForm({ onClose }: Props) {
    const [date, setDate] = useState('');
    const [correctionType, setCorrectionType] =
        useState<CorrectionType>('missed_punch_in');
    const [reason, setReason] = useState('');
    const [items, setItems] = useState<AdjustmentEntry[]>(() => [
        makeEntry('in'),
    ]);
    const [submitting, setSubmitting] = useState(false);

    const handleTypeChange = (value: CorrectionType) => {
        setCorrectionType(value);

        if (value === 'missed_punch_in') {
            setItems([makeEntry('in')]);
        } else if (value === 'missed_punch_out') {
            setItems([makeEntry('out')]);
        } else if (value === 'absent_to_present') {
            setItems([makeEntry('in'), makeEntry('out')]);
        } else {
            // time_adjustment: start with 1 empty entry
            setItems([makeEntry('in')]);
        }
    };

    const addEntry = () => {
        setItems([...items, makeEntry('in')]);
    };

    const removeEntry = (key: number) => {
        if (items.length <= 1) return;
        setItems(items.filter((e) => e.key !== key));
    };

    const updateEntry = (key: number, partial: Partial<AdjustmentEntry>) => {
        setItems(items.map((e) => (e.key === key ? { ...e, ...partial } : e)));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!date || !reason || items.length === 0) {
            toast.error('Please fill in all required fields.');
            return;
        }

        if (!items.every((e) => e.requested_time)) {
            toast.error('Please fill in the time for each adjustment entry.');
            return;
        }

        const inCount = items.filter((e) => e.punch_type === 'in').length;
        const outCount = items.filter((e) => e.punch_type === 'out').length;

        if (inCount > 1 || outCount > 1) {
            toast.error(
                'You can only have one IN and one OUT per correction request.',
            );
            return;
        }

        setSubmitting(true);

        router.post(
            '/payroll/correction-requests',
            {
                date,
                correction_type: correctionType,
                reason,
                items: items.map(({ punch_type, requested_time }) => ({
                    punch_type,
                    requested_time,
                })),
            },
            {
                onSuccess: () => {
                    toast.success('Correction request submitted.');
                    setDate('');
                    setReason('');
                    setCorrectionType('missed_punch_in');
                    setItems([makeEntry('in')]);
                    onClose();
                },
                onError: (err: any) => {
                    toast.error(err.error ?? 'Failed to submit correction.');
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            },
        );
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div className="space-y-1">
                    <Label htmlFor="corr_date">Date *</Label>
                    <Input
                        id="corr_date"
                        type="date"
                        value={date}
                        onChange={(e) => setDate(e.target.value)}
                        required
                    />
                </div>

                <div className="space-y-1">
                    <Label htmlFor="corr_type">Type *</Label>
                    <Select
                        value={correctionType}
                        onValueChange={(v) =>
                            handleTypeChange(v as CorrectionType)
                        }
                    >
                        <SelectTrigger id="corr_type">
                            <SelectValue placeholder="Select..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="missed_punch_in">
                                Missed Punch In
                            </SelectItem>
                            <SelectItem value="missed_punch_out">
                                Missed Punch Out
                            </SelectItem>
                            <SelectItem value="time_adjustment">
                                Time Adjustment
                            </SelectItem>
                            <SelectItem value="absent_to_present">
                                Absent to Present
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <Label className="text-xs font-semibold text-muted-foreground uppercase">
                        Adjustments
                    </Label>
                </div>

                {items.map((entry, idx) => (
                    <div
                        key={entry.key}
                        className="space-y-2 rounded border bg-background p-3"
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">
                                Entry {idx + 1}
                            </span>
                            {items.length > 1 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-6 w-6 p-0 text-red-500 hover:text-red-600"
                                    onClick={() => removeEntry(entry.key)}
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                            )}
                        </div>

                        <div className="flex items-center gap-4">
                            <div className="flex items-center gap-2">
                                <label className="flex items-center gap-1.5 text-sm">
                                    <input
                                        type="radio"
                                        name={`entry_${entry.key}_type`}
                                        value="in"
                                        checked={entry.punch_type === 'in'}
                                        onChange={() =>
                                            updateEntry(entry.key, {
                                                punch_type: 'in',
                                            })
                                        }
                                        className="accent-foreground"
                                    />
                                    IN
                                </label>
                                <label className="flex items-center gap-1.5 text-sm">
                                    <input
                                        type="radio"
                                        name={`entry_${entry.key}_type`}
                                        value="out"
                                        checked={entry.punch_type === 'out'}
                                        onChange={() =>
                                            updateEntry(entry.key, {
                                                punch_type: 'out',
                                            })
                                        }
                                        className="accent-foreground"
                                    />
                                    OUT
                                </label>
                            </div>

                            <div className="flex items-center gap-2">
                                <Label
                                    htmlFor={`entry_${entry.key}_time`}
                                    className="text-xs text-muted-foreground"
                                >
                                    Time
                                </Label>
                                <Input
                                    id={`entry_${entry.key}_time`}
                                    type="time"
                                    className="w-32"
                                    value={entry.requested_time}
                                    onChange={(e) =>
                                        updateEntry(entry.key, {
                                            requested_time: e.target.value,
                                        })
                                    }
                                    required
                                />
                            </div>
                        </div>
                    </div>
                ))}

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={addEntry}
                    className="w-full"
                >
                    <Plus className="mr-1 h-3.5 w-3.5" />
                    Add another adjustment
                </Button>
            </div>

            <div className="space-y-1">
                <Label htmlFor="corr_reason">Reason *</Label>
                <Textarea
                    id="corr_reason"
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
                <Button type="submit" size="sm" disabled={submitting}>
                    {submitting ? 'Submitting...' : 'Submit'}
                </Button>
            </div>
        </form>
    );
}
