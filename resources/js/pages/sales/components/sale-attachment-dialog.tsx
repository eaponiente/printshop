import { router } from '@inertiajs/react';
import { ImagePlus, Loader2, Paperclip, ZoomIn } from 'lucide-react';
import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { Transaction } from '@/types/transaction';

interface SaleAttachmentDialogProps {
    transaction: Transaction | null;
    open: boolean;
    setOpen: (open: boolean) => void;
}

export default function SaleAttachmentDialog({
    transaction,
    open,
    setOpen,
}: SaleAttachmentDialogProps) {
    const inputId = useId();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [pendingFile, setPendingFile] = useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [zoomOpen, setZoomOpen] = useState(false);

    const revokePreview = useCallback(() => {
        setPreviewUrl((prev) => {
            if (prev) {
                URL.revokeObjectURL(prev);
            }

            return null;
        });
        setPendingFile(null);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }, []);

    useEffect(() => {
        if (!open) {
            setZoomOpen(false);
            revokePreview();
        }
    }, [open, revokePreview]);

    const onFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            toast.error('Please choose an image file.');
            e.target.value = '';

            return;
        }

        setPreviewUrl((prev) => {
            if (prev) {
                URL.revokeObjectURL(prev);
            }

            return URL.createObjectURL(file);
        });
        setPendingFile(file);
    };

    const handleUpload = (e: React.FormEvent) => {
        e.preventDefault();

        if (!transaction || !pendingFile) {
            return;
        }

        const formData = new FormData();
        formData.append('attachment', pendingFile);

        setUploading(true);
        router.post(route('sales.attachment.store', transaction.id), formData, {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => setUploading(false),
            onSuccess: () => {
                toast.success('Attachment saved.');
                revokePreview();
            },
            onError: (errors) => {
                toast.error(
                    (errors as { attachment?: string }).attachment ??
                        'Upload failed.',
                );
            },
        });
    };

    const handleDelete = () => {
        if (!transaction?.attachment_url) {
            return;
        }

        setDeleting(true);
        router.delete(route('sales.attachment.destroy', transaction.id), {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
            onSuccess: () => {
                toast.success('Attachment removed.');
            },
            onError: (errors) => {
                toast.error(
                    (errors as { attachment?: string }).attachment ??
                        'Could not remove attachment.',
                );
            },
        });
    };

    if (!transaction) {
        return null;
    }

    const displaySrc = previewUrl ?? transaction.attachment_url ?? null;
    const hasServerImage = Boolean(transaction.attachment_url);
    const showClearSelection = Boolean(pendingFile);

    return (
        <>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Paperclip className="h-5 w-5 text-primary" />
                            Sale attachment
                        </DialogTitle>
                        <DialogDescription>
                            Invoice{' '}
                            <span className="font-medium text-foreground">
                                #{transaction.invoice_number}
                            </span>
                            — one image, optional reference for this sale.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {displaySrc ? (
                            <div className="relative rounded-md border bg-muted/30 p-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="icon"
                                    className="absolute top-3 right-3 z-10 h-8 w-8 shadow-md"
                                    title="Zoom image"
                                    onClick={() => setZoomOpen(true)}
                                >
                                    <ZoomIn className="h-4 w-4" />
                                    <span className="sr-only">Zoom image</span>
                                </Button>
                                <img
                                    src={displaySrc}
                                    alt="Sale attachment"
                                    className="mx-auto max-h-64 max-w-full rounded object-contain"
                                />
                            </div>
                        ) : (
                            <div className="flex min-h-[10rem] items-center justify-center rounded-md border border-dashed bg-muted/20 text-center text-sm text-muted-foreground">
                                No image yet — choose one below.
                            </div>
                        )}

                        <form onSubmit={handleUpload} className="space-y-3">
                            <div className="space-y-2">
                                <Label
                                    htmlFor={inputId}
                                    className="flex items-center gap-2"
                                >
                                    <ImagePlus className="h-4 w-4" />
                                    Image file
                                </Label>
                                <input
                                    ref={fileInputRef}
                                    id={inputId}
                                    type="file"
                                    accept="image/*"
                                    className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-secondary-foreground"
                                    onChange={onFileChange}
                                />
                            </div>

                            <DialogFooter className="flex-col gap-2 sm:flex-row sm:justify-between">
                                <div className="flex flex-wrap gap-2">
                                    {showClearSelection && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={revokePreview}
                                        >
                                            Clear selection
                                        </Button>
                                    )}
                                    {hasServerImage && !pendingFile && (
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            disabled={deleting}
                                            onClick={handleDelete}
                                        >
                                            {deleting ? (
                                                <>
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    Removing…
                                                </>
                                            ) : (
                                                'Delete image'
                                            )}
                                        </Button>
                                    )}
                                </div>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={!pendingFile || uploading}
                                >
                                    {uploading ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Uploading…
                                        </>
                                    ) : (
                                        'Upload'
                                    )}
                                </Button>
                            </DialogFooter>
                        </form>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={zoomOpen} onOpenChange={setZoomOpen}>
                <DialogContent className="flex max-h-[92vh] w-auto max-w-[calc(100vw-2rem)] flex-col gap-2 overflow-hidden p-3 sm:max-w-[calc(100vw-2rem)] sm:p-4">
                    <DialogHeader className="shrink-0">
                        <DialogTitle className="sr-only">
                            Full-size attachment preview
                        </DialogTitle>
                        <DialogDescription className="sr-only">
                            Image shown at its saved pixel dimensions. Scroll if
                            it is larger than the window. Close with the dialog
                            button or Escape.
                        </DialogDescription>
                    </DialogHeader>
                    {displaySrc && (
                        <div className="max-h-[min(88vh,calc(92vh-3.5rem))] min-h-0 max-w-[min(95vw,calc(100vw-2.5rem))] min-w-0 overflow-auto rounded-md border bg-muted/20 p-1">
                            <img
                                src={displaySrc}
                                alt=""
                                className="block h-auto w-auto max-w-none rounded-sm"
                            />
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
