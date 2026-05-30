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
    { title: 'Company Config', href: '/payroll/company-config' },
];

const DEFAULT_CONFIGS = [
    {
        key: 'philhealth_premium_percentage',
        label: 'PhilHealth Premium Percentage',
        value: '5.00',
    },
    {
        key: 'pagibig_monthly_employee_share',
        label: 'Pag-IBIG Monthly Employee Share',
        value: '100',
    },
    {
        key: 'pagibig_monthly_employer_share',
        label: 'Pag-IBIG Monthly Employer Share',
        value: '100',
    },
];

type Props = {
    configs: Record<
        string,
        { id: number; key: string; value: string; label: string }
    >;
};

export default function CompanyConfig({ configs }: Props) {
    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const fd = new FormData(e.currentTarget);
        const configArray: Array<{
            key: string;
            value: string;
            label: string;
        }> = [];
        DEFAULT_CONFIGS.forEach((c) => {
            configArray.push({
                key: c.key,
                value: (fd.get(c.key) as string) || c.value,
                label: c.label,
            });
        });
        router.post(
            '/payroll/company-config',
            { configs: configArray } as any,
            { onSuccess: () => toast.success('Saved') },
        );
    };

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Company Configuration" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">
                        Company Configuration
                    </h1>
                </div>
                <form onSubmit={handleSubmit} className="max-w-md space-y-4">
                    {DEFAULT_CONFIGS.map((c) => (
                        <div key={c.key} className="space-y-1">
                            <Label htmlFor={c.key}>{c.label}</Label>
                            <Input
                                id={c.key}
                                name={c.key}
                                defaultValue={configs[c.key]?.value ?? c.value}
                            />
                        </div>
                    ))}
                    <Button type="submit">Save Configuration</Button>
                </form>
            </div>
        </PayrollLayout>
    );
}
