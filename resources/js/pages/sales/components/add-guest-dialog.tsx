import { useForm } from '@inertiajs/react'; // Use the hook instead of the component
import { useEffect, useRef } from 'react';
import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogPortal,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Customer } from '@/types/user';

interface AddGuestModalProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    searchQuery: string;
    onCustomerCreated: (customer: Customer) => void;
}

export function AddGuestModal({
    open,
    setOpen,
    searchQuery,
    onCustomerCreated,
}: AddGuestModalProps) {
    const form = useForm({
        first_name: '',
        last_name: '',
        company: '',
    });

    const wasOpen = useRef(false);

    useEffect(() => {
        if (open && !wasOpen.current) {
            const [f, l] = searchQuery.split(' ', 2);
            form.setData({
                first_name: f || '',
                last_name: l || '',
                company: '',
            });
        }
        wasOpen.current = open;
    }, [form.setData, open, searchQuery]);

    const handleAddCustomerSubmit = (e?: React.FormEvent | React.MouseEvent) => {
        // Essential: prevent both default behavior and event bubbling to parent form
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        form.post(route('customers.store'), {
            onSuccess: (page) => {
                const newCustomer = (page.props as any).flash?.new_customer;
                if (newCustomer) {
                    onCustomerCreated(newCustomer);
                    form.reset();
                    setOpen(false);
                }
            },
        });
    };

    // Helper to allow "Enter" key submission without a "submit" button
    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            handleAddCustomerSubmit();
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogPortal>
                {/* onPointerDownOutside and onInteractOutside prevent clicks from affecting parent */}
                <DialogContent
                    className="sm:max-w-[425px]"
                    onPointerDownOutside={(e) => e.stopPropagation()}
                    onInteractOutside={(e) => e.stopPropagation()}
                >
                    <DialogHeader>
                        <DialogTitle>Add New Customer</DialogTitle>
                    </DialogHeader>

                    {/* Remove <form> tag and use a <div> to avoid any 
                        automatic HTML form submission logic.
                    */}
                    <div className="grid gap-4" onKeyDown={handleKeyDown}>
                        <div className="grid gap-2">
                            <Label htmlFor="first_name">First Name</Label>
                            <Input
                                id="first_name"
                                value={form.data.first_name}
                                onChange={(e) => form.setData('first_name', e.target.value)}
                            />
                            <InputError message={form.errors.first_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="last_name">Last Name</Label>
                            <Input
                                id="last_name"
                                value={form.data.last_name}
                                onChange={(e) => form.setData('last_name', e.target.value)}
                            />
                            <InputError message={form.errors.last_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="company">Company</Label>
                            <Input
                                id="company"
                                value={form.data.company}
                                onChange={(e) => form.setData('company', e.target.value)}
                            />
                            <InputError message={form.errors.company} />
                        </div>

                        <Button
                            type="button" // Changed from "submit"
                            disabled={form.processing}
                            onClick={handleAddCustomerSubmit}
                        >
                            {form.processing ? <Spinner /> : 'Create Customer'}
                        </Button>
                    </div>
                </DialogContent>
            </DialogPortal>
        </Dialog>
    );
}