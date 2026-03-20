import { Form, router, useForm } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Loader2, PlusIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from "sonner"
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from "@/components/ui/textarea"
import { store } from '@/routes/sales';
import type { Branch } from '@/types/branches';
import type { Customer } from '@/types/user';
import { AddGuestModal } from './components/add-guest-dialog';

interface SaleDialogProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    branches: Branch[];
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

export default function SaleDialog({ open, setOpen, branches }: SaleDialogProps) {

    const { auth } = usePage().props;

    const [customers, setCustomers] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [showAddCustomer, setShowAddCustomer] = useState(false);
    const [displayName, setDisplayName] = useState("");
    const form = store.form();

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

    const customerInputRef = useRef<HTMLInputElement>(null);

    // 2. Manual Update Handler
    const handleManualUpdate = (id: number, name: string) => {
        if (customerInputRef.current) {
            customerInputRef.current.value = String(id);
            customerInputRef.current.dispatchEvent(new Event('change', { bubbles: true }));
            setDisplayName(name);
            setSearchOpen(false);
        }
    };

    return (
        <>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-[825px]">
                    <DialogHeader>
                        <DialogTitle>Create Transaction</DialogTitle>
                    </DialogHeader>

                    <Form
                        {...form}
                        className="flex flex-col gap-6"
                        setDefaultsOnSuccess={true}
                        onSuccess={() => {
                            toast.success('Sale saved successfully', { position: 'top-center' });
                            setOpen(false);
                        }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="customer_id" ref={customerInputRef} />
                                {/* Guest Information */}
                                <div className="grid grid-cols-2 gap-4">
                                    {/* Guest Search Section */}
                                    <div className="grid gap-3">
                                        <Label>Guest Name</Label>
                                        <Popover open={searchOpen} onOpenChange={setSearchOpen}>
                                            <PopoverTrigger asChild>
                                                <Button
                                                    variant="outline"
                                                    role="combobox"
                                                    className="justify-between w-full"
                                                >
                                                    {displayName || "Select customer..."}
                                                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent className="w-[400px] p-0">
                                                <Command shouldFilter={false}>
                                                    <CommandInput
                                                        placeholder="Type to search customers..."
                                                        onValueChange={debouncedSearch}
                                                    />
                                                    <CommandList>
                                                        {isLoading && (
                                                            <div className="flex items-center justify-center p-4 text-sm">
                                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" /> Searching...
                                                            </div>
                                                        )}
                                                        <CommandEmpty>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                className="w-full justify-start text-black-600"
                                                                onClick={() => setShowAddCustomer(true)}
                                                            >
                                                                <PlusIcon className="mr-2 h-4 w-4" /> Add New Customer
                                                            </Button>
                                                        </CommandEmpty>
                                                        <CommandGroup>
                                                            {customers.map((c: Customer) => (
                                                                <CommandItem
                                                                    key={c.id}
                                                                    onSelect={() => handleManualUpdate(c.id, `${c.first_name} ${c.last_name}`)}
                                                                >
                                                                    <div className="flex flex-col">
                                                                        <span>{c.first_name} {c.last_name}</span>
                                                                        <span className="text-[10px] text-muted-foreground uppercase">{c.company}</span>
                                                                    </div>
                                                                </CommandItem>
                                                            ))}
                                                        </CommandGroup>
                                                    </CommandList>
                                                </Command>
                                            </PopoverContent>
                                        </Popover>
                                            <InputError message={errors.customer_id} />
                                    </div>

                                    <div className="grid gap-3">
                                        <Label htmlFor="last_name">Last name</Label>
                                        <Input id="last_name" name="last_name" tabIndex={2} />
                                            <InputError message={errors.last_name} />
                                    </div>
                                </div>

                                {/* Branch and Particular */}
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-3">
                                        <Label htmlFor="branch_id">Branch</Label>
                                        <NativeSelect name="branch_id" defaultValue={auth.user.branch_id}>
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
                                        <NativeSelect name="particular">
                                            <NativeSelectOption value="">Select particular</NativeSelectOption>
                                            <NativeSelectOption value="service">Service</NativeSelectOption>
                                            <NativeSelectOption value="product">Product</NativeSelectOption>
                                        </NativeSelect>
                                            <InputError message={errors.particular} />
                                    </div>
                                </div>

                                {/* Description - Full Width */}
                                <div className="grid gap-3">
                                    <Label htmlFor="description">Description</Label>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        placeholder="Enter details..."
                                    />
                                        <InputError message={errors.description} />
                                </div>

                                {/* Payment Details */}
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-3">
                                        <Label htmlFor="payment_type">Type of Payment</Label>
                                        <NativeSelect name="payment_type">
                                            <NativeSelectOption value="">Select type</NativeSelectOption>
                                            <NativeSelectOption value="cash">Cash</NativeSelectOption>
                                            <NativeSelectOption value="card">Card</NativeSelectOption>
                                            <NativeSelectOption value="transfer">Bank Transfer</NativeSelectOption>
                                        </NativeSelect>
                                            <InputError message={errors.payment_type} />
                                    </div>

                                    <div className="grid gap-3">
                                        <Label htmlFor="status">Status</Label>
                                        <NativeSelect name="status">
                                            <NativeSelectOption value="">Select status</NativeSelectOption>
                                            <NativeSelectOption value="pending">Pending</NativeSelectOption>
                                            <NativeSelectOption value="paid">Paid</NativeSelectOption>
                                            <NativeSelectOption value="partial">Partial</NativeSelectOption>
                                        </NativeSelect>
                                            <InputError message={errors.status} />
                                    </div>
                                </div>

                                {/* Financials */}
                                <div className="grid grid-cols-3 gap-4">
                                    <div className="grid gap-3">
                                        <Label htmlFor="amount_to_pay">Amount to Pay</Label>
                                        <Input id="amount_to_pay" name="amount_to_pay" type="number" />
                                            <InputError message={errors.amount_to_pay} />
                                    </div>

                                    <div className="grid gap-3">
                                        <Label htmlFor="amount_paid">Amount Paid</Label>
                                        <Input id="amount_paid" name="amount_paid" type="number" />
                                            <InputError message={errors.amount_paid} />
                                    </div>

                                    <div className="grid gap-3">
                                        <Label htmlFor="balance">Balance</Label>
                                        <Input id="balance" name="balance" type="number" />
                                            <InputError message={errors.balance} />
                                    </div>
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-4 w-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Save Sale
                                </Button>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <AddGuestModal
                open={showAddCustomer}
                setOpen={setShowAddCustomer}
                onCustomerCreated={(newCustomer: Customer) => {
                    // This automatically selects the person you just created
                    handleManualUpdate(newCustomer.id, newCustomer.full_name)
                }}
            />

        </>
    );
}
