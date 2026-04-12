import { router } from '@inertiajs/react';
import { Pencil, Ban } from 'lucide-react';
import React, { useState } from 'react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'sonner';

interface ExpenseActionsProps {
    expense: any; // Replace with your Expense type
    onEdit: (expense: any) => void;
}

export default function ExpenseActions({
    expense,
    onEdit,
}: ExpenseActionsProps) {
    const [reason, setReason] = useState('');
    const [isOpen, setIsOpen] = useState(false);

    const handleVoid = () => {
        if (!reason.trim()) {
            return;
        }

        router.patch(
            route('expenses.void', expense.id),
            { reason },
            {
                onSuccess: () => {
                    console.log(999);
                    setReason('');
                    setIsOpen(false);
                },
                onError: (errors) => {
                    // Get the first error message from the object
                    const firstError = Object.values(errors)[0];
                    toast.error(firstError || 'An unexpected error occurred.');
                },
            },
        );
    };

    const isVoided = expense.status === 'void';

    return (
        <div className="flex items-center gap-1">
            {/* Edit Button */}
            {!isVoided && (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onEdit(expense)}
                >
                    <Pencil className="h-4 w-4" />
                </Button>
            )}

            {/* Void Dialog */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-destructive hover:text-destructive"
                        disabled={isVoided}
                    >
                        <Ban className="h-4 w-4" />
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Void Expense</DialogTitle>
                        <DialogDescription>
                            Please provide a reason for voiding this expense.
                            This will reverse the cash adjustment in the branch
                            records.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="py-4">
                        <Label htmlFor="reason" className="mb-2 block">
                            Reason for Voiding
                        </Label>
                        <Textarea
                            id="reason"
                            required={true}
                            placeholder="e.g., Wrong amount entered, duplicate entry..."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                        />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={handleVoid}
                            disabled={!reason.trim()}
                        >
                            Confirm Void
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
