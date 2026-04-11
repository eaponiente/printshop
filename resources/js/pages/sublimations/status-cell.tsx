import { router, usePage } from '@inertiajs/react';
import { ArrowRightCircle, Loader2 } from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';

interface StatusCellProps {
    item: any;
    statuses: { key: string; value: string; color: string }[];
}

// 1. Define a standalone component for the Status Cell
export const StatusCell = ({ item, statuses }: StatusCellProps) => {
    const [isUpdating, setIsUpdating] = useState(false);

    const { auth } = usePage().props;

    // Define the boundaries
    const phase1Keys = [
        'for approval',
        'done layout',
        'waiting for dp',
        'downpayment complete',
    ];
    const isCompleted = item.status === 'completed';

    // 1. Advanced 3-Phase Filtering Logic
    const getVisibleStatuses = () => {

        if (auth.user.role === 'superadmin') {
            return statuses;
        }

        // 1. Phase 3 (Strict Gateway): If currently claimed, ONLY show completed
        if (item.status === 'claimed') {
            return statuses.filter((s) => s.key === 'completed');
        }

        // 2. Phase 1 (Setup): Pre-payment stages
        const isSetupPhase = [
            'for approval',
            'done layout',
            'waiting for dp',
        ].includes(item.status);

        if (isSetupPhase) {
            // Show: For Approval, Done Layout, Waiting for DP, Downpayment Complete
            return statuses.filter((s) => phase1Keys.includes(s.key));
        }

        // 3. Phase 2 (Production): From 'Downpayment Complete' until 'Ready for Pickup'
        // We hide the initial setup phases and the final 'completed' status
        // (Force the user to go through 'claimed' before they can 'complete')
        return statuses.filter((s) => {
            const isInitialSetup = [
                'for approval',
                'done layout',
                'waiting for dp',
            ].includes(s.key);
            const isTerminal = s.key === 'completed';

            return !isInitialSetup && !isTerminal;
        });
    };

    const visibleStatuses = getVisibleStatuses();
    const currentStatus = statuses.find((s) => s.key === item.status) || {
        value: item.status,
        color: 'bg-gray-500',
    };

    const updateStatus = (newStatus: string) => {
        if (newStatus === item.status) {
            return;
        }

        setIsUpdating(true);

        router.patch(
            route('sublimations.update-status', item.id),
            {
                status: newStatus,
            },
            {
                preserveScroll: true,
                onSuccess: () => toast.success(`Moved to ${newStatus}`),
                onError: (err) =>
                    toast.error(Object.values(err)[0] || 'Update failed'),
                onFinish: () => setIsUpdating(false),
            },
        );
    };

    // If terminal status (Completed), just show a static badge
    if (isCompleted) {
        return (
            <Badge
                className={`border-0 capitalize shadow-none ${currentStatus.color}`}
            >
                {currentStatus.value}
            </Badge>
        );
    }

    return (
        <div className="flex items-center gap-2">
            <Select
                value={item.status}
                onValueChange={updateStatus}
                disabled={isUpdating}
            >
                <SelectTrigger className="h-8 w-fit border-none bg-transparent p-0 shadow-none outline-none focus:ring-0">
                    {isUpdating ? (
                        <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                    ) : (
                        <Badge
                            className={`cursor-pointer border-0 capitalize shadow-none transition-all hover:brightness-95 ${currentStatus.color}`}
                        >
                            {currentStatus.value}
                        </Badge>
                    )}
                </SelectTrigger>

                <SelectContent
                    position="popper"
                    side="top"
                    sideOffset={5}
                    className="max-h-64 w-[200px]"
                >
                    {visibleStatuses.map((status) => (
                        <SelectItem
                            key={status.key}
                            value={status.key}
                            disabled={status.key === item.status}
                        >
                            <div className="flex items-center gap-2">
                                <div
                                    className={`h-2 w-2 rounded-full ${status.color.split(' ')[0]}`}
                                />
                                <span className="text-sm font-medium">
                                    {status.value}
                                </span>
                            </div>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

        </div>
    );
};
