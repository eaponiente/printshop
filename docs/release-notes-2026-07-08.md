# What's New — July 8, 2026

Sublimation totals can now be corrected after a downpayment has been taken, with the linked sales transaction kept in sync.

---

## Sublimations

### Edit the amount at "Downpayment Complete"
- The **Total Amount** on a sublimation used to lock as soon as the order reached **Downpayment Complete**. It is now **editable through Downpayment Complete**, so a total can be corrected (e.g. items added or a price renegotiated) even after the downpayment has been collected. Every later production status (For Sizing, Printed, Sewed, Claimed, …) still locks the amount.
- **Who can edit:** staff can change the amount only while nothing has been paid yet (the order's transaction is still *pending*). Once a downpayment has been recorded, **only an admin** can adjust the total; staff attempting it get a clear error and the field is disabled for them.
- Changing the total **keeps the linked sales transaction in sync automatically**: the transaction's total, remaining balance, and status are updated in the same operation. If the new total equals what has already been paid, the transaction is marked **Paid**; if a balance remains, it stays **Partial**.
- **Safety guard:** the total can never be set **below the amount already paid** — the edit is rejected with a clear error. This prevents negative balances and accidental over-collection.
- Recorded payments (the payment ledger) and branch cash-on-hand are **not** altered by an amount change — only new payments move cash. Changing the total only re-derives what is still owed.
- **Status is always recomputed** from the freshest payment state: a fully paid order whose total is raised drops back to **Partial**; an order whose downpayment was refunded to zero returns to **Pending**.

### Capture a reason for the change
- When an order already has a transaction, editing the amount now shows an optional **"Reason for amount change"** field (e.g. *"customer added items"*, *"price renegotiated"*). The reason is saved on the transaction and recorded in the audit log.
- Every amount change is written to the **audit log** with the before/after total, the reason, and who made the change — so finance can trace any adjustment after the fact.

### Notes for reviewers
- Enforcement lives in `SublimationController::update()`; the transaction sync, status recompute, sublimation save, and audit entry all run inside one DB transaction.
- The transaction row is **locked (`lockForUpdate`) and re-read inside the transaction**, so the paid-amount floor and status recompute run against the freshest state — a payment collected concurrently (after the edit form was opened) can't be clobbered by a stale total.
- Auditing reuses the shared `Payroll\Audit\Traits\Auditable` trait (action `amount_updated`, polymorphic on the `Sublimation`).
- The Sales page's own amount-edit guard is unchanged and still blocks edits on non-pending transactions — amount corrections for a downpaid order are made from the Sublimations page.

---

## Employees

### Moving an employee to another branch keeps their login in sync
- When an employee is reassigned to a different branch, their linked login account now **always** follows to the new branch — previously the account's branch only updated as a side effect of also changing the username, role, or password. This keeps branch-scoped visibility (Sales, Expenses, Sublimations, Incentives, sidebar counts) consistent with the employee's actual branch.

### Notes for reviewers
- Fix is in `EmployeeService::update()`. The linked-user sync no longer sits behind the "credentials changed" guard; instead it mirrors `branch_id`/`first_name`/`last_name` onto the user based on `wasChanged(...)`, and only writes the user row when something actually changed.
- The normal admin edit form was already unaffected because `UpdateEmployeeRequest` requires `username`/`role` on every submit; this hardens the service against callers that update an employee without passing credentials.
- Reassigning branch is only safe when the employee has no locked attendance sheets from an existing payroll period spanning the move — `PayrollPeriodService` void/delete unlock sheets by the *period's* branch, so a post-generation move can orphan locked sheets. No such risk exists before any period has been generated.
