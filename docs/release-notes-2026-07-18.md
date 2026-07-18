# What's New — July 18, 2026

You can now **delete an approved payroll period** from the period-detail page, and it cleanly reverses the cash advances (and everything else) that period touched.

---

## Payroll / Attendance

### Delete an approved payroll period from the UI

Previously the **Delete** button only appeared for **draft** periods; unwinding an approved period required a superadmin **Void**. The period-show page now shows **Delete** for **approved** periods too, with a confirmation that spells out exactly what happens.

Deleting an approved period (in one transaction):

- **Reverses the cash advances it deducted** — restores each advance's `remaining_balance` and flips a fully-paid advance back to `approved`, driven by the period's own `cash_advance_deductions` ledger rows.
- **Unlocks that period's attendance sheets** (its date range only).
- **Removes the period and all its payroll items.**

Because the reversal is ledger-driven and scoped to the period, deleting a **prior** approved period does **not** disturb a current/other period — only the deleted period's own deductions are given back; any other period's deductions against the same advance stay intact. The current payroll's stored figures are unchanged (it isn't auto-recomputed).

**Access:** unchanged from the backend policy — superadmin (any branch) or admin (own branch); staff cannot delete. `paid` and `voided` periods remain non-deletable. **Void** stays superadmin-only and coexists with Delete for approved periods (Void keeps the row for audit).

### Notes for reviewers

- Backend needed **no change** — `PayrollPeriodService::delete()` already permitted `[DRAFT, APPROVED]` and reversed cash advances; `PayrollPeriodController::destroy()` and `PayrollPeriodPolicy::delete()` already authorized it. The only change was the frontend gate.
- Frontend: `resources/js/pages/payroll/payroll/period-show.tsx` — the Delete block now renders for `draft` **or** `approved`, and the confirmation title/description are status-aware (the approved copy notes the cash-advance reversal).
- Covered by a new HTTP test in `tests/Feature/Payroll/PayrollPeriodTest.php` ("reverts the cash advance and everything connected when an approved period is deleted via HTTP"): approves a period that paid off a ₱1,000 advance, deletes it through the route, and asserts the advance is restored to ₱1,000 / `approved`, the ledger rows are gone, the sheets are unlocked, and the items/period are deleted. Full suite green (68 passing in that file).
