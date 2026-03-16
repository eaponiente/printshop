import { Form } from '@inertiajs/react';
import { toast } from "sonner"
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/users';
import type { User } from '@/types';
import type { Branch } from '@/types/user';

interface UserDialogProps {
    user?: User; // If null, we are in 'Create' mode
    open: boolean;
    setOpen: (open: boolean) => void;
    branches: Branch[];
}

export default function UserDialog({ open, setOpen, user, branches }: UserDialogProps) {

    const isEdit = !!user;

    const roles = [
        {
            id: 'staff',
            name: 'Staff',
        },
        {
            id: 'admin',
            name: 'Admin',
        }
    ]

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-[625px]">
                <DialogHeader>
                    <DialogTitle>Add Staff</DialogTitle>
                </DialogHeader>

                <Form
                    {...(isEdit ? update.form(user) : store.form())}
                    className="flex flex-col gap-6"
                    setDefaultsOnSuccess={true}
                    onSuccess={() => {
                        toast.success(
                            isEdit
                                ? 'User update complete!'
                                : 'User saved successfully',
                            { position: 'top-center' }
                        );

                        setOpen(false);
                    }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-3">
                                    <Label htmlFor="first_name">
                                        First name
                                    </Label>
                                    <Input
                                        id="first_name"
                                        defaultValue={user?.first_name}
                                        name="first_name"
                                        tabIndex={1}
                                    />
                                    <InputError message={errors.first_name} />
                                </div>

                                <div className="grid gap-3">
                                    <Label htmlFor="last_name">Last name</Label>
                                    <Input
                                        defaultValue={user?.last_name}
                                        id="last_name"
                                        name="last_name"
                                        tabIndex={2}
                                    />
                                    <InputError message={errors.last_name} />
                                </div>
                            </div>
                            <div className="grid gap-6">

                                <div className="grid gap-3">
                                    <Label htmlFor="branch_id">Branch</Label>
                                    <NativeSelect
                                        name={'branch_id'}
                                        defaultValue={user?.branch_id}
                                    >
                                        <NativeSelectOption value="">
                                            Select branch
                                        </NativeSelectOption>
                                        {branches.map((branch) => (
                                            <NativeSelectOption
                                                value={branch.id}
                                            >
                                                {branch.name}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <InputError message={errors.branch_id} />
                                </div>


                            </div>
                            <div className="grid grid-cols-2 gap-6">
                                <div className="grid gap-3">
                                    <Label htmlFor="username">Username</Label>
                                    <Input
                                        id="username"
                                        defaultValue={user?.username}
                                        type="text"
                                        name="username"
                                        tabIndex={3}
                                    />
                                    <InputError message={errors.username} />
                                </div>
                                <div className="grid gap-3">
                                    <Label htmlFor="role">Role</Label>
                                    <NativeSelect
                                        name={'role'}
                                        defaultValue={user?.role}
                                    >
                                        <NativeSelectOption value="">
                                            Select role
                                        </NativeSelectOption>
                                        {roles.map((role) => (
                                            <NativeSelectOption
                                                value={role.id}
                                            >
                                                {role.name}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <InputError message={errors.role} />
                                </div>


                            </div>
                            <div className="grid grid-cols-2 gap-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        tabIndex={4}
                                        autoComplete="current-password"
                                        placeholder="Password"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm Password
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        tabIndex={5}
                                        placeholder="Confirm Password"
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
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
