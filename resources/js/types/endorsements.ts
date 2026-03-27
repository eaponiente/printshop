import type { Branch } from '@/types/branches';
import type { PaginatedResponse } from '@/types/pagination';
import type { User } from '@/types/user';

export type EndorsementsList = {
    endorsements: PaginatedResponse<Endorsement>;
    branches: Branch[];
}

export type Endorsement = {
    id: number;
    amount: string;
    branch_id: number;

    user: User;
    branch: Branch;
}
