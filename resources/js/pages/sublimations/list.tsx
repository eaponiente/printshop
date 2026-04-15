import { Head, Link, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpDown,
    ExternalLink,
    Images,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import { DataTable } from '@/components/data-table';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { StatusCell } from '@/pages/sublimations/status-cell';
import SublimationDialog from '@/pages/sublimations/sublimation-dialog';
import SublimationGallery from '@/pages/sublimations/sublimation-gallery';
import type { BreadcrumbItem } from '@/types';
import type { Branch } from '@/types/branches';
import type { UploadedImage } from '@/types/images';
import type { PaginatedResponse } from '@/types/pagination';
import type { Tag } from '@/types/settings';
import type { Sublimation } from '@/types/sublimations';
import type { User } from '@/types/user';
import { sortBy } from '@/utils/helpers';
import { StatusFilter } from '@/pages/sublimations/components/status-filter';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sublimations', href: '/sublimations' },
];

interface SublimationIndexProps {
    sublimations: PaginatedResponse<Sublimation>;
    branches: Branch[];
    filters: any;
    availableTags: Tag[];
    users: User[];
    statuses: { key: string; value: string; color: string }[];
}

export default function SublimationIndex({
    sublimations,
    branches,
    filters,
    statuses,
    users,
}: SublimationIndexProps) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [selectedSublimation, setSelectedSublimation] = useState<Sublimation | null>(null);
    const [zoomedImage, setZoomedImage] = useState<UploadedImage | null>(null);

    const [isGalleryOpen, setIsGalleryOpen] = useState(false);
    const [gallerySublimation, setGallerySublimation] = useState<Sublimation | null>(null);

    const openGallery = (sublimation: Sublimation) => {
        setGallerySublimation(sublimation);
        setIsGalleryOpen(true);
    };

    const openCreateForm = () => {
        setSelectedSublimation(null);
        setIsDialogOpen(true);
    };

    const openEditForm = (sublimation: Sublimation) => {
        setSelectedSublimation(sublimation);
        setIsDialogOpen(true);
    };

    const deleteSublimation = (sublimation: Sublimation) => {
        router.delete(route('sublimations.destroy', sublimation.id), {
            onSuccess: () => toast.success('Sublimation deleted', { position: 'top-center' }),
        });
    };

    const handleFilterChange = (
        // Update value to accept string or string array
        value: string | string[] | boolean,
        type: 'date' | 'status' | 'branch_id' | 'include_completed' | 'user_id',
    ) => {
        // Clone filters
        const params = { ...filters };

        console.log('filters', filters);

        // You can simplify this logic significantly
        params[type] = value;

        // IMPORTANT: If you are using Inertia.js or a similar router,
        // it will automatically convert ['active', 'pending'] into
        // ?status[]=active&status[]=pending in the URL.
        router.get(`/sublimations`, params, {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        router.get(route('sublimations.index'), {}, { replace: true });
    };

    const columns: ColumnDef<any>[] = [
        {
            accessorKey: 'customer.full_name',
            header: 'Customer',
            cell: ({ row }) => {
                const { transaction, customer } = row.original;

                return (
                    <div className="flex items-center gap-2">
                        {/* Name stays as plain text for readability */}
                        <span className="font-medium text-slate-900">
                            {customer.full_name}
                        </span>

                        {/* Icon link appears only if transaction exists */}
                        {transaction && (
                            <Link
                                href={route('sales.index', {
                                    search: transaction.invoice_number,
                                    mode: 'yearly',
                                })}
                                className="rounded p-1 text-indigo-500 transition-colors hover:bg-indigo-100 hover:text-indigo-700"
                                title={`View Invoice: ${transaction.invoice_number}`}
                            >
                                <ExternalLink size={16} />
                            </Link>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'description',
            header: 'Description',
        },
        {
            accessorKey: 'due_at',
            header: () => {
                const isSorted = filters.sort_field === 'due_at';

                return (
                    <Button
                        variant="ghost"
                        // Pass the field, the current filters object, and the route
                        onClick={() =>
                            sortBy('due_at', filters, 'sublimations.index')
                        }
                        className="p-0 hover:bg-transparent"
                    >
                        Due At
                        <ArrowUpDown
                            className={`ml-2 h-4 w-4 ${isSorted ? 'text-primary' : 'text-muted-foreground/50'}`}
                        />
                    </Button>
                );
            },
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => (
                <StatusCell item={row.original} statuses={statuses} />
            ),
        },
        {
            accessorKey: 'branch.name',
            header: 'Branch',
        },
        {
            accessorKey: 'user.fullname',
            header: 'Assigned',
        },
        {
            header: 'Actions',
            cell: ({ row }: CellContext<any, any>) => {
                const prePaymentKeys = [
                    'for_approval',
                    'done_layout',
                    'waiting_for_dp',
                ];

                return (

                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => openGallery(row.original)}
                            title="Gallery"
                        >
                            <Images className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => openEditForm(row.original)}
                        >
                            <Pencil className="h-4 w-4" />
                        </Button>
                        {prePaymentKeys.includes(row.original.status) && (
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="ghost" size="sm">
                                        <Trash2 />
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            Are you absolutely sure?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This action cannot be undone. This will
                                            permanently delete this sublimation.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={() =>
                                                deleteSublimation(row.original)
                                            }
                                        >
                                            Continue
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        )}
                    </>
                );
            }
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sublimations" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Sublimations</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your sublimation.
                        </p>
                    </div>

                    <Button onClick={openCreateForm}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add sublimation
                    </Button>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar p-1">
                    <div className="mb-6 flex flex-wrap items-end gap-3 rounded-lg bg-slate-50/50">
                        <div className="mb-1 flex flex-wrap items-end gap-3 rounded-lg bg-slate-50/50 p-4">
                            {/* Branch Filter */}
                            <div className="flex flex-col gap-1.5">
                                <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                    Branch
                                </label>
                                <Select
                                    value={filters.branch_id || 'all'}
                                    onValueChange={(v) =>
                                        handleFilterChange(v, 'branch_id')
                                    }
                                >
                                    <SelectTrigger className="h-10 w-[160px] bg-white text-sm">
                                        <SelectValue placeholder="All Branch" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Branch
                                        </SelectItem>
                                        {branches.map((branch) => (
                                            <SelectItem
                                                key={branch.id}
                                                value={String(branch.id)}
                                            >
                                                {branch.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* User Filter - Only shows when a specific branch is selected */}
                            {filters.branch_id &&
                                filters.branch_id !== 'all' && (
                                    <div className="flex flex-col gap-1.5">
                                        <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                            User / Staff
                                        </label>
                                        <Select
                                            value={filters.user_id || 'all'}
                                            onValueChange={(v) =>
                                                handleFilterChange(v, 'user_id')
                                            }
                                        >
                                            <SelectTrigger className="h-10 w-[160px] bg-white text-sm">
                                                <SelectValue placeholder="All Users" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    All Users
                                                </SelectItem>
                                                {users
                                                    .filter(
                                                        (user) =>
                                                            String(
                                                                user.branch_id,
                                                            ) ===
                                                            String(
                                                                filters.branch_id,
                                                            ),
                                                    )
                                                    .map((user: User) => (
                                                        <SelectItem
                                                            key={user.id}
                                                            value={String(
                                                                user.id,
                                                            )}
                                                        >
                                                            {user.fullname}
                                                        </SelectItem>
                                                    ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                            {/* Status Filter */}
                            <StatusFilter filters={filters} statuses={statuses} handleFilterChange={(value) => handleFilterChange(value, 'status')} />

                            {/* New Checkbox Filter */}
                            <div className="flex h-10 items-center space-x-2 px-2">
                                <Checkbox
                                    id="include_completed"
                                    checked={
                                        filters.include_completed === 'true' ||
                                        filters.include_completed === true
                                    }
                                    onCheckedChange={(checked) =>
                                        handleFilterChange(
                                            checked ? 'true' : 'false',
                                            'include_completed',
                                        )
                                    }
                                />
                                <label
                                    htmlFor="include_completed"
                                    className="cursor-pointer text-sm leading-none font-medium text-muted-foreground transition-colors select-none hover:text-foreground"
                                >
                                    Include Completed
                                </label>
                            </div>

                            {/* Clear Button */}
                            <div className="flex flex-col gap-1.5">
                                <div className="h-[15px]" />
                                <Button
                                    variant="ghost"
                                    onClick={clearFilters}
                                    className="h-10 px-3 text-sm text-muted-foreground transition-colors hover:text-destructive"
                                >
                                    <X className="mr-1.5 h-4 w-4" />
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </div>

                    <DataTable columns={columns} pagination={sublimations} />
                </div>
            </div>

            {isDialogOpen && (
                <SublimationDialog
                    statuses={statuses}
                    open={isDialogOpen}
                    setOpen={setIsDialogOpen}
                    branches={branches}
                    users={users}
                    sublimation={selectedSublimation}
                />
            )}

            {isGalleryOpen && gallerySublimation && (
                <Dialog open={isGalleryOpen} onOpenChange={setIsGalleryOpen}>
                    <DialogContent className="flex h-[95vh] !max-h-[95vh] !w-[95vw] !max-w-[95vw] flex-col overflow-hidden border-zinc-800 bg-white/95 p-6">
                        <DialogHeader className="flex-none">
                            <DialogTitle className="text-3xl font-bold text-black">
                                Sublimation Gallery
                            </DialogTitle>
                            <DialogDescription className="text-black">
                                Manage images for{' '}
                                {gallerySublimation.customer?.full_name ||
                                    'Customer'}
                            </DialogDescription>
                        </DialogHeader>

                        {/* Gallery Container */}
                        <div className="custom-scrollbar mt-6 flex-1 overflow-y-auto pr-2">
                            <SublimationGallery
                                sublimationId={gallerySublimation.id}
                                onOpenZoomedImage={(image) =>
                                    setZoomedImage(image)
                                }
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}

            {/* Zoom Modal */}
            <Dialog
                open={!!zoomedImage}
                onOpenChange={(open) => !open && setZoomedImage(null)}
            >
                {/* FIXED "BIGGER" SIZE:
      - Used !w-[98vw] and !h-[95vh] to force it to fill the screen.
      - bg-white for uniform color behind the image.
      - flex items-center justify-center to center the "Actual Size" image in the large space.
    */}
                <DialogContent className="custom-scrollbar fixed top-1/2 left-1/2 h-[95vh] !max-h-[95vh] !w-[98vw] -translate-x-1/2 -translate-y-1/2 overflow-auto rounded-xl border border-zinc-200 bg-white p-0 shadow-2xl sm:!max-w-[98vw] [&>button]:fixed [&>button]:top-6 [&>button]:right-6 [&>button]:z-[130] [&>button]:flex [&>button]:h-8 [&>button]:w-8 [&>button]:items-center [&>button]:justify-center [&>button]:rounded-full [&>button]:bg-zinc-900 [&>button]:text-white [&>button]:opacity-100 [&>button]:transition-all [&>button]:hover:bg-black">
                    <DialogTitle className="sr-only">Zoomed Image</DialogTitle>
                    <DialogDescription className="sr-only">
                        Actual size view of the selected image.
                    </DialogDescription>

                    {zoomedImage && (
                        /* The wrapper div:
                           - min-h-full min-w-full ensures the white background fills the modal.
                           - flex items-center justify-center keeps the image centered if it's smaller than the screen.
                        */
                        <div className="relative flex min-h-full min-w-full items-center justify-center bg-white p-12">
                            <img
                                src={zoomedImage.url}
                                alt={zoomedImage.name}
                                /* Actual Size:
                                   - Removed 'max-h-full' so it stays at its true pixel size.
                                   - Added shadow-xl to separate the image from the white background if they are both white.
                                */
                                className="block h-auto w-auto min-w-[300px] border border-zinc-100 shadow-xl"
                            />

                            {/* DOWNLOAD BUTTON:
                   - Positioned 'fixed' relative to the viewport (bottom-right of the modal).
                   - Colors synced to the Close Button (Zinc-900).
                */}
                            <div className="fixed right-10 bottom-10 z-[120]">
                                <a
                                    href={zoomedImage.url}
                                    download={zoomedImage.name}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center gap-2 rounded-full bg-zinc-900 px-6 py-3 text-sm font-bold text-white shadow-2xl transition-all hover:scale-105 hover:bg-black active:scale-95"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="18"
                                        height="18"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2.5"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                        <polyline points="7 10 12 15 17 10" />
                                        <line x1="12" x2="12" y1="15" y2="3" />
                                    </svg>
                                    Download Actual Size
                                </a>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
