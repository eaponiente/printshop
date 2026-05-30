#!/usr/bin/env python3
"""
Command: backfill_employee_users

Creates user accounts for employees that have no linked user_id.
Generates username from employee_number and a random temporary password.

Usage:
    python backfill_employee_users.py [--branch-id=N] [--dry-run] [--output=json|text]
    python backfill_employee_users.py --all-branches
"""

import argparse
import subprocess
import sys
import json
import secrets
import string
from typing import List, Dict, Any, Optional
from dataclasses import dataclass, asdict

ARTISAN_PATH = "php /var/www/html/artisan"
PROJECT_ROOT = "/var/www/html"


@dataclass
class BackfillResult:
    employee_id: int
    employee_number: str
    employee_name: str
    username: str
    temp_password: str
    user_id: int
    status: str
    error: str = ""


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Create user accounts for employees missing linked users"
    )
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--branch-id", type=int)
    group.add_argument("--all-branches", action="store_true")
    parser.add_argument("--dry-run", action="store_true", help="Preview without creating")
    parser.add_argument("--output", type=str, choices=["json", "text"], default="text")
    return parser.parse_args()


def generate_temp_password(length: int = 12) -> str:
    alphabet = string.ascii_letters + string.digits + "!@#$%"
    return ''.join(secrets.choice(alphabet) for _ in range(length))


def get_unlinked_employees(branch_id: Optional[int] = None) -> List[Dict[str, Any]]:
    cmd = [ARTISAN_PATH, "employee:unlinked", "--format=json"]
    if branch_id:
        cmd.append(f"--branch-id={branch_id}")
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout).get("data", [])
    except (subprocess.CalledProcessError, json.JSONDecodeError) as e:
        print(f"Error fetching unlinked employees: {e}", file=sys.stderr)
        return []


def create_user_for_employee(employee: Dict[str, Any], temp_password: str) -> Dict[str, Any]:
    cmd = [
        ARTISAN_PATH, "employee:link-user",
        f"--employee-id={employee['id']}",
        f"--username={employee['employee_number']}",
        f"--password={temp_password}",
        f"--role=staff",
        f"--branch-id={employee.get('branch_id', 1)}",
        "--format=json",
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout)
    except subprocess.CalledProcessError as e:
        return {"success": False, "error": e.stderr, "user_id": 0}


def format_text(results: List[BackfillResult]) -> str:
    if not results:
        return "\nAll employees already have linked user accounts."
    lines = [
        f"\nEmployee User Backfill Report",
        f"{'=' * 60}",
    ]
    successes = [r for r in results if r.status == "created"]
    failures = [r for r in results if r.status != "created"]

    for r in successes:
        lines.append(f"  [OK] {r.employee_name} ({r.employee_number})")
        lines.append(f"       Username: {r.username}  Password: {r.temp_password}  User ID: {r.user_id}")

    for r in failures:
        lines.append(f"  [FAIL] {r.employee_name} - {r.error}")

    lines.append(f"\nTotal: {len(successes)} created, {len(failures)} failed")
    return "\n".join(lines)


def main() -> None:
    args = parse_args()

    if args.all_branches:
        employees = get_unlinked_employees()
    else:
        employees = get_unlinked_employees(args.branch_id)

    if not employees:
        print("No unlinked employees found.")
        return

    results: List[BackfillResult] = []

    for emp in employees:
        temp_password = generate_temp_password()
        emp_name = f"{emp.get('first_name', '')} {emp.get('last_name', '')}"

        if args.dry_run:
            results.append(BackfillResult(
                employee_id=emp["id"],
                employee_number=emp.get("employee_number", ""),
                employee_name=emp_name,
                username=emp.get("employee_number", ""),
                temp_password=temp_password,
                user_id=0,
                status="dry_run",
            ))
        else:
            response = create_user_for_employee(emp, temp_password)
            if response.get("success", False):
                results.append(BackfillResult(
                    employee_id=emp["id"],
                    employee_number=emp.get("employee_number", ""),
                    employee_name=emp_name,
                    username=emp.get("employee_number", ""),
                    temp_password=temp_password,
                    user_id=response.get("user_id", 0),
                    status="created",
                ))
            else:
                results.append(BackfillResult(
                    employee_id=emp["id"],
                    employee_number=emp.get("employee_number", ""),
                    employee_name=emp_name,
                    username=emp.get("employee_number", ""),
                    temp_password="",
                    user_id=0,
                    status="failed",
                    error=response.get("error", "Unknown error"),
                ))

    if args.output == "json":
        print(json.dumps([asdict(r) for r in results], indent=2))
    else:
        print(format_text(results))


if __name__ == "__main__":
    main()
