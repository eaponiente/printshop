export type TransactionStatus = {
    name: string
}

export type PaymentType = {
    name: string
}

export type Transaction = {
    id: number;
    invoice_number: string;
    customer_id: number | string;
    guest_name: string;
    particular: string;
    description: string | null;

    customer: {
        id: number;
        first_name: string;
        last_name: string;
    };

    // Financials
    amount_total: number;
    amount_paid: number;
    balance: number;

    // Metadata
    payment_type: string;
    status: string;

    // Relationships (Assuming they are eager-loaded)
    staff_id: number;
    staff?: {
        id: number;
        name: string;
    };
    branch_id: number;
    branch?: {
        id: number;
        name: string;
    };

    // Dates
    transaction_date: string; // ISO string from Laravel
    fulfilled_at: string | null;
    change_reason: string | null;
    created_at: string;
    updated_at: string;
}
