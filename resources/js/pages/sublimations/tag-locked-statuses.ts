// Sublimation statuses where category/tag changes are no longer allowed.
// Keep in sync with App\Models\Sublimation::TAG_LOCKED_STATUSES.
export const TAG_LOCKED_STATUSES = [
    'sewed',
    'checked',
    'ready_for_pickup',
    'claimed',
    'completed',
] as const;

export function tagsLocked(status?: string | null): boolean {
    return !!status && (TAG_LOCKED_STATUSES as readonly string[]).includes(status);
}
