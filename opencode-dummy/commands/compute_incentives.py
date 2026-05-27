#!/usr/bin/env python3
"""
Command: compute_incentives

Runs IncentiveService for all branches (or a specific branch), outputs
suggested payables per admin with cash-on-hand availability checks.

Usage:
    python compute_incentives.py --all-branches
    python compute_incentives.py --branch-id=1 --month=5 --year=2026
    python compute_incentives.py --all-branches --auto-pay-threshold=5000
"""

import argparse
import subprocess
import sys
import json
from typing import List, Dict, Any, Optional
from dataclasses import dataclass, asdict

sys.path.insert(0, "../skills")
from currency import format_peso, round_peso
from date_range import parse_date_string, get_date_range_for_mode

ARTISAN_PATH = "php /var/www/html/artisan"
PROJECT_ROOT = "/var/www/html"


@dataclass
class BranchIncentive:
    branch_id: int
    branch_name: str
    admin_name: str
    admin_user_id: int
    month: int
    year: int
    total_revenue: float
    total_expenses: float
    net_income: float
    cash_on_hand: float
    suggested_incentive: float
    cash_portion: float
    owner_contribution: float
    status: str


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Compute monthly incentives for branches")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--all-branches", action="store_true")
    group.add_argument("--branch-id", type=int)
    parser.add_argument("--month", type=int, help="Month (1-12). Defaults to previous month.")
    parser.add_argument("--year", type=int, help="Year. Defaults to current year.")
    parser.add_argument("--auto-pay-threshold", type=float, default=5000.00,
                        help="Max amount to auto-pay without superadmin approval")
    parser.add_argument("--rate", type=float, default=0.05,
                        help="Incentive rate as decimal (e.g., 0.05 = 5%% of net income)")
    parser.add_argument("--output", type=str, choices=["json", "text"], default="text")
    return parser.parse_args()


def fetch_branch_incentives(branch_id: int, month: int, year: int) -> Optional[Dict[str, Any]]:
    cmd = [
        ARTISAN_PATH, "incentive:compute",
        f"--branch-id={branch_id}",
        f"--month={month}",
        f"--year={year}",
        "--format=json",
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout)
    except (subprocess.CalledProcessError, json.JSONDecodeError) as e:
        print(f"Error computing incentives for branch {branch_id}: {e}", file=sys.stderr)
        return None


def get_branches(branch_id: Optional[int] = None) -> List[Dict[str, Any]]:
    cmd = [ARTISAN_PATH, "branch:list", "--format=json"]
    if branch_id:
        cmd.append(f"--branch-id={branch_id}")
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout)
    except (subprocess.CalledProcessError, json.JSONDecodeError):
        return []


def pay_incentive(branch_id: int, user_id: int, month: int, year: int, amount: float) -> bool:
    cmd = [
        ARTISAN_PATH, "incentive:pay",
        f"--branch-id={branch_id}",
        f"--user-id={user_id}",
        f"--month={month}",
        f"--year={year}",
        f"--amount={amount}",
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout).get("success", False)
    except subprocess.CalledProcessError:
        return False


def format_text(incentives: List[BranchIncentive]) -> str:
    if not incentives:
        return "\nNo incentive data found."

    lines = [
        f"\nMonthly Incentive Report",
        f"{'=' * 70}",
    ]
    total_net = 0.0
    total_incentive = 0.0

    for inv in incentives:
        total_net += inv.net_income
        total_incentive += inv.suggested_incentive
        lines.append(f"\n[{inv.branch_name}] {inv.admin_name}")
        lines.append(f"  Revenue:    {format_peso(inv.total_revenue)}")
        lines.append(f"  Expenses:   {format_peso(inv.total_expenses)}")
        lines.append(f"  Net Income: {format_peso(inv.net_income)}")
        lines.append(f"  Cash on Hand: {format_peso(inv.cash_on_hand)}")
        lines.append(f"  Suggested Incentive: {format_peso(inv.suggested_incentive)}")
        lines.append(f"    Cash Portion:      {format_peso(inv.cash_portion)}")
        lines.append(f"    Owner Contribution: {format_peso(inv.owner_contribution)}")
        lines.append(f"  Status: {inv.status}")

    lines.append(f"\n{'=' * 70}")
    lines.append(f"Total Net Income (all branches):  {format_peso(total_net)}")
    lines.append(f"Total Incentives (suggested):     {format_peso(total_incentive)}")

    return "\n".join(lines)


def main() -> None:
    args = parse_args()

    from datetime import date
    today = date.today()

    if args.month is None:
        # Default to previous month
        if today.month == 1:
            month = 12
            year = today.year - 1
        else:
            month = today.month - 1
            year = today.year
    else:
        month = args.month
        year = args.year or today.year

    if args.all_branches:
        branches = get_branches()
    else:
        branches = get_branches(args.branch_id)

    if not branches:
        print("No branches found.", file=sys.stderr)
        sys.exit(1)

    incentives: List[BranchIncentive] = []

    for branch in branches:
        bid = branch["id"]
        data = fetch_branch_incentives(bid, month, year)
        if data is None:
            continue

        net_income = float(data.get("net_income", 0))
        cash = float(data.get("cash_on_hand", 0))
        suggested = round_peso(net_income * args.rate)
        cash_portion = round_peso(min(suggested, cash))
        owner_contribution = round_peso(max(0, suggested - cash))

        inv = BranchIncentive(
            branch_id=bid,
            branch_name=branch.get("name", f"Branch {bid}"),
            admin_name=data.get("admin_name", ""),
            admin_user_id=int(data.get("admin_user_id", 0)),
            month=month,
            year=year,
            total_revenue=float(data.get("total_revenue", 0)),
            total_expenses=float(data.get("total_expenses", 0)),
            net_income=net_income,
            cash_on_hand=cash,
            suggested_incentive=suggested,
            cash_portion=cash_portion,
            owner_contribution=owner_contribution,
            status="computed",
        )

        # Auto-pay if below threshold and cash covers full amount
        if suggested <= args.auto_pay_threshold and cash_portion >= suggested:
            if pay_incentive(bid, inv.admin_user_id, month, year, suggested):
                inv.status = "paid"

        incentives.append(inv)

    if args.output == "json":
        print(json.dumps([asdict(inv) for inv in incentives], indent=2))
    else:
        print(format_text(incentives))


if __name__ == "__main__":
    main()
