#!/usr/bin/env python3
"""
Command: check_overdue

Finds transactions where the due date has passed and status is not PAID.
Groups by branch and customer for collection action.

Usage:
    python check_overdue.py [--threshold-days=7] [--branch-id=N] [--output=json|text]
"""

import argparse
import subprocess
import sys
import json
from datetime import date, datetime, timedelta
from typing import List, Dict, Any, Optional
from dataclasses import dataclass, asdict

sys.path.insert(0, "../skills")
from currency import format_peso
from date_range import parse_date_string, days_ago

ARTISAN_PATH = "php /var/www/html/artisan"
PROJECT_ROOT = "/var/www/html"


@dataclass
class OverdueTransaction:
    id: int
    invoice_number: str
    customer_name: str
    branch_name: str
    amount_total: float
    amount_paid: float
    balance: float
    transaction_date: str
    due_date: Optional[str]
    status: str
    days_overdue: int


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Find overdue transactions")
    parser.add_argument("--threshold-days", type=int, default=7, help="Minimum days overdue to include")
    parser.add_argument("--branch-id", type=int, help="Filter by branch")
    parser.add_argument("--output", type=str, choices=["json", "text"], default="text")
    parser.add_argument("--limit", type=int, default=100, help="Max results")
    return parser.parse_args()


def fetch_overdue(
    threshold_days: int,
    branch_id: Optional[int],
    limit: int,
) -> List[Dict[str, Any]]:
    cmd = [
        ARTISAN_PATH, "transaction:overdue",
        f"--threshold-days={threshold_days}",
        f"--limit={limit}",
        "--format=json",
    ]
    if branch_id:
        cmd.append(f"--branch-id={branch_id}")
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout).get("data", [])
    except (subprocess.CalledProcessError, json.JSONDecodeError) as e:
        print(f"Error: {e}", file=sys.stderr)
        return []


def parse_overdue(raw: List[Dict[str, Any]]) -> List[OverdueTransaction]:
    today = date.today()
    parsed = []
    for item in raw:
        due_date_str = item.get("due_at")
        days_overdue = 0
        if due_date_str:
            due_date = parse_date_string(due_date_str)
            if due_date:
                days_overdue = (today - due_date).days
        parsed.append(OverdueTransaction(
            id=item["id"],
            invoice_number=item.get("invoice_number", ""),
            customer_name=item.get("customer_name", item.get("particular", "Walk-in")),
            branch_name=item.get("branch_name", ""),
            amount_total=float(item.get("amount_total", 0)),
            amount_paid=float(item.get("amount_paid", 0)),
            balance=float(item.get("balance", 0)),
            transaction_date=item.get("transaction_date", ""),
            due_date=due_date_str,
            status=item.get("status", "pending"),
            days_overdue=days_overdue,
        ))
    return parsed


def format_text(transactions: List[OverdueTransaction]) -> str:
    if not transactions:
        return "\nNo overdue transactions found."
    lines = [
        f"\nOverdue Transactions Report",
        f"Generated: {date.today().isoformat()}",
        f"Total overdue: {len(transactions)}",
        f"{'=' * 80}",
    ]
    for t in transactions:
        lines.append(
            f"\n  #{t.invoice_number} | {t.customer_name} | {t.branch_name}"
            f"\n  Amount: {format_peso(t.amount_total)} | Paid: {format_peso(t.amount_paid)} | "
            f"Balance: {format_peso(t.balance)}"
            f"\n  Status: {t.status.upper()} | Overdue: {t.days_overdue} days"
            f"\n  Date: {t.transaction_date}" + (f" | Due: {t.due_date}" if t.due_date else "")
            f"\n  {'-' * 60}"
        )
    return "\n".join(lines)


def main() -> None:
    args = parse_args()
    raw = fetch_overdue(args.threshold_days, args.branch_id, args.limit)
    transactions = parse_overdue(raw)
    if args.output == "json":
        print(json.dumps([asdict(t) for t in transactions], indent=2))
    else:
        print(format_text(transactions))


if __name__ == "__main__":
    main()
