import { useForm } from '@inertiajs/react';
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
import type { Sublimation } from '@/types/sublimations';

interface SewedItemDialogProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    sublimation: Sublimation;
}

export default function SewedItemDialog({
    open,
    setOpen,
    sublimation,
}: SewedItemDialogProps) {
    const quantity = sublimation.quantity ?? 0;

    const { data, setData, post, processing, errors } = useForm({
        sublimation_id: sublimation.id,
        quantity: String(quantity),
        unit_price: '',
    });

    const amount = data.unit_price
        ? (Number(quantity) * Number(data.unit_price)).toFixed(2)
        : '0.00';

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/payroll/sewed-items', {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Create Sewed Item</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <div className="text-sm text-muted-foreground">
                        {sublimation.description}
                    </div>

                    <div className="grid gap-2">
                        <Label>Quantity</Label>
                        <p className="text-sm font-medium">{quantity}</p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="unit_price">Unit Price</Label>
                        <Input
                            id="unit_price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.unit_price}
                            onChange={(e) =>
                                setData('unit_price', e.target.value)
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="amount">Amount</Label>
                        <Input
                            id="amount"
                            value={amount}
                            readOnly
                            className="bg-muted"
                        />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner className="mr-2" />}
                        Save
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
