import type { Auth } from '@/types/auth';

export type PendingRequests = {
    overtime: number;
    leave: number;
    correction: number;
    cash_advance: number;
};

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            pending_requests: PendingRequests;
            [key: string]: unknown;
        };
    }
}
