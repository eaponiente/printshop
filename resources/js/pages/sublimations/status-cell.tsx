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
import { formatStatus } from '@/utils/formatters';

interface StatusCellProps {
    item: any;
    statuses: { key: string; value: string; color: string }[];
}

// 1. Define a standalone component for the Status Cell
export const StatusCell = ({ item, statuses }: StatusCellProps) => {
    const [isUpdating, setIsUpdating] = useState(false);

    const { auth } = usePage().props;

    const isCompleted = item.status === 'completed';

    // 1. Advanced 3-Phase Filtering Logic
    const getVisibleStatuses = () => {
        if (auth.user.role === 'superadmin') {
            return statuses;
        }

        const currentStatus = item.status;

        // 1. Phase 3: Claimed (Final Transition)
        if (currentStatus === 'claimed') {
            return statuses.filter((s) => s.key === 'completed');
        }

        // 2. Phase 1: Pre-Payment Setup
        // Goal: Show setup statuses + the 'downpayment_complete' option
        const prePaymentKeys = ['for_approval', 'done_layout', 'waiting_for_dp'];

        if (prePaymentKeys.includes(currentStatus)) {
            return statuses.filter((s) =>
                prePaymentKeys.includes(s.key) || s.key === 'downpayment_complete'
            );
        }

        // 3. Phase 2: Production
        // Triggered when currentStatus is 'downpayment_complete' or any production status
        // Goal: Exclude all Pre-Payment AND exclude 'downpayment_complete' itself
        return statuses.filter((s) => {
            const isPrePayment = prePaymentKeys.includes(s.key);
            const isDPComplete = s.key === 'downpayment_complete';
            const isTerminal = s.key === 'completed';

            // Hide pre-payment, the DP complete step, and the final 'completed'
            return !isPrePayment && !isDPComplete && !isTerminal;
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
                onSuccess: () => toast.success(`Moved to ` + formatStatus(newStatus)),
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
