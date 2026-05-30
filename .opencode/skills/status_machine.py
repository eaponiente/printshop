"""
Skill: status_machine

Provides the sublimation status transition graph, validation logic,
and utility functions for determining valid next states and checking
transaction-amount-sync locking conditions.

All logic mirrors the PHP enum SublimationStatus and the canMoveTo()
method on the Sublimation model.
"""

from enum import Enum
from typing import List, Optional, Set, FrozenSet


class SublimationStatus(str, Enum):
    """Mirrors App\\Enums\\Sublimations\\SublimationStatus."""

    FOR_APPROVAL = "for_approval"
    DONE_LAYOUT = "done_layout"
    WAITING_FOR_DP = "waiting_for_dp"
    DOWNPAYMENT_COMPLETE = "downpayment_complete"
    FOR_SIZING = "for_sizing"
    DONE_SIZING = "done_sizing"
    PRINTED = "printed"
    CUT = "cut"
    SEWING = "sewing"
    SEWED = "sewed"
    CHECKED = "checked"
    READY_FOR_PICKUP = "ready_for_pickup"
    CLAIMED = "claimed"
    COMPLETED = "completed"


# Ordered status list (linear progression)
STATUS_ORDER: List[SublimationStatus] = list(SublimationStatus)

# Phase groupings
PRE_PAYMENT_PHASE: FrozenSet[SublimationStatus] = frozenset({
    SublimationStatus.FOR_APPROVAL,
    SublimationStatus.DONE_LAYOUT,
    SublimationStatus.WAITING_FOR_DP,
})

PRODUCTION_PHASE: FrozenSet[SublimationStatus] = frozenset({
    SublimationStatus.DOWNPAYMENT_COMPLETE,
    SublimationStatus.FOR_SIZING,
    SublimationStatus.DONE_SIZING,
    SublimationStatus.PRINTED,
    SublimationStatus.CUT,
    SublimationStatus.SEWING,
    SublimationStatus.SEWED,
    SublimationStatus.CHECKED,
    SublimationStatus.READY_FOR_PICKUP,
    SublimationStatus.CLAIMED,
})

POST_PRODUCTION_PHASE: FrozenSet[SublimationStatus] = frozenset({
    SublimationStatus.COMPLETED,
})

# The trigger gate: reaching this status creates a linked Transaction
TRANSACTION_TRIGGER_STATUS: SublimationStatus = SublimationStatus.WAITING_FOR_DP

# The production gate: cannot enter this phase without downpayment or PO
PRODUCTION_GATE_STATUS: SublimationStatus = SublimationStatus.DOWNPAYMENT_COMPLETE

# Terminal status
TERMINAL_STATUS: SublimationStatus = SublimationStatus.COMPLETED

# Statuses where the linked transaction amount can still be changed
# (before any payments are attached)
SYNCABLE_STATUSES: FrozenSet[SublimationStatus] = frozenset({
    SublimationStatus.FOR_APPROVAL,
    SublimationStatus.DONE_LAYOUT,
    SublimationStatus.WAITING_FOR_DP,
    SublimationStatus.DOWNPAYMENT_COMPLETE,
})


def is_pre_payment_phase(status: SublimationStatus) -> bool:
    """Check if the status is in the pre-payment phase (before downpayment)."""
    return status in PRE_PAYMENT_PHASE


def is_production_phase(status: SublimationStatus) -> bool:
    """Check if the status is in the production phase (after downpayment)."""
    return status in PRODUCTION_PHASE


def is_terminal(status: SublimationStatus) -> bool:
    """Check if the status is terminal (completed)."""
    return status == TERMINAL_STATUS


def get_next_status(current: SublimationStatus) -> Optional[SublimationStatus]:
    """
    Get the next status in the linear progression.

    Args:
        current: The current sublimation status.

    Returns:
        The next status if one exists, None if at terminal status.

    Example:
        >>> get_next_status(SublimationStatus.PRINTED)
        <SublimationStatus.CUT: 'cut'>
        >>> get_next_status(SublimationStatus.COMPLETED)
        None
    """
    try:
        current_index = STATUS_ORDER.index(current)
        if current_index < len(STATUS_ORDER) - 1:
            return STATUS_ORDER[current_index + 1]
        return None
    except ValueError:
        return None


def get_previous_status(current: SublimationStatus) -> Optional[SublimationStatus]:
    """
    Get the previous status in the linear progression.

    Args:
        current: The current sublimation status.

    Returns:
        The previous status if one exists, None if at the first status.
    """
    try:
        current_index = STATUS_ORDER.index(current)
        if current_index > 0:
            return STATUS_ORDER[current_index - 1]
        return None
    except ValueError:
        return None


def can_transition_to(current: SublimationStatus, target: SublimationStatus) -> bool:
    """
    Validate whether a status transition is allowed.

    Basic rule: can only move forward one step at a time in the linear order.
    Does NOT check the production gate (downpayment/PO requirement) - that
    requires additional context and is checked by Sublimation::canMoveTo().

    Args:
        current: The current status.
        target: The desired next status.

    Returns:
        True if the transition is structurally valid.

    Example:
        >>> can_transition_to(SublimationStatus.FOR_APPROVAL, SublimationStatus.DONE_LAYOUT)
        True
        >>> can_transition_to(SublimationStatus.FOR_APPROVAL, SublimationStatus.PRINTED)
        False
    """
    next_status = get_next_status(current)
    return next_status is not None and next_status == target


def needs_transaction_creation(current: SublimationStatus, target: SublimationStatus) -> bool:
    """
    Determine if transitioning to the target status should auto-create a linked Transaction.

    Returns True when the sublimation reaches WAITING_FOR_DP without an existing
    linked transaction.

    Args:
        current: The current status.
        target: The desired next status.

    Returns:
        True if a transaction should be created on this transition.
    """
    return target == TRANSACTION_TRIGGER_STATUS


def needs_production_gate_check(target: SublimationStatus) -> bool:
    """
    Determine if the production gate check is required for this transition.

    The production gate check validates that the downpayment has been paid,
    or the sublimation is linked to a PurchaseOrder, or a superadmin bypasses.

    Args:
        target: The desired next status.

    Returns:
        True if the production gate check is needed.
    """
    return target == PRODUCTION_GATE_STATUS


def is_status_locked_for_amount_sync(transaction_status: str) -> bool:
    """
    Check if a linked transaction's amount is locked due to existing payments.

    Once payments exist on a transaction, its amount_total cannot be changed.
    This prevents syncing sublimation amount changes back to a locked transaction.

    Args:
        transaction_status: The status of the linked transaction ('pending', 'partial', 'paid').

    Returns:
        True if the amount is locked and cannot be synced.
    """
    return transaction_status in ("partial", "paid")


def is_amount_syncable(sublimation_status: SublimationStatus) -> bool:
    """
    Check if a sublimation at this status can have its amount synced to a linked transaction.

    Only pre-payment phase and early production statuses are syncable.
    Once production is well underway, syncing is blocked.

    Args:
        sublimation_status: The current sublimation status.

    Returns:
        True if amount syncing is allowed.
    """
    return sublimation_status in SYNCABLE_STATUSES


def get_status_label(status: SublimationStatus) -> str:
    """
    Get a human-readable label for a sublimation status.

    Args:
        status: The sublimation status enum value.

    Returns:
        A formatted label string.
    """
    labels = {
        SublimationStatus.FOR_APPROVAL: "For Approval",
        SublimationStatus.DONE_LAYOUT: "Done Layout",
        SublimationStatus.WAITING_FOR_DP: "Waiting for Downpayment",
        SublimationStatus.DOWNPAYMENT_COMPLETE: "Downpayment Complete",
        SublimationStatus.FOR_SIZING: "For Sizing",
        SublimationStatus.DONE_SIZING: "Done Sizing",
        SublimationStatus.PRINTED: "Printed",
        SublimationStatus.CUT: "Cut",
        SublimationStatus.SEWING: "Sewing",
        SublimationStatus.SEWED: "Sewed",
        SublimationStatus.CHECKED: "Checked",
        SublimationStatus.READY_FOR_PICKUP: "Ready for Pickup",
        SublimationStatus.CLAIMED: "Claimed",
        SublimationStatus.COMPLETED: "Completed",
    }
    return labels.get(status, status.value.replace("_", " ").title())
