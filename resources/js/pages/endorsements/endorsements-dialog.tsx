import { Form, useForm, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import type { Branch } from '@/types/branches';
import type { Endorsement } from '@/types/endorsements';

interface EndorsementDialogProps {
    branches: Branch[];
    endorsement?: Endorsement; // If null, we are in 'Create' mode
    open: boolean;
    setOpen: (open: boolean) => void;
}

export default function EndorsementDialog({
    open,
    setOpen,
    branches,
    endorsement,
}: EndorsementDialogProps) {
    const isEdit = !!endorsement;

    const { auth } = usePage().props;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        amount: endorsement?.amount ?? '',
        branch_id: endorsement?.branch_id ?? auth.user.branch_id,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            onSuccess: () => {
                toast.success(
                    isEdit ? 'Endorsement updated' : 'Endorsement created',
                );
                setOpen(false);

                if (!isEdit) {
                    reset();
                }
            },
            onError: () => {
                // Optional: specifically handle validation failures
                toast.error('Please check the form for errors.');
            },
            // This runs whether it failed or succeeded
            onFinish: () => {
                // Useful for toggling manual loading states if not using {processing}
            },
        };

        if (isEdit) {
            put(route('endorsements.update', endorsement.id), options);
        } else {
            post(route('endorsements.store'), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-[625px]">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit' : 'Add'} Endorsement
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-3">
                            <Label htmlFor="amount">Amount</Label>
                            <Input
                                id="amount"
                                defaultValue={endorsement?.amount}
                                name="amount"
                                onChange={e => setData('amount', e.target.value)}
                                tabIndex={1}
                            />
                            <InputError message={errors.amount} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="branch_id">Branch</Label>
                            <NativeSelect
                                name="branch_id"
                                onChange={(e) =>
                                    setData('branch_id', +e.target.value)
                                }
                                value={data.branch_id}
                            >
                                <NativeSelectOption value="">
                                    Select branch
                                </NativeSelectOption>
                                {branches.map((branch) => (
                                    <NativeSelectOption
                                        key={branch.id}
                                        value={branch.id}
                                    >
                                        {branch.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <InputError message={errors.branch_id} />
                        </div>
                    </div>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={processing}
                    >
                        {processing && <Spinner className="mr-2" />}
                        {isEdit ? 'Update Endorsement' : 'Submit Endorsement'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
