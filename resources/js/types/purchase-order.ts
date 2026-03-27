import type { PaginatedResponse } from '@/types/pagination';
import type { Branch } from '@/types/branches';

export type POStatus = 'pending' | 'active' | 'finished' | 'released';

export interface PurchaseOrdersList {
    purchase_orders: PaginatedResponse<PurchaseOrder>;
    branches: Branch[];
}

export interface PurchaseOrder {
    id: number;
    particular: string;
    description: string | null;
    status: POStatus;
    branch_id: number;
    staff_id: number;
    grand_total: number;
    ordered_at: string;

    // Timestamps
    created_at: string;
    updated_at: string;

    // Optional Relationships (Loaded via Eloquent 'with')
    details?: PurchaseOrderDetail[];
    branch?: {
        id: number;
        name: string;
    };
    staff?: {
        id: number;
        name: string;
    };
}

export interface PurchaseOrderDetail {
    id: number;
    purchase_order_id: number;
    item_name: string;
    item_description: string | null;
    quantity: number;
    unit_price: number;

    created_at: string;
    updated_at: string;
}
