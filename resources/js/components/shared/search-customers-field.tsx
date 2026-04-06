import {
    Building2,
    Check,
    ChevronsUpDown,
    PlusIcon,
    Search,
    User,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
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
        field
            ? `${field.customer?.first_name} ${field.customer?.last_name}`
            : '',
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
        [],
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
            <div className="grid gap-3">
                <input
                    type="hidden"
                    name="customer_id"
                    defaultValue={field?.customer_id ?? ''}
                />
                <Label className="text-sm font-semibold text-foreground/70">
                    Customer Name
                </Label>
                <Popover open={searchOpen} onOpenChange={setSearchOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            variant="outline"
                            role="combobox"
                            aria-expanded={searchOpen}
                            className="h-11 w-full justify-between px-4 shadow-sm transition-colors hover:bg-accent/50"
                        >
                            <div className="flex items-center gap-2 truncate">
                                <User className="h-4 w-4 text-muted-foreground" />
                                <span
                                    className={cn(
                                        !displayName && 'text-muted-foreground',
                                    )}
                                >
                                    {displayName ||
                                        'Select or search customer...'}
                                </span>
                            </div>
                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent
                        className="w-[400px] p-0 shadow-lg"
                        align="start"
                    >
                        <Command shouldFilter={false} className="rounded-lg">
                            <div className="flex items-center border-b px-3">
                                <Search className="mr-2 h-4 w-4 shrink-0 opacity-50" />
                                <CommandInput
                                    placeholder="Type name or company..."
                                    onValueChange={(v) => {
                                        setSearchQuery(v); // Update local state for the UI
                                        debouncedSearch(v); // Trigger your existing API search
                                    }}
                                    className="h-11 border-none focus:ring-0"
                                />
                            </div>
                            <CommandList className="scrollbar-thin scrollbar-thumb-gray-200 max-h-[450px] min-h-[200px] overflow-y-auto">
                                {isLoading && (
                                    <div className="flex items-center justify-center p-6 text-sm text-muted-foreground">
                                        <span className="animate-pulse">
                                            Searching for customers...
                                        </span>
                                    </div>
                                )}

                                <CommandEmpty className="px-4 py-6">
                                    <div className="flex flex-col items-center gap-3 text-center">
                                        <div className="rounded-full bg-muted p-3">
                                            <Search className="h-6 w-6 text-muted-foreground" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">
                                                No results found
                                            </p>
                                            <p className="text-xs text-balance text-muted-foreground">
                                                We couldn't find a customer
                                                matching "{searchQuery}"
                                            </p>
                                        </div>

                                        {searchQuery && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="mt-2 w-full border-dashed bg-primary/5 transition-all hover:border-primary/50 hover:bg-primary/10"
                                                onClick={() => {
                                                    // You can pass searchQuery to your 'Add Customer' modal/form
                                                    setShowAddCustomer(true);
                                                }}
                                            >
                                                <PlusIcon className="mr-2 h-4 w-4" />
                                                Create{' '}
                                                <span className="px-1 font-bold">
                                                    "{searchQuery}"
                                                </span>{' '}
                                                as new customer
                                            </Button>
                                        )}
                                    </div>
                                </CommandEmpty>

                                <CommandGroup heading="Recent or Found Customers">
                                    {customers.map((c: Customer) => {
                                        const fullName = `${c.first_name} ${c.last_name}`;
                                        const isSelected =
                                            displayName === fullName;

                                        return (
                                            <CommandItem
                                                key={c.id}
                                                onSelect={() =>
                                                    handleCustomerSelect(
                                                        c.id,
                                                        fullName,
                                                    )
                                                }
                                                className="flex cursor-pointer items-start gap-3 p-3 data-[selected=true]:bg-accent"
                                            >
                                                <div className="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                                    <User className="h-4 w-4" />
                                                </div>

                                                <div className="flex flex-1 flex-col overflow-hidden">
                                                    <div className="flex items-center justify-between">
                                                        <span className="truncate text-sm font-semibold">
                                                            {fullName}
                                                        </span>
                                                        {isSelected && (
                                                            <Check className="h-4 w-4 text-primary" />
                                                        )}
                                                    </div>

                                                    {/* Company Logic */}
                                                    {c.company && (
                                                        <div className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                                            <Building2 className="h-3 w-3 shrink-0" />
                                                            <span className="truncate italic">
                                                                {c.company}
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                            </CommandItem>
                                        );
                                    })}
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
                <InputError message={errors.customer_id} className="mt-1" />
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
