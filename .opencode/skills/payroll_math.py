"""
Skill: payroll_math

Atomic payroll computation utilities: late deduction (3-tier system),
overtime pay (2-block flat-rate model), holiday pay with day-before lookback,
government deductions (SSS bracket lookup, PhilHealth percentage, Pag-IBIG flat),
cash advance deduction, and full daily wage computation.

All formulas mirror the attendance-flows-and-use-cases.md specification.
"""

from dataclasses import dataclass, field
from datetime import time, timedelta, date
from typing import Optional, List, Dict, Tuple
from enum import Enum
import math


class HolidayType(str, Enum):
    REGULAR = "regular"
    SPECIAL = "special"


class LeaveType(str, Enum):
    VACATION = "vacation"
    SICK = "sick"
    EMERGENCY = "emergency"
    MATERNITY = "maternity"
    PATERNITY = "paternity"
    BEREAVEMENT = "bereavement"
    UNPAID = "unpaid"


@dataclass
class SSSBracket:
    """A single SSS contribution bracket."""
    salary_min: float
    salary_max: float
    employee_percentage: float
    employer_percentage: float


@dataclass
class DailyWageResult:
    """Complete daily wage computation result."""
    is_present: bool = False
    is_absent: bool = False
    is_holiday: bool = False
    regular_hours: float = 0.0
    late_minutes: int = 0
    undertime_minutes: float = 0.0
    ot_worked_minutes: int = 0
    lunch_minutes: int = 0
    gross_pay: float = 0.0
    late_deduction: float = 0.0
    undertime_deduction: float = 0.0
    overtime_pay: float = 0.0
    holiday_pay: float = 0.0
    fine_total: float = 0.0
    fine_breakdown: Dict[str, float] = field(default_factory=dict)


def compute_late_deduction(late_minutes: int, hourly_rate: float) -> float:
    """
    Compute late deduction using the 3-tier system.

    Tier 1 (0 min):        ���0
    Tier 2 (1-19 min):     late_min x ���5
    Tier 3 (20-59 min):    Flat ���100
    Tier 4 (60+ min):      ���100 + floor(late_min / 60) x hourly_rate

    Fractional hours past each full hour of lateness are NOT additionally
    penalized. 90 min costs same as 60 min: floor(90/60) = 1.

    Args:
        late_minutes: Total minutes late (integer, non-negative).
        hourly_rate: The employee's hourly rate (daily_rate / 8).

    Returns:
        The late deduction amount in PHP.

    Example:
        >>> compute_late_deduction(10, 63.75)
        50.0
        >>> compute_late_deduction(45, 63.75)
        100.0
        >>> compute_late_deduction(90, 63.75)
        163.75
        >>> compute_late_deduction(0, 63.75)
        0.0
    """
    if late_minutes <= 0:
        return 0.0

    if late_minutes <= 19:
        return late_minutes * 5.0

    if late_minutes <= 59:
        return 100.0

    # 60+ minutes: base 100 + hourly rate per full hour of lateness
    full_hours_late = late_minutes // 60
    return 100.0 + (full_hours_late * hourly_rate)


def compute_overtime_pay(
    ot_worked_minutes: int,
    ot_amount_30min: float,
    ot_amount_hour: float,
) -> float:
    """
    Compute overtime pay using the 2-block flat-rate model with rounding.

    Rules:
    - Full hours: multiply by ot_amount_hour.
    - Remainder <= 30 min: add ot_amount_30min.
    - Remainder 31-59 min: add ot_amount_hour (rounds up to full hour).

    Args:
        ot_worked_minutes: Actual OT minutes worked (must meet threshold).
        ot_amount_30min: Rate for a 30-minute OT block.
        ot_amount_hour: Rate for a 1-hour OT block.

    Returns:
        Total overtime pay in PHP.

    Example:
        >>> compute_overtime_pay(30, 50.0, 70.0)
        50.0
        >>> compute_overtime_pay(65, 50.0, 70.0)
        120.0
        >>> compute_overtime_pay(95, 50.0, 70.0)
        140.0
        >>> compute_overtime_pay(0, 50.0, 70.0)
        0.0
    """
    if ot_worked_minutes <= 0:
        return 0.0

    full_hours = ot_worked_minutes // 60
    remainder = ot_worked_minutes % 60

    total = full_hours * ot_amount_hour

    if remainder == 0:
        pass  # no remainder
    elif remainder <= 30:
        total += ot_amount_30min
    else:
        # remainder 31-59 rounds up to 1 hour
        total += ot_amount_hour

    return total


def compute_holiday_pay(
    daily_rate: float,
    holiday_type: HolidayType,
    is_worked: bool,
    day_before_present: Optional[bool] = None,
) -> Tuple[float, float]:
    """
    Compute holiday pay according to Philippine labor rules.

    Regular Holiday:
      - Worked: 200% of daily rate, regardless of day-before status.
      - Unworked + day-before present/on-leave: 100%.
      - Unworked + day-before absent: 0%.

    Special Non-Working Day:
      - Worked: 130%.
      - Unworked: 0% (no work, no pay).

    Args:
        daily_rate: The employee's daily rate.
        holiday_type: 'regular' or 'special'.
        is_worked: Whether the employee worked on the holiday.
        day_before_present: For regular unworked holidays, whether the
                            employee was present on the last working day
                            before the holiday. None if not applicable.

    Returns:
        A tuple of (holiday_pay_amount, holiday_pay_percent).

    Example:
        >>> compute_holiday_pay(510.0, HolidayType.REGULAR, True)
        (1020.0, 200.0)
        >>> compute_holiday_pay(510.0, HolidayType.REGULAR, False, True)
        (510.0, 100.0)
        >>> compute_holiday_pay(510.0, HolidayType.REGULAR, False, False)
        (0.0, 0.0)
        >>> compute_holiday_pay(510.0, HolidayType.SPECIAL, True)
        (663.0, 130.0)
    """
    if holiday_type == HolidayType.REGULAR:
        if is_worked:
            return daily_rate * 2.0, 200.0
        else:
            if day_before_present:
                return daily_rate, 100.0
            else:
                return 0.0, 0.0
    elif holiday_type == HolidayType.SPECIAL:
        if is_worked:
            return daily_rate * 1.30, 130.0
        else:
            return 0.0, 0.0
    return 0.0, 0.0


def compute_sss_deduction(
    monthly_salary: float,
    sss_number: Optional[str],
    brackets: List[Dict[str, float]],
) -> float:
    """
    Compute weekly SSS deduction.

    Formula: (monthly_salary x bracket.employee_percentage / 100) / 4

    Only deducted if the employee has an SSS number. Same amount every
    week regardless of absences or overtime.

    Args:
        monthly_salary: Regular monthly salary (daily_rate x 26).
        sss_number: Employee's SSS number. Skips deduction if empty.
        brackets: List of bracket dicts with keys: salary_min, salary_max,
                  employee_percentage.

    Returns:
        Weekly SSS deduction amount.

    Example:
        >>> brackets = [{"salary_min": 1, "salary_max": 4250, "employee_percentage": 4.5}]
        >>> compute_sss_deduction(13000.0, "12-3456789-0", brackets)
        0.0  # salary doesn't match bracket
    """
    if not sss_number:
        return 0.0

    for bracket_data in brackets:
        bracket = SSSBracket(**bracket_data)
        bracket_max = bracket.salary_max

        if bracket.salary_min <= monthly_salary <= bracket_max:
            monthly_contribution = monthly_salary * bracket.employee_percentage / 100.0
            return monthly_contribution / 4.0

    return 0.0


def compute_philhealth_deduction(
    monthly_salary: float,
    philhealth_number: Optional[str],
    premium_percent: float = 5.0,
) -> float:
    """
    Compute weekly PhilHealth deduction (50/50 split).

    Formula: (monthly_salary x premium_percent / 100 x 0.50) / 4

    Only deducted if the employee has a PhilHealth number.

    Args:
        monthly_salary: Regular monthly salary (daily_rate x 26).
        philhealth_number: Employee's PhilHealth number. Skips if empty.
        premium_percent: Premium percentage (default 5.0).

    Returns:
        Weekly PhilHealth deduction amount.

    Example:
        >>> compute_philhealth_deduction(13260.0, "12-345678901-2", 5.0)
        82.875
    """
    if not philhealth_number:
        return 0.0

    monthly_contribution = monthly_salary * premium_percent / 100.0 * 0.50
    return monthly_contribution / 4.0


def compute_pagibig_deduction(
    pagibig_number: Optional[str],
    monthly_employee_share: float = 100.0,
) -> float:
    """
    Compute weekly Pag-IBIG deduction.

    Formula: monthly_employee_share / 4

    Only deducted if the employee has a Pag-IBIG number.

    Args:
        pagibig_number: Employee's Pag-IBIG number. Skips if empty.
        monthly_employee_share: Monthly employee share (default ���100).

    Returns:
        Weekly Pag-IBIG deduction amount.

    Example:
        >>> compute_pagibig_deduction("1234-5678-9012", 100.0)
        25.0
        >>> compute_pagibig_deduction(None)
        0.0
    """
    if not pagibig_number:
        return 0.0

    return monthly_employee_share / 4.0


def compute_cash_advance_deduction(
    remaining_balance: float,
    net_pay_before_ca: float,
) -> Tuple[float, float]:
    """
    Compute cash advance deduction for a payroll period.

    Rules:
    - Deduction = min(remaining_balance, net_pay_before_ca)
    - Net pay must not go below 0.
    - Remaining balance carries over to next period.

    Args:
        remaining_balance: Current unpaid cash advance balance.
        net_pay_before_ca: Net pay before cash advance deduction.

    Returns:
        A tuple of (deduction_amount, new_remaining_balance).

    Example:
        >>> compute_cash_advance_deduction(500.0, 2000.0)
        (500.0, 0.0)
        >>> compute_cash_advance_deduction(3000.0, 2000.0)
        (2000.0, 1000.0)
        >>> compute_cash_advance_deduction(0.0, 2000.0)
        (0.0, 0.0)
    """
    if remaining_balance <= 0:
        return 0.0, 0.0

    deduction = min(remaining_balance, max(0.0, net_pay_before_ca))
    remaining = remaining_balance - deduction
    return deduction, remaining


def compute_daily_wage(
    daily_rate: float,
    schedule_start: str,
    schedule_end: str,
    actual_in: Optional[str] = None,
    actual_out: Optional[str] = None,
    lunch_out: Optional[str] = None,
    lunch_in: Optional[str] = None,
    is_rest_day: bool = False,
    is_holiday: bool = False,
    holiday_type: Optional[str] = None,
    holiday_worked: bool = False,
    day_before_present: Optional[bool] = None,
    has_leave: bool = False,
    leave_type: Optional[str] = None,
    leave_duration: Optional[str] = None,
    leave_hours_worked: float = 0.0,
    approved_ot_minutes: int = 0,
    ot_rate_30min: float = 50.0,
    ot_rate_1hour: float = 70.0,
    fines: Optional[List[Dict[str, float]]] = None,
) -> DailyWageResult:
    """
    Compute the full daily wage for one employee on one day.

    This is the core payroll computation function, combining:
    - Late deduction
    - Undertime (including over-lunch)
    - Overtime (lower-of-two: min(actual, approved))
    - Holiday pay
    - Leave blending (full-day, half-day AM, half-day PM)
    - Fines

    Args:
        daily_rate: Employee's daily rate.
        schedule_start: Scheduled start time (HH:MM).
        schedule_end: Scheduled end time (HH:MM).
        actual_in: Actual punch IN time.
        actual_out: Actual punch OUT time.
        lunch_out: LUNCH_OUT punch time.
        lunch_in: LUNCH_IN punch time.
        is_rest_day: Whether this day is a rest day.
        is_holiday: Whether this date is a holiday.
        holiday_type: 'regular' or 'special'.
        holiday_worked: Whether employee worked on the holiday.
        day_before_present: For regular unworked holiday check.
        has_leave: Whether there's an approved leave.
        leave_type: Leave type string.
        leave_duration: 'full_day', 'half_day_am', 'half_day_pm'.
        leave_hours_worked: Hours actually worked during leave.
        approved_ot_minutes: Approved OT minutes from OT request.
        ot_rate_30min: OT rate for 30-min block.
        ot_rate_1hour: OT rate for 1-hour block.
        fines: List of fine dicts with 'amount' key.

    Returns:
        DailyWageResult with all computed values.
    """
    hourly_rate = daily_rate / 8.0
    result = DailyWageResult()

    # Parse times
    def _parse_time(t: Optional[str]) -> Optional[tuple]:
        if not t:
            return None
        parts = t.split(":")
        return (int(parts[0]), int(parts[1]))

    sched_start = _parse_time(schedule_start)
    sched_end = _parse_time(schedule_end)
    in_time = _parse_time(actual_in)
    out_time = _parse_time(actual_out)
    l_out = _parse_time(lunch_out)
    l_in = _parse_time(lunch_in)

    # Holiday handling
    if is_holiday and holiday_type:
        ht = HolidayType(holiday_type)
        hp_amount, hp_percent = compute_holiday_pay(
            daily_rate=daily_rate,
            holiday_type=ht,
            is_worked=holiday_worked,
            day_before_present=day_before_present,
        )
        result.is_holiday = True
        result.holiday_pay = hp_amount
        if holiday_worked:
            result.is_present = True
            result.gross_pay = hp_amount
        else:
            result.gross_pay = hp_amount
            result.is_present = hp_amount > 0
        return result

    # Normal day computation
    if not in_time or not out_time:
        result.is_absent = True
        return result

    # Convert to minutes from midnight
    def _to_minutes(t: tuple) -> int:
        return t[0] * 60 + t[1]

    sched_start_min = _to_minutes(sched_start) if sched_start else 480
    sched_end_min = _to_minutes(sched_end) if sched_end else 1020
    in_min = _to_minutes(in_time)
    out_min = _to_minutes(out_time)

    # Cap OUT at schedule end for regular pay computation
    # (work past schedule end is OT, not regular pay)
    capped_out = min(out_min, sched_end_min)

    # Late computation
    late_minutes = max(0, in_min - sched_start_min)
    result.late_minutes = late_minutes

    # Lunch computation (4-punch model)
    if l_out and l_in:
        l_out_min = _to_minutes(l_out)
        l_in_min = _to_minutes(l_in)
        actual_lunch = l_in_min - l_out_min
        result.lunch_minutes = actual_lunch

        if actual_lunch > 60:
            # Excess lunch deducted as undertime
            excess_lunch = actual_lunch - 60
            result.undertime_minutes += excess_lunch
    else:
        # Fallback: auto-deduct if span >= 5h and overlaps 11am-2pm
        raw_duration = capped_out - in_min
        if raw_duration >= 300:
            lunch_auto_window_start = 11 * 60
            lunch_auto_window_end = 14 * 60
            if in_min <= lunch_auto_window_end and capped_out >= lunch_auto_window_start:
                result.undertime_minutes += 60

    # Regular hours worked (morning + afternoon, minus lunch, capped)
    morning_work = 0.0
    afternoon_work = 0.0

    if l_out and l_in:
        l_out_min = _to_minutes(l_out)
        l_in_min = _to_minutes(l_in)
        morning_work = max(0, l_out_min - in_min) / 60.0
        afternoon_work = max(0, capped_out - l_in_min) / 60.0
    else:
        raw_hours = max(0, capped_out - in_min) / 60.0
        # Fallback: if auto-deduct was applied, subtract 60 min
        if result.undertime_minutes > 0:
            raw_hours -= 1.0
        morning_work = raw_hours
        afternoon_work = 0.0

    regular_hours = max(0.0, morning_work + afternoon_work)
    result.regular_hours = regular_hours

    # Leave blending
    if has_leave and leave_duration and leave_type:
        leave_hours_credited = 0.0
        if leave_duration == "full_day":
            leave_hours_credited = 8.0
        elif leave_duration == "half_day_am":
            leave_hours_credited = 4.0
            # Afternoon worked hours already in regular_hours
        elif leave_duration == "half_day_pm":
            leave_hours_credited = 4.0
            # Morning worked hours already in regular_hours

        if leave_type == "unpaid":
            # Hours credited but daily_wage = 0 for leave portion
            pass
        else:
            regular_hours = max(regular_hours, leave_hours_credited)

    # Late deduction
    result.late_deduction = compute_late_deduction(late_minutes, hourly_rate)

    # Undertime deduction
    if result.undertime_minutes > 0:
        result.undertime_deduction = (result.undertime_minutes / 60.0) * hourly_rate

    # Base pay
    if late_minutes > 0:
        base_pay = daily_rate - result.late_deduction
    else:
        base_pay = hourly_rate * regular_hours

    # OT computation (lower-of-two rule)
    if approved_ot_minutes > 0 and out_min > sched_end_min:
        actual_extra = out_min - sched_end_min
        ot_minutes = min(actual_extra, approved_ot_minutes)
        result.ot_worked_minutes = ot_minutes
        result.overtime_pay = compute_overtime_pay(
            ot_worked_minutes=ot_minutes,
            ot_amount_30min=ot_rate_30min,
            ot_amount_hour=ot_rate_1hour,
        )

    # Fines
    if fines:
        for fine in fines:
            amount = fine.get("amount", 0.0)
            fine_type = fine.get("type", "unknown")
            result.fine_total += amount
            result.fine_breakdown[fine_type] = amount

    # Gross pay
    result.gross_pay = max(0.0, base_pay + result.overtime_pay + result.holiday_pay - result.fine_total)
    result.is_present = regular_hours > 0

    return result
