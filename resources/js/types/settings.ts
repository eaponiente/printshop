export type TagsList = {
    tags: Tag[];
};

export type Tag = {
    id: number;
    name: string;
    color: string;
    price_per_piece?: string;
};

export type TypeOfPayment = {
    key: string;
    value: string;
};
