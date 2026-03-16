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
import { update, store } from '@/routes/branches';
import type { Branch } from '@/types/branches';

interface BranchDialogProps {
    branch?: Branch; // If null, we are in 'Create' mode
    open: boolean;
    setOpen: (open: boolean) => void;
}

export default function BranchDialog({ open, setOpen, branch }: BranchDialogProps) {

    const isEdit = !!branch;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-[625px]">
                <DialogHeader>
                    <DialogTitle>{ isEdit ? 'Edit' : 'Add'} Branch</DialogTitle>
                </DialogHeader>

                <Form
                    {...(isEdit ? update.form(branch) : store.form())}
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
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        defaultValue={branch?.name}
                                        name="name"
                                        tabIndex={1}
                                    />
                                    <InputError message={errors.name} />
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
