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
import { Spinner } from '@/components/ui/spinner';
import { update, store } from '@/routes/customers';
import type { Customer } from '@/types/user';

interface CustomerDialogProps {
    customer?: Customer; // If null, we are in 'Create' mode
    open: boolean;
    setOpen: (open: boolean) => void;
}

export default function CustomerDialog({ open, setOpen, customer }: CustomerDialogProps) {

    const isEdit = !!customer;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-[625px]">
                <DialogHeader>
                    <DialogTitle>{ isEdit ? 'Edit' : 'Add'} Branch</DialogTitle>
                </DialogHeader>

                <Form
                    {...(isEdit ? update.form(customer) : store.form())}
                    className="flex flex-col gap-6"
                    setDefaultsOnSuccess={true}
                    onSuccess={() => {
                        toast.success(
                            isEdit
                                ? 'Branch update complete!'
                                : 'Branch saved successfully',
                            { position: 'top-center' },
                        );

                        setOpen(false);
                    }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4">
                                <div className="grid gap-3">
                                    <Label htmlFor="first_name">First Name</Label>
                                    <Input
                                        id="first_name"
                                        defaultValue={customer?.first_name}
                                        name="first_name"
                                        tabIndex={1}
                                    />
                                    <InputError message={errors.first_name} />
                                </div>

                                <div className="grid gap-3">
                                    <Label htmlFor="last_name">Last Name</Label>
                                    <Input
                                        id="last_name"
                                        defaultValue={customer?.last_name}
                                        name="last_name"
                                        tabIndex={2}
                                    />
                                    <InputError message={errors.last_name} />
                                </div>

                                <div className="grid gap-3">
                                    <Label htmlFor="company">Company</Label>
                                    <Input
                                        id="company"
                                        defaultValue={customer?.company}
                                        name="company"
                                        tabIndex={3}
                                    />
                                    <InputError message={errors.company} />
                                </div>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Save
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
