export type TagsList = {
    tags: Tag[];
}

export type Tag = {
    id: number;
    name: string;
}

export type SublimationsList = {
    sublimations: Sublimation[];
    availableTags: Tag[];
}

export type Sublimation = {
    id: number;
    name: string;
}





