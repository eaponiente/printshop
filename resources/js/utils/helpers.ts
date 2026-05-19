import { router } from '@inertiajs/react';
import { route } from 'ziggy-js';

export const sortBy = (
    field: string,
    currentFilters: any,
    routeName: string = 'sublimations.index',
) => {
    const isCurrentField = currentFilters.sort_field === field;
    const nextDirection =
        isCurrentField && currentFilters.sort_direction === 'asc'
            ? 'desc'
            : 'asc';

    // We spread ...currentFilters so that branch_id, tags, etc. are preserved
    router.get(
        route(routeName),
        {
            ...currentFilters,
            sort_field: field,
            sort_direction: nextDirection,
        },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        },
    );
};

export function debounce<T extends (...args: any[]) => any>(
    fn: T,
    delay: number,
) {
    let timeoutId: ReturnType<typeof setTimeout>;

    return function (...args: Parameters<T>) {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        timeoutId = setTimeout(() => fn(...args), delay);
    };
}

export const getAvatarColor = (name: string) => {
    const strId = String(name);
    let hash = 0;

    for (let i = 0; i < strId.length; i++) {
        // Generate a unique hash from the string characters
        hash = strId.charCodeAt(i) + ((hash << 5) - hash);
    }

    // Hue: 0-360 | Saturation: 65% | Lightness: 45%
    // Constant saturation/lightness ensures white text always has high contrast
    const hue = Math.abs(hash) % 360;

    return `hsl(${hue}, 65%, 45%)`;
};
