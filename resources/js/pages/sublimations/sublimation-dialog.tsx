import { useForm, usePage } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { AddGuestModal } from '@/pages/sales/components/add-guest-dialog';
import type { Branch } from '@/types/branches';
import type { Sublimation, SublimationStatus } from '@/types/sublimations';
import type { Customer, User } from '@/types/user';

function debounce<T extends (...args: any[]) => any>(fn: T, delay: number) {
    let timeoutId: ReturnType<typeof setTimeout>;

    return function (...args: Parameters<T>) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
}

interface SublimationDialogProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    branches: Branch[];
    users: User[];
    statuses: SublimationStatus[];
    sublimation?: Sublimation | null;
}

export default function SublimationDialog({
    open,
    setOpen,
    branches,
    statuses,
    users,
    sublimation,
}: SublimationDialogProps) {
    const isEdit = !!sublimation;
    const { auth } = usePage().props;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        notes: sublimation?.notes ?? '',
        transaction_type: sublimation?.transaction_type ?? 'retail',
        production_authorized: sublimation?.production_authorized ?? false,
        amount_total: sublimation?.amount_total ?? '',
        amount_paid: sublimation?.amount_paid ?? '',
        description: sublimation?.description ?? '',
        branch_id: sublimation?.branch_id ?? (auth.user as any).branch_id ?? '',
        customer_id: sublimation?.customer_id ?? '',
        user_id: sublimation?.user_id ?? (auth.user as any).id ?? '',
        status: sublimation?.status ?? 'for_approval',
        due_at: sublimation?.due_at
            ? new Date(sublimation.due_at).toISOString().split('T')[0]
            : '',
    });

    const [showAddCustomer, setShowAddCustomer] = useState(false);
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [displayName, setDisplayName] = useState(
        sublimation?.customer
            ? `${sublimation.customer.first_name} ${sublimation.customer.last_name}`
            : '',
    );

    const fetchCustomers = async (query = '') => {
        setIsLoading(true);

        try {
            const response = await fetch(route('api.customers.index'));
            const result = await response.json();
            setCustomers(result);
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
        if (open) {
            fetchCustomers();
        }
    }, [open]);

    const handleCustomerSelect = (id: number, name: string) => {
        setData('customer_id', id as any);
        setDisplayName(name);
        setSearchOpen(false);
    };

    // Automatically uncheck/reset if the transaction type changes to PO
    useEffect(() => {
        if (data.transaction_type === 'purchase_order') {
            setData('production_authorized', false);
        }
    }, [data.transaction_type, setData]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            onSuccess: () => {
                toast.success(
                    isEdit ? 'Sublimation updated' : 'Sublimation created',
                );
                setOpen(false);

                if (!isEdit) {
                    reset();
                }
            },
            onError: (errors: any) => {
                // Check if the specific 'message' error from the backend exists
                if (errors.message) {
                    toast.error(errors.message);
                } else {
                    toast.error('Please check the form for errors.');
                }
            },
        };

        if (isEdit) {
            put(route('sublimations.update', sublimation.id), options);
        } else {
            post(route('sublimations.store'), options);
        }
    };

    // Define the visibility logic
    const isManagerOrAdmin = ['admin', 'superadmin'].includes(auth.user.role);
    const showAuthorization =
        isEdit && data.transaction_type === 'retail' && isManagerOrAdmin;

    return (
        <>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-175">
                    <DialogHeader>
                        <DialogTitle>
                            {isEdit ? 'Edit' : 'Add'} Sublimation
                        </DialogTitle>
                    </DialogHeader>

                    <form
                        onSubmit={handleSubmit}
                        className="flex flex-col gap-5"
                    >
                        {/* notes */}
                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Input
                                id="notes"
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                            />
                            <InputError message={errors.notes} />
                        </div>

                        {/* Description */}
                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                className="min-h-[80px] resize-none"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            {/* Customer Search */}
                            <div className="grid gap-2">
                                <input
                                    type="hidden"
                                    name="customer_id"
                                    defaultValue={data?.customer_id ?? ''}
                                />
                                <Label>Customer</Label>

                                <Popover
                                    open={searchOpen}
                                    onOpenChange={setSearchOpen}
                                >
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="w-full justify-between"
                                        >
                                            {displayName ||
                                                'Select customer...'}
                                            <ChevronsUpDown className="ml-2 h-4 w-4 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>

                                    <PopoverContent
                                        className="w-[300px] p-0"
                                        align="start"
                                    >
                                        <Command shouldFilter={false}>
                                            <CommandInput
                                                placeholder="Search customer..."
                                                onValueChange={(value) => {
                                                    setSearchQuery(value); // Capture the typed text
                                                    debouncedSearch(value); // Keep your existing debounced logic
                                                }}
                                            />
                                            <CommandList>
                                                {isLoading && (
                                                    <div className="p-4 text-center text-sm">
                                                        Searching...
                                                    </div>
                                                )}

                                                <CommandEmpty className="p-2">
                                                    <p className="mb-2 text-center text-sm">
                                                        No customers found.
                                                    </p>
                                                    <Button
                                                        variant="secondary"
                                                        className="w-full text-xs"
                                                        onClick={() => {
                                                            setSearchOpen(
                                                                false,
                                                            ); // Close search dropdown
                                                            setShowAddCustomer(
                                                                true,
                                                            ); // Open your "Create Customer" dialog
                                                        }}
                                                    >
                                                        + Add New Customer
                                                    </Button>
                                                </CommandEmpty>

                                                <CommandGroup>
                                                    {customers.map((c) => (
                                                        <CommandItem
                                                            key={c.id}
                                                            onSelect={() =>
                                                                handleCustomerSelect(
                                                                    c.id,
                                                                    `${c.first_name} ${c.last_name}`,
                                                                )
                                                            }
                                                        >
                                                            {c.first_name}{' '}
                                                            {c.last_name}
                                                        </CommandItem>
                                                    ))}
                                                </CommandGroup>
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                                <InputError message={errors.customer_id} />
                            </div>

                            {/* Branch */}
                            <div className="grid gap-2">
                                <Label htmlFor="branch_id">Branch</Label>
                                <NativeSelect
                                    value={data.branch_id}
                                    onChange={(e) => {
                                        setData({
                                            ...data,
                                            branch_id: e.target.value,
                                            user_id: '', // Reset staff when branch changes
                                        });
                                    }}
                                >
                                    <NativeSelectOption value="">
                                        Select branch
                                    </NativeSelectOption>
                                    {branches.map((b) => (
                                        <NativeSelectOption
                                            key={b.id}
                                            value={b.id}
                                        >
                                            {b.name}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.branch_id} />
                            </div>
                        </div>

                        {/* Transaction Type & Production Authorization */}
                        <div className="grid grid-cols-2 gap-4">
                            {/* Transaction Type */}
                            <div className="grid gap-2">
                                <Label htmlFor="transaction_type">
                                    Transaction Type
                                </Label>
                                <NativeSelect
                                    id="transaction_type"
                                    value={data.transaction_type}
                                    onChange={(e) =>
                                        setData(
                                            'transaction_type',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <NativeSelectOption value="retail">
                                        Retail (Standard)
                                    </NativeSelectOption>
                                    <NativeSelectOption value="purchase_order">
                                        Purchase Order (Corporate)
                                    </NativeSelectOption>
                                </NativeSelect>
                                <InputError message={errors.transaction_type} />
                            </div>

                            {/* Production Authorized (Manual Override) */}
                            {showAuthorization && (
                                <div className="flex items-end pb-2">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="production_authorized"
                                            checked={data.production_authorized}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'production_authorized',
                                                    checked as boolean,
                                                )
                                            }
                                        />
                                        <div className="grid gap-1.5 leading-none">
                                            <Label
                                                htmlFor="production_authorized"
                                                className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                            >
                                                Authorize Production
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                Bypass payment requirements.
                                            </p>
                                        </div>
                                    </div>
                                    <InputError
                                        message={errors.production_authorized}
                                    />
                                </div>
                            )}
                        </div>

                        <div
                            className={`grid gap-4 ${isEdit ? 'grid-cols-3' : 'grid-cols-2'}`}
                        >
                            {/* Assigned Staff */}
                            <div className="grid gap-2">
                                <Label htmlFor="user_id">Assigned Staff</Label>
                                <NativeSelect
                                    value={data.user_id}
                                    onChange={(e) =>
                                        setData(
                                            'user_id',
                                            e.target.value as any,
                                        )
                                    }
                                    // Disable the select if no branch is chosen yet
                                    disabled={!data.branch_id}
                                >
                                    <NativeSelectOption value="">
                                        {data.branch_id
                                            ? 'Select staff'
                                            : 'Select a branch first'}
                                    </NativeSelectOption>

                                    {/* Filter users whose branch_id matches the selected branch_id */}
                                    {users
                                        .filter(
                                            (u) =>
                                                String(u.branch_id) ===
                                                String(data.branch_id),
                                        )
                                        .map((u) => (
                                            <NativeSelectOption
                                                key={u.id}
                                                value={u.id}
                                            >
                                                {u.first_name} {u.last_name}
                                            </NativeSelectOption>
                                        ))}
                                </NativeSelect>
                                <InputError message={errors.user_id} />
                            </div>

                            {isEdit && (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="due_at">Due Date</Label>
                                        <Input
                                            id="due_at"
                                            type="date"
                                            value={data.due_at}
                                            onChange={(e) =>
                                                setData(
                                                    'due_at',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError message={errors.due_at} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <NativeSelect
                                            value={data.status}
                                            onChange={(e) =>
                                                setData(
                                                    'status',
                                                    e.target.value as any,
                                                )
                                            }
                                        >
                                            <NativeSelectOption value="">
                                                Select status
                                            </NativeSelectOption>
                                            {statuses.map((u) => (
                                                <NativeSelectOption
                                                    key={u.key}
                                                    value={u.key}
                                                >
                                                    {u.value}
                                                </NativeSelectOption>
                                            ))}
                                        </NativeSelect>
                                        <InputError message={errors.status} />
                                    </div>
                                </>
                            )}

                            {!isEdit && (
                                <div className="grid gap-2">
                                    <Label htmlFor="due_at">Due Date</Label>
                                    <Input
                                        id="due_at"
                                        type="date"
                                        value={data.due_at}
                                        onChange={(e) =>
                                            setData('due_at', e.target.value)
                                        }
                                    />
                                    <InputError message={errors.due_at} />
                                </div>
                            )}
                        </div>

                        {/* notes */}
                        <div className="grid grid-cols-1 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="amount_total">
                                    Total Amount
                                </Label>
                                <Input
                                    id="amount_total"
                                    value={data.amount_total}
                                    onChange={(e) =>
                                        setData('amount_total', e.target.value)
                                    }
                                />
                                <InputError message={errors.amount_total} />
                            </div>
                        </div>

                        <Button
                            type="submit"
                            className="mt-2"
                            disabled={processing}
                        >
                            {processing && <Spinner className="mr-2" />}
                            {isEdit
                                ? 'Update Sublimation'
                                : 'Create Sublimation'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>

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
