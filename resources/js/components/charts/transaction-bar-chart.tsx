import { Bar, BarChart, CartesianGrid, XAxis } from "recharts"
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent
} from '@/components/ui/chart';
import type { ChartConfig
} from '../ui/chart';

// 1. Define the appearance of your data keys
const chartConfig = {
    total: {
        label: "Total Billed",
        // Option A: Use a theme variable (defined in globals.css)
        color: "#000000",
    },
    paid: {
        label: "Amount Paid",
        // Option B: Use a direct Hex or HSL string
        color: "#10b981",
    },
} satisfies ChartConfig

export default function TransactionBarChart({ chartData }: any) {
    return (
        // "h-full w-full" allows it to fill the 400px container we set above
        <ChartContainer config={chartConfig} className="h-full w-full">
            <BarChart accessibilityLayer data={chartData}>
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="date"
                    tickLine={false}
                    tickMargin={10}
                    axisLine={false}
                    tickFormatter={(value) => value.split('-').slice(1).join('/')} // Shorten dates
                />
                <ChartTooltip content={<ChartTooltipContent />} />
                <Bar dataKey="total" fill="var(--color-total)" radius={4} />
                <Bar dataKey="paid" fill="var(--color-paid)" radius={4} />
            </BarChart>
        </ChartContainer>
    )
}
