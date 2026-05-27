"""
Skill: lock_helper

Pessimistic locking utilities for race-condition prevention in financial
operations. Provides duplicate detection windows and lock timeout helpers.

Mirrors the lockForUpdate() patterns in Transaction::generateNumber() and
the 5-minute duplicate punch throttle.
"""

from datetime import datetime, timedelta
from typing import Optional, Dict, Any, Tuple
from dataclasses import dataclass


# Default throttle windows in minutes
DUPLICATE_PUNCH_WINDOW_MINUTES: int = 5
PAYROLL_LOCK_TIMEOUT_SECONDS: int = 30
CORRECTION_LOCK_TIMEOUT_SECONDS: int = 10


@dataclass
class LockResult:
    """Result of a lock acquisition attempt."""
    acquired: bool
    lock_key: str
    locked_at: str
    holder: str
    timeout_seconds: int
    error_message: str = ""


def is_within_duplicate_window(
    timestamp1: datetime,
    timestamp2: datetime,
    window_minutes: int = DUPLICATE_PUNCH_WINDOW_MINUTES,
) -> bool:
    """
    Check if two timestamps fall within the duplicate detection window.

    Used for punch throttling: if two IN punches occur within 5 minutes,
    the second is marked as a duplicate.

    Args:
        timestamp1: First timestamp.
        timestamp2: Second timestamp.
        window_minutes: Window size in minutes. Defaults to 5.

    Returns:
        True if timestamps are within the window.

    Example:
        >>> t1 = datetime(2026, 5, 25, 8, 0, 0)
        >>> t2 = datetime(2026, 5, 25, 8, 3, 0)
        >>> is_within_duplicate_window(t1, t2)
        True
        >>> t3 = datetime(2026, 5, 25, 8, 10, 0)
        >>> is_within_duplicate_window(t1, t3)
        False
    """
    diff = abs((timestamp2 - timestamp1).total_seconds())
    return diff <= (window_minutes * 60)


def build_lock_key(resource_type: str, resource_id: int, action: str) -> str:
    """
    Build a deterministic lock key for pessimistic locking.

    Args:
        resource_type: Type of resource (e.g., 'transaction', 'sublimation', 'correction').
        resource_id: Unique identifier of the resource.
        action: The action being performed (e.g., 'payment', 'approve', 'generate').

    Returns:
        A unique lock key string.

    Example:
        >>> build_lock_key('transaction', 42, 'payment')
        'lock:transaction:42:payment'
    """
    return f"lock:{resource_type}:{resource_id}:{action}"


def validate_lock_timeout(
    locked_at: Optional[datetime],
    timeout_seconds: int,
) -> bool:
    """
    Check if a lock has timed out and can be considered released.

    Args:
        locked_at: When the lock was acquired. None means no lock exists.
        timeout_seconds: Maximum lock duration in seconds.

    Returns:
        True if the lock has expired (or never existed).
    """
    if locked_at is None:
        return True
    elapsed = (datetime.now() - locked_at).total_seconds()
    return elapsed > timeout_seconds


def acquire_lock(
    resource_type: str,
    resource_id: int,
    action: str,
    timeout_seconds: int = PAYROLL_LOCK_TIMEOUT_SECONDS,
    current_locks: Optional[Dict[str, datetime]] = None,
) -> LockResult:
    """
    Attempt to acquire a pessimistic lock on a resource.

    In production, this would call the database's lockForUpdate().
    This skill provides the application-level lock management logic.

    Args:
        resource_type: Resource type string.
        resource_id: Resource ID.
        action: Action string.
        timeout_seconds: Lock timeout in seconds.
        current_locks: Dict of existing locks for collision detection.

    Returns:
        LockResult indicating success or failure.
    """
    lock_key = build_lock_key(resource_type, resource_id, action)
    now = datetime.now()

    if current_locks is None:
        current_locks = {}

    if lock_key in current_locks:
        existing_locked_at = current_locks[lock_key]
        if not validate_lock_timeout(existing_locked_at, timeout_seconds):
            return LockResult(
                acquired=False,
                lock_key=lock_key,
                locked_at=existing_locked_at.isoformat(),
                holder=resource_type,
                timeout_seconds=timeout_seconds,
                error_message=f"Resource {lock_key} is locked. Try again later.",
            )

    current_locks[lock_key] = now
    return LockResult(
        acquired=True,
        lock_key=lock_key,
        locked_at=now.isoformat(),
        holder=resource_type,
        timeout_seconds=timeout_seconds,
    )


def release_lock(
    lock_key: str,
    current_locks: Optional[Dict[str, datetime]] = None,
) -> bool:
    """
    Release a previously acquired lock.

    Args:
        lock_key: The lock key to release.
        current_locks: Dict of existing locks.

    Returns:
        True if the lock was found and released.
    """
    if current_locks is None:
        return False
    if lock_key in current_locks:
        del current_locks[lock_key]
        return True
    return False
