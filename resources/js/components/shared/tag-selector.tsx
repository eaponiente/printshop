import { router } from '@inertiajs/react';
import { Plus, X, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Command, CommandInput, CommandList, CommandEmpty, CommandGroup, CommandItem, CommandSeparator } from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { Tag } from '@/types/settings';

const randomColor = () => {
    const letters = '0123456789ABCDEF';
    let color = '#';
    for (let i = 0; i < 6; i++) {
        color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
};

interface TagSelectorProps {
    selectedTagIds: number[];
    availableTags: Tag[];
    onAdd: (tagId: number) => void;
    onRemove: (tagId: number) => void;
    onTagCreated?: (tag: Tag) => void;
    loading?: boolean;
    layout?: 'row' | 'col';
}

export default function TagSelector({
    selectedTagIds,
    availableTags,
    onAdd,
    onRemove,
    onTagCreated,
    loading = false,
    layout = 'row',
}: TagSelectorProps) {
    const [open, setOpen] = useState(false);
    const [newTagName, setNewTagName] = useState('');
    const [isCreating, setIsCreating] = useState(false);

    const handleCreateTag = () => {
        const trimmed = newTagName.trim();
        if (!trimmed || isCreating) return;

        setIsCreating(true);
        router.post('/tags', {
            name: trimmed,
            color: randomColor(),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewTagName('');
                router.reload({
                    preserveScroll: true,
                    onSuccess: (page: any) => {
                        const created = page.props.availableTags?.find(
                            (t: any) => t.name === trimmed
                        );
                        if (created) {
                            onAdd(created.id);
                            onTagCreated?.(created);
                            setOpen(false);
                        }
                        setIsCreating(false);
                    },
                    onError: () => setIsCreating(false),
                });
            },
            onError: () => setIsCreating(false),
        });
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleCreateTag();
        }
    };

    return (
        <div className={layout === 'col' ? 'flex flex-col gap-1.5' : 'flex flex-wrap items-center gap-1.5'}>
            {selectedTagIds.map((tagId) => {
                const tag = availableTags.find((t) => t.id === tagId);
                if (!tag) return null;
                return (
                    <Badge
                        key={tag.id}
                        className="flex items-center gap-1 border-none px-2 text-white"
                        style={{ backgroundColor: tag.color }}
                    >
                        {tag.name}
                        <button
                            type="button"
                            disabled={loading}
                            onClick={() => onRemove(tag.id)}
                            className="hover:bg-red/20 rounded-full"
                        >
                            <X className="h-3 w-3" />
                        </button>
                    </Badge>
                );
            })}

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <button
                        type="button"
                        className="flex h-6 w-6 items-center justify-center rounded-full border border-dashed border-slate-400 hover:bg-slate-50"
                    >
                        {loading ? <Loader2 className="h-3 w-3 animate-spin" /> : <Plus className="h-3 w-3" />}
                    </button>
                </PopoverTrigger>
                <PopoverContent className="w-[220px] p-0" align="start">
                    <Command>
                        <CommandInput placeholder="Search tags..." />
                        <CommandList>
                            <CommandEmpty>No results.</CommandEmpty>
                            <CommandGroup>
                                {availableTags.map((tag) => (
                                    <CommandItem
                                        key={tag.id}
                                        onSelect={() => {
                                            if (!selectedTagIds.includes(tag.id)) {
                                                onAdd(tag.id);
                                            }
                                            setOpen(false);
                                        }}
                                        disabled={selectedTagIds.includes(tag.id)}
                                    >
                                        <div
                                            className="mr-2 h-2 w-2 rounded-full"
                                            style={{ backgroundColor: tag.color }}
                                        />
                                        {tag.name}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                        <CommandSeparator />
                        <div className="flex items-center gap-1.5 p-2">
                            <Input
                                value={newTagName}
                                onChange={(e) => setNewTagName(e.target.value)}
                                onKeyDown={handleKeyDown}
                                placeholder="New tag name..."
                                className="h-8 text-xs"
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-8 shrink-0 text-xs"
                                disabled={!newTagName.trim() || isCreating}
                                onClick={handleCreateTag}
                            >
                                {isCreating ? <Loader2 className="h-3 w-3 animate-spin" /> : 'Add'}
                            </Button>
                        </div>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    );
}
