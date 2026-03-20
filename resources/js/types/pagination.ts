export interface PaginatedResponse<T> {
    current_page: number;
    data: T[];
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: Array<{
        url: string | null;
        label: string;
        page: number
        active: boolean;
    }>;
    next_page_url: string;
    prev_page_url: string;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
}
