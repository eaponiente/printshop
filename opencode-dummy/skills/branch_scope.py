"""
Skill: branch_scope

Branch access scoping utilities: determines which branches a given role can access,
handles special group branch sharing (Babak, Pen aplata, Tibungco), and provides
cross-branch expense approval routing logic.

Mirrors the role-based branch scoping in controllers and policies.
"""

from enum import Enum
from typing import List, Dict, Set, Optional, Literal


class UserRole(str, Enum):
    SUPERADMIN = "superadmin"
    ADMIN = "admin"
    STAFF = "staff"


# Special group branches that share access with each other
SPECIAL_GROUP_BRANCHES: Set[str] = {"Babak", "Peñaplata", "Tibungco"}


def is_special_group_branch(branch_name: str) -> bool:
    """
    Check if a branch is part of the special group that shares access.

    Args:
        branch_name: The branch name.

    Returns:
        True if the branch is in the special group.

    Example:
        >>> is_special_group_branch("Babak")
        True
        >>> is_special_group_branch("Davao")
        False
    """
    return branch_name in SPECIAL_GROUP_BRANCHES


def can_access_branch(
    user_role: UserRole,
    user_branch_id: Optional[int],
    user_branch_name: Optional[str],
    target_branch_id: int,
    target_branch_name: Optional[str] = None,
) -> bool:
    """
    Determine if a user can access records from a target branch.

    Rules:
    - Superadmin: access to all branches.
    - Admin: access to own branch. If in special group, also access to
      other special group branches.
    - Staff: access only to own branch.

    Args:
        user_role: The user's role.
        user_branch_id: The user's branch ID.
        user_branch_name: The user's branch name.
        target_branch_id: The branch ID being accessed.
        target_branch_name: The target branch name (for special group check).

    Returns:
        True if access is allowed.

    Example:
        >>> can_access_branch(UserRole.SUPERADMIN, 1, "Main", 5)
        True
        >>> can_access_branch(UserRole.ADMIN, 2, "Babak", 3, "Peñaplata")
        True
        >>> can_access_branch(UserRole.ADMIN, 2, "Babak", 4, "Davao")
        False
        >>> can_access_branch(UserRole.STAFF, 1, "Main", 2)
        False
    """
    if user_role == UserRole.SUPERADMIN:
        return True

    if user_branch_id == target_branch_id:
        return True

    if user_role == UserRole.ADMIN:
        if user_branch_name and target_branch_name:
            if is_special_group_branch(user_branch_name) and is_special_group_branch(target_branch_name):
                return True

    return False


def get_accessible_branches(
    superadmin: bool = False,
    branch_id: Optional[int] = None,
) -> List[Dict[str, object]]:
    """
    Get a list of accessible branches for query construction.

    Args:
        superadmin: Whether the caller has superadmin privileges.
        branch_id: Specific branch ID to scope to.

    Returns:
        List of dicts with branch 'id' and 'name'.
    """
    # In production, this would call the Branch model with scope
    # AccessibleBy. For the skill, we return a placeholder that
    # the calling command would use to construct artisan calls.
    branches = []
    if branch_id:
        branches.append({"id": branch_id, "name": ""})
    elif superadmin:
        # Return all branches marker
        branches.append({"id": "ALL", "name": "All Branches"})
    return branches


def resolve_expense_approver(
    expense_branch_id: int,
    debtor_branch_id: Optional[int],
    expense_creator_role: UserRole,
    expense_creator_branch_id: int,
) -> Dict[str, object]:
    """
    Determine who should approve a cross-branch expense.

    Rules from ExpensePolicy:
    - If debtor_branch_id is set and != creator branch: debtor branch admin.
    - Creator branch admin cannot self-approve cross-branch expenses.
    - Superadmin can approve any expense.

    Args:
        expense_branch_id: The branch where the expense was created.
        debtor_branch_id: The debtor branch responsible for approval.
        expense_creator_role: Role of the user who created the expense.
        expense_creator_branch_id: Branch of the user who created the expense.

    Returns:
        Dict with 'approver_role' and 'approver_branch_id'.
    """
    if debtor_branch_id and debtor_branch_id != expense_creator_branch_id:
        return {
            "approver_role": UserRole.ADMIN.value,
            "approver_branch_id": debtor_branch_id,
            "note": "Cross-branch expense requires debtor branch admin approval",
        }
    else:
        return {
            "approver_role": UserRole.SUPERADMIN.value,
            "approver_branch_id": expense_branch_id,
            "note": "Same-branch or no debtor: superadmin approval required",
        }
