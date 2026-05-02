import { router } from '@inertiajs/react'
import { X, Plus, Loader2 } from 'lucide-react'
import { useState } from 'react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Command, CommandInput, CommandList, CommandEmpty, CommandGroup, CommandItem, CommandSeparator } from '@/components/ui/command'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

const randomColor = () => {
    const letters = '0123456789ABCDEF'
    let color = '#'
    for (let i = 0; i < 6; i++) {
        color += letters[Math.floor(Math.random() * 16)]
    }
    return color
}

export const TagCell = ({ sublimation, allTags }: { sublimation: any; allTags: any[] }) => {
    const [loading, setLoading] = useState(false)
    const [newTagName, setNewTagName] = useState('')
    const [isCreating, setIsCreating] = useState(false)

    const handleAdd = (tagId: number) => {
        setLoading(true)
        router.post(`/sublimations/${sublimation.id}/tags`, {
            tag_id: tagId
        }, {
            preserveScroll: true,
            onFinish: () => setLoading(false),
        })
    }

    const handleRemove = (tagId: number) => {
        setLoading(true)
        router.delete(`/sublimations/${sublimation.id}/tags/${tagId}`, {
            preserveScroll: true,
            onFinish: () => setLoading(false),
        })
    }

    const handleCreateTag = (e: React.FormEvent) => {
        e.preventDefault()

        const trimmed = newTagName.trim()
        if (!trimmed || isCreating) return

        setIsCreating(true)
        router.post('/tags', {
            name: trimmed,
            color: randomColor(),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewTagName('')
                router.reload({
                    preserveScroll: true,
                    onSuccess: (page: any) => {
                        const created = page.props.availableTags?.find(
                            (t: any) => t.name === trimmed
                        )
                        if (created) {
                            router.post(`/sublimations/${sublimation.id}/tags`, {
                                tag_id: created.id,
                            }, {
                                preserveScroll: true,
                                onFinish: () => setIsCreating(false),
                            })
                        } else {
                            setIsCreating(false)
                        }
                    },
                    onError: () => setIsCreating(false),
                })
            },
            onError: () => setIsCreating(false),
        })
    }

    return (
        <div className="flex flex-wrap gap-1 items-center">
            {sublimation.tags.map((tag: any) => (
                <Badge
                    key={tag.id}
                    className="flex items-center gap-1 border-none px-2 text-white"
                    style={{ backgroundColor: tag.color }}
                >
                    {tag.name}
                    <button
                        disabled={loading}
                        onClick={() => handleRemove(tag.id)}
                        className="hover:bg-red/20 rounded-full"
                    >
                        <X className="h-3 w-3" />
                    </button>
                </Badge>
            ))}

            <Popover>
                <PopoverTrigger asChild>
                    <button className="h-6 w-6 flex items-center justify-center rounded-full border border-dashed border-slate-400 hover:bg-slate-50">
                        {loading ? <Loader2 className="h-3 w-3 animate-spin" /> : <Plus className="h-3 w-3" />}
                    </button>
                </PopoverTrigger>
                <PopoverContent className="p-0 w-[220px]" align="start">
                    <Command>
                        <CommandInput placeholder="Search tags..." />
                        <CommandList>
                            <CommandEmpty>No results.</CommandEmpty>
                            <CommandGroup>
                                {allTags.map((tag) => (
                                    <CommandItem
                                        key={tag.id}
                                        onSelect={() => handleAdd(tag.id)}
                                        disabled={sublimation.tags.some((t: any) => t.id === tag.id)}
                                    >
                                        <div className="mr-2 h-2 w-2 rounded-full" style={{ backgroundColor: tag.color }} />
                                        {tag.name}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                        <CommandSeparator />
                        <form onSubmit={handleCreateTag} className="flex items-center gap-1.5 p-2">
                            <Input
                                value={newTagName}
                                onChange={(e) => setNewTagName(e.target.value)}
                                placeholder="New tag name..."
                                className="h-8 text-xs"
                            />
                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                className="h-8 shrink-0 text-xs"
                                disabled={!newTagName.trim() || isCreating}
                            >
                                {isCreating ? <Loader2 className="h-3 w-3 animate-spin" /> : 'Add'}
                            </Button>
                        </form>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    )
}
