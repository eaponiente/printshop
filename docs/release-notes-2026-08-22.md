# What's New — August 22, 2026

Payslips now show the correct itemized earnings breakdown on weeks that include a worked holiday or a worked rest day — gross and net pay were never wrong, only the line items above them.

---

## Payroll / Attendance

### Payslip breakdown fixed for worked holidays and rest days

An employee's payslip lists earnings as separate lines — Basic Pay, Overtime, Holiday Pay, Incentive, Paid Leave — that are supposed to add up to GROSS PAY. On any week where the employee worked a holiday or a rest day, they didn't: Basic Pay was quietly short by that day's base wage, even though GROSS PAY and the employee's actual take-home NET PAY were both correct the whole time. Nobody was underpaid — the payslip just displayed the earnings breakdown incorrectly.

**A real example that surfaced the bug**: an employee on a ₱550 daily rate worked all 6 days of the period, and the last day happened to be a special (non-working) holiday. Basic Pay should be ₱3,300.00 (₱550 × 6 days worked). The payslip instead showed **₱2,750.00** (₱550 × 5 days) — the worked holiday's ₱550 base wage had vanished from the breakdown, even though it was still correctly included in GROSS PAY. The line items were short by exactly ₱550 and no longer summed to the gross total shown right below them.

The cause: the payslip computed "Basic Pay" by multiplying the daily rate by a day-count column (`total_regular_days`) that is deliberately defined to *exclude* worked holidays and rest days (those days get their own holiday-pay treatment elsewhere in the system). That exclusion is correct for what that column is used for internally — but the payslip was using it to reconstruct a peso amount, and reconstructing pay from an incomplete day count understated it.

The fix: the payslip no longer multiplies a day count by the daily rate to get Basic Pay. It now starts from GROSS PAY — the one number that was always correct — and subtracts out Overtime, Holiday Pay, and Incentive (which already have their own lines), adding back Late/Undertime/Fine penalties (which already appear as their own negative lines further down, so they'd otherwise be subtracted twice), and subtracting Paid Leave (which also has its own line). What's left is the true Basic Pay, and it now always reconciles with GROSS PAY.

The payslip's attendance summary strip also gets a new **"Days Paid"** figure alongside the existing **"Regular"** day count, so the "Basic Pay (Nd)" label shows the actual number of days that earned base pay (worked days including holidays and rest days, excluding only paid-leave days, which are shown separately) instead of the narrower internal count.

### Notes for reviewers

- `resources/js/pages/payroll/payroll/payslip.tsx` (`EarningsCard`) — Basic Pay is now back-solved from `item.gross_pay` (subtract `overtime_pay`, `holiday_pay`, `incentive`; add back `late_deduction`, `undertime_deduction`, `fine_deduction`; subtract `daily_rate × leave_paid_days`) instead of `daily_rate × total_regular_days`. Mirrors the reference implementation already used in `resources/js/pages/payroll/reports/print.tsx` (unchanged by this fix). `AttendanceStrip` now takes a `basicPayDays` prop, relabels the old day-count cell **"Regular"**, and adds a new **"Days Paid"** cell for `basicPayDays`.
- `payroll/Attendance/Controllers/PayrollPeriodController.php::payslip()` — new server-computed `basicPayDays` prop: `AttendanceSheet` rows for the item's employee and period date range, `is_present = true` and (`leave_type` is null or `leave_is_paid` = true), minus `leave_paid_days`, floored at 0. Also fixed the date-range query on this endpoint to use `->toDateString()` on `period_start`/`period_end` — comparing the raw Carbon casts against a plain `date` column silently excluded the period's first day from the count (caught by the new tests below).
- No changes to `AttendanceService` or `PayrollPeriodService` — `gross_pay` and `net_pay` were correct before this fix and remain byte-for-byte the same. This is a presentation-only fix.
- `resources/js/pages/payroll/payroll/my-payslip.tsx` and `resources/js/pages/payroll/payroll/period-show.tsx` were audited for the same `daily_rate × total_regular_days` pattern — neither derives a basic-pay figure that way (`my-payslip.tsx` only shows `net_pay`; `period-show.tsx` shows `total_regular_days` as a plain day-count column and `gross_pay` directly), so neither needed a change.
- Extended `tests/Feature/Payroll/PayslipPageTest.php` (+4): an end-to-end reproduction of the ₱550/6-day scenario above (worked special holiday on the last day, a mid-week incentive) asserting Basic Pay comes out to ₱3,300.00 and `basicPayDays` is 6; a worked-rest-day case showing the same day is included in `basicPayDays` even though `total_regular_days` excludes it; a paid-leave case confirming leave gets its own line without being double-counted into Basic Pay; and a general invariant test asserting the itemized earnings always sum back to `gross_pay` across several OT/holiday/incentive/penalty/leave combinations.
- Docs: `docs/payroll.md` §2.13 (`payroll_period_items`, new note on `total_regular_days` and the Basic Pay back-solve) and §7 (Payslip Design rules updated for the "Days Paid" cell and the back-solve rule).
