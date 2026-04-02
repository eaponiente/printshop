import { useForm, usePage } from '@inertiajs/react';
import { ChevronsUpDown, Loader2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
        description: sublimation?.description ?? '',
        branch_id: sublimation?.branch_id ?? (auth.user as any).branch_id ?? '',
        customer_id: sublimation?.customer_id ?? '',
        user_id: sublimation?.user_id ?? (auth.user as any).id ?? '',
        status: sublimation?.status ?? 'for_approval',
        due_at: sublimation?.due_at
            ? new Date(sublimation.due_at).toISOString().split('T')[0]
            : '',
    });

    const [customers, setCustomers] = useState<Customer[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [displayName, setDisplayName] = useState(
        sublimation?.customer
            ? `${sublimation.customer.first_name} ${sublimation.customer.last_name}`
            : '',
    );

    const fetchCustomers = async (query = '') => {
        setIsLoading(true);

        try {
            const response = await fetch(`/api/customers?customer=${query}`);
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

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            onSuccess: () => {
                toast.success(isEdit ? 'Sublimation updated' : 'Sublimation created');
                setOpen(false);

                if (!isEdit) {
                    reset();
                }
            },
            onError: () => toast.error('Please check the form for errors.'),
        };

        if (isEdit) {
            put(route('sublimations.update', sublimation.id), options);
        } else {
            post(route('sublimations.store'), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-[700px]">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit' : 'Add'} Sublimation
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-5">
                    {/* notes */}
                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <Input
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
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
                                        {displayName || 'Select customer...'}
                                        <ChevronsUpDown className="ml-2 h-4 w-4 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-[300px] p-0">
                                    <Command shouldFilter={false}>
                                        <CommandInput
                                            placeholder="Search..."
                                            onValueChange={debouncedSearch}
                                        />
                                        <CommandList>
                                            {isLoading && (
                                                <div className="p-4 text-center text-sm">
                                                    Searching...
                                                </div>
                                            )}
                                            <CommandEmpty>
                                                No customers found.
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
                                onChange={(e) =>
                                    setData('branch_id', e.target.value as any)
                                }
                            >
                                <NativeSelectOption value="">
                                    Select branch
                                </NativeSelectOption>
                                {branches.map((b) => (
                                    <NativeSelectOption key={b.id} value={b.id}>
                                        {b.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <InputError message={errors.branch_id} />
                        </div>
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                        {/* Assigned Staff */}
                        <div className="grid gap-2">
                            <Label htmlFor="user_id">Assigned Staff</Label>
                            <NativeSelect
                                value={data.user_id}
                                onChange={(e) =>
                                    setData('user_id', e.target.value as any)
                                }
                            >
                                <NativeSelectOption value="">
                                    Select staff
                                </NativeSelectOption>
                                {users.map((u) => (
                                    <NativeSelectOption key={u.id} value={u.id}>
                                        {u.first_name} {u.last_name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <InputError message={errors.user_id} />
                        </div>

                        {isEdit && (
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

                        {/* Status */}
                        {!isEdit && (
                            <div className="grid gap-2">
                                <Label htmlFor="status">Status</Label>
                                <NativeSelect
                                    value={data.status}
                                    onChange={(e) =>
                                        setData('status', e.target.value as any)
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

                    <Button
                        type="submit"
                        className="mt-2"
                        disabled={processing}
                    >
                        {processing && <Spinner className="mr-2" />}
                        {isEdit ? 'Update Sublimation' : 'Create Sublimation'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
