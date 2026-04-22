import axios from 'axios';
import type { ChangeEvent, DragEvent } from 'react';
import React, { useState, useRef, useEffect } from 'react';
import { toast } from 'sonner';
import type { UploadedImage } from '@/types/images';
import { route } from 'ziggy-js';

export default function SublimationGallery({
    sublimationId,
    onOpenZoomedImage,
}: {
    sublimationId?: number | string;
    onOpenZoomedImage: (image: UploadedImage) => void;
}) {
    const [images, setImages] = useState<UploadedImage[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB limit
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    useEffect(() => {
        const controller = new AbortController();

        const fetchImages = async () => {
            setIsLoading(true);
            try {
                const response = await axios.get(route('sublimations.images.index', sublimationId), {
                    signal: controller.signal
                });
                setImages(response.data);
            } catch (err) {
                if (!axios.isCancel(err)) {
                    toast.error('Failed to load images.');
                }
            } finally {
                setIsLoading(false);
            }
        };

        fetchImages();
        return () => controller.abort();  // Cleanup on unmount
    }, [sublimationId]);

    const handleFiles = async (files: FileList | null) => {
        if (!files || files.length === 0 || !sublimationId) {
            return;
        }

        setError(null);
        setIsUploading(true);

        const validFiles: File[] = [];

        Array.from(files).forEach((file) => {
            if (!ALLOWED_TYPES.includes(file.type)) {
                setError(
                    'Some files were ignored. Only .jpg, .png, and .webp formats are supported.',
                );

                return;
            }

            if (file.size > MAX_FILE_SIZE) {
                setError(
                    'Some files were ignored because they exceed the 5MB limit.',
                );

                return;
            }

            validFiles.push(file);
        });

        if (validFiles.length > 0) {
            toast.info(`Uploading ${validFiles.length} file(s)...`);
        }

        // Upload valid files sequentially
        for (const file of validFiles) {
            const formData = new FormData();
            formData.append('image', file);

            try {
                const response = await axios.post(
                    `/sublimations/${sublimationId}/images`,
                    formData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data',
                        },
                    },
                );

                setImages((prev) => [...prev, response.data]);
                toast.success(`Uploaded ${file.name} successfully`);
            } catch (err: any) {
                console.error(err);
                toast.error(err.response?.data.message);
            }
        }

        setIsUploading(false);
    };

    const onFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        handleFiles(e.target.files);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleDragOver = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const handleDrop = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        e.stopPropagation();
        handleFiles(e.dataTransfer.files);
    };

    const removeImage = async (idToRemove: string | number) => {
        if (!sublimationId) {
            return;
        }

        try {
            await axios.delete(
                `/sublimations/${sublimationId}/images/${idToRemove}`,
            );
            setImages((prev) => prev.filter((img) => img.id !== idToRemove));
            toast.success('Image removed from server.');
        } catch (err) {
            console.error('Failed to remove image', err);
            toast.error('Failed to remove image.');
        }
    };

    return (
        <div className="flex w-full flex-col gap-4">
            {/* Dropzone */}
            <div
                className={`mb-2 flex w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-zinc-700 p-8 text-center transition-colors duration-200 sm:p-12 ${isUploading ? 'pointer-events-none opacity-50' : 'hover:border-zinc-500 hover:bg-white-800/50'}`}
                onClick={() => !isUploading && fileInputRef.current?.click()}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
            >
                <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full border border-zinc-700 bg-white-800 shadow-sm">
                    {isUploading ? (
                        <div className="h-8 w-8 animate-spin rounded-full border-4 border-zinc-400 border-t-transparent"></div>
                    ) : (
                        <svg
                            className="h-8 w-8 text-zinc-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                            />
                        </svg>
                    )}
                </div>
                <h3 className="mb-1 text-lg font-medium text-black">
                    {isUploading
                        ? 'Uploading files...'
                        : 'Click to upload or drag and drop'}
                </h3>
                <p className="text-sm text-zinc-500">
                    PNG, JPG, WEBP (max. 5MB)
                </p>
                <input
                    type="file"
                    className="hidden"
                    ref={fileInputRef}
                    multiple
                    disabled={isUploading}
                    accept=".jpg,.jpeg,.png,.webp"
                    onChange={onFileChange}
                />
            </div>

            {/* Error Message */}
            {error && (
                <div className="rounded-lg border border-red-800/60 bg-red-950/40 p-4 text-sm text-red-400">
                    {error}
                </div>
            )}

            {/* Gallery Header */}
            {images.length > 0 && (
                <div className="mt-2 mb-2 flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-black">
                        Uploaded Images ({images.length})
                    </h3>
                </div>
            )}

            {/* Responsive Image Grid */}
            {isLoading ? (
                <div className="rounded-xl border border-dashed border-zinc-700 bg-zinc-900/50 py-12 text-center">
                    <p className="text-sm text-zinc-500">
                        Loading images from server...
                    </p>
                </div>
            ) : images.length > 0 ? (
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    {images.map((image) => (
                        <div
                            key={image.id}
                            className="group relative aspect-square cursor-pointer overflow-hidden rounded-xl border border-zinc-700 bg-zinc-800 shadow-sm select-none"
                            onClick={() => onOpenZoomedImage(image)}
                        >
                            <img
                                src={image.url}
                                alt={image.name}
                                className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                            />

                            {/* Hover Overlay */}
                            <div className="pointer-events-none absolute inset-0 bg-black/0 transition-colors duration-200 group-hover:bg-black/40">
                                <button
                                    onClick={(e) => {
                                        e.stopPropagation(); // preserve zooming

                                        removeImage(image.id);
                                    }}
                                    className="pointer-events-auto absolute top-2 right-2 transform rounded-full bg-red-500 p-1.5 text-white opacity-0 shadow-md transition-all group-hover:opacity-100 hover:scale-110 hover:bg-red-600"
                                    title="Remove permanently"
                                >
                                    <svg
                                        className="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>

                                <div className="absolute right-0 bottom-0 left-0 bg-gradient-to-t from-black/80 to-transparent p-2 pt-6 opacity-0 transition-opacity group-hover:opacity-100">
                                    <p className="truncate text-xs font-medium text-white/90">
                                        {image.name}
                                    </p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="rounded-xl border border-dashed border-zinc-700 bg-white-900/50 py-12 text-center">
                    <p className="text-sm text-zinc-500">
                        No images uploaded yet.
                    </p>
                </div>
            )}
        </div>
    );
}
