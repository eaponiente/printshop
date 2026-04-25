import type { PaginatedResponse } from '@/types/pagination';
import type { Branch } from '@/types/branches';
import { Transaction } from './transaction';

export type POStatus = 'pending' | 'active' | 'finished' | 'released';

export interface PurchaseOrdersList {
    purchase_orders: PaginatedResponse<PurchaseOrder>;
    branches: Branch[];
    statuses: PurchaseOrderStatus[];
    filters: any;
}

export type PurchaseOrderStatus = {
    key: string;
    value: string;
    color: string;
};

export interface PurchaseOrder {
    id: number;
    po_number: string;
    description: string | null;
    status: POStatus;
    branch_id: number;
    customer_id: number;
    transaction: Transaction | null;
    user_id: number;
    grand_total: number;
    received_at: string;
    due_at: string;

    // Timestamps
    created_at: string;
    updated_at: string;

    // Optional Relationships (Loaded via Eloquent 'with')
    details?: PurchaseOrderDetail[];
    branch?: {
        id: number;
        name: string;
    };
    user?: {
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
