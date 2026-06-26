import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Payroll Settings', href: '/payroll/settings' },
];

type Setting = {
    id: number;
    key: string;
    value: string | null;
    type: string;
    description: string | null;
};

type AuditEntry = {
    action: string;
    before: Record<string, string> | null;
    after: Record<string, string> | null;
    user_name: string;
    created_at: string;
};

type Props = {
    settings: Record<string, Setting>;
    auditLogs: AuditEntry[];
    defaults: {
        late_deduction_per_minute: string;
        late_deduction_threshold_minutes: string;
        no_break_fine: string;
    };
};

export default function PayrollSettings({
    settings,
    auditLogs,
    defaults,
}: Props) {
    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const fd = new FormData(e.currentTarget);
        router.put(
            '/payroll/settings',
            {
                late_deduction_per_minute: fd.get('late_deduction_per_minute'),
                late_deduction_threshold_minutes: fd.get(
                    'late_deduction_threshold_minutes',
                ),
                no_break_fine: fd.get('no_break_fine'),
            } as any,
            {
                onSuccess: () => toast.success('Payroll settings updated.'),
                onError: () => toast.error('Failed to update settings.'),
            },
        );
    };

    const value = (key: string) =>
        settings[key]?.value ?? defaults[key as keyof Props['defaults']] ?? '';

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll Settings" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Payroll Settings</h1>
                    <p className="text-sm text-muted-foreground">
                        Late deduction formula configuration.
                    </p>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="max-w-md space-y-4 rounded-md border border-sidebar-border bg-sidebar p-4"
                >
                    <div className="space-y-1">
                        <Label htmlFor="late_deduction_per_minute">
                            Late deduction per minute (₱)
                        </Label>
                        <Input
                            id="late_deduction_per_minute"
                            name="late_deduction_per_minute"
                            type="number"
                            step="0.01"
                            min="0"
                            defaultValue={value('late_deduction_per_minute')}
                        />
                        <p className="text-xs text-muted-foreground">
                            Flat peso amount charged for each minute of lateness
                            within the threshold.
                        </p>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="late_deduction_threshold_minutes">
                            Threshold (minutes)
                        </Label>
                        <Input
                            id="late_deduction_threshold_minutes"
                            name="late_deduction_threshold_minutes"
                            type="number"
                            min="1"
                            defaultValue={value(
                                'late_deduction_threshold_minutes',
                            )}
                        />
                        <p className="text-xs text-muted-foreground">
                            Minutes after which the penalty switches from
                            per-minute flat rate to hourly-rate-based.
                        </p>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="no_break_fine">
                            No-break fine (₱)
                        </Label>
                        <Input
                            id="no_break_fine"
                            name="no_break_fine"
                            type="number"
                            step="0.01"
                            min="0"
                            defaultValue={value('no_break_fine')}
                        />
                        <p className="text-xs text-muted-foreground">
                            Deducted from daily wage when an employee has no
                            lunch-break punches (LUNCH OUT / LUNCH IN).
                        </p>
                    </div>

                    <Button type="submit">Save Settings</Button>
                </form>

                {auditLogs.length > 0 && (
                    <div className="max-w-md space-y-2">
                        <h2 className="text-sm font-semibold">
                            Change History
                        </h2>
                        <div className="rounded-md border border-sidebar-border bg-sidebar p-3 text-xs">
                            {auditLogs.map((entry, i) => (
                                <div
                                    key={i}
                                    className="flex flex-col gap-0.5 border-b border-sidebar-border py-1.5 last:border-0"
                                >
                                    <div className="flex justify-between text-muted-foreground">
                                        <span>{entry.created_at}</span>
                                        <span>{entry.user_name}</span>
                                    </div>
                                    {entry.before &&
                                        entry.after &&
                                        Object.keys(entry.before).map(
                                            (key) =>
                                                entry.before![key] !==
                                                    entry.after![key] && (
                                                    <div key={key}>
                                                        <code className="text-blue-600">
                                                            {key}
                                                        </code>
                                                        :{' '}
                                                        <span className="text-red-500 line-through">
                                                            {entry.before![
                                                                key
                                                            ] ?? '—'}
                                                        </span>{' '}
                                                        →{' '}
                                                        <span className="text-green-600">
                                                            {entry.after![
                                                                key
                                                            ] ?? '—'}
                                                        </span>
                                                    </div>
                                                ),
                                        )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </PayrollLayout>
    );
}
