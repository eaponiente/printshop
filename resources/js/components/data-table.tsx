"use client"

import { router } from "@inertiajs/react"
import type { ColumnDef ,
    SortingState,
    ColumnFiltersState} from "@tanstack/react-table";
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
    getSortedRowModel,
    getFilteredRowModel
} from "@tanstack/react-table"

import { useState } from 'react';
import { Button } from "@/components/ui/button"
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table"

interface DataTableProps<TData> {
    columns: ColumnDef<TData, any>[]
    // 'pagination' represents the object returned by Laravel ->paginate()
    pagination: {
        data: TData[]
        prev_page_url: string | null
        next_page_url: string | null
        current_page: number
        last_page: number
        total: number
    }
}

export function DataTable<TData>({ columns, pagination }: DataTableProps<TData>) {
    const [sorting, setSorting] = useState<SortingState>([])
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>(
        []
    )

    const table = useReactTable({
        data: pagination.data, // Access the array here
        columns,
        getCoreRowModel: getCoreRowModel(),
        onSortingChange: setSorting,
        getSortedRowModel: getSortedRowModel(),
        onColumnFiltersChange: setColumnFilters,
        getFilteredRowModel: getFilteredRowModel(),
        state: {
            sorting,
            columnFilters
        },
    })

    return (
        <div className="space-y-4">
            <div className="rounded-md">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {flexRender(header.column.columnDef.header, header.getContext())}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    className="even:bg-black/[0.07] dark:even:bg-white/[0.07]"
                                    key={row.id}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-24 text-center">
                                    No results.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Pagination Controls */}
            <div className="flex items-center justify-between px-2">
                <div className="text-sm text-muted-foreground">
                    Page {pagination.current_page} of {pagination.last_page} ({pagination.total} total)
                </div>
                <div className="flex items-center space-x-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.get(pagination.prev_page_url!)}
                        disabled={!pagination.prev_page_url}
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.get(pagination.next_page_url!)}
                        disabled={!pagination.next_page_url}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    )
}
