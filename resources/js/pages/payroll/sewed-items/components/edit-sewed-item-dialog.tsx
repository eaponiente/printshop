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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

interface TagPivot {
    id: number;
    name: string;
    color: string;
    price_per_piece?: string;
    pivot: { quantity: number; price_per_piece?: string | null };
}

interface SewedItem {
    id: number;
    notes: string | null;
    tags: TagPivot[];
    sublimation: {
        description: string;
    };
}

interface EditSewedItemDialogProps {
    open: boolean;
    setOpen: (open: boolean) => void;
    item: SewedItem;
}

export default function EditSewedItemDialog({
    open,
    setOpen,
    item,
}: EditSewedItemDialogProps) {
    const tags = item.tags ?? [];

    const [quantities, setQuantities] = useState<Record<number, string>>(
        Object.fromEntries(tags.map((t) => [t.id, String(t.pivot.quantity)])),
    );

    const [prices, setPrices] = useState<Record<number, string>>(
        Object.fromEntries(
            tags.map((t) => [
                t.id,
                t.pivot.price_per_piece ?? t.price_per_piece ?? '0',
            ]),
        ),
    );

    const [notes, setNotes] = useState(item.notes ?? '');
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

        if (!notes.trim()) {
            toast.error('Notes is required');

            return;
        }

        setProcessing(true);

        router.put(
            `/payroll/sewed-items/${item.id}`,
            {
                tags: tagItems,
                notes: notes,
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
                    <DialogTitle>Edit Sewed Item</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <div className="text-sm text-muted-foreground">
                        {item.sublimation?.description}
                    </div>

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
                                    <th className="w-28 px-3 py-2 text-right font-medium">
                                        Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {tags.map((tag) => {
                                    const qty = Number(quantities[tag.id]) || 0;
                                    const price = Number(prices[tag.id]) || 0;

                                    return (
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
                                                    value={quantities[tag.id]}
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
                                                        setPrices((prev) => ({
                                                            ...prev,
                                                            [tag.id]:
                                                                e.target.value,
                                                        }))
                                                    }
                                                    className="h-8 w-full text-right text-sm tabular-nums"
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                ₱{(qty * price).toFixed(2)}
                                            </td>
                                        </tr>
                                    );
                                })}
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
                                    <td className="px-3 py-2" />
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        ₱{totalAmount.toFixed(2)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit-notes">Notes</Label>
                        <Textarea
                            id="edit-notes"
                            className="min-h-[60px] resize-none"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                        />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner className="mr-2" />}
                        Save Changes
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
