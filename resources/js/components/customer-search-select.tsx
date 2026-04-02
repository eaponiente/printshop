import { debounce } from 'lodash';
import {
    Check,
    ChevronsUpDown,
    User,
    Search,
    Building2,
    Loader2,
    PlusIcon,
} from 'lucide-react';
import React, { useState, useEffect, useMemo } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

interface Customer {
    id: number;
    first_name: string;
    last_name: string;
    company?: string;
}

interface CustomerSearchProps {
    value?: number | string;
    error?: string;
    label?: string;
    onSelect: (customer: Customer) => void;
    onCreateNew?: (name: string) => void;
}

const CustomerSearchSelect = ({
    value,
    error,
    label = 'Customer Name',
    onSelect,
    onCreateNew,
}: CustomerSearchProps) => {
    const [open, setOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedName, setSelectedName] = useState('');

    // 1. Internal Fetch Logic
    const fetchCustomers = async (query = '') => {
        setIsLoading(true);

        try {
            // Using your existing endpoint structure
            const response = await fetch(`/api/customers?customer=${query}`);
            const data = await response.json();
            setCustomers(data);

            // If we have a value but no display name yet, find it in the initial load
            if (value && !selectedName) {
                const current = data.find((c: Customer) => c.id === value);

                if (current) {
setSelectedName(
                        `${current.first_name} ${current.last_name}`,
                    );
}
            }
        } catch (e) {
            console.error('Search failed', e);
        } finally {
            setIsLoading(false);
        }
    };

    // 2. Optimized Debounce
    const debouncedSearch = useMemo(
        () => debounce((val: string) => fetchCustomers(val), 400),
        [],
    );

    // 3. Initial Load on Open
    useEffect(() => {
        if (open) {
            fetchCustomers();
        }
    }, [open]);

    const handleSelect = (customer: Customer) => {
        const fullName = `${customer.first_name} ${customer.last_name}`;
        setSelectedName(fullName);
        onSelect(customer);
        setOpen(false);
    };

    return (
        <div className="grid gap-3">
            <Label className="text-sm font-semibold text-foreground/70">
                {label}
            </Label>

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        className={cn(
                            'h-11 w-full justify-between px-4 shadow-sm transition-colors hover:bg-accent/50',
                            error && 'border-destructive text-destructive',
                        )}
                    >
                        <div className="flex items-center gap-2 truncate">
                            <User className="h-4 w-4 text-muted-foreground" />
                            <span
                                className={cn(
                                    !selectedName && 'text-muted-foreground',
                                )}
                            >
                                {selectedName || 'Select or search customer...'}
                            </span>
                        </div>
                        {isLoading ? (
                            <Loader2 className="h-4 w-4 animate-spin opacity-50" />
                        ) : (
                            <ChevronsUpDown className="h-4 w-4 shrink-0 opacity-50" />
                        )}
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    className="w-[400px] p-0 shadow-lg"
                    align="start"
                >
                    <Command shouldFilter={false}>
                        <div className="flex items-center border-b px-3">
                            <Search className="mr-2 h-4 w-4 shrink-0 opacity-50" />
                            <CommandInput
                                placeholder="Type name or company..."
                                onValueChange={(v) => {
                                    setSearchQuery(v);
                                    debouncedSearch(v);
                                }}
                                className="h-11 border-none focus:ring-0"
                            />
                        </div>

                        <CommandList className="max-h-[400px] overflow-y-auto">
                            {isLoading && customers.length === 0 && (
                                <div className="animate-pulse p-6 text-center text-sm text-muted-foreground">
                                    Searching...
                                </div>
                            )}

                            <CommandEmpty className="px-4 py-6">
                                <div className="flex flex-col items-center gap-3 text-center">
                                    <p className="text-sm font-medium">
                                        No results for "{searchQuery}"
                                    </p>
                                    {onCreateNew && searchQuery && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="w-full border-dashed"
                                            onClick={() =>
                                                onCreateNew(searchQuery)
                                            }
                                        >
                                            <PlusIcon className="mr-2 h-4 w-4" />
                                            Add "{searchQuery}"
                                        </Button>
                                    )}
                                </div>
                            </CommandEmpty>

                            <CommandGroup>
                                {customers.map((c) => (
                                    <CommandItem
                                        key={c.id}
                                        onSelect={() => handleSelect(c)}
                                        className="flex cursor-pointer items-start gap-3 p-3"
                                    >
                                        <div className="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <User className="h-4 w-4" />
                                        </div>
                                        <div className="flex flex-1 flex-col truncate">
                                            <span className="font-semibold">
                                                {c.first_name} {c.last_name}
                                            </span>
                                            {c.company && (
                                                <div className="flex items-center gap-1.5 text-xs text-muted-foreground italic">
                                                    <Building2 className="h-3 w-3" />
                                                    <span className="truncate">
                                                        {c.company}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                        {value === c.id && (
                                            <Check className="h-4 w-4 text-primary" />
                                        )}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            {error && <InputError message={error} />}
        </div>
    );
};

export default CustomerSearchSelect;
