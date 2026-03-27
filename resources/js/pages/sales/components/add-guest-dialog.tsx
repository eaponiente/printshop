import { Form } from '@inertiajs/react';
import { toast } from "sonner"
import InputError from '@/components/input-error';
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Spinner } from '@/components/ui/spinner'; // Assuming you have a guest route store
import { store as guestStore } from '@/routes/customers';
import type { Customer } from '@/types/user';


interface AddGuestModalProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    onCustomerCreated: (customer: Customer) => void;
}

export function AddGuestModal({ open, setOpen, onCustomerCreated }: AddGuestModalProps) {
    const form = guestStore.form();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Add New Customer</DialogTitle>
                </DialogHeader>

                <Form
                    {...form}
                    className="grid gap-4"
                    onSuccess={(page) => {
                        // If you followed the middleware step above, it should be here:
                        const newCustomer = (page.props as any).flash?.new_customer;

                        if (newCustomer) {
                            onCustomerCreated(newCustomer);
                            setOpen(false);
                        } else {
                            console.error("New customer data missing from Inertia props!");
                        }
                    }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="first_name">First Name</Label>
                                <Input id="first_name" name="first_name" autoFocus />
                                <InputError message={errors.first_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="last_name">Last Name</Label>
                                <Input id="last_name" name="last_name" autoFocus />
                                <InputError message={errors.last_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="company">Company</Label>
                                <Input id="company" name="company" autoFocus />
                                <InputError message={errors.company} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing ? <Spinner /> : "Create Customer"}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
