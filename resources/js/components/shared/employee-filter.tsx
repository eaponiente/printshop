import { router } from '@inertiajs/react';
import { Check, ChevronsUpDown, Filter, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

type ColumnOption = { key: string; value: string };
type ValueOption = { key: string; value: string };
type FilterEntry = { column: string; value: string };

interface EmployeeFilterProps {
    columns: ColumnOption[];
    filters: FilterEntry[];
    statuses: ValueOption[];
    positions: ValueOption[];
    url: string;
}

export function EmployeeFilter({
    columns,
    filters,
    statuses,
    positions,
    url,
}: EmployeeFilterProps) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<FilterEntry[]>(filters);
    const activeCount = selected.filter((s) => s.column && s.value).length;

    const toggleColumn = (col: string) => {
        setSelected((prev) => {
            const exists = prev.find((s) => s.column === col);

            if (exists) {
                return prev.filter((s) => s.column !== col);
            }

            return [...prev, { column: col, value: '' }];
        });
    };

    const updateValue = (col: string, val: string) => {
        setSelected((prev) =>
            prev.map((s) => (s.column === col ? { ...s, value: val } : s)),
        );
    };

    const apply = () => {
        const valid = selected.filter((s) => s.column && s.value);

        if (valid.length === 0) {
            return;
        }

        const params: Record<string, string> = {};
        valid.forEach((f, i) => {
            params[`filters[${i}][column]`] = f.column;
            params[`filters[${i}][value]`] = f.value;
        });

        router.get(url, params, { preserveState: true, replace: true });

        setOpen(false);
    };

    const clear = () => {
        setSelected([]);
        setOpen(false);
        router.get(url, {}, { replace: true });
    };

    return (
        <div className="self-start">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 gap-1.5 border-dashed px-2.5 text-xs"
                    >
                        <Filter className="h-3.5 w-3.5" />
                        Filters
                        {activeCount > 0 && (
                            <Badge
                                variant="secondary"
                                className="ml-1 rounded-sm px-1.5 py-0 text-xs font-medium"
                            >
                                {activeCount}
                            </Badge>
                        )}
                        <ChevronsUpDown className="ml-1 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-[280px] p-0" align="start">
                    <Command>
                        <CommandInput placeholder="Search columns..." />
                        <CommandList>
                            <CommandGroup>
                                {columns.map((col) => {
                                    const entry = selected.find(
                                        (s) => s.column === col.key,
                                    );
                                    const isChecked = !!entry;

                                    return (
                                        <div key={col.key}>
                                            <CommandItem
                                                onSelect={() =>
                                                    toggleColumn(col.key)
                                                }
                                                className="flex items-center gap-2"
                                            >
                                                <div
                                                    className={`flex h-4 w-4 shrink-0 items-center justify-center rounded-sm border ${isChecked ? 'border-primary bg-primary text-primary-foreground' : 'border-muted-foreground/30'}`}
                                                >
                                                    {isChecked && (
                                                        <Check className="h-3 w-3" />
                                                    )}
                                                </div>
                                                <span className="text-sm">
                                                    {col.value}
                                                </span>
                                            </CommandItem>
                                            {isChecked && (
                                                <div className="mx-2 mb-2 w-48">
                                                    {col.key === 'position' ? (
                                                        <NativeSelect
                                                            value={entry.value}
                                                            onChange={(e) =>
                                                                updateValue(
                                                                    col.key,
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-8 text-xs"
                                                        >
                                                            <NativeSelectOption value="">
                                                                Position...
                                                            </NativeSelectOption>
                                                            {positions.map(
                                                                (p) => (
                                                                    <NativeSelectOption
                                                                        key={
                                                                            p.key
                                                                        }
                                                                        value={
                                                                            p.key
                                                                        }
                                                                    >
                                                                        {
                                                                            p.value
                                                                        }
                                                                    </NativeSelectOption>
                                                                ),
                                                            )}
                                                        </NativeSelect>
                                                    ) : col.key === 'status' ? (
                                                        <NativeSelect
                                                            value={entry.value}
                                                            onChange={(e) =>
                                                                updateValue(
                                                                    col.key,
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-8 text-xs"
                                                        >
                                                            <NativeSelectOption value="">
                                                                Status...
                                                            </NativeSelectOption>
                                                            {statuses.map(
                                                                (s) => (
                                                                    <NativeSelectOption
                                                                        key={
                                                                            s.key
                                                                        }
                                                                        value={
                                                                            s.key
                                                                        }
                                                                    >
                                                                        {
                                                                            s.value
                                                                        }
                                                                    </NativeSelectOption>
                                                                ),
                                                            )}
                                                        </NativeSelect>
                                                    ) : (
                                                        <Input
                                                            value={entry.value}
                                                            onChange={(e) =>
                                                                updateValue(
                                                                    col.key,
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Value..."
                                                            className="h-8 text-xs"
                                                        />
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                    <div className="flex items-center justify-between border-t p-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={clear}
                            className="h-8 text-xs"
                            disabled={activeCount === 0}
                        >
                            <X className="mr-1 h-3.5 w-3.5" />
                            Clear
                        </Button>
                        <Button
                            size="sm"
                            onClick={apply}
                            className="h-8 text-xs"
                            disabled={activeCount === 0}
                        >
                            Apply
                        </Button>
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    );
}
