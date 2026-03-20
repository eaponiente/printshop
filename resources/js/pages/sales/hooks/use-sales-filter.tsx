import { router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export const useSalesFilters = (initialFilters: any) => {
    const [searchTerm, setSearchTerm] = useState(initialFilters.search || "");
    const [mode, setMode] = useState(initialFilters.mode || "daily");
    const [filters, setFilters] = useState({
        date: initialFilters.date || "",
        status: initialFilters.status || "all",
    });

    // Handle Search Debounce
    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (searchTerm !== (initialFilters.search || "")) {
                router.get('/sales',
                    { ...initialFilters, search: searchTerm, page: 1 },
                    { preserveState: true, replace: true }
                );
            }
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [searchTerm]);

    const handleFilterChange = (value: string, type: string) => {
        const params = { ...initialFilters, search: searchTerm };

        if (type === 'mode') {
            setMode(value);
            params.mode = value;
            params.date = ""; // Reset date on mode change
        } else if (type === 'status') {
            params.status = value;
        } else if (type === 'date') {
            params.date = value;
        }

        router.get('/sales', params, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        setSearchTerm("");
        setMode("daily");
        router.get('/sales', {}, { replace: true });
    };

    return { searchTerm, setSearchTerm, mode, filters, handleFilterChange, clearFilters };
};
