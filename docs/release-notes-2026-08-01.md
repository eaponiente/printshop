# What's New — August 1, 2026

Admins can now award a per-day **incentive** on an employee's attendance sheet, and it flows straight through to the payslip.

---

## Payroll / Attendance

### Per-day incentive on the attendance sheet

Admins and superadmins can enter a discretionary incentive amount for a specific attendance day — separate from the existing monthly branch-manager `Incentive` payouts, which are untouched.

- New `attendance_sheets.incentive` and `payroll_period_items.incentive` columns (`decimal(10,2)`, default `0`).
- Set from the attendance sheet detail page (`payroll/attendance-sheets/{employee}`), via a new "Incentive" card with an inline number input and Save button — right above the existing Daily Wage card.
- New endpoint: `PATCH payroll/attendance-sheets/{employee}/incentive` (`payroll.attendance.incentive.update`). Staff cannot use it; every write is audited (`incentive_updated`).
- **Locked sheets reject the edit** — once a sheet is inside a generated payroll period, the incentive (like punches, fines, and corrections) can no longer be changed.
- The incentive is folded into `daily_wage` inside `AttendanceService::processDailyAttendance`, and — because it reads the sheet's existing incentive back before recomputing — it **survives every reprocess** (a punch correction, an approved OT/leave request, etc. no longer wipes it out).
- Since `daily_wage` is canonical (`gross_pay = SUM(daily_wage)`), the incentive automatically flows into `gross_pay` and `net_pay` with no separate addition anywhere. `PayrollPeriodService` also rolls the period total into `payroll_period_items.incentive` as a display-only figure.
- Shows as its own "Incentive" earnings line on both the payslip (`payroll/my-payslip` / admin payslip view) and the printable payroll report — the printable report's "Basic" figure was also corrected to subtract the incentive (in addition to OT and holiday pay) so it isn't overstated now that gross includes it.

### Notes for reviewers

- Migration: `database/migrations/2026_08_01_000000_add_incentive_to_attendance_and_period_items.php`.
- `app/Models/Payroll/AttendanceSheet.php`, `app/Models/Payroll/PayrollPeriodItem.php` — `incentive` cast to `decimal:2`.
- `payroll/Attendance/Services/AttendanceService.php` — reads `$existingSheet->incentive` up front, reuses `$existingSheet` for the lock check (removing a duplicate query), and adds `+ $incentive` to both the full-day-leave and normal `daily_wage` formulas.
- `payroll/Attendance/Controllers/AttendanceSheetController.php` — new `updateIncentive()` action (inline validation, matching this controller's existing style; no FormRequest).
- `payroll/Attendance/Services/PayrollPeriodService.php` — `generateItemForEmployee` sums `incentive` across the period's sheets into the new column; gross/net math is unchanged.
- Frontend: `resources/js/pages/payroll/attendance/sheet-detail.tsx`, `resources/js/pages/payroll/payroll/payslip.tsx`, `resources/js/pages/payroll/reports/print.tsx`.
- Docs: `docs/payroll.md` §9 "Incentive".
- New test: `tests/Feature/Payroll/AttendanceIncentiveTest.php` (4 passing) — daily_wage delta, survives reprocessing, rolls up into the payroll period + gross pay, rejected on a locked sheet.
