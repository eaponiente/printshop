#!/usr/bin/env python3
"""
Command: export_financial_report

Generates financial reports (daily, weekly, monthly, yearly) with revenue
breakdowns by payment type, expense summaries, and net income per branch.
Exports to CSV or JSON.

Usage:
    python export_financial_report.py --mode=daily|weekly|monthly|yearly [--branch-id=N] [--format=csv|json]
    python export_financial_report.py --all-branches --mode=monthly --format=csv
"""

import argparse
import subprocess
import sys
import json
import os
from datetime import date, datetime
from typing import List, Dict, Any, Optional
from dataclasses import dataclass, asdict

sys.path.insert(0, "../skills")
from currency import format_peso, pesos_to_number
from date_range import get_date_range_for_mode, DateRangeMode
from branch_scope import get_accessible_branches

ARTISAN_PATH = "php /var/www/html/artisan"
PROJECT_ROOT = "/var/www/html"
EXPORT_DIR = os.path.join(PROJECT_ROOT, "storage", "app", "reports")


@dataclass
class FinancialReport:
    report_date: str
    mode: str
    branch_id: int
    branch_name: str
    total_revenue: float
    revenue_by_type: Dict[str, float]
    total_expenses: float
    expenses_by_type: Dict[str, float]
    net_income: float
    cash_on_hand: float
    transaction_count: int
    expense_count: int


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export financial reports")
    parser.add_argument("--mode", type=str, required=True,
                        choices=["daily", "weekly", "monthly", "yearly"])
    parser.add_argument("--branch-id", type=int)
    parser.add_argument("--all-branches", action="store_true")
    parser.add_argument("--format", type=str, choices=["csv", "json"], default="csv")
    parser.add_argument("--date", type=str, help="Reference date (YYYY-MM-DD). Defaults to today.")
    return parser.parse_args()


def fetch_financials(branch_id: int, mode: str, ref_date: Optional[str] = None) -> Dict[str, Any]:
    cmd = [
        ARTISAN_PATH, "report:financial",
        f"--mode={mode}",
        f"--branch-id={branch_id}",
        "--format=json",
    ]
    if ref_date:
        cmd.append(f"--date={ref_date}")
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout)
    except (subprocess.CalledProcessError, json.JSONDecodeError) as e:
        print(f"Error fetching financials for branch {branch_id}: {e}", file=sys.stderr)
        return {}


def build_report(raw: Dict[str, Any], mode: str) -> FinancialReport:
    return FinancialReport(
        report_date=raw.get("date", date.today().isoformat()),
        mode=mode,
        branch_id=raw.get("branch_id", 0),
        branch_name=raw.get("branch_name", "Unknown"),
        total_revenue=float(raw.get("total_revenue", 0)),
        revenue_by_type=raw.get("revenue_by_type", {}),
        total_expenses=float(raw.get("total_expenses", 0)),
        expenses_by_type=raw.get("expenses_by_type", {}),
        net_income=float(raw.get("net_income", 0)),
        cash_on_hand=float(raw.get("cash_on_hand", 0)),
        transaction_count=int(raw.get("transaction_count", 0)),
        expense_count=int(raw.get("expense_count", 0)),
    )


def export_csv(reports: List[FinancialReport], mode: str) -> str:
    filename = f"financial_report_{mode}_{date.today().isoformat()}.csv"
    filepath = os.path.join(EXPORT_DIR, filename)
    os.makedirs(EXPORT_DIR, exist_ok=True)

    with open(filepath, "w") as f:
        headers = [
            "Date", "Mode", "Branch ID", "Branch Name", "Total Revenue",
            "Cash Revenue", "GCash Revenue", "Card Revenue", "Bank Transfer Revenue",
            "Total Expenses", "Cash Expenses", "GCash Expenses",
            "Net Income", "Cash On Hand", "Transaction Count", "Expense Count",
        ]
        f.write(",".join(headers) + "\n")

        for r in reports:
            row = [
                r.report_date, r.mode, str(r.branch_id), r.branch_name,
                str(r.total_revenue),
                str(r.revenue_by_type.get("cash", 0)),
                str(r.revenue_by_type.get("gcash", 0)),
                str(r.revenue_by_type.get("card", 0)),
                str(r.revenue_by_type.get("bank_transfer", 0)),
                str(r.total_expenses),
                str(r.expenses_by_type.get("cash", 0)),
                str(r.expenses_by_type.get("gcash", 0)),
                str(r.net_income),
                str(r.cash_on_hand),
                str(r.transaction_count),
                str(r.expense_count),
            ]
            f.write(",".join(row) + "\n")

    return filepath


def main() -> None:
    args = parse_args()

    if args.all_branches:
        branches = get_accessible_branches(superadmin=True)
    elif args.branch_id:
        branches = get_accessible_branches(superadmin=True, branch_id=args.branch_id)
    else:
        branches = get_accessible_branches(superadmin=True)

    if not branches:
        print("No branches found.", file=sys.stderr)
        sys.exit(1)

    reports: List[FinancialReport] = []
    for branch in branches:
        raw = fetch_financials(branch["id"], args.mode, args.date)
        if raw:
            reports.append(build_report(raw, args.mode))

    if args.format == "json":
        print(json.dumps([asdict(r) for r in reports], indent=2, default=str))
    else:
        filepath = export_csv(reports, args.mode)
        print(f"\nReport exported to: {filepath}")
        for r in reports:
            print(f"  {r.branch_name}: Revenue {format_peso(r.total_revenue)} | "
                  f"Expenses {format_peso(r.total_expenses)} | "
                  f"Net {format_peso(r.net_income)}")


if __name__ == "__main__":
    main()
