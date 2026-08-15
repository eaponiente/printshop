import { Check, ChevronsUpDown } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type BranchOption = { id: number; name: string };

type BranchMultiSelectProps = {
    branches: BranchOption[];
    value: number[];
    onChange: (value: number[]) => void;
    disabled?: boolean;
    placeholder?: string;
};

/**
 * Multi-select combobox for picking a subset of branches. An empty selection
 * is treated by callers as "applies to every branch" — this component only
 * renders that as an explicit placeholder, it doesn't encode the semantics.
 */
export function BranchMultiSelect({
    branches,
    value,
    onChange,
    disabled = false,
    placeholder = 'All branches (nationwide)',
}: BranchMultiSelectProps) {
    const selected = new Set(value);
    const selectedBranches = branches.filter((branch) =>
        selected.has(branch.id),
    );

    const toggle = (id: number) => {
        onChange(
            selected.has(id) ? value.filter((v) => v !== id) : [...value, id],
        );
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    disabled={disabled}
                    className="h-auto min-h-9 w-full justify-between font-normal"
                >
                    <div className="flex flex-wrap gap-1">
                        {selectedBranches.length === 0 ? (
                            <span className="text-muted-foreground">
                                {placeholder}
                            </span>
                        ) : (
                            <>
                                {selectedBranches.slice(0, 2).map((branch) => (
                                    <Badge
                                        key={branch.id}
                                        variant="secondary"
                                        className="rounded-sm px-1 font-normal"
                                    >
                                        {branch.name}
                                    </Badge>
                                ))}
                                {selectedBranches.length > 2 && (
                                    <Badge
                                        variant="secondary"
                                        className="rounded-sm px-1 font-normal"
                                    >
                                        +{selectedBranches.length - 2}
                                    </Badge>
                                )}
                            </>
                        )}
                    </div>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[260px] p-0" align="start">
                <Command>
                    <CommandInput placeholder="Search branches..." />
                    <CommandList>
                        <CommandEmpty>No branches found.</CommandEmpty>
                        <CommandGroup>
                            {branches.map((branch) => (
                                <CommandItem
                                    key={branch.id}
                                    onSelect={() => toggle(branch.id)}
                                >
                                    <div
                                        className={cn(
                                            'mr-2 flex h-4 w-4 items-center justify-center rounded-sm border border-primary',
                                            selected.has(branch.id)
                                                ? 'bg-primary text-primary-foreground'
                                                : 'opacity-50 [&_svg]:invisible',
                                        )}
                                    >
                                        <Check className="h-4 w-4" />
                                    </div>
                                    <span>{branch.name}</span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                        {selected.size > 0 && (
                            <>
                                <div className="h-[1px] bg-border" />
                                <CommandGroup>
                                    <CommandItem
                                        onSelect={() => onChange([])}
                                        className="justify-center text-center"
                                    >
                                        Clear (apply to all branches)
                                    </CommandItem>
                                </CommandGroup>
                            </>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
