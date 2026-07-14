# What's New — July 14, 2026

Working on a rest day (Sunday, or any configured rest day) is now paid like an ordinary working day — no premium.

---

## Payroll / Attendance

### Rest-day work is paid at the regular rate

Previously, an employee who worked a rest day earned a **1.30× base-pay premium** and any overtime was billed at the **1.69× rest-day rate**. That premium is gone. A worked rest day now pays:

- **Base pay** = `hours_worked × hourly_rate` (pro-rata by the hours actually worked, no premium).
- **Overtime** = the regular **1.25×** rate, same as any weekday.

**Example** (daily rate ₱600, hourly ₱75): a full 8-hour Sunday pays **₱600** (one plain daily rate) instead of the old ₱780; a 4-hour Sunday pays **₱300**. Two hours of approved OT on that Sunday pays `2 × ₱75 × 1.25 = ₱187.50` instead of the old 1.69× amount.

Not working a rest day is unchanged — it's still a normal day off, so no absence is recorded and no deduction applies.

**Scope:** this applies to every configured rest day, not only literal Sunday. Holiday pay is unchanged — a holiday that falls on a Sunday still behaves exactly as before.

### Notes for reviewers

- `AttendanceService::processDailyAttendance` (`payroll/Attendance/Services/AttendanceService.php`):
  - Rest-day base pay dropped from `hours_worked × hourly_rate × 1.30` to `hours_worked × hourly_rate`.
  - The rest-day worked branch now keeps `hours_worked` from the in→out span (minus lunch) and pays OT (from OT punches or an approved `OvertimeRequest`) at `getOTMultiplier('regular_day')` = 1.25×, instead of zeroing `overtime_pay` and tagging a 1.69× display multiplier. `overtime_minutes` on a rest day now means *actual* OT (the worked span lives in `hours_worked`).
  - `$isRestDay` still suppresses absence-marking, half-day, undertime (early-departure), and the no-break fine, so days off remain unpenalized. Late/undertime/fine deductions on a worked rest day are unchanged.
- `OvertimeRequestController::resolveShiftType` no longer returns `rest_day`; a rest-day OT request records `regular_day` (or the holiday type when applicable), keeping the stored `shift_type` truthful. `getOTMultiplier()` itself is untouched — its holiday entries still serve the unchanged holiday paths.
- `PayrollPeriodService` needed no change: it sums `daily_wage`, which now already reflects the regular rate.
- Covered by new `tests/Feature/Payroll/RestDayPayTest.php` (full 8h = ₱600, partial 6h = ₱450, worked + OT billed at 1.25× not 1.69×, unworked rest day records no absence, rest-day OT request stores `regular_day`). Full suite green (451 passing).
