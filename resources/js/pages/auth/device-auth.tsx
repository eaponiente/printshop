import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

type Props = {
    userId: number;
    hasApproved?: boolean;
    hasPending?: boolean;
};

export default function DeviceAuth({ hasApproved, hasPending }: Props) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);
    const [deviceName, setDeviceName] = useState('');
    const [pending, setPending] = useState(hasPending ?? false);

    const handleRegister = async () => {
        setLoading(true);
        setError(null);

        try {
            const res = await fetch('/device-auth/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Inertia': 'true',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    device_name: deviceName.trim() || navigator.userAgent.slice(0, 100) || 'Unknown Device',
                }),
            });

            const text = await res.text();
            let data: Record<string, unknown>;
            try {
                data = JSON.parse(text);
            } catch {
                setError(text || 'Unexpected response.');
                return;
            }

            if (data.verified) {
                window.location.href = (data.redirect as string) || '/dashboard';
            } else if (data.pending) {
                setSuccess((data.message as string) || 'Device registered. Awaiting superadmin approval.');
                setPending(true);
            } else {
                setError((data.error as string) || 'Registration failed.');
            }
        } catch {
            setError('Network error. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <AuthLayout title="Device Registration" description="Register this device to continue">
            <Head title="Device Registration" />

            <div className="flex flex-col gap-6">
                {pending ? (
                    <>
                        <p className="text-center text-sm text-muted-foreground">
                            Your device is pending superadmin approval. Contact your administrator to approve this device.
                        </p>
                        {success && (
                            <div className="rounded-md bg-green-50 dark:bg-green-950 p-3 text-sm text-green-700 dark:text-green-300">{success}</div>
                        )}
                    </>
                ) : hasApproved ? (
                    <p className="text-center text-sm text-muted-foreground">
                        Your approved device is not recognized on this browser. Try logging in from your registered browser, or contact your administrator.
                    </p>
                ) : (
                    <>
                        <p className="text-center text-sm text-muted-foreground">
                            This device must be registered before you can access the application.
                        </p>
                        {error && (
                            <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">{error}</div>
                        )}
                        <input
                            type="text"
                            placeholder="Device name — e.g. Branch A Terminal, MacBook Pro"
                            value={deviceName}
                            onChange={(e) => setDeviceName(e.target.value)}
                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        />
                        <Button onClick={handleRegister} disabled={loading} className="w-full">
                            {loading ? 'Registering...' : 'Register This Device'}
                        </Button>
                    </>
                )}
            </div>
        </AuthLayout>
    );
}
