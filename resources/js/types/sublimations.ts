import type { Branch } from '@/types/branches';
import type { Tag } from '@/types/settings';
import type { Customer, User } from '@/types/user';

export type SublimationsList = {
    sublimations: Sublimation[];
    availableTags: Tag[];
}

export type Sublimation = {
    id: number;
    notes: string;
    description: string;
    branch_id: number;
    customer_id: number;
    user_id: number;
    status: 'pending' | 'active' | 'finished' | 'released';
    due_at: string;
    branch?: Branch;
    user?: User;
    customer?: Customer;
    tags?: Tag[];
}
