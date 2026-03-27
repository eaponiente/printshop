import type { Branch } from '@/types/branches';
import type { PaginatedResponse } from '@/types/pagination';
import type { User } from '@/types/user';

export type ExpensesList = {
    expenses: PaginatedResponse<Expense>;
    branches: Branch[];
    payment_methods: any;
}

export type ExpenseStatus = 'pending' | 'approved' | 'rejected' | 'reimbursed';

export interface Expense {
    id: number;
    description: string;
    vendor_name: string | null;

    // In TS, Decimal/Numeric from MySQL usually comes as a string
    // to preserve precision during JSON transport.
    amount: string | number;

    // This should match the keys in your config/settings.type_of_payment
    payment_method: string | null;

    user_id: number;
    branch_id: number;
    receipt_path: string | null;

    status: ExpenseStatus;
    expense_date: string; // ISO Date string (YYYY-MM-DD)

    // Laravel Timestamps
    created_at: string;
    updated_at: string;
    deleted_at: string | null; // For SoftDeletes

    // Optional: If you are eager loading relationships in your Controller
    user: User;
    branch?: Branch;
}

// Useful for the useForm hook in Inertia
export interface ExpenseForm {
    description: string;
    vendor_name: string;
    amount: string | number;
    payment_method: string;
    user_id: number;
    branch_id: number;
    expense_date: string;
    status: ExpenseStatus;
    receipt: File | null; // For the actual upload
}
