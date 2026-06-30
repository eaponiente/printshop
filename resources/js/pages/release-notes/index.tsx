import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { toManilaTime } from '@/utils/dateHelper';

type Note = { date: string; html: string };

interface Props {
    notes: Note[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Release Notes', href: '/release-notes' },
];

export default function ReleaseNotesIndex({ notes }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Release Notes" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold">Release Notes</h1>
                    <p className="text-sm text-muted-foreground">
                        Updates and improvements to the system.
                    </p>
                </div>

                {notes.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No release notes available.
                    </p>
                ) : (
                    notes.map((note) => (
                        <article
                            key={note.date}
                            className="rounded-lg border border-sidebar-border bg-sidebar p-6"
                        >
                            <div className="mb-4 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                {toManilaTime(note.date)}
                            </div>
                            <div
                                className="text-sm leading-relaxed
                                    [&_h1]:mt-0 [&_h1]:mb-3 [&_h1]:text-2xl [&_h1]:font-bold
                                    [&_h2]:mt-6 [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-semibold
                                    [&_h3]:mt-4 [&_h3]:mb-1 [&_h3]:text-base [&_h3]:font-semibold
                                    [&_p]:my-2 [&_p]:text-foreground
                                    [&_ul]:my-2 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-6
                                    [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:pl-6
                                    [&_li]:text-foreground
                                    [&_strong]:font-semibold
                                    [&_em]:italic
                                    [&_a]:text-primary [&_a]:underline
                                    [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-xs
                                    [&_hr]:my-6 [&_hr]:border-border"
                                dangerouslySetInnerHTML={{ __html: note.html }}
                            />
                        </article>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
