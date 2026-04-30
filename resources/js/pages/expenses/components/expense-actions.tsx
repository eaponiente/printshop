import { router, usePage } from '@inertiajs/react';
import { Pencil, Ban, CheckCircle, XCircle } from 'lucide-react';
import { useState } from 'react';
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
    const { auth } = usePage<any>().props;
    const [reason, setReason] = useState('');
    const [isOpen, setIsOpen] = useState(false);

    const canApprove = (auth.user.role === 'admin' || auth.user.role === 'superadmin') && expense.status === 'pending';
    const isPending = expense.status === 'pending';

    const handleApprove = () => {
        router.post(route('expenses.approve', expense.id), {}, {
            onSuccess: () => toast.success('Expense approved.'),
            onError: (err) => toast.error(Object.values(err)[0] || 'Error approving expense.')
        });
    };

    const handleReject = () => {
        router.post(route('expenses.reject', expense.id), {}, {
            onSuccess: () => toast.success('Expense rejected.'),
            onError: (err) => toast.error(Object.values(err)[0] || 'Error rejecting expense.')
        });
    };

    const handleVoid = () => {
        if (!reason.trim()) {
            return;
        }

        router.patch(
            route('expenses.void', expense.id),
            { reason },
            {
                onSuccess: () => {
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
            <Button
                variant="ghost"
                size="sm"
                onClick={() => onEdit(expense)}
            >
                <Pencil className="h-4 w-4" />
            </Button>

            {/* Approve Button */}
            {canApprove && (
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-emerald-600 hover:text-emerald-700"
                    onClick={handleApprove}
                    title="Approve Expense"
                >
                    <CheckCircle className="h-4 w-4" />
                </Button>
            )}

            {/* Reject Button */}
            {canApprove && (
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-amber-600 hover:text-amber-700"
                    onClick={handleReject}
                    title="Reject Expense"
                >
                    <XCircle className="h-4 w-4" />
                </Button>
            )}

            {/* Void Dialog */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-destructive hover:text-destructive"
                        disabled={isVoided || isPending}
                        title="Void Expense"
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
