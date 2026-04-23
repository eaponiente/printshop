import { debounce } from 'lodash';
import React, { useState, useEffect, useMemo } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Command,
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

    const fetchCustomers = async (query = '') => {
        setIsLoading(true);
        try {
            const response = await fetch(`/api/customers?customer=${query}`);
            const data = await response.json();
            setCustomers(data);

            if (value && !selectedName) {
                const current = data.find((c: Customer) => c.id === value);
                if (current) {
                    setSelectedName(`${current.first_name} ${current.last_name}`);
                }
            }
        } catch (e) {
            console.error('Search failed', e);
        } finally {
            setIsLoading(false);
        }
    };

    const debouncedSearch = useMemo(
        () => debounce((val: string) => fetchCustomers(val), 400),
        [],
    );

    useEffect(() => {
        if (open) fetchCustomers();
    }, [open]);

    const handleSelect = (customer: Customer) => {
        setSelectedName(`${customer.first_name} ${customer.last_name}`);
        onSelect(customer);
        setOpen(false);
    };

    return (
        <div className="grid gap-1.5">
            <Label className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                {label}
            </Label>

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        className={cn(
                            'h-9 w-full justify-start px-3 font-normal shadow-none',
                            error && 'border-destructive text-destructive',
                            !selectedName && 'text-muted-foreground'
                        )}
                    >
                        {selectedName || 'Search customers...'}
                        {isLoading && <span className="ml-auto text-[10px] uppercase animate-pulse">Loading...</span>}
                    </Button>
                </PopoverTrigger>

                <PopoverContent className="w-[350px] p-0" align="start">
                    <Command shouldFilter={false}>
                        <CommandInput
                            placeholder="Type to search..."
                            onValueChange={(v) => {
                                setSearchQuery(v);
                                debouncedSearch(v);
                            }}
                            className="h-9 border-none focus:ring-0"
                        />

                        <CommandList className="max-h-[300px]">
                            {/* Create New - Positioned Above List */}
                            {onCreateNew && searchQuery && (
                                <div className="p-1 border-b">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="w-full justify-start text-xs font-medium text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                        onClick={() => onCreateNew(searchQuery)}
                                    >
                                        + Create "{searchQuery}"
                                    </Button>
                                </div>
                            )}

                            {customers.length === 0 && !isLoading && (
                                <div className="py-4 text-center text-xs text-muted-foreground">
                                    No results found.
                                </div>
                            )}

                            <CommandGroup>
                                {customers.map((c) => (
                                    <CommandItem
                                        key={c.id}
                                        onSelect={() => handleSelect(c)}
                                        className="flex flex-col items-start px-3 py-2 cursor-pointer"
                                    >
                                        <span className={cn(
                                            "text-sm",
                                            value === c.id && "font-bold text-primary"
                                        )}>
                                            {c.first_name} {c.last_name}
                                        </span>
                                        {c.company && (
                                            <span className="text-[11px] text-muted-foreground line-clamp-1">
                                                {c.company}
                                            </span>
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