#!/usr/bin/env python3
"""
Command: reconcile_cash

Compares the CashOnHand balance for each branch against the computed
expected balance: sum of cash/gcash payments minus sum of cash/gcash expenses.

Usage:
    python reconcile_cash.py [--branch-id=N] [--output=json|text]
    python reconcile_cash.py --all-branches
"""

import argparse
import subprocess
import sys
import json
from datetime import date
from typing import List, Dict, Any, Optional, Tuple
from dataclasses import dataclass, asdict

sys.path.insert(0, "../skills")
from currency import format_peso
from branch_scope import is_special_group_branch

ARTISAN_PATH = "php /var/www/html/artisan"
PROJECT_ROOT = "/var/www/html"
CASH_PAYMENT_TYPES = ["cash", "gcash"]
RECONCILIATION_TOLERANCE = 0.01


@dataclass
class BranchReconciliation:
    branch_id: int
    branch_name: str
    recorded_cash_on_hand: float
    total_cash_payments: float
    total_cash_expenses: float
    endorsements_in: float
    endorsements_out: float
    expected_cash: float
    discrepancy: float
    status: str  # "balanced", "short", "over", "error"
    error_message: str = ""


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Reconcile Cash on Hand across branches"
    )
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--branch-id", type=int)
    group.add_argument("--all-branches", action="store_true")
    parser.add_argument("--output", type=str, choices=["json", "text"], default="text")
    parser.add_argument("--date-from", type=str, help="Filter from date (YYYY-MM-DD)")
    parser.add_argument("--date-to", type=str, help="Filter to date (YYYY-MM-DD)")
    return parser.parse_args()


def run_artisan(command: str, args: List[str]) -> Dict[str, Any]:
    """Execute an artisan command and return JSON output."""
    cmd = [ARTISAN_PATH, command] + args + ["--format=json"]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout)
    except (subprocess.CalledProcessError, json.JSONDecodeError) as e:
        return {"error": str(e), "data": []}


def get_branches(branch_id: Optional[int] = None) -> List[Dict[str, Any]]:
    args = []
    if branch_id:
        args.append(f"--branch-id={branch_id}")
    result = run_artisan("branch:list", args)
    return result.get("data", []) if "data" in result else result if isinstance(result, list) else []


def get_cash_on_hand(branch_id: int) -> float:
    result = run_artisan("cash:balance", [f"--branch-id={branch_id}"])
    return float(result.get("amount", 0.0))


def get_payment_aggregates(branch_id: int, date_from: Optional[str], date_to: Optional[str]) -> Dict[str, float]:
    args = [f"--branch-id={branch_id}"]
    if date_from:
        args.append(f"--date-from={date_from}")
    if date_to:
        args.append(f"--date-to={date_to}")
    result = run_artisan("sales:aggregates", args)
    aggregates = result.get("data", result)
    return {
        payment_type: float(amount)
        for payment_type, amount in aggregates.items()
    }


def get_expense_totals(branch_id: int, date_from: Optional[str], date_to: Optional[str]) -> Dict[str, float]:
    args = [f"--branch-id={branch_id}", "--status=paid"]
    if date_from:
        args.append(f"--date-from={date_from}")
    if date_to:
        args.append(f"--date-to={date_to}")
    result = run_artisan("expense:totals", args)
    aggregates = result.get("data", result)
    return {
        payment_type: float(amount)
        for payment_type, amount in aggregates.items()
    }


def get_endorsement_totals(branch_id: int) -> Tuple[float, float]:
    """Get endorsements in (received) and out (sent) for a branch."""
    result_in = run_artisan("endorsement:totals", [f"--branch-id={branch_id}", "--direction=in"])
    result_out = run_artisan("endorsement:totals", [f"--branch-id={branch_id}", "--direction=out"])
    amount_in = float(result_in.get("total", 0.0))
    amount_out = float(result_out.get("total", 0.0))
    return amount_in, amount_out


def reconcile_branch(
    branch: Dict[str, Any],
    date_from: Optional[str] = None,
    date_to: Optional[str] = None,
) -> BranchReconciliation:
    branch_id = branch["id"]
    branch_name = branch.get("name", "Unknown")

    try:
        recorded_cash = get_cash_on_hand(branch_id)
        payments = get_payment_aggregates(branch_id, date_from, date_to)
        expenses = get_expense_totals(branch_id, date_from, date_to)
        endorsements_in, endorsements_out = get_endorsement_totals(branch_id)

        total_cash_in = sum(payments.get(t, 0.0) for t in CASH_PAYMENT_TYPES)
        total_cash_out = sum(expenses.get(t, 0.0) for t in CASH_PAYMENT_TYPES)

        expected = total_cash_in + endorsements_in - total_cash_out - endorsements_out
        discrepancy = recorded_cash - expected

        if abs(discrepancy) <= RECONCILIATION_TOLERANCE:
            status = "balanced"
        elif discrepancy > 0:
            status = "over"
        else:
            status = "short"

        return BranchReconciliation(
            branch_id=branch_id,
            branch_name=branch_name,
            recorded_cash_on_hand=round(recorded_cash, 2),
            total_cash_payments=round(total_cash_in, 2),
            total_cash_expenses=round(total_cash_out, 2),
            endorsements_in=round(endorsements_in, 2),
            endorsements_out=round(endorsements_out, 2),
            expected_cash=round(expected, 2),
            discrepancy=round(discrepancy, 2),
            status=status,
        )
    except Exception as e:
        return BranchReconciliation(
            branch_id=branch_id,
            branch_name=branch_name,
            recorded_cash_on_hand=0.0,
            total_cash_payments=0.0,
            total_cash_expenses=0.0,
            endorsements_in=0.0,
            endorsements_out=0.0,
            expected_cash=0.0,
            discrepancy=0.0,
            status="error",
            error_message=str(e),
        )


def format_text(reconciliations: List[BranchReconciliation]) -> str:
    lines = ["\nCash on Hand Reconciliation Report", f"Date: {date.today().isoformat()}", "=" * 70]
    for r in reconciliations:
        icon = "\u2713" if r.status == "balanced" else "\u2717" if r.status == "error" else "\u26a0"
        lines.append(f"\n[{icon}] {r.branch_name} (ID: {r.branch_id}) - {r.status.upper()}")
        lines.append(f"  Recorded Cash:     {format_peso(r.recorded_cash_on_hand)}")
        lines.append(f"  Cash In (+):       {format_peso(r.total_cash_payments)}")
        lines.append(f"  Endorsements In:   {format_peso(r.endorsements_in)}")
        lines.append(f"  Cash Out (-):      {format_peso(r.total_cash_expenses)}")
        lines.append(f"  Endorsements Out:  {format_peso(r.endorsements_out)}")
        lines.append(f"  Expected Cash:     {format_peso(r.expected_cash)}")
        lines.append(f"  Discrepancy:       {format_peso(r.discrepancy)}")
        if r.error_message:
            lines.append(f"  Error: {r.error_message}")
    return "\n".join(lines)


def main() -> None:
    args = parse_args()

    if args.all_branches:
        branches = get_branches()
    else:
        branches = get_branches(args.branch_id)

    if not branches:
        print("No branches found.", file=sys.stderr)
        sys.exit(1)

    reconciliations = [
        reconcile_branch(b, args.date_from, args.date_to)
        for b in branches
    ]

    if args.output == "json":
        print(json.dumps([asdict(r) for r in reconciliations], indent=2))
    else:
        print(format_text(reconciliations))


if __name__ == "__main__":
    main()
