import { Card, CardContent } from "@/components/ui/card";
import { Branch } from "@/types/branches";
import { formatCurrency } from "@/utils/formatters";
import { Banknote, Wallet, TrendingUp } from "lucide-react";

interface SaleSummarySectionProps {
    cash_on_hand_amount: number;
    cash_amount: number;
    gcash_amount: number;
    bank_transfer_amount: number;
    card_amount: number;
    check_amount: number;
    total_sales: number;
    total_expenses: number;
    net_income: number;
    selectedBranch: Branch | null;
}

export default function SaleSummarySection({
    cash_on_hand_amount,
    cash_amount,
    gcash_amount,
    bank_transfer_amount,
    card_amount,
    check_amount,
    total_sales,
    total_expenses,
    net_income,
    selectedBranch,
}: SaleSummarySectionProps) {
    return (
        <div className="grid gap-3 md:grid-cols-3">
            {/* 1. Total Revenue - Ultra Compact */}
            <Card className="border-sidebar-border bg-sidebar">
                <CardContent className="p-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="mb-1 text-[10px] leading-none font-bold tracking-wider text-muted-foreground uppercase">
                                Cash on Hand
                            </p>
                            <h2 className="text-lg leading-none font-bold">
                                {formatCurrency(cash_on_hand_amount)}
                            </h2>
                        </div>
                        <Banknote className="h-4 w-4 text-primary/40" />
                    </div>
                    <p className="mt-1.5 truncate text-[9px] text-muted-foreground italic opacity-70">
                        {selectedBranch?.name || ''}
                    </p>
                </CardContent>
            </Card>

            <Card className="border-sidebar-border bg-sidebar">
                <CardContent className="p-3">
                    <div className="mb-2 flex items-center justify-between">
                        <p className="text-[10px] leading-none font-bold tracking-wider text-muted-foreground uppercase">
                            Payment Breakdown
                        </p>
                        <Wallet className="h-4 w-4 text-orange-500/40" />
                    </div>

                    {/* 3x2 Grid for 5-6 Payment Types */}
                    <div className="grid grid-cols-3 gap-x-2 gap-y-2">
                        {/* Cash */}
                        <div className="border-r border-sidebar-border pr-1">
                            <p className="mb-1 text-[8px] leading-none font-medium text-muted-foreground uppercase">
                                Cash
                            </p>
                            <p className="truncate text-[11px] leading-none font-bold">
                                {formatCurrency(cash_amount || 0)}
                            </p>
                        </div>

                        {/* GCash */}
                        <div className="border-r border-sidebar-border pr-1">
                            <p className="mb-1 text-[8px] leading-none font-medium text-muted-foreground uppercase">
                                GCash
                            </p>
                            <p className="truncate text-[11px] leading-none font-bold">
                                {formatCurrency(gcash_amount || 0)}
                            </p>
                        </div>

                        {/* Bank Transfer */}
                        <div className="pl-0.5">
                            <p className="mb-1 text-[8px] leading-none font-medium text-muted-foreground uppercase">
                                Bank
                            </p>
                            <p className="truncate text-[11px] leading-none font-bold">
                                {formatCurrency(
                                    bank_transfer_amount || 0,
                                )}
                            </p>
                        </div>

                        {/* Card */}
                        <div className="border-t border-r border-sidebar-border pt-2 pr-1">
                            <p className="mb-1 text-[8px] leading-none font-medium text-muted-foreground uppercase">
                                Card
                            </p>
                            <p className="truncate text-[11px] leading-none font-bold">
                                {formatCurrency(card_amount || 0)}
                            </p>
                        </div>

                        {/* Check */}
                        <div className="border-t border-r border-sidebar-border pt-2 pr-1">
                            <p className="mb-1 text-[8px] leading-none font-medium text-muted-foreground uppercase">
                                Check
                            </p>
                            <p className="truncate text-[11px] leading-none font-bold">
                                {formatCurrency(check_amount || 0)}
                            </p>
                        </div>

                        {/* Placeholder / Other */}
                        <div className="border-t border-sidebar-border pt-2 pl-0.5 opacity-40">
                            <p className="mb-1 text-[8px] leading-none font-medium uppercase">
                                Other
                            </p>
                            <p className="text-[11px] leading-none font-bold italic">
                                —
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* 3. Income - Single Row logic */}
            <Card className="overflow-hidden border-sidebar-border bg-sidebar">
                <CardContent className="p-3">
                    {/* Top Section: The Breakdown */}
                    <div className="mb-2.5 flex items-center justify-between gap-4">
                        <div className="flex-1">
                            <p className="mb-1 text-[9px] leading-none font-bold text-muted-foreground uppercase">
                                Revenue
                            </p>
                            <p className="text-sm leading-none font-semibold text-primary/90">
                                {formatCurrency(total_sales)}
                            </p>
                        </div>

                        <div className="flex-1 border-l border-sidebar-border pl-3">
                            <p className="mb-1 text-[9px] leading-none font-bold text-muted-foreground uppercase">
                                Expenses
                            </p>
                            <p className="text-sm leading-none font-semibold text-destructive/80">
                                {formatCurrency(total_expenses)}
                            </p>
                        </div>

                        <TrendingUp className="h-4 w-4 self-start text-green-500/30" />
                    </div>

                    {/* Bottom Section: The Result */}
                    <div className="border-t border-sidebar-border/50 pt-2">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <p className="mb-1.5 text-[10px] leading-none font-bold tracking-wider text-muted-foreground uppercase">
                                    Net Income
                                </p>
                                <div className="flex items-baseline gap-1.5">
                                    <h2 className="text-lg leading-none font-black tracking-tight">
                                        {formatCurrency(net_income)}
                                    </h2>
                                </div>
                            </div>
                        </div>

                        {/* Visual Progress Bar (Revenue vs Expenses Ratio) */}
                        <div className="mt-2 h-1 w-full overflow-hidden rounded-full bg-sidebar-border">
                            <div
                                className="h-full bg-green-500 opacity-60"
                                style={{
                                    width: `${Math.min((net_income / total_sales) * 100, 100)}%`,
                                }}
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    )
}