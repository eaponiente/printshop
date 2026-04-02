import { useForm } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Loader2, PlusIcon, Search, User } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from "sonner"
import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import { Button } from "@/components/ui/button"
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Textarea } from "@/components/ui/textarea"
import { cn } from '@/lib/utils';
import type { Branch } from '@/types/branches';
import type { Transaction } from '@/types/transaction';
import type { Customer } from '@/types/user';
import { AddGuestModal } from './components/add-guest-dialog';

interface SaleDialogProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    branches: Branch[];
    transaction?: Transaction;
    customers: Customer[];
}

function debounce<T extends (...args: any[]) => any>(fn: T, delay: number) {
    let timeoutId: ReturnType<typeof setTimeout>;

    return function (...args: Parameters<T>) {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        timeoutId = setTimeout(() => fn(...args), delay);
    };
}

export default function SaleDialog({ open, setOpen, transaction, branches }: SaleDialogProps) {

    const isEdit = !!transaction;
    const { auth } = usePage().props;

    // 1. Initialize useForm with conditional data
    const { data, setData, post, put, processing, errors, reset } = useForm({
        customer_id: transaction?.customer_id || '',
        branch_id: transaction?.branch_id || auth.user.branch_id || '',
        particular: transaction?.particular || '',
        description: transaction?.description || '',
        status: transaction?.status || 'pending',
        amount_total: transaction?.amount_total || '',
        amount_paid: transaction?.amount_paid || '',
        invoice_number: transaction?.invoice_number || '', // Important for unique validation
    });

    const [searchQuery, setSearchQuery] = useState("");
    const [customers, setCustomers] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [showAddCustomer, setShowAddCustomer] = useState(false);
    const [displayName, setDisplayName] = useState(
        transaction ? `${transaction.customer?.first_name} ${transaction.customer?.last_name}` : ""
    );

    // 1. Fetching Logic (Isolated from Inertia)
    const fetchCustomers = async (query = '') => {
        setIsLoading(true);

        try {
            const response = await fetch(`/api/customers?customer=${query}`);
            const data = await response.json();
            setCustomers(data);
        } catch (e) {
            console.error("Search failed", e);
        } finally {
            setIsLoading(false);
        }
    };

    const debouncedSearch = useMemo(
        () => debounce((val: string) => fetchCustomers(val), 400),
        []
    );

    // Initial load
    useEffect(() => {
        if (open) {
            fetchCustomers();
        }
    }, [open]);

    // 2. Handle Submit
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            onSuccess: () => {
                toast.success(`Sale ${isEdit ? 'updated' : 'saved'} successfully`);
                setOpen(false);

                if (!isEdit) {
                    reset();
                }
            },
        };

        if (isEdit) {
            put(route('sales.update', transaction.id), options);
        } else {
            post(route('sales.store'), options);
        }
    };

    const handleCustomerSelect = (id: number, name: string) => {
        setData('customer_id', id);
        setDisplayName(name);
        setSearchOpen(false);
    };

    return (
        <>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-[825px]">
                    <DialogHeader>
                        <DialogTitle>{isEdit ? 'Update' : 'Create'} Transaction</DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                        <div className="grid grid-cols-2 gap-4">
                            <input
                                type="hidden"
                                name="customer_id"
                                defaultValue={transaction?.customer_id ?? ""}
                            />

                            <div className="grid gap-3">
                                <Label className="text-sm font-semibold text-foreground/70">Customer Name</Label>
                                <Popover open={searchOpen} onOpenChange={setSearchOpen}>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            role="combobox"
                                            aria-expanded={searchOpen}
                                            className="justify-between w-full h-11 px-4 shadow-sm hover:bg-accent/50 transition-colors"
                                        >
                                            <div className="flex items-center gap-2 truncate">
                                                <User className="h-4 w-4 text-muted-foreground" />
                                                <span className={cn(!displayName && "text-muted-foreground")}>
                        {displayName || "Select or search customer..."}
                    </span>
                                            </div>
                                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-[400px] p-0 shadow-lg" align="start">
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
                                            <CommandList className="max-h-[450px] min-h-[200px] overflow-y-auto scrollbar-thin scrollbar-thumb-gray-200">
                                                {isLoading && (
                                                    <div className="flex items-center justify-center p-6 text-sm text-muted-foreground">
                                                        <span className="animate-pulse">Searching for customers...</span>
                                                    </div>
                                                )}

                                                <CommandEmpty className="py-6 px-4">
                                                    <div className="flex flex-col items-center gap-3 text-center">
                                                        <div className="rounded-full bg-muted p-3">
                                                            <Search className="h-6 w-6 text-muted-foreground" />
                                                        </div>
                                                        <div>
                                                            <p className="text-sm font-medium">No results found</p>
                                                            <p className="text-xs text-muted-foreground text-balance">
                                                                We couldn't find a customer matching "{searchQuery}"
                                                            </p>
                                                        </div>

                                                        {searchQuery && (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                className="mt-2 w-full border-dashed bg-primary/5 hover:bg-primary/10 hover:border-primary/50 transition-all"
                                                                onClick={() => {
                                                                    // You can pass searchQuery to your 'Add Customer' modal/form
                                                                    setShowAddCustomer(true);
                                                                }}
                                                            >
                                                                <PlusIcon className="mr-2 h-4 w-4" />
                                                                Create <span className="font-bold px-1">"{searchQuery}"</span> as new customer
                                                            </Button>
                                                        )}
                                                    </div>
                                                </CommandEmpty>

                                                <CommandGroup heading="Recent or Found Customers">
                                                    {customers.map((c: Customer) => {
                                                        const fullName = `${c.first_name} ${c.last_name}`;
                                                        const isSelected = displayName === fullName;

                                                        return (
                                                            <CommandItem
                                                                key={c.id}
                                                                onSelect={() => handleCustomerSelect(c.id, fullName)}
                                                                className="flex items-start gap-3 p-3 cursor-pointer data-[selected=true]:bg-accent"
                                                            >
                                                                <div className="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                                                    <User className="h-4 w-4" />
                                                                </div>

                                                                <div className="flex flex-col flex-1 overflow-hidden">
                                                                    <div className="flex items-center justify-between">
                                            <span className="font-semibold truncate text-sm">
                                                {fullName}
                                            </span>
                                                                        {isSelected && <Check className="h-4 w-4 text-primary" />}
                                                                    </div>

                                                                    {/* Company Logic */}
                                                                    {c.company && (
                                                                        <div className="flex items-center gap-1.5 mt-0.5 text-xs text-muted-foreground">
                                                                            <Building2 className="h-3 w-3 shrink-0" />
                                                                            <span className="truncate italic">{c.company}</span>
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


                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-3">
                                <Label htmlFor="branch_id">Branch</Label>
                                <NativeSelect
                                    value={data.branch_id}
                                    onChange={e => setData('branch_id', e.target.value)}
                                >
                                    <NativeSelectOption value="">Select branch</NativeSelectOption>
                                    {branches.map((branch) => (
                                        <NativeSelectOption key={branch.id} value={branch.id}>
                                            {branch.name}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.branch_id} />
                            </div>

                            <div className="grid gap-3">
                                <Label htmlFor="particular">Particular</Label>
                                <Input
                                    type="string"
                                    value={data.particular}
                                    onChange={e => setData('particular', e.target.value)}
                                />
                                <InputError message={errors.particular} />
                            </div>
                        </div>

                        <div className="grid gap-3">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                value={data.description}
                                onChange={e => setData('description', e.target.value)}
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid grid-cols-1 gap-4">
                            <div className="grid gap-3">
                                <Label htmlFor="amount_total">Total Amount</Label>
                                <Input
                                    type="number"
                                    value={data.amount_total}
                                    onChange={e => setData('amount_total', e.target.value)}
                                />
                                <InputError message={errors.amount_total} />
                            </div>
                        </div>

                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            {isEdit ? 'Update Sale' : 'Save Sale'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>

            <AddGuestModal
                open={showAddCustomer}
                setOpen={setShowAddCustomer}
                onCustomerCreated={(newCustomer: Customer) => handleCustomerSelect(newCustomer.id, `${newCustomer.first_name} ${newCustomer.last_name}`)}
            />
        </>
    );
}
