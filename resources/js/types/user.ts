import type { Branch } from '@/types/branches';
import type { PaginatedResponse } from '@/types/pagination';

export type User = {
    id: number;
    branch: Branch;
    fullname: string;
    username: string;
    first_name?: string;
    last_name?: string;
    role?: string;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type UsersList = {
    users: PaginatedResponse<User>;
    branches: Branch[];
};

export type Customer = {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    company: string;
    mobile: string;
}

export type CustomersList = {
    customers: PaginatedResponse<Customer>;
};
