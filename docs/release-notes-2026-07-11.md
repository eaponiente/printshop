# What's New — July 11, 2026

Payroll periods can only be approved once their attendance is fully complete — the Approve button now stays hidden until Check Payroll confirms every record.

---

## Payroll Periods

### Approve only after attendance is complete
- The **Approve** button now appears only when **Check Payroll** has been run **and** finds every attendance record complete. If any record is incomplete, the button stays hidden so a period can't be approved with missing punches.
- The incomplete-records panel now spells out the next step: **"Resolve these records and re-run Check Payroll before the period can be approved."** Each listed record still links straight to that day's attendance for correction.
- Nothing else in the flow changed: run **Check Payroll**, fix anything it flags, re-check, and once it comes back all-clear the **Approve** button reappears.

### Notes for reviewers
- Visibility-only change in `resources/js/pages/payroll/payroll/period-show.tsx`: the Approve button guard gained `incompleteSheets.length === 0` (alongside the existing `canApprove`, `status === 'draft'`, and `checked_at` conditions). `incompleteSheets` is already computed server-side in `PayrollPeriodController::show` via `PayrollPeriodService::findIncompleteSheets`, only after `checked_at` is set.
- Consistent with the existing check→approve convention, which is frontend-gated. Server-side hard enforcement on the approve endpoint was intentionally deferred — the current approve/delete test fixtures create attendance sheets without punches, so every sheet reads as incomplete; enforcing it would require reworking those fixtures across the suite.

### Approved-period Delete removed from the UI
- The **Delete** button for **approved** payroll periods (added July 10) has been **removed** from both the period detail page and the periods list. The Delete button now shows for **draft** periods only again.
- **Void** is unchanged and remains the way to reverse an approved period (superadmin-only, keeps the period on record as `voided`).
- Reviewer note: frontend-only revert of PR #96's UI — the delete guards on `period-show.tsx` and `periods.tsx` are back to `status === 'draft'`. The backend `payroll-periods.delete` route/service still accepts approved periods (unchanged, and still covered by tests); it is simply no longer reachable from the UI.
