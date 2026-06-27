import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
    const tags = sublimation.tags ?? [];

    const [quantities, setQuantities] = useState<Record<number, string>>(
        Object.fromEntries(tags.map((t) => [t.id, '0'])),
    );

    const [prices, setPrices] = useState<Record<number, string>>(
        Object.fromEntries(tags.map((t) => [t.id, t.price_per_piece ?? '0'])),
    );

    const [processing, setProcessing] = useState(false);

    const totalQty = Object.values(quantities).reduce(
        (sum, q) => sum + (Number(q) || 0),
        0,
    );

    const totalAmount = tags.reduce((sum, tag) => {
        const qty = Number(quantities[tag.id]) || 0;
        const price = Number(prices[tag.id]) || 0;

        return sum + qty * price;
    }, 0);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const tagItems = tags
            .filter((t) => Number(quantities[t.id]) > 0)
            .map((t) => ({
                tag_id: t.id,
                quantity: Number(quantities[t.id]),
                price_per_piece: Number(prices[t.id]) || 0,
            }));

        if (tagItems.length === 0) {
            toast.error('Enter at least one quantity');

            return;
        }

        setProcessing(true);

        router.post(
            '/payroll/sewed-items',
            {
                sublimation_id: sublimation.id,
                tags: tagItems,
            },
            {
                onSuccess: () => setOpen(false),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create Sewed Item</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <div className="text-sm text-muted-foreground">
                        {sublimation.description}
                    </div>

                    {tags.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No categories assigned to this sublimation.
                        </p>
                    ) : (
                        <>
                            <div className="overflow-hidden rounded-md border">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/50">
                                            <th className="px-3 py-2 text-left font-medium">
                                                Category
                                            </th>
                                            <th className="w-28 px-3 py-2 text-left font-medium">
                                                Quantity
                                            </th>
                                            <th className="w-32 px-3 py-2 text-right font-medium">
                                                Price per piece
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tags.map((tag) => (
                                            <tr
                                                key={tag.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-3 py-2">
                                                    <span className="inline-flex items-center gap-1.5">
                                                        <span
                                                            className="inline-block h-3 w-3 rounded-full"
                                                            style={{
                                                                backgroundColor:
                                                                    tag.color,
                                                            }}
                                                        />
                                                        {tag.name}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Input
                                                        type="text"
                                                        value={
                                                            quantities[tag.id]
                                                        }
                                                        onChange={(e) =>
                                                            setQuantities(
                                                                (prev) => ({
                                                                    ...prev,
                                                                    [tag.id]:
                                                                        e.target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                        className="h-8 w-full text-sm"
                                                    />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Input
                                                        type="text"
                                                        value={prices[tag.id]}
                                                        onChange={(e) =>
                                                            setPrices(
                                                                (prev) => ({
                                                                    ...prev,
                                                                    [tag.id]:
                                                                        e.target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                        className="h-8 w-full text-right text-sm tabular-nums"
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="border-t bg-muted/30 font-medium">
                                            <td className="px-3 py-2">
                                                Total Qty:{' '}
                                                <span className="tabular-nums">
                                                    {totalQty}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2" />
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                Amount: ₱
                                                {totalAmount.toFixed(2)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </>
                    )}

                    <Button
                        type="submit"
                        disabled={processing || tags.length === 0}
                    >
                        {processing && <Spinner className="mr-2" />}
                        Save
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
