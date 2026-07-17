# What's New — July 17, 2026

The **Work Week Table** now shows **Sunday as its own column**.

---

## Payroll / Attendance

### Sunday is now a dedicated column on the Work Week Table

Previously the grid showed 6 columns — Saturday, then Monday through Friday — and Sunday was omitted even though the payroll week always spanned all 7 days. Sunday activity was folded into totals but had no cell of its own.

The grid now shows **7 columns in calendar order: Saturday, Sunday, Monday, Tuesday, Wednesday, Thursday, Friday**, on both the interactive page and the printable view. A Sunday cell is usually a rest day (rendered with the existing rest-day glyph); an employee with no Sunday sheet simply shows an empty cell. Row and footer totals are unchanged — Sunday was already counted.

### Notes for reviewers

- `WorkWeekTableController::dayColumns()` (`payroll/Attendance/Controllers/WorkWeekTableController.php`): inserted the `addDays(1)` (Sunday) entry between Saturday and Monday, so the method now returns 7 dates. `resolveDateRange()` was already a full Sat→Fri range and was untouched.
- Frontend: the hardcoded `dayLabels` arrays in `resources/js/pages/payroll/work-week/index.tsx` and `print.tsx` gained `'Sun'` in the second position to stay index-aligned with `dayColumns`. colSpan math derives from `dayColumns.length`, so it auto-adjusted.
- `WorkWeekTableService` needed no change — it was already date-range agnostic and built a cell for every sheet in range (including Sunday).
- Covered by `tests/Feature/Payroll/WorkWeekTableTest.php` ("shows Sunday as its own column and still folds it into totals"): asserts the `dayColumns` prop now contains the Sunday date (7 entries) while Sunday OT still feeds row/footer totals and gets its own cell.
