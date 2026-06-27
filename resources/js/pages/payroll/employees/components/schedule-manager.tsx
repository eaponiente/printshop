import { router } from '@inertiajs/react';
import { Clock, Plus, Trash2, Pencil } from 'lucide-react';
import { useState } from 'react';
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
import { formatTime } from '@/utils/formatters';

type Schedule = {
    id: number;
    employee_id: number;
    start_time: string;
    end_time: string;
    rest_days: number[];
    effective_from: string;
    effective_to: string | null;
    is_active: boolean;
};

type Props = {
    employeeId: number;
    schedules: Schedule[];
    daysOfWeek: Array<{ value: number; label: string }>;
};

export function ScheduleManager({ employeeId, schedules, daysOfWeek }: Props) {
    const activeSchedule = schedules.find((s) => s.is_active);

    return (
        <div>
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-sm font-semibold text-muted-foreground uppercase">
                    Work Schedule
                </h2>
                <ScheduleDialog employeeId={employeeId} daysOfWeek={daysOfWeek}>
                    <Button variant="outline" size="sm">
                        <Plus className="mr-1 h-3 w-3" />
                        Add Schedule
                    </Button>
                </ScheduleDialog>
            </div>

            {activeSchedule ? (
                <div className="mb-4 rounded-md border border-green-200 bg-green-50 p-4">
                    <div className="flex items-center gap-2 text-sm font-medium text-green-800">
                        <Clock className="h-4 w-4" />
                        Active Schedule
                    </div>
                    <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <span className="text-muted-foreground">
                                Shift:{' '}
                            </span>
                            <span className="font-medium">
                                {formatTime(activeSchedule.start_time)} –{' '}
                                {formatTime(activeSchedule.end_time)}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Rest Days:{' '}
                            </span>
                            <span className="font-medium">
                                {activeSchedule.rest_days
                                    .map(
                                        (d) =>
                                            daysOfWeek.find(
                                                (dw) => dw.value === d,
                                            )?.label ?? d,
                                    )
                                    .join(', ') || 'None'}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Effective:{' '}
                            </span>
                            <span className="font-medium">
                                {activeSchedule.effective_from}
                                {activeSchedule.effective_to
                                    ? ` – ${activeSchedule.effective_to}`
                                    : ' (ongoing)'}
                            </span>
                        </div>
                    </div>
                </div>
            ) : (
                <p className="mb-4 text-sm text-muted-foreground">
                    No schedule configured. Defaults to Mon–Sat, 8:00 AM – 5:00
                    PM.
                </p>
            )}

            {schedules.length > 0 && (
                <div className="space-y-2">
                    <h3 className="text-xs font-medium text-muted-foreground">
                        All Schedules
                    </h3>
                    {schedules.map((s) => (
                        <div
                            key={s.id}
                            className={`flex items-center justify-between rounded border px-3 py-2 text-sm ${s.is_active ? 'border-green-200 bg-green-50/50' : 'border-muted bg-muted/20'}`}
                        >
                            <div className="flex flex-wrap gap-x-4 gap-y-0.5">
                                <span className="text-xs">
                                    <span className="text-muted-foreground">
                                        Shift:{' '}
                                    </span>
                                    {formatTime(s.start_time)}–
                                    {formatTime(s.end_time)}
                                </span>
                                <span className="text-xs">
                                    <span className="text-muted-foreground">
                                        Rest:{' '}
                                    </span>
                                    {s.rest_days
                                        .map(
                                            (d) =>
                                                daysOfWeek
                                                    .find(
                                                        (dw) => dw.value === d,
                                                    )
                                                    ?.label?.substring(0, 3) ??
                                                d,
                                        )
                                        .join(', ') || 'None'}
                                </span>
                                <span className="text-xs">
                                    {s.effective_from}
                                    {s.effective_to ? `–${s.effective_to}` : ''}
                                </span>
                                {!s.is_active && (
                                    <span className="rounded bg-yellow-100 px-1 py-0.5 text-xs text-yellow-700">
                                        Inactive
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center gap-1">
                                <ScheduleDialog
                                    employeeId={employeeId}
                                    daysOfWeek={daysOfWeek}
                                    schedule={s}
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        title="Edit"
                                    >
                                        <Pencil className="h-3 w-3" />
                                    </Button>
                                </ScheduleDialog>
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            title="Delete"
                                        >
                                            <Trash2 className="h-3 w-3 text-red-500" />
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>
                                                Delete schedule?
                                            </AlertDialogTitle>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>
                                                Cancel
                                            </AlertDialogCancel>
                                            <AlertDialogAction
                                                onClick={() =>
                                                    router.delete(
                                                        `/payroll/employees/schedules/${s.id}`,
                                                        {
                                                            onSuccess: () =>
                                                                toast.success(
                                                                    'Schedule deleted.',
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
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function ScheduleDialog({
    employeeId,
    schedule,
    daysOfWeek,
    children,
}: {
    employeeId: number;
    schedule?: Schedule;
    daysOfWeek: Props['daysOfWeek'];
    children: React.ReactNode;
}) {
    const isEdit = !!schedule;
    const [open, setOpen] = useState(false);

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const fd = new FormData(e.currentTarget);

        if (isEdit && schedule) {
            router.put(`/payroll/employees/schedules/${schedule.id}`, fd, {
                onSuccess: () => {
                    setOpen(false);
                    toast.success('Schedule updated.');
                },
                onError: () => toast.error('Failed.'),
            });
        } else {
            router.post(`/payroll/employees/${employeeId}/schedules`, fd, {
                onSuccess: () => {
                    setOpen(false);
                    toast.success('Schedule added.');
                },
                onError: () => toast.error('Failed.'),
            });
        }
    };

    const selectedDays = schedule?.rest_days.map((n) => Number(n)) ?? [];

    return (
        <Dialog key={schedule?.id ?? 'new'} open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-w-sm">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit Schedule' : 'Add Schedule'}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="start_time">Start Time</Label>
                            <Input
                                id="start_time"
                                name="start_time"
                                type="time"
                                required
                                defaultValue={schedule?.start_time ?? '08:00'}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="end_time">End Time</Label>
                            <Input
                                id="end_time"
                                name="end_time"
                                type="time"
                                required
                                defaultValue={schedule?.end_time ?? '17:00'}
                            />
                        </div>
                    </div>
                    <div className="space-y-1">
                        <Label>Rest Days</Label>
                        <div className="flex flex-wrap gap-2">
                            {daysOfWeek.map((d) => (
                                <label
                                    key={d.value}
                                    className="flex cursor-pointer items-center gap-1 rounded border px-2 py-1 text-xs"
                                >
                                    <input
                                        type="checkbox"
                                        name="rest_days[]"
                                        value={d.value}
                                        defaultChecked={selectedDays.includes(
                                            d.value,
                                        )}
                                    />
                                    {d.label.substring(0, 3)}
                                </label>
                            ))}
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="effective_from">
                                Effective From
                            </Label>
                            <Input
                                id="effective_from"
                                name="effective_from"
                                type="date"
                                required
                                defaultValue={schedule?.effective_from}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="effective_to">
                                Effective To (optional)
                            </Label>
                            <Input
                                id="effective_to"
                                name="effective_to"
                                type="date"
                                defaultValue={schedule?.effective_to ?? ''}
                            />
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0" />
                        <input
                            type="checkbox"
                            id="is_active"
                            name="is_active"
                            value="1"
                            defaultChecked={
                                schedule ? schedule.is_active : true
                            }
                            className="h-4 w-4"
                        />
                        <Label htmlFor="is_active" className="cursor-pointer">
                            Active
                        </Label>
                    </div>
                    <Button type="submit" className="w-full">
                        {isEdit ? 'Update' : 'Add'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
