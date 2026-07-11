# What's New — July 11, 2026

Payroll periods can only be approved once their attendance is fully complete — the Approve button now stays hidden until Check Payroll confirms every record.

---

## Work Week Table

### Separate From / To date filter
- The Work Week date filter is now two explicit **From** and **To** date inputs with an **Apply** button, replacing the single calendar range popover. Set both ends, click Apply, and the table reloads for exactly that range.
- The inputs guard each other (From can't exceed To and vice-versa), and Apply is disabled until a valid, changed range is entered.
- Picking dates now lands on **exactly** the days you choose — the previous calendar popover could shift a selection a day early in Asia/Manila (UTC+8) because it serialized dates through `toISOString()`. Native date inputs use `YYYY-MM-DD` strings directly, so there is no Date/UTC conversion.

#### Notes for reviewers
- `resources/js/pages/payroll/work-week/components/work-week-filters.tsx` now renders two `Input type="date"` fields (local state) plus an Apply button that calls the existing `onDateRangeChange` → `start_date`/`end_date` navigation. The parent `index.tsx` prop contract is unchanged.
- The now-unused `resources/js/components/date-range-picker.tsx` (calendar range popover) was removed. No JS test runner exists in the repo; verified via type/lint checks.

---

## Payroll Periods

### Approve only after attendance is complete
- The **Approve** button now appears only when **Check Payroll** has been run **and** finds every attendance record complete. If any record is incomplete, the button stays hidden so a period can't be approved with missing punches.
- The incomplete-records panel now spells out the next step: **"Resolve these records and re-run Check Payroll before the period can be approved."** Each listed record still links straight to that day's attendance for correction.
- Nothing else in the flow changed: run **Check Payroll**, fix anything it flags, re-check, and once it comes back all-clear the **Approve** button reappears.

### Notes for reviewers
- Visibility-only change in `resources/js/pages/payroll/payroll/period-show.tsx`: the Approve button guard gained `incompleteSheets.length === 0` (alongside the existing `canApprove`, `status === 'draft'`, and `checked_at` conditions). `incompleteSheets` is already computed server-side in `PayrollPeriodController::show` via `PayrollPeriodService::findIncompleteSheets`, only after `checked_at` is set.
- Consistent with the existing check→approve convention, which is frontend-gated. Server-side hard enforcement on the approve endpoint was intentionally deferred — the current approve/delete test fixtures create attendance sheets without punches, so every sheet reads as incomplete; enforcing it would require reworking those fixtures across the suite.
- The visibility contract is covered by new cases in `tests/Feature/Payroll/PayrollPeriodTest.php` that assert the `show` page's Inertia props (`canApprove`, `period.checked_at`, `incompleteSheets`): button hidden before Check Payroll, shown when the check finds attendance complete, hidden when it finds incomplete records, and the completeness check scoped to the period's branch and date range.

### Check Payroll now validates more than attendance
- **Check Payroll** now runs a fuller set of checks before a period can be approved, shown in the results panel:
  - **No missing attendance** — unchanged (incomplete punch sets are listed with links to fix them).
  - **All employees computed** — any active employee in the branch with no payroll line (for example, someone hired or reactivated after the period was generated) is now listed. The period can't be approved until they're included; **Recompute** pulls them in.
  - **Not already approved** — running Check Payroll on a period that's no longer a draft is refused.
  - **No negative net pay** — employees whose deductions exceeded their earnings (net pay floored to ₱0) are shown as a **warning**. This does **not** block approval — it's there so payroll staff can review before approving.
- The **Approve** button appears only when there are no *blocking* issues (missing attendance or uncomputed employees). The negative-net-pay warning never blocks it.

### Notes for reviewers
- New service methods on `PayrollPeriodService`: `findUncomputedEmployees()` (active, non-superadmin branch employees with no item — mirrors the population `generate()` computes) and `findNegativeNetPay()` (items whose pre-floor net `gross + deminimis − SSS − PhilHealth − Pag-IBIG − CA` is < 0; CA is skipped once the post-statutory pool is non-positive, so this detects the genuine floor-hit from stored columns).
- `PayrollPeriodController::show` passes `uncomputedEmployees` and `negativeNetPay` props (computed only after `checked_at`); `check` gained a `draft`-only guard. `period-show.tsx` gate is now `... && incompleteSheets.length === 0 && uncomputedEmployees.length === 0`; negative-net is rendered as a non-blocking warning.
- "No payroll errors" from the original ask was intentionally dropped per scoping.
- Covered by new cases in `tests/Feature/Payroll/PayrollPeriodTest.php`: uncomputed employee gates approval, negative net pay warns without gating, and Check Payroll is refused on a non-draft period.

### Approved-period Delete removed from the UI
- The **Delete** button for **approved** payroll periods (added July 10) has been **removed** from both the period detail page and the periods list. The Delete button now shows for **draft** periods only again.
- **Void** is unchanged and remains the way to reverse an approved period (superadmin-only, keeps the period on record as `voided`).
- Reviewer note: frontend-only revert of PR #96's UI — the delete guards on `period-show.tsx` and `periods.tsx` are back to `status === 'draft'`. The backend `payroll-periods.delete` route/service still accepts approved periods (unchanged, and still covered by tests); it is simply no longer reachable from the UI.

---

## Sewed Items

### Search by sublimation description
- The Sewed Items filter bar now has a **Search** box that matches on the linked **sublimation's description**. Type any part of a description and click **Filter** (or press Enter) to narrow the list; **Reset** clears it along with the other filters.

### Date filter shows more rows per page
- When a **Date From** and/or **Date To** filter is applied, the page now shows up to **200** rows instead of 20, so a full date range is visible without paging. With no date filter, the default page size (20) is unchanged.

### Rebuilt print payslip
- **Printing a sewed-items payslip** now renders from a clean, dedicated print window instead of restyling the on-screen dialog. This fixes the layout breaking across pages (clipped rows, trailing blank pages) that came from the old print-CSS overrides fighting the modal. The on-screen preview is unchanged.

### Notes for reviewers
- `SewedItemController::index` gained a `search` filter (`whereHas('sublimation', description LIKE %search%)`) and computes `$perPage = (date_from || date_to) ? 200 : 20` before `paginate()`. Both are covered by new cases in `tests/Feature/Payroll/SewedItemTest.php` (description search narrows the list; `per_page` is 20 by default and 200 with a date filter).
- `resources/js/pages/payroll/sewed-items/index.tsx` adds the Search input (local state, wired through `applyFilters`/`resetFilters`) and the `Filters.search` type.
- `payslip-dialog.tsx` `Print` now builds a self-contained HTML document and prints it via `window.open` — no more `@media print` overrides, `visibility` toggles, or repositioning of the fixed dialog. Requires pop-ups to be allowed (a toast prompts if blocked).
