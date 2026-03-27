import { router } from '@inertiajs/react';
import { route } from 'ziggy-js';

export const sortBy = (
    field: string,
    currentFilters: any,
    routeName: string = 'sublimations.index'
) => {
    const isCurrentField = currentFilters.sort_field === field;
    const nextDirection = (isCurrentField && currentFilters.sort_direction === 'asc') ? 'desc' : 'asc';
    console.log('isCurrentField', isCurrentField);
    console.log('sort dir', currentFilters);
    console.log('next direction', nextDirection);

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
