import { router } from '@inertiajs/react';
import { route } from 'ziggy-js';

export const sortBy = (
    field: string,
    currentFilters: any,
    routeName: string = 'sublimations.index'
) => {
    const isCurrentField = currentFilters.sort_field === field;
    const nextDirection = (isCurrentField && currentFilters.sort_direction === 'asc') ? 'desc' : 'asc';

    // We spread ...currentFilters so that branch_id, tags, etc. are preserved
    router.get(
        route(routeName),
        {
            ...currentFilters,
            sort_field: field,
            sort_direction: nextDirection
        },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true
        }
    );
};

export function debounce<T extends (...args: any[]) => any>(fn: T, delay: number) {
    let timeoutId: ReturnType<typeof setTimeout>;

    return function (...args: Parameters<T>) {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        timeoutId = setTimeout(() => fn(...args), delay);
    };
}
