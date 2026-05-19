import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';
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
import { Spinner } from '@/components/ui/spinner';
import { update, store } from '@/routes/tags';
import type { Tag } from '@/types/settings';

interface BranchDialogProps {
    tag?: Tag;
    open: boolean;
    setOpen: (open: boolean) => void;
}

const randomColor = () => {
    const letters = '0123456789ABCDEF';
    let color = '#';

    for (let i = 0; i < 6; i++) {
        color += letters[Math.floor(Math.random() * 16)];
    }

    return color;
};

export default function TagDialog({ open, setOpen, tag }: BranchDialogProps) {
    const isEdit = !!tag;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: tag?.name ?? '',
        color: tag?.color ?? randomColor(),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            onSuccess: () => {
                toast.success(isEdit ? 'Tag updated' : 'Tag created');
                setOpen(false);

                if (!isEdit) {
                    reset();
                }
            },
        };

        if (isEdit && tag) {
            put(update.url(tag), options);
        } else {
            post(store.url(), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-[625px]">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Edit' : 'Add'} Tag</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    <div className="grid gap-4">
                        <div className="grid gap-3">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                tabIndex={1}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-3">
                            <Label>Color</Label>
                            <div className="flex items-center gap-3">
                                <input
                                    type="color"
                                    value={data.color}
                                    onChange={(e) =>
                                        setData('color', e.target.value)
                                    }
                                    className="h-10 w-14 cursor-pointer rounded border border-input bg-white p-1"
                                    tabIndex={2}
                                />
                                <Input
                                    type="text"
                                    value={data.color}
                                    placeholder={data.color}
                                    className="w-28 font-mono"
                                    maxLength={7}
                                    tabIndex={3}
                                    onChange={(e) =>
                                        setData('color', e.target.value)
                                    }
                                />
                            </div>
                            <InputError message={errors.color} />
                        </div>
                    </div>

                    <Button
                        type="submit"
                        className="w-full"
                        tabIndex={4}
                        disabled={processing}
                    >
                        {processing && <Spinner className="mr-2" />}
                        {isEdit ? 'Update Tag' : 'Create Tag'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
