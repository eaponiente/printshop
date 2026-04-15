import { Check, ChevronsUpDown } from 'lucide-react';
import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { SublimationStatus } from '@/types/sublimations';

export function StatusFilter({ filters, statuses, handleFilterChange }: any) {
    // Assuming filters.status is now an array: string[]
    const selectedValues = new Set(
        Array.isArray(filters.status)
            ? filters.status
            : filters.status
              ? [filters.status]
              : [],
    );

    const toggleStatus = (value: string) => {
        const newValues = new Set(selectedValues);

        console.log('new', value);

        if (newValues.has(value)) {
            newValues.delete(value);
        } else {
            newValues.add(value);
        }


        handleFilterChange(Array.from(newValues), 'status');
    };

    return (
        <div className="flex flex-col gap-1.5">
            <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                Status
            </label>
            <Popover>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        className="h-10 w-[200px] justify-between bg-white text-sm font-normal"
                    >
                        <div className="flex gap-1 truncate">
                            {selectedValues.size > 0 ? (
                                selectedValues.size > 2 ? (
                                    <Badge
                                        variant="secondary"
                                        className="rounded-sm px-1 font-normal"
                                    >
                                        {selectedValues.size} selected
                                    </Badge>
                                ) : (
                                    statuses
                                        .filter((s: SublimationStatus) =>
                                            selectedValues.has(s.key),
                                        )
                                        .map((s: SublimationStatus) => (
                                            <Badge
                                                variant="secondary"
                                                key={s.key}
                                                className="rounded-sm px-1 font-normal"
                                            >
                                                {s.value}
                                            </Badge>
                                        ))
                                )
                            ) : (
                                <span className="text-muted-foreground">
                                    All status
                                </span>
                            )}
                        </div>
                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-[200px] p-0" align="start">
                    <Command>
                        <CommandInput placeholder="Search status..." />
                        <CommandEmpty>No status found.</CommandEmpty>
                        <CommandGroup>
                            {statuses.map((status: SublimationStatus) => (
                                <CommandItem
                                    key={status.key}
                                    onSelect={() => toggleStatus(status.key)}
                                >
                                    <div
                                        className={cn(
                                            'mr-2 flex h-4 w-4 items-center justify-center rounded-sm border border-primary',
                                            selectedValues.has(status.key)
                                                ? 'bg-primary text-primary-foreground'
                                                : 'opacity-50 [&_svg]:invisible',
                                        )}
                                    >
                                        <Check className={cn('h-4 w-4')} />
                                    </div>
                                    <span>{status.value}</span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                        {selectedValues.size > 0 && (
                            <>
                                <div className="h-[1px] bg-border" />
                                <CommandGroup>
                                    <CommandItem
                                        onSelect={() =>
                                            handleFilterChange([], 'status')
                                        }
                                        className="justify-center text-center"
                                    >
                                        Clear filters
                                    </CommandItem>
                                </CommandGroup>
                            </>
                        )}
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    );
}
