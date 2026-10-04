# What's New — October 4, 2026

An admin approved an employee's paid leave before the employee's leave balance had been set, so the approval silently went through as unpaid. There was no way to undo that approval short of deleting the leave outright and asking the employee to file it again. Approved leaves can now be reverted back to pending, so the admin can fix the balance and approve it again — correctly, as paid — without losing the original request.

---

## Payroll / Attendance

### Approved leaves can be reverted to pending

**What was happening.** `LeaveRequestController::approve` checks the employee's `paid_leave_balance` at the moment of approval and saves `is_paid = false` when there isn't enough balance left — this is correct behavior when the balance really is zero. The problem is there was no recovery path when the balance was wrong for an unrelated reason (e.g. it hadn't been set yet for a new hire). The only admin-facing options were **Deny**, which isn't shown for approved leaves and would leave the request denied instead of approved, or **Delete**, which refunds the balance correctly but destroys the leave record, forcing the employee to file it again.

**What changed.** A new **Revert to pending** action (`POST /payroll/leave-requests/{lr}/revert`, route name `payroll.leaves.revert`) is available next to Delete for any `approved` leave, under the same authorization as approving it — anyone who could approve the leave can revert it.

On revert:

- If the leave was `is_paid`, 1 day is refunded to `paid_leave_balance` (mirrors `deny`/`destroy`).
- The leave goes back to `status = pending`, `is_paid = false`, `approved_by`/`approved_at` cleared.
- `AttendanceService::processDailyAttendance` re-runs for that date so the attendance sheet no longer reflects the leave.
- Reverting is refused if the date's attendance sheet is already locked inside a generated payroll period — void the period first. This mirrors the existing `approve`/`destroy` lock guard, so a finalized pay period can't be reopened through the back door.
- The action is logged through the `Auditable` trait as a `reverted` action.

**Recovery flow:** revert the leave → open the employee's record and set the **paid leave balance** field directly (not just the default entitlement — `approve` reads `paid_leave_balance`, and only the yearly reset copies the entitlement into it) → approve the leave again, which now goes through as paid. If the day is already inside a generated payroll period, void that period first.

---

## Notes for administrators

If you have an employee whose leave was already approved as unpaid because their balance wasn't set at the time: revert that leave, set their paid leave balance, then approve it again. If the date falls inside an already-generated payroll period, void that period before reverting.
