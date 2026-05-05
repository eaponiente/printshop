import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Device {
    id: number;
    device_name: string;
    user: { id: number; username: string; first_name: string; last_name: string; role: string; branch?: { name: string } | null };
    is_approved: boolean;
    last_used_at: string | null;
    created_at: string;
}

interface Props {
    pending: Device[];
    approved: Device[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Device Management', href: '/devices' }];

export default function DeviceManagement({ pending, approved }: Props) {
    const [tab, setTab] = useState<'approved' | 'pending'>('pending');

    const approve = (id: number) => {
        router.post(`/devices/${id}/approve`, {}, { preserveScroll: true });
    };

    const reject = (id: number) => {
        if (!confirm('Remove this device registration?')) return;
        router.delete(`/devices/${id}/reject`, { preserveScroll: true });
    };

    const deactivate = (id: number) => {
        if (!confirm('Deactivate this device? The user will need to re-register.')) return;
        router.post(`/devices/${id}/deactivate`, {}, { preserveScroll: true });
    };

    const devices = tab === 'pending' ? pending : approved;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Device Management" />

            <div className="flex flex-col gap-6 p-6">
                <h1 className="text-2xl font-semibold">Device Management</h1>

                <div className="flex gap-2 border-b">
                    <button
                        onClick={() => setTab('pending')}
                        className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
                            tab === 'pending'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Pending ({pending.length})
                    </button>
                    <button
                        onClick={() => setTab('approved')}
                        className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
                            tab === 'approved'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Approved ({approved.length})
                    </button>
                </div>

                {devices.length === 0 && (
                    <p className="text-center text-sm text-muted-foreground py-8">
                        No {tab} devices.
                    </p>
                )}

                <div className="rounded-md border">
                    <div className="grid grid-cols-5 gap-4 border-b bg-muted/50 px-4 py-2 text-sm font-medium text-muted-foreground">
                        <span>Device Name</span>
                        <span>User</span>
                        <span>Branch</span>
                        <span>Last Used</span>
                        <span className="text-right">Actions</span>
                    </div>

                    {devices.map((device) => (
                        <div
                            key={device.id}
                            className="grid grid-cols-5 gap-4 px-4 py-3 border-b last:border-0 text-sm items-center"
                        >
                            <div>
                                <span className="font-medium">{device.device_name}</span>
                                <span className="ml-2 text-xs text-muted-foreground">
                                    {new Date(device.created_at).toLocaleDateString()}
                                </span>
                            </div>
                            <div>
                                <span>{device.user.first_name} {device.user.last_name}</span>
                                <span className="ml-1 text-xs text-muted-foreground">({device.user.role})</span>
                            </div>
                            <div className="text-muted-foreground">
                                {device.user.branch?.name ?? '—'}
                            </div>
                            <div className="text-muted-foreground text-xs">
                                {device.last_used_at
                                    ? new Date(device.last_used_at).toLocaleString()
                                    : 'Never'}
                            </div>
                            <div className="flex justify-end gap-2">
                                {tab === 'pending' && (
                                    <>
                                        <Button size="sm" onClick={() => approve(device.id)}>
                                            Approve
                                        </Button>
                                        <Button size="sm" variant="destructive" onClick={() => reject(device.id)}>
                                            Reject
                                        </Button>
                                    </>
                                )}
                                {tab === 'approved' && (
                                    <Button size="sm" variant="outline" onClick={() => deactivate(device.id)}>
                                        Deactivate
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
