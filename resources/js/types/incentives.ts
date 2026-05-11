import type { Branch } from '@/types/branches';

export type Incentive = {
    id: number;
    branch_id: number;
    branch_name: string;
    manager_name: string;
    manager_id: number | null;
    month: number;
    year: number;
    revenue: number;
    expenses: number;
    net_income: number;
    incentive_amount: number;
    owner_contribution: number;
    cash_on_hand: number;
    status: 'uncomputed' | 'pending' | 'paid' | 'cancelled';
    paid_at: string | null;
};

export type IncentiveHistory = {
    id: number;
    branch_id: number;
    user_id: number;
    month: number;
    year: number;
    net_income: number;
    incentive_amount: number;
    owner_contribution: number;
    status: string;
    paid_at: string;
    branch: Branch;
    user: {
        id: number;
        first_name: string;
        last_name: string;
    };
};

export type IncentivesList = {
    incentives: Incentive[];
    branches: Branch[];
    history: IncentiveHistory[];
    filters: {
        month: number;
        year: number;
        branch_id: string;
    };
};
