# What's New — July 10, 2026

Approved and pending leaves can now be deleted, with the leave balance refunded and the day's attendance recomputed from real punches. Approved payroll periods can now be deleted outright (branch-scoped), unlocking their sheets and restoring cash-advance balances.

---

## Leaves

### Delete a leave an employee won't use
- A leave that was granted but won't be taken can now be **deleted** — for when an employee changes their mind, or was marked on leave for a date they actually came in and worked. Available to **admins and superadmins** from the Leave Requests page (a trash button in the Actions column).
- Works for both **pending** and **approved** leaves. Denied and cancelled leaves can't be deleted. The delete is a hard delete, so the same date can be requested again afterwards.
- **Balance is refunded** automatically: if the leave was **approved and paid**, the employee's paid-leave balance goes back up by one. Pending or unpaid leaves don't affect the balance.
- **The day is recomputed from actual attendance.** Deleting an approved leave re-runs attendance for that date, so the day reflects the real punches — a full worked wage if the employee clocked in, or absent if they didn't. **Punches are never deleted** by removing a leave.
- **On leave today but actually worked?** Delete the leave and the day's pay switches from the flat leave rate to the actual worked computation — the employee's punches and their pay for the day are preserved.
- **Past dates are fine.** There's no date restriction — a leave for an earlier date (e.g. one the employee forgot to cancel) can still be deleted, as long as that date hasn't been locked into a generated payroll period yet.
- **Finalized pay is protected.** If the date's attendance sheet is already locked inside a generated payroll period, the delete is refused with a clear message — that period's pay is final and its leave credit stays spent.

### Notes for reviewers
- New `LeaveRequestController::destroy()` mirrors `approve`/`deny`: status guard (`pending`/`approved` only), locked-sheet guard (reused from `approve`), then a single `DB::transaction` that refunds the balance (approved+paid only), hard-deletes the row, and re-runs `AttendanceService::processDailyAttendance` for approved leaves. The delete is audited via the shared `Auditable` trait (action `deleted`).
- Authorization is a new `leave-requests.delete` gate backed by `LeaveRequestPolicy::delete()`, which delegates to `approve()` (superadmin bypass; admins only within their branch on staff; no acting on your own request). Route: `DELETE /payroll/leave-requests/{leaveRequest}` → `payroll.leaves.destroy`.
- Payroll needs no change: leave value already lives inside `daily_wage`, and `PayrollPeriodService::generate` reads only `attendance_sheets`, so once the sheet is reprocessed the period math is correct with no double-counting.
- Covered by new cases in `tests/Feature/Payroll/LeaveBalanceTest.php` (refund + reprocess, worked-through-leave recompute, unpaid/pending no-op, locked-period block, staff forbidden, denied rejected).

---

## Payroll Periods

### Delete an approved payroll period
- An **approved** payroll period can now be **deleted**, not just voided. Use it to fully undo a period that was approved by mistake — it disappears entirely instead of lingering as a voided record.
- Deleting an approved period **unlocks all of its attendance sheets** (so the days can be corrected and the period re-generated) and **restores any cash-advance balances** those payslips had deducted, exactly like void does.
- **Branch-scoped.** A branch admin can delete an approved period **in their own branch**; a superadmin can delete in any branch; staff cannot delete. The Delete button appears on the period page for drafts and approved periods, with a confirmation dialog spelling out the consequences.
- **Only draft and approved periods are deletable.** Paid periods (money already disbursed) and already-voided periods stay protected.
- **Void still exists** and is unchanged: it keeps the period on record as `voided` and remains superadmin-only. Void when you want an audit trail; delete when you want the period gone.

### Notes for reviewers
- Single guard change in `PayrollPeriodService::delete()`: the DRAFT-only check (pre-transaction and the re-check under `lockForUpdate`) now allows `DELETABLE_STATUSES = [DRAFT, APPROVED]`. The existing branch-scoped sheet unlock + `reverseCashAdvanceDeductions` + item/period delete already handled approved periods correctly.
- No authorization change: `PayrollPeriodController::destroy` already authorizes `payroll-periods.delete` with `$period->branch_id` and `PayrollPeriodPolicy::delete()` is already branch-scoped (staff denied, admin own branch, superadmin any). Only the success flash was made status-neutral.
- Frontend: `period-show.tsx` now shows the delete dialog for `approved` as well as `draft`, with approved-specific copy warning about the sheet unlock and cash-advance restore.
- Covered by new cases in `tests/Feature/Payroll/PayrollPeriodTest.php` (approved delete unlocks sheets + restores CA + drops items/row, paid/voided refused, admin-in-branch allowed, cross-branch admin and staff forbidden, superadmin any branch).
