import type { Branch } from '@/types/branches';
import type { Tag } from '@/types/settings';
import type { Transaction } from '@/types/transaction';
import type { Customer, User } from '@/types/user';

export type SublimationsList = {
    sublimations: Sublimation[];
    availableTags: Tag[];
};

export type SublimationStatus = {
    key: string;
    value: string;
    color: string;
};

export type Sublimation = {
    id: number;
    description: string;
    notes: string;
    branch_id: number;
    customer_id: number;
    user_id: number;
    status: string;
    status_color: string;
    status_label: string;
    amount_total: number;
    amount_paid: number;
    quantity: number;
    transaction_type: 'retail' | 'purchase_order' | string;
    production_authorized: boolean;
    due_at: string;
    branch?: Branch;
    user?: User;
    customer?: Customer;
    tags?: Tag[];
    transaction: Transaction;
    sewed_item?: {
        id: number;
        quantity: number;
        unit_price: number;
        amount: number;
        sewed_date: string;
        notes: string | null;
    } | null;
};
