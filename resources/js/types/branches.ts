export type BranchesList = {
    branches: Branch[];
};

export type Branch = {
    id: number;
    name: string;
    latitude: number | null;
    longitude: number | null;
    geofence_radius: number;
};
