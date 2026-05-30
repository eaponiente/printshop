#!/usr/bin/env python3
"""
Command: generate_payroll

Triggers payroll period generation for a specified branch.
Locks attendance sheets, aggregates daily rates, computes deductions,
and creates payroll_period + payroll_period_items.

Usage:
    python generate_payroll.py --branch-id=1 [--date-start=YYYY-MM-DD] [--date-end=YYYY-MM-DD] [--auto-approve]
    python generate_payroll.py --all-branches

Depends on:
    - php artisan (Laravel console)
    - payroll_math skill (late deductions, OT, holiday pay)
    - lock_helper skill (pessimistic locks)
"""

import argparse
import subprocess
import sys
import json
from datetime import datetime, timedelta
from typing import Optional, Tuple, List, Dict, Any
from dataclasses import dataclass

sys.path.insert(0, "../skills")
from date_range import get_previous_work_week, validate_date_range
from payroll_math import (
    compute_daily_wage,
    compute_late_deduction,
    compute_overtime_pay,
    compute_holiday_pay,
    compute_sss_deduction,
    compute_philhealth_deduction,
    compute_pagibig_deduction,
    compute_cash_advance_deduction,
)
from currency import format_peso, RoundingMode

ARTISAN_PATH = "php /var/www/html/artisan"
PROJECT_ROOT = "/var/www/html"


@dataclass
class PayrollPeriodResult:
    branch_id: int
    branch_name: str
    period_start: str
    period_end: str
    employee_count: int
    total_gross_pay: float
    total_deductions: float
    total_net_pay: float
    status: str
    errors: List[str]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Generate weekly payroll period for printing shop management system"
    )
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--branch-id", type=int, help="Branch ID to generate payroll for")
    group.add_argument("--all-branches", action="store_true", help="Generate payroll for all branches")
    parser.add_argument("--date-start", type=str, help="Period start date (YYYY-MM-DD). Defaults to last Monday.")
    parser.add_argument("--date-end", type=str, help="Period end date (YYYY-MM-DD). Defaults to last Saturday.")
    parser.add_argument("--auto-approve", action="store_true", help="Auto-approve the payroll period after generation")
    parser.add_argument("--dry-run", action="store_true", help="Compute and display results without persisting")
    parser.add_argument("--output", type=str, choices=["json", "text", "csv"], default="text", help="Output format")
    return parser.parse_args()


def get_branches(branch_id: Optional[int] = None) -> List[Dict[str, Any]]:
    """Fetch branch list from the application."""
    cmd = [ARTISAN_PATH, "branch:list", "--format=json"]
    if branch_id:
        cmd.extend(["--branch-id", str(branch_id)])
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout)
    except subprocess.CalledProcessError as e:
        print(f"Error fetching branches: {e.stderr}", file=sys.stderr)
        return []
    except json.JSONDecodeError:
        print("Error parsing branch data", file=sys.stderr)
        return []


def get_employees_for_branch(branch_id: int) -> List[Dict[str, Any]]:
    """Fetch active employees for a branch with their current daily rates."""
    cmd = [
        ARTISAN_PATH, "employee:list",
        f"--branch-id={branch_id}",
        "--status=active",
        "--format=json",
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout)
    except (subprocess.CalledProcessError, json.JSONDecodeError) as e:
        print(f"Error fetching employees for branch {branch_id}: {e}", file=sys.stderr)
        return []


def get_attendance_sheets(branch_id: int, date_start: str, date_end: str) -> List[Dict[str, Any]]:
    """Fetch attendance sheets within the date range for a branch."""
    cmd = [
        ARTISAN_PATH, "attendance:sheets",
        f"--branch-id={branch_id}",
        f"--date-from={date_start}",
        f"--date-to={date_end}",
        "--format=json",
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout)
    except (subprocess.CalledProcessError, json.JSONDecodeError) as e:
        print(f"Error fetching attendance sheets: {e}", file=sys.stderr)
        return []


def get_government_config() -> Dict[str, Any]:
    """Fetch current government contribution config (SSS brackets, PhilHealth, Pag-IBIG)."""
    cmd = [ARTISAN_PATH, "config:government-contributions", "--format=json"]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        return json.loads(result.stdout)
    except (subprocess.CalledProcessError, json.JSONDecodeError) as e:
        print(f"Error fetching government config: {e}", file=sys.stderr)
        return {
            "philhealth_premium_percent": 5.0,
            "pagibig_monthly_employee_share": 100.0,
            "sss_brackets": [],
        }


def get_active_cash_advances(branch_id: int) -> Dict[int, float]:
    """Fetch active cash advances (remaining_balance > 0) for branch employees."""
    cmd = [
        ARTISAN_PATH, "cash-advance:active",
        f"--branch-id={branch_id}",
        "--format=json",
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        data = json.loads(result.stdout)
        return {item["employee_id"]: item["remaining_balance"] for item in data}
    except (subprocess.CalledProcessError, json.JSONDecodeError):
        return {}


def compute_employee_payroll(
    employee: Dict[str, Any],
    sheets: List[Dict[str, Any]],
    gov_config: Dict[str, Any],
    cash_advances: Dict[int, float],
) -> Dict[str, Any]:
    """
    Compute a single employee's payroll period item.

    Args:
        employee: Employee record with id, daily_rate, sss_number, philhealth_number, pagibig_number
        sheets: All attendance sheets for this employee in the period
        gov_config: Government contribution configuration
        cash_advances: Active cash advance balances keyed by employee_id

    Returns:
        Dict with gross_pay, deductions, net_pay, and component breakdowns
    """
    employee_id = employee["id"]
    daily_rate = float(employee["current_daily_rate"])
    hourly_rate = daily_rate / 8.0

    total_gross_pay = 0.0
    total_late_deduction = 0.0
    total_undertime_deduction = 0.0
    total_overtime_pay = 0.0
    total_holiday_pay = 0.0
    total_fine_deduction = 0.0
    days_present = 0
    days_absent = 0
    days_holiday = 0
    late_minutes = 0
    ot_minutes = 0

    employee_sheets = [s for s in sheets if s.get("employee_id") == employee_id]

    for sheet in employee_sheets:
        daily_wage_result = compute_daily_wage(
            daily_rate=daily_rate,
            schedule_start=sheet.get("schedule_start", "08:00"),
            schedule_end=sheet.get("schedule_end", "17:00"),
            actual_in=sheet.get("time_in"),
            actual_out=sheet.get("time_out"),
            lunch_out=sheet.get("lunch_out"),
            lunch_in=sheet.get("lunch_in"),
            is_rest_day=sheet.get("is_rest_day", False),
            is_holiday=sheet.get("is_holiday", False),
            holiday_type=sheet.get("holiday_type"),
            holiday_worked=sheet.get("holiday_worked", False),
            day_before_present=sheet.get("day_before_present"),
            has_leave=sheet.get("has_leave", False),
            leave_type=sheet.get("leave_type"),
            leave_duration=sheet.get("leave_duration"),
            leave_hours_worked=sheet.get("leave_hours_worked"),
            approved_ot_minutes=sheet.get("approved_ot_minutes", 0),
            ot_rate_30min=sheet.get("ot_rate_30min", 50.0),
            ot_rate_1hour=sheet.get("ot_rate_1hour", 70.0),
            fines=sheet.get("fines", []),
        )

        if daily_wage_result.is_present:
            days_present += 1
        elif daily_wage_result.is_absent:
            days_absent += 1

        if daily_wage_result.is_holiday:
            days_holiday += 1

        total_gross_pay += daily_wage_result.gross_pay
        total_late_deduction += daily_wage_result.late_deduction
        total_undertime_deduction += daily_wage_result.undertime_deduction
        total_overtime_pay += daily_wage_result.overtime_pay
        total_holiday_pay += daily_wage_result.holiday_pay
        total_fine_deduction += daily_wage_result.fine_total
        late_minutes += daily_wage_result.late_minutes
        ot_minutes += daily_wage_result.ot_worked_minutes

    # Government deductions - computed on regular monthly salary (daily_rate x 26)
    monthly_salary = daily_rate * 26.0

    sss_weekly = compute_sss_deduction(
        monthly_salary=monthly_salary,
        sss_number=employee.get("sss_number"),
        brackets=gov_config.get("sss_brackets", []),
    )

    philhealth_weekly = compute_philhealth_deduction(
        monthly_salary=monthly_salary,
        philhealth_number=employee.get("philhealth_number"),
        premium_percent=gov_config.get("philhealth_premium_percent", 5.0),
    )

    pagibig_weekly = compute_pagibig_deduction(
        pagibig_number=employee.get("pagibig_number"),
        monthly_employee_share=gov_config.get("pagibig_monthly_employee_share", 100.0),
    )

    total_govt_deductions = sss_weekly + philhealth_weekly + pagibig_weekly
    net_before_ca = total_gross_pay - total_govt_deductions - total_fine_deduction

    # Cash advance deduction
    ca_balance = cash_advances.get(employee_id, 0.0)
    ca_deduction, ca_remaining = compute_cash_advance_deduction(
        remaining_balance=ca_balance,
        net_pay_before_ca=net_before_ca,
    )

    net_pay = max(0.0, net_before_ca - ca_deduction)

    return {
        "employee_id": employee_id,
        "employee_number": employee.get("employee_number", ""),
        "employee_name": f"{employee.get('first_name', '')} {employee.get('last_name', '')}",
        "daily_rate": daily_rate,
        "monthly_salary": monthly_salary,
        "days_present": days_present,
        "days_absent": days_absent,
        "days_holiday": days_holiday,
        "late_minutes": late_minutes,
        "ot_minutes": ot_minutes,
        "gross_pay": round(total_gross_pay, 2),
        "late_deduction": round(total_late_deduction, 2),
        "undertime_deduction": round(total_undertime_deduction, 2),
        "overtime_pay": round(total_overtime_pay, 2),
        "holiday_pay": round(total_holiday_pay, 2),
        "fine_deduction": round(total_fine_deduction, 2),
        "sss_weekly": round(sss_weekly, 2),
        "philhealth_weekly": round(philhealth_weekly, 2),
        "pagibig_weekly": round(pagibig_weekly, 2),
        "total_govt_deductions": round(total_govt_deductions, 2),
        "cash_advance_deduction": round(ca_deduction, 2),
        "cash_advance_remaining": round(ca_remaining, 2),
        "net_pay": round(net_pay, 2),
    }


def lock_and_generate(
    branch_id: int,
    date_start: str,
    date_end: str,
    auto_approve: bool,
    payroll_items: List[Dict[str, Any]],
) -> PayrollPeriodResult:
    """Call Laravel artisan command to persist the payroll period."""
    items_json = json.dumps(payroll_items)
    cmd = [
        ARTISAN_PATH, "payroll:generate",
        f"--branch-id={branch_id}",
        f"--date-start={date_start}",
        f"--date-end={date_end}",
        f"--items={items_json}",
    ]
    if auto_approve:
        cmd.append("--approve")

    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=PROJECT_ROOT)
        response = json.loads(result.stdout)
        return PayrollPeriodResult(
            branch_id=branch_id,
            branch_name=response.get("branch_name", ""),
            period_start=date_start,
            period_end=date_end,
            employee_count=len(payroll_items),
            total_gross_pay=sum(item["gross_pay"] for item in payroll_items),
            total_deductions=sum(
                item["total_govt_deductions"] + item["fine_deduction"] + item["cash_advance_deduction"]
                for item in payroll_items
            ),
            total_net_pay=sum(item["net_pay"] for item in payroll_items),
            status=response.get("status", "unknown"),
            errors=response.get("errors", []),
        )
    except subprocess.CalledProcessError as e:
        return PayrollPeriodResult(
            branch_id=branch_id,
            branch_name="",
            period_start=date_start,
            period_end=date_end,
            employee_count=0,
            total_gross_pay=0.0,
            total_deductions=0.0,
            total_net_pay=0.0,
            status="failed",
            errors=[e.stderr],
        )


def format_output_text(results: List[PayrollPeriodResult]) -> str:
    """Format payroll results as human-readable text."""
    lines = []
    for r in results:
        lines.append(f"\n{'=' * 60}")
        lines.append(f"Branch: {r.branch_name} (ID: {r.branch_id})")
        lines.append(f"Period: {r.period_start} to {r.period_end}")
        lines.append(f"Status: {r.status}")
        lines.append(f"Employees: {r.employee_count}")
        lines.append(f"Total Gross Pay:  {format_peso(r.total_gross_pay)}")
        lines.append(f"Total Deductions: {format_peso(r.total_deductions)}")
        lines.append(f"Total Net Pay:    {format_peso(r.total_net_pay)}")
        if r.errors:
            lines.append(f"Errors: {', '.join(r.errors)}")
    return "\n".join(lines)


def main() -> None:
    args = parse_args()

    # Resolve date range
    if args.date_start and args.date_end:
        date_start, date_end = validate_date_range(args.date_start, args.date_end)
    else:
        date_start, date_end = get_previous_work_week()

    # Fetch branches
    if args.all_branches:
        branches = get_branches()
    else:
        branches = get_branches(args.branch_id)

    if not branches:
        print("No branches found.", file=sys.stderr)
        sys.exit(1)

    # Load global config once
    gov_config = get_government_config()

    results: List[PayrollPeriodResult] = []

    for branch in branches:
        bid = branch["id"]
        employees = get_employees_for_branch(bid)
        if not employees:
            results.append(PayrollPeriodResult(
                branch_id=bid,
                branch_name=branch.get("name", ""),
                period_start=date_start,
                period_end=date_end,
                employee_count=0,
                total_gross_pay=0.0,
                total_deductions=0.0,
                total_net_pay=0.0,
                status="skipped",
                errors=["No active employees"],
            ))
            continue

        sheets = get_attendance_sheets(bid, date_start, date_end)
        cash_advances = get_active_cash_advances(bid)

        payroll_items = []
        for emp in employees:
            item = compute_employee_payroll(emp, sheets, gov_config, cash_advances)
            payroll_items.append(item)

        if args.dry_run:
            result = PayrollPeriodResult(
                branch_id=bid,
                branch_name=branch.get("name", ""),
                period_start=date_start,
                period_end=date_end,
                employee_count=len(payroll_items),
                total_gross_pay=sum(item["gross_pay"] for item in payroll_items),
                total_deductions=sum(
                    item["total_govt_deductions"] + item["fine_deduction"] + item["cash_advance_deduction"]
                    for item in payroll_items
                ),
                total_net_pay=sum(item["net_pay"] for item in payroll_items),
                status="dry_run",
                errors=[],
            )
        else:
            result = lock_and_generate(bid, date_start, date_end, args.auto_approve, payroll_items)

        results.append(result)

    # Output
    if args.output == "json":
        print(json.dumps([r.__dict__ for r in results], indent=2, default=str))
    elif args.output == "csv":
        print("branch_id,branch_name,period_start,period_end,employee_count,gross_pay,deductions,net_pay,status")
        for r in results:
            print(f"{r.branch_id},{r.branch_name},{r.period_start},{r.period_end},"
                  f"{r.employee_count},{r.total_gross_pay},{r.total_deductions},"
                  f"{r.total_net_pay},{r.status}")
    else:
        print(format_output_text(results))


if __name__ == "__main__":
    main()
