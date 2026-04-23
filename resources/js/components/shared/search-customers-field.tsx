import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Command, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { AddGuestModal } from '@/pages/sales/components/add-guest-dialog';
import type { Customer } from '@/types/user';
import { debounce } from '@/utils/helpers';

export default function SearchCustomersField({ field, selectCustomer, errors }: any) {
    const [searchQuery, setSearchQuery] = useState('');
    const [customers, setCustomers] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [showAddCustomer, setShowAddCustomer] = useState(false);
    const [displayName, setDisplayName] = useState(
        field ? `${field.customer?.first_name} ${field.customer?.last_name}` : ''
    );

    useEffect(() => {
        fetchCustomers();
    }, []);

    const handleCustomerSelect = (id: number, name: string) => {
        selectCustomer(id);
        setDisplayName(name);
        setSearchOpen(false);
    };

    const debouncedSearch = useMemo(
        () => debounce((val: string) => fetchCustomers(val), 400),
        []
    );

    const fetchCustomers = async (query = '') => {
        setIsLoading(true);
        try {
            const response = await fetch(`/api/customers?customer=${query}`);
            const data = await response.json();
            setCustomers(data);
        } catch (e) {
            console.error('Search failed', e);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <>
            <div className="grid gap-1.5">
                <input type="hidden" name="customer_id" defaultValue={field?.customer_id ?? ''} />

                <Label className="ml-1 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                    Customer
                </Label>

                <Popover open={searchOpen} onOpenChange={setSearchOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            variant="outline"
                            role="combobox"
                            className="h-9 w-full justify-start px-3 text-sm font-normal shadow-none transition-colors"
                        >
                            <span className={cn('truncate', !displayName && 'text-muted-foreground')}>
                                {displayName || 'Search customers...'}
                            </span>
                            {isLoading && (
                                <span className="ml-auto text-[10px] uppercase text-muted-foreground animate-pulse">
                                    Loading...
                                </span>
                            )}
                        </Button>
                    </PopoverTrigger>

                    <PopoverContent className="w-[340px] p-0" align="start">
                        <Command shouldFilter={false}>
                            <CommandInput
                                placeholder="Type name or company..."
                                onValueChange={(v) => {
                                    setSearchQuery(v);
                                    debouncedSearch(v);
                                }}
                                className="h-9 border-none focus:ring-0"
                            />

                            <CommandList className="max-h-[300px] overflow-y-auto">
                                {/* 1. Create New - Moved to Top */}
                                {!isLoading && (
                                    <div className="border-b p-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="w-full justify-start text-xs font-semibold text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                setShowAddCustomer(true);
                                            }}
                                        >
                                            + Create "{searchQuery}"
                                        </Button>
                                    </div>
                                )}

                                {isLoading && customers.length === 0 && (
                                    <div className="p-4 text-center text-xs text-muted-foreground">
                                        Searching...
                                    </div>
                                )}

                                {customers.length === 0 && !isLoading && (
                                    <div className="p-4 text-center text-xs text-muted-foreground">
                                        No results for "{searchQuery}"
                                    </div>
                                )}

                                <CommandGroup>
                                    {customers.map((c: Customer) => {
                                        const fullName = `${c.first_name} ${c.last_name}`;
                                        const isSelected = displayName === fullName;

                                        return (
                                            <CommandItem
                                                key={c.id}
                                                onSelect={() => handleCustomerSelect(c.id, fullName)}
                                                className="flex flex-col items-start px-3 py-2 cursor-pointer"
                                            >
                                                <span className={cn(
                                                    "text-sm",
                                                    isSelected && "font-bold text-primary"
                                                )}>
                                                    {fullName}
                                                </span>
                                                {c.company && (
                                                    <span className="text-[11px] text-muted-foreground line-clamp-1 italic">
                                                        {c.company}
                                                    </span>
                                                )}
                                            </CommandItem>
                                        );
                                    })}
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
                <InputError message={errors.customer_id} />
            </div>

            <AddGuestModal
                open={showAddCustomer}
                setOpen={setShowAddCustomer}
                searchQuery={searchQuery}
                onCustomerCreated={(newCustomer: Customer) =>
                    handleCustomerSelect(
                        newCustomer.id,
                        `${newCustomer.first_name} ${newCustomer.last_name}`,
                    )
                }
            />
        </>
    );
}