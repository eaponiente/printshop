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
        // Only trigger the data sync when the modal IS opening (false -> true)
        if (open && !wasOpen.current) {
            const [f, l] = searchQuery.split(' ', 2);
            form.setData({
                first_name: f || '',
                last_name: l || '',
                company: '',
            });
        }

        wasOpen.current = open;
    }, [form, open, searchQuery]); // searchQuery is here if it changes while open, but wasOpen prevents the loop

    // Manual submission handler
    const handleAddCustomerSubmit = (e: React.MouseEvent | React.FormEvent) => {
        e.preventDefault();
        e.stopPropagation();

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

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogPortal>
                <DialogContent
                    className="sm:max-w-[425px]"
                    // Prevent the Dialog itself from passing events up
                    onPointerDownOutside={(e) => e.stopPropagation()}
                >
                    <DialogHeader>
                        <DialogTitle>Add New Customer</DialogTitle>
                    </DialogHeader>

                    {/* WE USE A DIV HERE INSTEAD OF <Form>.
                        This is the most reliable way to prevent parent form submission.
                    */}
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="first_name">First Name</Label>
                            <Input
                                id="first_name"
                                tabIndex={1}
                                value={form.data.first_name}
                                onChange={(e) =>
                                    form.setData('first_name', e.target.value)
                                }
                            />
                            <InputError message={form.errors.first_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="last_name">Last Name</Label>
                            <Input
                                id="last_name"
                                tabIndex={2}
                                value={form.data.last_name}
                                onChange={(e) =>
                                    form.setData('last_name', e.target.value)
                                }
                            />
                            <InputError message={form.errors.last_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="company">Company</Label>
                            <Input
                                id="company"
                                tabIndex={3}
                                value={form.data.company}
                                onChange={(e) =>
                                    form.setData('company', e.target.value)
                                }
                            />
                            <InputError message={form.errors.company} />
                        </div>

                        {/* Use type="button" and onClick.
                            This guarantees NO 'submit' event is ever fired.
                        */}
                        <Button
                            type="button"
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
