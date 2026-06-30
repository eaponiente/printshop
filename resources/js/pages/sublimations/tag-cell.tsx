import { router } from '@inertiajs/react';
import { useState } from 'react';
import { route } from 'ziggy-js';
import TagSelector from '@/components/shared/tag-selector';
import { tagsLocked } from '@/pages/sublimations/tag-locked-statuses';

export const TagCell = ({
    sublimation,
    allTags,
}: {
    sublimation: any;
    allTags: any[];
}) => {
    const [loading, setLoading] = useState(false);
    const readOnly = tagsLocked(sublimation.status);

    const handleAdd = (tagId: number) => {
        setLoading(true);
        router.post(
            `/sublimations/${sublimation.id}/tags`,
            {
                tag_id: tagId,
            },
            {
                preserveScroll: true,
                onFinish: () => setLoading(false),
            },
        );
    };

    const handleRemove = (tagId: number) => {
        setLoading(true);
        router.delete(`/sublimations/${sublimation.id}/tags/${tagId}`, {
            preserveScroll: true,
            onFinish: () => setLoading(false),
        });
    };

    const handleQuantityChange = (tagId: number, qty: number) => {
        setLoading(true);
        router.patch(
            route('sublimations.tags.update-quantity', {
                sublimation: sublimation.id,
                tag: tagId,
            }),
            { quantity: qty },
            {
                preserveScroll: true,
                onFinish: () => setLoading(false),
            },
        );
    };

    const quantities = Object.fromEntries(
        sublimation.tags.map((t: any) => [t.id, t.pivot?.quantity ?? 1]),
    );

    return (
        <TagSelector
            selectedTagIds={sublimation.tags.map((t: any) => t.id)}
            availableTags={allTags}
            onAdd={handleAdd}
            onRemove={handleRemove}
            layout="col"
            loading={loading}
            quantities={quantities}
            onQuantityChange={handleQuantityChange}
            readOnly={readOnly}
        />
    );
};
