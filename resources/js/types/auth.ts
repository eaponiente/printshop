import { Branch } from '@/types/branches';

export type User = {
    id: number;
    first_name: string;
    last_name: string;
    fullname: string;
    branch_id: number;
    username: string;
    role: string;
    created_at: string;
    updated_at: string;
    branch: Branch;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
