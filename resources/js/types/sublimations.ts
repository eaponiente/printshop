import type { Tag } from '@/types/settings';

export type SublimationsList = {
    sublimations: Sublimation[];
    availableTags: Tag[];
}

export type Sublimation = {
    id: number;
    name: string;
}

