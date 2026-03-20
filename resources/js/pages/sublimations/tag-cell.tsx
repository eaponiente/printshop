import { router } from '@inertiajs/react'
import { X, Plus, Loader2 } from "lucide-react"
import { useState } from "react"
import { route } from 'ziggy-js';
import { Badge } from "@/components/ui/badge"
import { Command, CommandInput, CommandList, CommandEmpty, CommandGroup, CommandItem } from "@/components/ui/command"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"

export const TagCell = ({ sublimation, allTags }: { sublimation: any; allTags: any[] }) => {
    const [loading, setLoading] = useState(false);

    const handleAdd = (tagId: number) => {
        setLoading(true);
        router.post(`/sublimations/${sublimation.id}/tags`, {
            tag_id: tagId
        }, {
            preserveScroll: true,
            onFinish: () => setLoading(false),
        });
    }

    const handleRemove = (tagId: number) => {
        setLoading(true);
        router.delete(`/sublimations/${sublimation.id}/tags/${tagId}`, {
            preserveScroll: true,
            onFinish: () => setLoading(false),
        });
    }

    return (
        <div className="flex flex-wrap gap-1 items-center">
            {/* Existing Tags */}
            {sublimation.tags.map((tag: any) => (
                <Badge
                    key={tag.id}
                    className={`${tag.color} text-white flex items-center gap-1 bsublimation-none px-2`}
                >
                    {tag.name}
                    <button
                        disabled={loading}
                        onClick={() => handleRemove(tag.id)}
                        className="hover:bg-black/20 rounded-full"
                    >
                        <X className="h-3 w-3" />
                    </button>
                </Badge>
            ))}

            {/* Add Tag Popover */}
            <Popover>
                <PopoverTrigger asChild>
                    <button className="h-6 w-6 flex items-center justify-center rounded-full bsublimation bsublimation-dashed bsublimation-slate-400 hover:bg-slate-50">
                        {loading ? <Loader2 className="h-3 w-3 animate-spin" /> : <Plus className="h-3 w-3" />}
                    </button>
                </PopoverTrigger>
                <PopoverContent className="p-0 w-[200px]" align="start">
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
                                        <div className={`mr-2 h-2 w-2 rounded-full ${tag.color}`} />
                                        {tag.name}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    )
}
