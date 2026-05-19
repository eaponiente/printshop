import { router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { Calendar as CalendarIcon, X } from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { readableDate } from '@/utils/dateHelper';

interface Props {
    recordId: number;
    dueAt: string | null; // Expects ISO string from Laravel (Y-m-d)
}

export function EditableDateCell({ recordId, dueAt }: Props) {
    const [open, setOpen] = useState(false);

    // Parse the string from the database into a Date object for the Calendar
    const dateValue = dueAt ? parseISO(dueAt) : undefined;

    const status = dueAt
        ? readableDate(dueAt)
        : { text: 'Set due date', className: 'text-muted-foreground/60' };

    const updateDate = (newDate: Date | undefined) => {
        // 1. Close immediately for a snappy UX
        setOpen(false);

        // 2. Send to Laravel
        router.patch(
            route('sublimations.update-duedate', recordId),
            {
                due_at: newDate ? format(newDate, 'yyyy-MM-dd') : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    // Optional: add a toast notification here
                },
                onError: (errors: any) => {
                    // Revert to old date if something goes wrong
                    // use toast and get validation backend error
                    setOpen(false);
                    console.log('errors', errors);
                    toast.error('Due date must be today or later.');
                },
            },
        );
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    className={cn(
                        'group flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left transition-all',
                        'hover:bg-accent hover:text-accent-foreground',
                        !dueAt && 'text-muted-foreground/60',
                    )}
                >
                    {/* The Identifier: Calendar Icon */}
                    <CalendarIcon
                        className={cn(
                            'h-3.5 w-3.5 shrink-0 transition-colors',
                            dueAt ? 'text-primary' : 'text-muted-foreground/40',
                        )}
                    />

                    {/* The Date Text with Dotted Underline */}
                    <span
                        className={cn(
                            'truncate border-b border-dotted text-xs transition-colors',
                            'border-muted-foreground/30 group-hover:border-primary/50',
                            status.className, // This applies your Red/Green/Slate logic
                        )}
                    >
                        {status.text}
                    </span>
                </button>
            </PopoverTrigger>

            <PopoverContent
                className="w-auto border-muted p-0 shadow-xl"
                align="start"
            >
                {/* Header with Quick Actions */}
                <div className="flex items-center justify-between border-b bg-muted/20 p-2">
                    <span className="px-2 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                        Due Date
                    </span>
                    {dueAt && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 px-2 text-[10px] text-destructive hover:bg-destructive/10"
                            onClick={(e) => {
                                e.stopPropagation();
                                updateDate(undefined);
                            }}
                        >
                            <X className="mr-1 h-3 w-3" />
                            Clear
                        </Button>
                    )}
                </div>

                <Calendar
                    mode="single"
                    selected={dateValue}
                    onSelect={updateDate}
                    initialFocus
                    // styling the calendar to be slightly more compact for table use
                    className="p-3"
                />
            </PopoverContent>
        </Popover>
    );
}
