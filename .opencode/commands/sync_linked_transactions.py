#!/usr/bin/env python3
"""
Command: sync_linked_transactions

Ensures that sublimation amount_total values match their linked transaction
amount_total values. Detects mismatches and reports (or fixes) them.

Usage:
    python sync_linked_transactions.py [--fix] [--branch-id=N] [--output=json|text]
"""

import argparse
import subprocess
import sys
import json
from typing import List, Dict, Any, Optional
from dataclasses import dataclass, asdict

sys.path.insert(0, "../skills")
from currency import format_peso
from status_machine import is_status_locked_for_amount_sync

ARTISAN_PATH = "php /var/www/html/artisan"
PROJECT_ROOT = "/var/www/html"


@dataclass
class SyncMismatch:
    sublimation_id: int
    sublimation_status: str
    transaction_id: int
    transaction_status: str
    sublimation_amount: float
    transaction_amount: float
    difference: float
    is_locked: bool


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Sync sublimation amounts with linked transactions"
    )
    parser.add_argument("--fix", action="store_true", help="Auto-fix mismatches where safe")
    parser.add_argument("--branch-id", type=int)
    parser.add_argument("--output", type=str, choices=["json", "text"], default="text")
    return parser.parse_args()


def fetch_linked_sublimations(branch_id: Optional[int]) -> List[Dict[str, Any]]:
    cmd = [ARTISAN_PATH, "sublimation:linked-transactions", "--format=json"]
    if branch_id:
        cmd.append(f"--branch-id={branch_id}")
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout).get("data", [])
    except (subprocess.CalledProcessError, json.JSONDecodeError) as e:
        print(f"Error: {e}", file=sys.stderr)
        return []


def fix_mismatch(sublimation_id: int, new_amount: float) -> bool:
    cmd = [
        ARTISAN_PATH, "sublimation:sync-amount",
        f"--sublimation-id={sublimation_id}",
        f"--amount={new_amount}",
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout).get("success", False)
    except subprocess.CalledProcessError:
        return False


def main() -> None:
    args = parse_args()
    linked = fetch_linked_sublimations(args.branch_id)
    mismatches: List[SyncMismatch] = []

    for item in linked:
        sub_amount = float(item.get("amount_total", 0))
        txn_amount = float(item.get("transaction_amount_total", 0))
        diff = round(sub_amount - txn_amount, 2)

        if diff != 0.0:
            locked = is_status_locked_for_amount_sync(item.get("transaction_status", ""))
            mismatches.append(SyncMismatch(
                sublimation_id=item["id"],
                sublimation_status=item.get("status", ""),
                transaction_id=item.get("transaction_id", 0),
                transaction_status=item.get("transaction_status", ""),
                sublimation_amount=sub_amount,
                transaction_amount=txn_amount,
                difference=diff,
                is_locked=locked,
            ))

            if args.fix and not locked:
                success = fix_mismatch(item["id"], sub_amount)
                if not success:
                    print(f"Failed to fix sublimation #{item['id']}", file=sys.stderr)

    if args.output == "json":
        print(json.dumps([asdict(m) for m in mismatches], indent=2))
    else:
        if not mismatches:
            print("\nAll linked sublimation-transaction amounts are in sync.")
        else:
            print(f"\nFound {len(mismatches)} mismatches:")
            for m in mismatches:
                lock_str = " [LOCKED - payments exist]" if m.is_locked else ""
                print(
                    f"  Sublimation #{m.sublimation_id} ({m.sublimation_status}) -> "
                    f"Transaction #{m.transaction_id} ({m.transaction_status}){lock_str}"
                    f"\n    Sublimation: {format_peso(m.sublimation_amount)} | "
                    f"Transaction: {format_peso(m.transaction_amount)} | "
                    f"Diff: {format_peso(m.difference)}"
                )


if __name__ == "__main__":
    main()
