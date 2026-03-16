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

export type Branch = {
    id: number;
    name: string;
}

export type UsersList = {
    users: User[];
    branches: Branch[];
};
