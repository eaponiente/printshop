"""
Skill: date_range

Date range computation utilities for financial reporting modes:
daily, weekly, monthly, yearly. Also provides previous work week
calculation (Monday-Saturday) for payroll periods.

All dates are handled as Python date objects for timezone-agnostic
computation, mirroring the Carbon-based logic in the backend.
"""

from datetime import date, timedelta, datetime
from typing import Tuple, Optional, List, Literal
from enum import Enum


DateRangeMode = Literal["daily", "weekly", "monthly", "yearly"]


def parse_date_string(date_str: str) -> Optional[date]:
    """
    Parse a date string into a date object.

    Supports formats: YYYY-MM-DD, YYYY/MM/DD, MM/DD/YYYY.

    Args:
        date_str: Date string to parse.

    Returns:
        A date object, or None if parsing fails.
    """
    formats = ["%Y-%m-%d", "%Y/%m/%d", "%m/%d/%Y"]
    for fmt in formats:
        try:
            return datetime.strptime(date_str, fmt).date()
        except ValueError:
            continue
    return None


def get_date_range_for_mode(
    mode: DateRangeMode,
    ref_date: Optional[date] = None,
) -> Tuple[date, date]:
    """
    Get the start and end dates for a reporting mode relative to a reference date.

    Args:
        mode: One of 'daily', 'weekly', 'monthly', 'yearly'.
        ref_date: Reference date. Defaults to today.

    Returns:
        A tuple of (start_date, end_date) inclusive.

    Example:
        >>> today = date(2026, 5, 25)  # Monday
        >>> get_date_range_for_mode('daily', today)
        (date(2026, 5, 25), date(2026, 5, 25))
        >>> get_date_range_for_mode('weekly', today)
        (date(2026, 5, 25), date(2026, 5, 31))
    """
    if ref_date is None:
        ref_date = date.today()

    if mode == "daily":
        return ref_date, ref_date

    elif mode == "weekly":
        # Monday to Sunday
        start = ref_date - timedelta(days=ref_date.weekday())
        end = start + timedelta(days=6)
        return start, end

    elif mode == "monthly":
        start = ref_date.replace(day=1)
        if ref_date.month == 12:
            end = ref_date.replace(year=ref_date.year + 1, month=1, day=1) - timedelta(days=1)
        else:
            end = ref_date.replace(month=ref_date.month + 1, day=1) - timedelta(days=1)
        return start, end

    elif mode == "yearly":
        start = ref_date.replace(month=1, day=1)
        end = ref_date.replace(month=12, day=31)
        return start, end

    raise ValueError(f"Unknown date range mode: {mode}")


def get_previous_work_week(ref_date: Optional[date] = None) -> Tuple[str, str]:
    """
    Get the previous completed work week (Monday-Saturday) as ISO date strings.

    Used for payroll period generation which runs on Saturday after shift.
    If today is Saturday, returns this week Mon-Sat. Otherwise returns
    the previous completed week.

    Args:
        ref_date: Reference date. Defaults to today.

    Returns:
        A tuple of (monday_iso, saturday_iso) strings.

    Example:
        >>> # If today is Saturday May 30, 2026:
        >>> get_previous_work_week(date(2026, 5, 30))
        ('2026-05-25', '2026-05-30')
    """
    if ref_date is None:
        ref_date = date.today()

    weekday = ref_date.weekday()  # Monday=0, Sunday=6
    if weekday == 5:  # Saturday
        monday = ref_date - timedelta(days=5)
        saturday = ref_date
    else:
        # Go back to last Saturday
        days_since_saturday = (weekday + 1) % 7
        if days_since_saturday == 0:
            days_since_saturday = 7
        last_saturday = ref_date - timedelta(days=days_since_saturday)
        monday = last_saturday - timedelta(days=5)
        saturday = last_saturday

    return monday.isoformat(), saturday.isoformat()


def validate_date_range(start_str: str, end_str: str) -> Tuple[str, str]:
    """
    Validate and normalize a date range string pair.

    Args:
        start_str: Start date string.
        end_str: End date string.

    Returns:
        Normalized (start_iso, end_iso) tuple.

    Raises:
        ValueError: If dates are invalid or end is before start.
    """
    start = parse_date_string(start_str)
    end = parse_date_string(end_str)

    if start is None:
        raise ValueError(f"Invalid start date: {start_str}")
    if end is None:
        raise ValueError(f"Invalid end date: {end_str}")
    if end < start:
        raise ValueError(f"End date {end_str} is before start date {start_str}")

    return start.isoformat(), end.isoformat()


def days_ago(n: int) -> date:
    """
    Get the date N days ago from today.

    Args:
        n: Number of days in the past.

    Returns:
        The date N days before today.
    """
    return date.today() - timedelta(days=n)


def get_dates_in_range(start: date, end: date) -> List[date]:
    """
    Get a list of all dates between start and end (inclusive).

    Args:
        start: Start date.
        end: End date.

    Returns:
        List of date objects.

    Example:
        >>> get_dates_in_range(date(2026, 5, 25), date(2026, 5, 27))
        [date(2026, 5, 25), date(2026, 5, 26), date(2026, 5, 27)]
    """
    days = (end - start).days
    return [start + timedelta(days=i) for i in range(days + 1)]
