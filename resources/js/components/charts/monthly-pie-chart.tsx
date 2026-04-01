import * as React from 'react';
import { Pie, PieChart, Cell } from 'recharts';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    ChartLegend,
    ChartLegendContent,
} from '@/components/ui/chart';

export default function SixMonthPie({ pieData }: any) {
    // 1. Create the Config with 6 distinct colors
    const chartConfig = {
        january: { label: 'January', color: '#2563eb' }, // Blue
        february: { label: 'February', color: '#10b981' }, // Emerald
        march: { label: 'March', color: '#f59e0b' }, // Amber
        april: { label: 'April', color: '#ef4444' }, // Red
        may: { label: 'May', color: '#8b5cf6' }, // Violet
        june: { label: 'June', color: '#f472b6' }, // Pink
        july: { label: 'July', color: '#06b6d4' }, // Cyan
        august: { label: 'August', color: '#fb923c' }, // Orange
        september: { label: 'September', color: '#14b8a6' }, // Teal
        october: { label: 'October', color: '#6366f1' }, // Indigo
        november: { label: 'November', color: '#a855f7' }, // Purple
        december: { label: 'December', color: '#ec4899' }, // Rose
    } satisfies ChartConfig;

    return (
        <div className="flex h-full w-full flex-col">
            <ChartContainer
                config={chartConfig}
                className="mx-auto aspect-square max-h-[300px] w-full"
            >
                <PieChart>
                    <ChartTooltip
                        cursor={false}
                        content={
                            <ChartTooltipContent
                                hideLabel={false} // Ensure month name shows
                                formatter={(value: any, name: any) => (
                                    <div className="flex min-w-[130px] items-center justify-between gap-4">
                                        <span className="text-muted-foreground">
                                            {chartConfig[
                                                name as keyof typeof chartConfig
                                            ]?.label || name}
                                        </span>
                                        <span className="font-mono font-medium text-foreground">
                                            {/* Adding the space and formatting the number */}
                                            {Number(value).toLocaleString()}
                                        </span>
                                    </div>
                                )}
                            />
                        }
                    />
                    <Pie data={pieData} dataKey="total" nameKey="month">
                        {pieData.map((entry: any, index: any) => (
                            <Cell
                                key={`cell-${index}`}
                                fill={
                                    // Cast the string to a key of chartConfig
                                    chartConfig[entry.month.toLowerCase() as keyof typeof chartConfig]
                                        ?.color || '#cbd5e1'
                                }
                            />
                        ))}
                    </Pie>
                    {/* Added a Legend at the bottom to identify colors */}
                    <ChartLegend
                        content={
                            <ChartLegendContent className="-translate-y-2 flex-wrap" />
                        }
                    />
                </PieChart>
            </ChartContainer>
        </div>
    );
}
