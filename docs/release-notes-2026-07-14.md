# What's New — July 14, 2026

A worked rest day (Sunday, or any configured rest day) is now paid **exactly like a regular working day** — no premium, and no special pro-rata rules.

---

## Payroll / Attendance

### Rest-day work is treated as a regular working day

Previously, a worked rest day earned a **1.30× base-pay premium** with **1.69× overtime**. Then (interim) it was paid **pro-rata by hours, uncapped** — which meant working past 8 hours could pay *more* than one daily rate. Both are gone. A worked rest day now runs through the **exact same computation as a weekday**:

- **Flat daily rate** when present (e.g. ₱510), not pro-rata by hours.
- **Hours capped at the paid end (~8h)** — extra time is paid **only via an approved OT request** (regular 1.25×).
- Normal **late / undertime / half-day / no-break-fine** rules apply.

**Example** (daily rate ₱510): clock in 1 minute late and stay past 5 PM with no OT request → **₱500** (₱510 − ₱10 late), *not* the ₱522.31 the uncapped pro-rata model produced.

The **only** way a rest day differs from a weekday: **not working one is not an absence** — no sheet is created and no deduction applies. Employees are never docked for taking their rest day off.

**Holiday exception:** a holiday that falls on a rest day still uses the existing rest-day holiday rules (and a holiday on Sunday is still suppressed). This is now the *only* place rest-day status affects pay; collapsing it into regular-holiday behavior is a separate decision.

### Notes for reviewers

- `AttendanceService::processDailyAttendance` (`payroll/Attendance/Services/AttendanceService.php`): removed every `! $isRestDay` guard on the **pay** paths (half-day detection, early-departure undertime, the 8h hours cap, the no-break fine) and deleted the special rest-day base-pay / OT branch. Base pay is now `isPresent ? daily_rate : 0` and OT flows through the standard path for all days. The **one** `! $isRestDay` guard that remains is on absence marking, so a rest day with no punches is still not an absence.
- `OvertimeRequestController::resolveShiftType` records `regular_day` for a rest-day OT request (unchanged from the interim step). The holiday `$isRestDay` branches are intentionally left as-is.
- Frontend: the attendance sheet-detail page no longer shows the phantom "Rest Day × 1.30×" addition (it re-derived a premium the backend never paid); `daily_wage` is canonical.
- `PayrollPeriodService` needed no change — it sums `daily_wage`.
- Covered by `tests/Feature/Payroll/RestDayPayTest.php`: full 8h = ₱600 (flat), partial day charges undertime like a weekday, **>8h with no OT caps at one daily rate**, OT via request at 1.25×, unworked rest day has no sheet/absence. Full suite green (455 passing).
