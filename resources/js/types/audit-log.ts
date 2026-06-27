export type AuditLog = {
    id: number;
    user_id: number | null;
    branch_id: number | null;
    action: string;
    model_type: string;
    model_id: number;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    ip_address: string;
    user_agent: string | null;
    created_at: string;
    user: {
        id: number;
        first_name: string;
        last_name: string;
        fullname: string;
        username: string;
        role: string;
    } | null;
    branch: {
        id: number;
        name: string;
    } | null;
};

export type AuditLogsList = {
    logs: {
        current_page: number;
        data: AuditLog[];
        first_page_url: string;
        from: number | null;
        last_page: number;
        last_page_url: string;
        links: Array<{
            url: string | null;
            label: string;
            page: number;
            active: boolean;
        }>;
        next_page_url: string | null;
        prev_page_url: string | null;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};
