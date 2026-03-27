import { Form, useForm } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { ChevronsUpDown, Loader2, PlusIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from "@/components/ui/textarea"
import { store, update } from '@/routes/sales';
import type { Branch } from '@/types/branches';
import type { TypeOfPayment } from '@/types/settings';
import type { Transaction } from '@/types/transaction';
import type { Customer } from '@/types/user';
import { AddGuestModal } from './components/add-guest-dialog';

interface SaleDialogProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    branches: Branch[];
    transaction?: Transaction;
    types_of_payment: TypeOfPayment[];
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

export default function SaleDialog({ open, setOpen, transaction, types_of_payment, branches }: SaleDialogProps) {

    const isEdit = !!transaction;
    const { auth } = usePage().props;

    // 1. Initialize useForm with conditional data
    const { data, setData, post, put, processing, errors, reset } = useForm({
        customer_id: transaction?.customer_id || '',
        payment_type: transaction?.payment_type || '',
        branch_id: transaction?.branch_id || auth.user.branch_id || '',
        particular: transaction?.particular || '',
        description: transaction?.description || '',
        status: transaction?.status || 'pending',
        amount_total: transaction?.amount_total || '',
        amount_paid: transaction?.amount_paid || '',
        invoice_number: transaction?.invoice_number || '', // Important for unique validation
    });

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

                            {/* Customer Search */}
                            <div className="grid gap-3">
                                <Label>Customer Name</Label>
                                <Popover open={searchOpen} onOpenChange={setSearchOpen}>
                                    <PopoverTrigger asChild>
                                        <Button variant="outline" className="justify-between w-full">
                                            {displayName || "Select customer..."}
                                            <ChevronsUpDown className="ml-2 h-4 w-4 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-[400px] p-0">
                                        <Command shouldFilter={false}>
                                            <CommandInput placeholder="Search..." onValueChange={debouncedSearch} />
                                            <CommandList>
                                                {isLoading && <div className="p-4 text-center text-sm">Searching...</div>}
                                                <CommandEmpty>
                                                    <Button type="button" variant="ghost" onClick={() => setShowAddCustomer(true)}>
                                                        <PlusIcon className="mr-2 h-4 w-4" /> Add New Customer
                                                    </Button>
                                                </CommandEmpty>
                                                <CommandGroup>
                                                    {customers.map((c: Customer) => (
                                                        <CommandItem
                                                            key={c.id}
                                                            onSelect={() => handleCustomerSelect(c.id, `${c.first_name} ${c.last_name}`)}
                                                        >
                                                            {c.first_name} {c.last_name}
                                                        </CommandItem>
                                                    ))}
                                                </CommandGroup>
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                                <InputError message={errors.customer_id} />
                            </div>

                            {/* Payment Type */}
                            <div className="grid gap-3">
                                <Label htmlFor="payment_type">Type of Payment</Label>
                                <NativeSelect
                                    value={data.payment_type}
                                    onChange={e => setData('payment_type', e.target.value)}
                                >
                                    <NativeSelectOption value="">Select type</NativeSelectOption>
                                    {types_of_payment.map((payment) => (
                                        <NativeSelectOption key={payment.key} value={payment.key}>{payment.value}</NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.payment_type} />
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

                        <div className="grid grid-cols-3 gap-4">
                            <div className="grid gap-3">
                                <Label htmlFor="status">Status</Label>
                                <NativeSelect
                                    value={data.status}
                                    onChange={e => setData('status', e.target.value)}
                                >
                                    <NativeSelectOption value="pending">Pending</NativeSelectOption>
                                    <NativeSelectOption value="paid">Paid</NativeSelectOption>
                                    <NativeSelectOption value="partial">Partial</NativeSelectOption>
                                    <NativeSelectOption value="void">Void</NativeSelectOption>
                                </NativeSelect>
                                <InputError message={errors.status} />
                            </div>
                            <div className="grid gap-3">
                                <Label htmlFor="amount_total">Total Amount</Label>
                                <Input
                                    type="number"
                                    value={data.amount_total}
                                    onChange={e => setData('amount_total', e.target.value)}
                                />
                                <InputError message={errors.amount_total} />
                            </div>
                            <div className="grid gap-3">
                                <Label htmlFor="amount_paid">Amount Paid</Label>
                                <Input
                                    type="number"
                                    value={data.amount_paid}
                                    onChange={e => setData('amount_paid', e.target.value)}
                                />
                                <InputError message={errors.amount_paid} />
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
