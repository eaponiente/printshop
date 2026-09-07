# Payroll System — Architecture & Implementation Spec

**Printing Shop Management System** · June 2026

---

## Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Database Schema](#2-database-schema)
3. [Business Rules](#3-business-rules)
4. [API Routes](#4-api-routes)
5. [RBAC & Authorization](#5-rbac--authorization)
6. [Edge Cases](#6-edge-cases)
7. [Payslip Design](#7-payslip-design)
8. [Work Week Payroll Table](#8-work-week-payroll-table)
9. [Incentive](#9-incentive)

---

## 1. Architecture Overview

### 3-Layer Data Flow

```
time_logs  ──→  attendance_sheets  ──→  payroll_period_items
  (raw)          (daily worksheets)       (period summaries)
  immutable      re-computable            permanently locked on approval
```

- **Layer 1: `time_logs`** — raw punch records, append-only, never mutated after creation
- **Layer 2: `attendance_sheets`** — daily computed worksheets, re-computable until locked
- **Layer 3: `payroll_period_items`** — aggregated per pay period, permanently locked on approval

### Computation Triggers

| Trigger                   | What Runs                                      | Purpose                                          |
| ------------------------- | ---------------------------------------------- | ------------------------------------------------ |
| Per punch (event-driven)  | `processDailyAttendance()` for employee + date | Real-time status: late warnings, daily wage, OT  |
| On correction approval    | `processDailyAttendance()` for employee + date | Regenerate corrected sheet                       |
| Payroll period generation | Lock sheets, aggregate into period items       | Finalize pay period                              |
| Payroll period void       | Unlock all sheets in period                    | Enable corrections then re-generate              |
| Payroll period delete     | Unlock sheets, reverse cash advances, drop row | Undo a `draft` **or `approved`** period entirely |

### Auth Architecture

The system separates **authentication** (users table) from **employment** (employees table):

- `users` — holds username, password, first_name, last_name, role, branch_id. Used for login and RBAC.
- `employees` — holds employment data: employee_number, position, status, daily_rate, government IDs. Linked to `users` via `users.employee_id`.
- An employee can exist without a user account (no login). A user account can be created and linked later. When an employee's status changes to non-active, the linked user is blocked from login.

---

## 2. Database Schema

### 2.1 `users`

| Column                     | Type                         | Notes                                                       |
| -------------------------- | ---------------------------- | ----------------------------------------------------------- |
| `id`                       | bigint PK                    |                                                             |
| `branch_id`                | string, nullable             | Branch assignment (null for superadmin)                     |
| `first_name`               | string(255)                  |                                                             |
| `last_name`                | string(255)                  |                                                             |
| `username`                 | string(255), unique          | Login identifier                                            |
| `password`                 | string(255)                  | Hashed                                                      |
| `role`                     | string(20)                   | `staff`, `admin`, `superadmin`. Determines all RBAC.        |
| `employee_id`              | unsignedBigInteger, nullable | FK to employees. Links auth credentials to employee record. |
| `deleted_at`               | timestamp, nullable          | Soft deletes                                                |
| `remember_token`           | string(100), nullable        |                                                             |
| `created_at`, `updated_at` | timestamps                   |                                                             |

**Role methods** on the User model:

- `isSuperAdmin()` — checks `role === 'superadmin'`
- `isAdmin()` — checks `role === 'admin'`
- `isStaff()` — checks `role === 'staff'`
- `canLogin()` — if linked to employee, blocks login unless employee is soft-deleted or status is not ACTIVE

### 2.2 `employees`

| Column                     | Type               | Notes                                          |
| -------------------------- | ------------------ | ---------------------------------------------- |
| `id`                       | bigint PK          | auto                                           |
| `employee_number`          | string(50), unique | auto: `EMP-{YEAR}-{NNNN}`                      |
| `branch_id`                | foreignId          | FK to branches. Scopes all data access.        |
| `first_name`               | string(100)        |                                                |
| `last_name`                | string(100)        |                                                |
| `middle_name`              | string(100)        | nullable                                       |
| `email`                    | string(255)        | nullable, unique                               |
| `phone`                    | string(20)         | nullable                                       |
| `address`                  | string(500)        | nullable                                       |
| `birth_date`               | date               | nullable                                       |
| `hire_date`                | date               |                                                |
| `end_date`                 | date               | nullable. Set on resignation/termination.      |
| `position`                 | string(50)         | `regular`, `contractual`, `project_based`      |
| `status`                   | string(20)         | `active`, `inactive`, `resigned`, `terminated` |
| `current_daily_rate`       | decimal(10,2)      | Synced from latest salary record               |
| `sss_number`               | string(20)         | nullable. Required for SSS deduction.          |
| `philhealth_number`        | string(20)         | nullable. Required for PhilHealth deduction.   |
| `pagibig_number`           | string(20)         | nullable. Required for Pag-IBIG deduction.     |
| `tin_number`               | string(20)         | nullable                                       |
| `notes`                    | text               | nullable                                       |
| `deleted_at`               | timestamp          | nullable. Soft deletes.                        |
| `created_at`, `updated_at` | timestamps         |                                                |

Indexes: `status`, `position`, `employee_number` (unique)

**Employee ↔ User Relationship**: `users.employee_id` FK → `employees.id`. An employee can have zero or one linked user. When employee `status != 'active'`, the linked user is blocked from login.

### 2.3 `salaries`

| Column                     | Type           | Notes                                      |
| -------------------------- | -------------- | ------------------------------------------ |
| `id`                       | bigint PK      |                                            |
| `employee_id`              | foreignId      | FK to employees, cascade delete            |
| `daily_rate`               | decimal(10,2)  |                                            |
| `effective_date`           | date           | When this rate became effective            |
| `end_date`                 | date, nullable | When this rate ended. NULL = current rate. |
| `notes`                    | text, nullable | Reason for change                          |
| `created_at`, `updated_at` | timestamps     |                                            |

Indexes: `[employee_id, effective_date]`, `[employee_id, end_date]`

**Salary history pattern**: Each employee has multiple salary records forming a timeline. `Salary::createForEmployee()` closes the prior active salary (sets `end_date` to now) then creates the new record. The current rate is the row where `end_date IS NULL`.

### 2.4 `time_logs`

| Column         | Type                | Notes                                           |
| -------------- | ------------------- | ----------------------------------------------- |
| `id`           | bigint PK           |                                                 |
| `employee_id`  | foreignId           | FK to employees                                 |
| `type`         | string(20)          | `in`, `out`, `lunch_out`, `lunch_in`            |
| `source`       | string(20)          | `self_service`, `manual`, `correction`          |
| `timestamp`    | datetime            | Actual punch datetime                           |
| `note`         | text, nullable      | Optional note (used for manual/correction logs) |
| `duplicate_of` | foreignId, nullable | FK to time_logs. Set for duplicate punches.     |
| `created_at`   | timestamp           | No `updated_at` (immutable)                     |

Indexes: `[employee_id, type]`, `[employee_id, created_at]`

**Immutable**: Once created, never updated. Corrections create new rows with `source=correction`. Duplicates are throttled (5-min window) and marked via `duplicate_of`.

### 2.5 `attendance_sheets`

| Column                     | Type                   | Notes                                        |
| -------------------------- | ---------------------- | -------------------------------------------- |
| `id`                       | bigint PK              |                                              |
| `employee_id`              | foreignId              |                                              |
| `date`                     | date                   | The work date                                |
| `schedule_start_time`      | time, nullable         | e.g., `08:00`                                |
| `schedule_end_time`        | time, nullable         | e.g., `17:00`                                |
| `rest_days`                | json, nullable         | Array of day-of-week integers (0=Sun, 6=Sat) |
| `daily_rate`               | decimal(10,2)          | Rate snapshot at computation time            |
| `late_minutes`             | integer                |                                              |
| `late_deduction`           | decimal(10,2)          |                                              |
| `undertime_minutes`        | integer                | Includes over-lunch minutes                  |
| `undertime_deduction`      | decimal(10,2)          |                                              |
| `overtime_minutes`         | integer                | lower-of-two: min(actual, approved)          |
| `overtime_pay`             | decimal(10,2)          |                                              |
| `overtime_multiplier`      | decimal(5,2), nullable | Labor law multiplier used (e.g., 1.25, 1.69) |
| `holiday_type`             | string(20), nullable   | `regular`, `special`                         |
| `holiday_pay_percent`      | integer, nullable      | 0, 100, 130, 200                             |
| `holiday_pay`              | decimal(10,2)          |                                              |
| `leave_type`               | string(20), nullable   | `vacation`, `sick`, `emergency`, etc.        |
| `leave_duration`           | string(20), nullable   | `full_day`, `half_day_am`, `half_day_pm`     |
| `leave_is_paid`            | boolean                |                                              |
| `leave_hours_credited`     | decimal(5,2)           | Hours credited from leave                    |
| `fine_deduction`           | decimal(10,2)          | Sum of all fines for the date                |
| `hours_worked`             | decimal(5,2)           | Total hours worked (after lunch deduction)   |
| `daily_wage`               | decimal(10,2)          | Final daily wage                             |
| `is_present`               | boolean                |                                              |
| `absence_type`             | string(30), nullable   | `unexcused`, `approved_leave`                |
| `is_rest_day`              | boolean                |                                              |
| `locked_at`                | timestamp, nullable    | Set on payroll period generation             |
| `created_at`, `updated_at` | timestamps             |                                              |

Unique index: `[employee_id, date]`
Additional indexes: `date`, `locked_at`

**Computed per-punch**: `processDailyAttendance()` runs after every punch, correction approval, fine change. Recomputes the sheet unless locked.

### 2.6 `employee_schedules`

| Column                     | Type           | Notes                                                               |
| -------------------------- | -------------- | ------------------------------------------------------------------- |
| `id`                       | bigint PK      |                                                                     |
| `employee_id`              | foreignId      |                                                                     |
| `start_time`               | time           | e.g., `08:00`                                                       |
| `end_time`                 | time           | e.g., `17:00`                                                       |
| `unpaid_tail_minutes`      | integer        | Unpaid buffer after shift (default 30). OT starts after 480 + this. |
| `rest_days`                | json           | Array of day-of-week integers (0=Sunday, 6=Saturday)                |
| `effective_from`           | date           |                                                                     |
| `effective_to`             | date, nullable | NULL = currently active                                             |
| `is_active`                | boolean        | Default `true`                                                      |
| `created_at`, `updated_at` | timestamps     |                                                                     |

Index: `[employee_id, effective_from, effective_to]`

**Active schedule lookup**: `EmployeeSchedule::activeForDate(employeeId, date)` finds the schedule where `effective_from <= date <= COALESCE(effective_to, '9999-12-31')` AND `is_active = true`, ordered by `effective_from DESC`.

### 2.7 `overtime_requests`

| Column                     | Type                | Notes                                                           |
| -------------------------- | ------------------- | --------------------------------------------------------------- |
| `id`                       | bigint PK           |                                                                 |
| `employee_id`              | foreignId           |                                                                 |
| `date`                     | date                | Date of OT                                                      |
| `hours_needed`             | integer             | Hours requested                                                 |
| `shift_type`               | string(30)          | `regular_day`, `rest_day`, `regular_holiday`, `special_holiday` |
| `reason`                   | string(500)         | Required                                                        |
| `status`                   | string(20)          | `pending`, `approved`, `denied`                                 |
| `approved_by`              | foreignId, nullable | FK to users                                                     |
| `approved_at`              | timestamp, nullable |                                                                 |
| `created_at`, `updated_at` | timestamps          |                                                                 |

Unique index: `[employee_id, date]`
Additional index: `status`

**No rate snapshot**: OT rates use fixed labor law multipliers derived from `daily_rate / 8` at computation time. No frozen rates on approval.

### 2.8 `leave_requests`

| Column                     | Type                | Notes                                                                              |
| -------------------------- | ------------------- | ---------------------------------------------------------------------------------- |
| `id`                       | bigint PK           |                                                                                    |
| `employee_id`              | foreignId           |                                                                                    |
| `date`                     | date                |                                                                                    |
| `leave_type`               | string(30)          | `vacation`, `sick`, `emergency`, `maternity`, `paternity`, `bereavement`, `unpaid` |
| `duration`                 | string(20)          | `full_day`, `half_day_am`, `half_day_pm`                                           |
| `is_paid`                  | boolean             | Default `true`                                                                     |
| `reason`                   | string(500)         | Required                                                                           |
| `status`                   | string(20)          | `pending`, `approved`, `denied`                                                    |
| `approved_by`              | foreignId, nullable | FK to users                                                                        |
| `approved_at`              | timestamp, nullable |                                                                                    |
| `created_at`, `updated_at` | timestamps          |                                                                                    |

Unique index: `[employee_id, date]`
Additional index: `status`

### 2.9 `correction_requests`

| Column                     | Type                | Notes                                                                         |
| -------------------------- | ------------------- | ----------------------------------------------------------------------------- |
| `id`                       | bigint PK           |                                                                               |
| `employee_id`              | foreignId           |                                                                               |
| `date`                     | date                |                                                                               |
| `correction_type`          | string(30)          | `missed_punch_in`, `missed_punch_out`, `time_adjustment`, `absent_to_present` |
| `requested_time`           | datetime, nullable  | Single requested time (see items for multi-punch corrections)                 |
| `reason`                   | string(500)         | Required                                                                      |
| `status`                   | string(20)          | `pending`, `approved`, `denied`                                               |
| `denial_reason`            | string(500)         | Required on denial                                                            |
| `reviewed_by`              | foreignId, nullable | FK to users                                                                   |
| `reviewed_at`              | timestamp, nullable |                                                                               |
| `resolved_time_log_id`     | foreignId, nullable | FK to time_logs (the created correction log)                                  |
| `created_at`, `updated_at` | timestamps          |                                                                               |

Unique index: `[employee_id, date, correction_type]`
Additional index: `status`

**Correction Items**: Each correction request can have multiple `correction_request_items` (see 2.10), enabling multi-punch corrections from a single request.

### 2.10 `correction_request_items`

| Column                     | Type       | Notes                                     |
| -------------------------- | ---------- | ----------------------------------------- |
| `id`                       | bigint PK  |                                           |
| `correction_request_id`    | foreignId  | FK to correction_requests, cascade delete |
| `punch_type`               | string(20) | `in`, `out`                               |
| `requested_time`           | datetime   | The corrected punch timestamp             |
| `created_at`, `updated_at` | timestamps |                                           |

**Usage**: On correction approval, each item creates a corresponding `time_log` with `source=correction`.

### 2.11 `cash_advances`

| Column                     | Type                | Notes                                             |
| -------------------------- | ------------------- | ------------------------------------------------- |
| `id`                       | bigint PK           |                                                   |
| `employee_id`              | foreignId           |                                                   |
| `amount`                   | decimal(10,2)       | Original loan amount                              |
| `remaining_balance`        | decimal(10,2)       | Amount still unpaid                               |
| `reason`                   | string(500)         | Required                                          |
| `status`                   | string(20)          | `pending`, `approved`, `denied`, `unpaid`, `paid` |
| `approved_by`              | foreignId, nullable | FK to users                                       |
| `approved_at`              | timestamp, nullable |                                                   |
| `created_at`, `updated_at` | timestamps          |                                                   |

Index: `[employee_id, status]`

### 2.12 `payroll_periods`

| Column                     | Type                | Notes                                 |
| -------------------------- | ------------------- | ------------------------------------- |
| `id`                       | bigint PK           |                                       |
| `branch_id`                | foreignId           |                                       |
| `period_start`             | date                | Monday                                |
| `period_end`               | date                | Saturday                              |
| `status`                   | string(20)          | `draft`, `approved`, `paid`, `voided` |
| `approved_by`              | foreignId, nullable | FK to users                           |
| `approved_at`              | timestamp, nullable |                                       |
| `created_at`, `updated_at` | timestamps          |                                       |

Unique index: `[branch_id, period_start, period_end]`
Additional index: `status`

**Void vs. delete.** Both unlock the period's attendance sheets and reverse cash-advance
deductions (restoring `remaining_balance`), but differ in what survives and who may act:

- **Void** keeps the row as `voided` for audit. Superadmin-only (`payroll-periods.void`).
- **Delete** hard-deletes the period and its items (an `Auditable` `deleted` entry preserves
  the before-state). Allowed for `draft` **and `approved`** periods only — `paid` and `voided`
  stay non-deletable. **Branch-scoped** (`payroll-periods.delete`): superadmin any branch,
  admin their own branch, staff denied. See `PayrollPeriodService::delete()`. The period-show
  UI surfaces the **Delete** button for both `draft` and `approved` periods (the approved
  variant shows a confirmation that spells out the cash-advance reversal); deleting a prior
  approved period reverses only that period's own ledger entries and unlocks only its own date
  range, leaving any other period's cash-advance deductions untouched.

**Check Payroll → Approve gate.** A draft period must pass **Check Payroll** (`payroll.periods.check`,
which stamps `checked_at`) before the **Approve** button appears. `PayrollPeriodController::show`
computes the validation report only once `checked_at` is set and passes it to `period-show.tsx`,
which reveals Approve only when there are no **blocking** issues:

- **No missing attendance** — `PayrollPeriodService::findIncompleteSheets()` (present, non-rest,
  non-leave sheets that lack a matching punch set for that day). _Blocks._
- **All employees computed** — `findUncomputedEmployees()`: active, non-superadmin employees in the
  branch with no payroll item (e.g. hired/reactivated after generation). Recompute to include them.
  _Blocks._
- **Not already approved** — `check` is refused unless the period is `draft`; Approve is `draft`-only.
  _Blocks (enforced server-side)._
- **No negative net pay** — `findNegativeNetPay()`: items whose pre-floor net (`gross + deminimis −
SSS − PhilHealth − Pag-IBIG − CA`) is below zero because deductions outran earnings (stored
  `net_pay` is clamped to 0). **Warning only — does not block approval.**

The "No payroll errors" idea from early scoping was intentionally dropped. Enforcement of the two
non-attendance blockers is frontend-gated except `check`'s draft guard; the approve endpoint itself
is not hard-blocked (see the July 11 release notes for the test-fixture rationale).

### 2.13 `payroll_period_items`

| Column                     | Type              | Notes                                 |
| -------------------------- | ----------------- | ------------------------------------- |
| `id`                       | bigint PK         |                                       |
| `payroll_period_id`        | foreignId         | FK to payroll_periods                 |
| `employee_id`              | foreignId         |                                       |
| `total_regular_days`       | integer           | Excludes worked holidays and rest days — see note below |
| `absent_days`              | integer           |                                       |
| `total_late_minutes`       | integer           | Total across period                   |
| `late_deduction`           | decimal(10,2)     |                                       |
| `total_undertime_minutes`  | integer           | Total across period                   |
| `undertime_deduction`      | decimal(10,2)     |                                       |
| `total_overtime_minutes`   | integer           | Total across period                   |
| `overtime_pay`             | decimal(10,2)     |                                       |
| `holiday_pay_days`         | integer           |                                       |
| `holiday_pay`              | decimal(10,2)     |                                       |
| `leave_paid_days`          | integer           |                                       |
| `fine_deduction`           | decimal(10,2)     |                                       |
| `gross_pay`                | decimal(10,2)     | sum(daily_wage)                       |
| `deminimis_earnings`       | decimal(10,2)     | Sum of de minimis benefits for period |
| `sss_deduction`            | decimal(10,2)     |                                       |
| `philhealth_deduction`     | decimal(10,2)     |                                       |
| `pagibig_deduction`        | decimal(10,2)     |                                       |
| `ca_deduction`             | decimal(10,2)     | Cash advance deduction                |
| `net_pay`                  | decimal(10,2)     |                                       |
| `daily_rate`               | decimal(10,2)     | Rate snapshot at generation time      |
| `sss_bracket`              | integer, nullable | Bracket number for reference          |
| `created_at`, `updated_at` | timestamps        |                                       |

Unique index: `[payroll_period_id, employee_id]`
Additional index: `employee_id`

**`total_regular_days` must never be used to derive basic pay.** It is computed in
`PayrollPeriodService::generateItemForEmployee` as
`sheets->where('is_present', true)->whereNull('holiday_type')->where('is_rest_day', false)->count()` —
it deliberately **excludes** any day carrying a `holiday_type` (worked holiday) or `is_rest_day`
(worked rest day), because those days already have their own display treatment (holiday premium,
rest-day note). But the day's *base* pay is still folded into `daily_wage` and therefore into
`gross_pay` (`gross_pay = SUM(daily_wage)`) — only the display-only day *count* excludes it. A
payslip that computed `Basic Pay = daily_rate × total_regular_days` (as it used to) silently
dropped that day's base pay from the itemized earnings while it stayed inside GROSS PAY, so the
line items no longer summed to gross on any week containing a worked holiday or worked rest day.

The fix: **Basic Pay is back-solved from the canonical `gross_pay`**, not multiplied out from a day
count. `resources/js/pages/payroll/payroll/payslip.tsx` (`EarningsCard`) computes
`basicPay = gross_pay − overtime_pay − holiday_pay − incentive + late_deduction +
undertime_deduction + fine_deduction − (daily_rate × leave_paid_days)` — the penalties are added
back because they render as their own negative line items (they'd otherwise be charged twice), and
paid leave is subtracted because it has its own line. `resources/js/pages/payroll/reports/print.tsx`
already did this for its own basic-pay figure and was the reference implementation. The day *count*
shown next to "Basic Pay (Nd)" is a separate server-computed prop, `basicPayDays`
(`PayrollPeriodController::payslip()`): present days where the employee is not on unpaid full-day
leave, minus `leave_paid_days` (since paid leave gets its own line) — i.e. every day that actually
contributes to basic pay, holiday and rest days included. The payslip's Attendance Summary strip
keeps `total_regular_days` under the label **"Regular"** for reference and adds a separate **"Days
Paid"** cell for `basicPayDays`, so the two are never conflated again.

### 2.14 `holidays`

| Column                     | Type        | Notes                                                 |
| -------------------------- | ----------- | ----------------------------------------------------- |
| `id`                       | bigint PK   |                                                       |
| `name`                     | string(200) | e.g., "Araw ng Kagitingan"                            |
| `date`                     | date        |                                                       |
| `type`                     | string(20)  | `regular`, `special`                                  |
| `recurring`                | boolean     | Default `false`. If true, matches month+day annually. |
| `created_at`, `updated_at` | timestamps  |                                                       |

Indexes: `date`, `type`

#### `branch_holiday` (pivot)

| Column                      | Type       | Notes                              |
| ---------------------------- | ---------- | ----------------------------------- |
| `id`                         | bigint PK  |                                     |
| `branch_id`                  | bigint FK  | → `branches.id`, cascade on delete |
| `holiday_id`                 | bigint FK  | → `holidays.id`, cascade on delete |
| `created_at`, `updated_at`   | timestamps |                                     |

Unique index: `[branch_id, holiday_id]`. Additional index: `holiday_id` (the composite unique leads with `branch_id`, so `Holiday::forDate()`'s correlated `EXISTS` on `holiday_id` needs its own index).

**Branch scoping.** A holiday with **no** `branch_holiday` rows applies **nationwide**; rows in the pivot scope it to just those branches. The `holidays` table itself didn't need any schema change or backfill to support this — all 13 seeded national holidays stayed nationwide automatically because they simply have zero pivot rows. Only **special** holidays may be branch-scoped; a `type=regular` holiday with `branch_ids` in the request fails validation ("Regular holidays always apply to every branch.") — regular holidays are always nationwide.

`Holiday::forDate(string $date, ?int $branchId = null)` carries the branch filter. **`$branchId = null` means "nationwide only," never "no filter"** — an employee with no branch never inherits another city's branch-local holiday. `Holiday::isNationwide()`, `appliesToBranch(?int $branchId)`, and the shared query predicate `applyBranchScope()` implement the same "nationwide OR this branch" rule for, respectively, an already-loaded model, an already-loaded model when filtering a batch, and a query builder.

More than one `Holiday` row can now match the same date+branch (e.g. a nationwide regular holiday colliding with a branch-local special one). `forDate()` picks exactly **one**, in this tiebreak order:

1. **REGULAR before SPECIAL** — regular pays more (200%/260% vs. 130%/150%), so ties never shortchange the employee.
2. **Branch-scoped before nationwide** — the more specific declaration wins when both rows are the same type.
3. **Exact date before recurring** — a concrete row entered for this year outranks a generic month+day rule.
4. **`id` ascending** as the final stable tiebreak.

This deliberately does **not** implement "double holiday" pay (regular + special stacking to 300%) — that's unchanged from before branch scoping, not a regression.

**Seeding / year rollover.** `Holiday::defaultsForYear($year)` is the canonical Philippine calendar; `Holiday::seedYear($year)` persists it idempotently (existing rows untouched) and returns the count created. Fixed-date holidays are seeded `recurring` (so `forDate()` resolves them by month+day even in an unseeded year); the movable **National Heroes Day** (last Monday of August) is emitted as a concrete, non-recurring row per year. `HolidaySeeder` seeds the current year; run `php artisan holidays:seed {year}` to seed a future year (also prints a reminder that proclamation-based movable holidays — Eid'l Fitr, Eid'l Adha, Chinese New Year — must be added manually once proclaimed). Both `HolidaySeeder` and `Holiday::seedYear()`'s `firstOrCreate` lookups are constrained to nationwide rows (`whereDoesntHave('branches')`) — without that, a branch-local holiday sharing the same date+type would satisfy the match and silently prevent the national one from ever being created. `GenerateDemoPayrollData::createHoliday()` applies the same constraint for its demo Independence Day row.

**Landmine:** deleting a Branch cascades away its `branch_holiday` pivot rows. A holiday whose *only* scope was that branch silently becomes nationwide (no pivot rows left = `isNationwide()`). Accepted as-is since branch deletion is rare and admin-only, but worth knowing if a holiday appears to "spread" after a branch is removed.

**Management UI.** `/payroll/holidays` (nav: **Management**) lets **superadmin and admin** add/edit/delete holidays (`HolidayPolicy`); staff cannot. Note the consequence of that policy: an admin can scope a holiday to a branch other than their own — `HolidayPolicy` doesn't restrict by branch. The list sorts **upcoming holidays first** (today counts as upcoming), with already-passed holidays sunk below and tagged **Passed**; dates render as `Month Day` (e.g. `August 21`). Each row shows a **Branches** column — a "Nationwide" badge for unscoped holidays, or up to two branch-name badges plus a `+N` overflow badge. The add/edit dialog shows a branch multi-select ("Applies to", default: leave unchecked for nationwide) only when **Type** is set to `special`; switching the type away from `special` clears any selected branches client-side so a stale selection can't survive the switch.

Creating, updating, or deleting a holiday goes through `HolidayService` (transactional, `Auditable` — `holiday_created` / `holiday_updated` / `holiday_deleted`). Each write **auto-reprocesses unlocked attendance sheets** on the affected dates/branches so holiday pay appears or disappears immediately, without waiting for the next natural reprocess:

- On update, it reprocesses the **union of the old and new scope** — so narrowing a holiday's branches, or moving its date, correctly clears the sheets it no longer covers (it skips the second pass entirely if old and new scope are identical).
- **Locked sheets are never touched** (attendance inside a generated payroll period is immutable), and reprocessing never creates a new sheet — it only recomputes ones that already exist.
- For a `recurring` holiday, the reprocess only looks back **one year** from today, since there's no queue in this repo (no `app/Jobs`, no worker) and the work runs synchronously in the request — an unbounded fan-out across every past unlocked sheet was judged unsafe.

### 2.15 `sss_contribution_brackets`

| Column                     | Type          | Notes                       |
| -------------------------- | ------------- | --------------------------- |
| `id`                       | bigint PK     |                             |
| `salary_min`               | decimal(12,2) |                             |
| `salary_max`               | decimal(12,2) | nullable. NULL = unbounded. |
| `employee_percentage`      | decimal(5,2)  | Default 5                   |
| `employer_percentage`      | decimal(5,2)  | Default 10                  |
| `effective_from`           | date          |                             |
| `created_at`, `updated_at` | timestamps    |                             |

### 2.16 `company_configurations`

| Column                     | Type        | Notes                                      |
| -------------------------- | ----------- | ------------------------------------------ |
| `id`                       | bigint PK   |                                            |
| `key`                      | string(100) | Unique. e.g., `philhealth_premium_percent` |
| `value`                    | text        |                                            |
| `label`                    | string(200) | Human-readable label for admin UI          |
| `created_at`, `updated_at` | timestamps  |                                            |

### 2.17 `fines`

| Column                     | Type          | Notes              |
| -------------------------- | ------------- | ------------------ |
| `id`                       | bigint PK     |                    |
| `employee_id`              | foreignId     |                    |
| `date`                     | date          |                    |
| `fine_type`                | string(50)    | e.g., `no_uniform` |
| `amount`                   | decimal(10,2) |                    |
| `note`                     | string(500)   | nullable           |
| `marked_by`                | foreignId     | FK to users        |
| `created_at`, `updated_at` | timestamps    |                    |

Index: `[employee_id, date]`

### 2.18 `benefits`

| Column                          | Type                    | Notes                                      |
| ------------------------------- | ----------------------- | ------------------------------------------ |
| `id`                            | bigint PK               |                                            |
| `name`                          | string(100)             | e.g., "SSS", "PhilHealth", "Rice Subsidy"  |
| `type`                          | string(50)              | `statutory` or `perk`                      |
| `description`                   | text, nullable          |                                            |
| `employer_contribution_percent` | decimal(5,2), nullable  |                                            |
| `employee_contribution_percent` | decimal(5,2), nullable  |                                            |
| `employer_contribution_cap`     | decimal(12,2), nullable |                                            |
| `employee_contribution_cap`     | decimal(12,2), nullable |                                            |
| `is_active`                     | boolean                 | Default `true`                             |
| `monthly_amount`                | decimal(10,2), nullable | Flat monthly amount for perk-type benefits |
| `is_taxable`                    | boolean                 | Default `true`                             |
| `payslip_label`                 | string(100), nullable   | Display name on payslip                    |
| `created_at`, `updated_at`      | timestamps              |                                            |

### 2.19 `benefit_employee` (pivot)

| Column                     | Type                    | Notes                                 |
| -------------------------- | ----------------------- | ------------------------------------- |
| `id`                       | bigint PK               |                                       |
| `benefit_id`               | foreignId               | FK to benefits, cascade delete        |
| `employee_id`              | foreignId               | FK to employees, cascade delete       |
| `member_number`            | string(50), nullable    |                                       |
| `effective_date`           | date                    |                                       |
| `end_date`                 | date, nullable          | NULL = active                         |
| `custom_employer_cap`      | decimal(12,2), nullable |                                       |
| `custom_employee_cap`      | decimal(12,2), nullable |                                       |
| `is_active`                | boolean                 | Default `true`                        |
| `custom_monthly_amount`    | decimal(10,2), nullable | Override the benefit's monthly_amount |
| `created_at`, `updated_at` | timestamps              |                                       |

Unique index: `[benefit_id, employee_id, effective_date]`

### 2.20 `projects`

| Column                     | Type                    | Notes                              |
| -------------------------- | ----------------------- | ---------------------------------- |
| `id`                       | bigint PK               |                                    |
| `name`                     | string(255)             |                                    |
| `description`              | text, nullable          |                                    |
| `start_date`               | date                    |                                    |
| `end_date`                 | date, nullable          |                                    |
| `budget`                   | decimal(12,2), nullable |                                    |
| `status`                   | string(20)              | `active`, `completed`, `cancelled` |
| `created_at`, `updated_at` | timestamps              |                                    |

### 2.21 `employee_project` (pivot)

| Column                     | Type           | Notes           |
| -------------------------- | -------------- | --------------- |
| `id`                       | bigint PK      |                 |
| `employee_id`              | foreignId      | FK to employees |
| `project_id`               | foreignId      | FK to projects  |
| `assigned_at`              | date           |                 |
| `ended_at`                 | date, nullable |                 |
| `created_at`, `updated_at` | timestamps     |                 |

Unique index: `[employee_id, project_id, assigned_at]`

### 2.22 `audit_logs`

| Column       | Type                | Notes                                            |
| ------------ | ------------------- | ------------------------------------------------ |
| `id`         | bigint PK           |                                                  |
| `user_id`    | foreignId, nullable | FK to users                                      |
| `branch_id`  | foreignId, nullable | Branch context                                   |
| `action`     | string(50)          | `created`, `updated`, `deleted`, `rehired`, etc. |
| `model_type` | string(255)         | FQN of affected model                            |
| `model_id`   | unsignedBigInteger  | Primary key of affected record                   |
| `before`     | json, nullable      | Old values of changed fields                     |
| `after`      | json, nullable      | New values of changed fields                     |
| `ip_address` | string(45)          | Request IP                                       |
| `user_agent` | string(500)         | Browser user agent                               |
| `created_at` | timestamp           | No updated_at                                    |

Indexes: `[model_type, model_id]`, `action`, `user_id`, `branch_id`

---

## 3. Business Rules

### 3.1 Base Formula

```
hourly_rate = daily_rate / 8
monthly_salary = daily_rate × 26  (for government deduction computation only)
```

### 3.2 Late Deduction — 2-Tier System (No Grace Period)

| Late Minutes | Deduction Formula                                    | Example (daily = ₱510, hourly = ₱63.75) |
| ------------ | ---------------------------------------------------- | --------------------------------------- |
| 0            | ₱0                                                   | ₱0                                      |
| 1–20         | `late_min × ₱5`                                      | 15 min → ₱75                            |
| 21+          | `(20 × ₱5) + ((late_min − 20) × (hourly_rate / 60))` | 25 min → ₱100 + (5 × 1.0625) = ₱105.31  |

**Key**: First 20 minutes are charged at ₱5 per minute (flat rate). Minutes beyond 20 are charged at `hourly_rate / 60` per minute (pro-rated).

### 3.3 Daily Wage Formula

```
base_pay = (isPresent && !isOtOnlyDay) ? daily_rate : 0
  - Paid hours are capped at the paid end (max ~8h); work beyond it is paid
    only via an approved OT request.
  - A worked rest day (incl. Sunday) is paid EXACTLY like a regular working
    day — flat daily_rate, same 8h cap, same late/undertime/half-day/no-break
    rules, OT only via request. No premium. The one difference: NOT working a
    rest day is not an absence (no sheet, no deduction).
  - isOtOnlyDay: the day's ONLY punches are OVERTIME_IN/OVERTIME_OUT — no
    IN, OUT, LUNCH_OUT or LUNCH_IN at all (e.g. a rest-day call-in worked
    purely on overtime). No regular shift was worked, so there is no flat
    day to pay — the day earns the OT premium only. Any other incomplete
    shape (e.g. a punch-out with no punch-in) still keeps the full flat
    daily_rate exactly as before — this exception is scoped narrowly to
    "no regular punch of any kind."

daily_wage = base_pay − late_deduction − undertime_deduction − fine_deduction + overtime_pay + holiday_pay
  floor: 0
```

**Rest day vs. regular day**: identical for pay when worked; a rest day is simply a
_non-mandatory_ day, so skipping it never creates an absence. (Holiday pay still
treats a rest day specially — see §Holiday — that is the only remaining place
rest-day status affects pay.)

**OT-only day example (daily_rate = ₱600, hourly_rate = ₱75)**: `OVERTIME_IN` 19:44,
`OVERTIME_OUT` 00:20 the next calendar date (see the midnight rollover + bounded
lookahead below), no other punches that day. `overtime_minutes = 276` (4.6h),
`hours_worked = 0` (unchanged meaning — see §3.4's `hours_worked` note),
`base_pay = 0`, `daily_wage = 4.6 × 75 × 1.25 = ₱431.25`. The sheet is **not**
flagged incomplete — a matched OT pair with no regular punches is a complete
call-in day, not "No punch-in recorded".

### 3.4 Overtime Pay — Labor Law Multipliers

OT uses fixed Philippine labor law multipliers. No admin-configurable flat amounts, no rounding blocks.

```
hourly_rate = daily_rate / 8
ot_pay = ot_hours × hourly_rate × multiplier
```

**Source of OT minutes — punches are primary, an OT request is only the fallback.**
`AttendanceService::processDailyAttendance` computes `ot_worked_minutes` directly from the
day's `OVERTIME_IN` → `OVERTIME_OUT` punch diff whenever both punches exist for that date. An
approved `OvertimeRequest` (`getApprovedMinutes()` = its own `start_time`/`end_time` diff) is
used **only** when no OT punch pair exists that day — it is a fallback, not a cap on the punch
diff, and there is no "lower-of-two" reconciliation between the two sources. There is also no
60-minute floor: any positive punch diff (or approved-request duration) pays.

**Midnight rollover.** An `OVERTIME_OUT` punch is stamped with the shift's own calendar date
even when the employee actually punches out after midnight (e.g. OT in 19:58, OT out 01:15, both
recorded against the same date). If the OT-out timestamp is earlier in the day than the OT-in
timestamp, it's rolled forward one day before diffing — so 19:58 → 01:15 reads as 5h17m (317
minutes), not the 18h43m an unadjusted same-day diff would produce.

**Bounded 06:00 next-day lookahead.** Some punch clocks instead stamp the closing punch with the
*actual* next calendar date (e.g. OT in Sunday 19:44, OT out Monday 00:20 — two different `date`
values, not the same-date encoding the rollover above handles). When a day's `OVERTIME_IN` has no
same-date `OVERTIME_OUT`, `AttendanceService` looks for one on `date + 1` stamped before **06:00**
and, if found, treats it as this day's overtime-out (06:00 is safe because the default schedule
starts at 08:00 — no legitimate next-day shift begins that early). The exclusion is symmetric: the
day that actually owns that early punch (`date + 1`) drops it from its own punch set first, so it
is never *also* read as that day's own orphan overtime-out (which would otherwise double-count the
minutes and falsely flag `date + 1` with "Overtime punch-in missing"). `PayrollPeriodService`
mirrors both the lookahead and the exclusion for its period-level "Check Payroll" warnings, using
the same `TimeLog` batch already fetched for `findIncompleteSheets()` (widened — see below) so no
query is added per row. The cutoff is a class constant
(`AttendanceService::NEXT_DAY_OVERTIME_LOOKAHEAD_CUTOFF`, mirrored in `PayrollPeriodService`) —
keep the two in sync if it's ever changed.

**Consecutive midnight-crossing nights — a carry-over is never mistaken for a same-day close.**
The exclusion above needs to know whether yesterday's `OVERTIME_IN` was closed *by yesterday itself*
or is still waiting on today's early punch. Naively, "yesterday has its own close" was checked with
a raw "does yesterday have any `OVERTIME_OUT` at all" query — which breaks the moment yesterday's
own `OVERTIME_OUT` is itself a carry-over from the day *before* yesterday:

```
D1 22:00  OVERTIME_IN
D2 01:00  OVERTIME_OUT   ← closes D1, not "D2's own"
D2 22:00  OVERTIME_IN
D3 02:00  OVERTIME_OUT   ← closes D2, not "D3's own"
```

Processing D3: D3's early `OVERTIME_OUT` (02:00) needs D2 to be checked. D2 *does* have an
`OVERTIME_OUT` on record (01:00) — but that one belongs to D1, not D2. Trusting the raw existence
check would treat D2 as already closed, so D3's punch would never be excluded and D3 would be
flagged with a spurious "Overtime punch-in missing" (pay is unaffected either way — no minutes are
double-counted — but the sheet sits under a permanent, wrong review flag). The fix: "yesterday has
its own close" additionally checks whether yesterday's `OVERTIME_OUT` is itself an early (pre-06:00)
punch AND the day *before* yesterday has an `OVERTIME_IN` — if both hold, that punch is a
carry-over, not yesterday's own, and the exclusion still fires. One day further back is sufficient
(no recursion): each day's check only ever needs to know about the immediately preceding `OVERTIME_IN`.
`PayrollPeriodService::findIncompleteSheets()` widens its batch `TimeLog` window to **two** days
before `periodStart` (one after `periodEnd`, unchanged) so this one-day-further-back check resolves
in memory for the period's first sheet too, with no query added per row.

**Sanity cap (`max_overtime_minutes`).** Because the rollover can't distinguish "OT that crossed
midnight" from "a mis-stamped punch," any resulting span longer than `max_overtime_minutes`
(config `payroll.max_overtime_minutes`, env `PAYROLL_MAX_OVERTIME_MINUTES`, default `720` —
resolved through `PayrollSettingService` first, same pattern as `half_day_threshold_minutes`) is
treated as implausible: `overtime_minutes` still stores the raw computed span for a reviewer to
see, but `overtime_pay` is forced to `0`, `overtime_multiplier` to `null`, and the sheet is
flagged `is_incomplete = true` with a reason ending in `"verify punches"` (an earlier incomplete
reason, e.g. a missing lunch punch, is never overwritten by this one).
`PayrollPeriodService::findIncompleteSheets()` applies the same rollover + cap check against the
already-fetched punches so an over-cap day surfaces in the period's Check Payroll report too, not
only on the individual attendance sheet.

**`is_incomplete` for an OT-only day.** A day whose only punches are a MATCHED
`OVERTIME_IN`/`OVERTIME_OUT` pair (see the `isOtOnlyDay` case in §3.3) is a complete call-in day —
it is **not** flagged `"No punch-in recorded"` even though there is no regular `IN` punch.
Incomplete is only raised for an OT-only day when the pair is broken (one side missing — reason
`"Overtime punch-out missing"` / `"Overtime punch-in missing"`) or the `max_overtime_minutes` cap
above trips. A day with genuinely zero punches at all (no OT punches either) still reports
`"No punch-in recorded"` as before.

**`hours_worked` is unaffected by overtime, by design.** `hours_worked` keeps its existing
in→out-only meaning; it is never widened to include OT minutes. An OT-only day therefore reads
`hours_worked = 0` with the worked time entirely in `overtime_minutes` — this is intentional, not a
bug. (Folding OT into `hours_worked` would risk mislabeling a day as a "half day" — see the
`hours_worked <= 4.5` heuristic in `resources/js/pages/payroll/work-week/components/day-cell.tsx`.)

| Day Type                         | OT Multiplier                                    |
| -------------------------------- | ------------------------------------------------ |
| Ordinary working day             | **1.25x**                                        |
| Rest day (incl. Sunday), worked  | **1.25x** — treated exactly like an ordinary day |
| Special non-working day (worked) | **1.69x**                                        |
| Regular holiday (worked)         | **2.60x**                                        |

A rest day that also falls on a holiday takes the holiday multiplier (the rest-day component no longer adds a premium).

**Examples (daily_rate = ₱510, hourly_rate = ₱63.75)**:

| Day Type     | OT Hours | Computation          | OT Pay  |
| ------------ | -------- | -------------------- | ------- |
| Ordinary day | 2.0      | `2 × 63.75 × 1.25`   | ₱159.38 |
| Ordinary day | 1.5      | `1.5 × 63.75 × 1.25` | ₱119.53 |
| Rest day     | 2.0      | `2 × 63.75 × 1.25`   | ₱159.38 |
| Reg. holiday | 1.0      | `1 × 63.75 × 2.60`   | ₱165.75 |

**No rate snapshot at approval time**: the multiplier is determined at computation time, not when a request is approved. When OT minutes come from the punch diff (the primary path), the multiplier is always the ordinary-day **1.25x** — punches carry no `shift_type`. Only the OT-request fallback path picks a multiplier from `shift_type` on the approved request (`regular_day`, `rest_day`, `special_holiday`, etc., per the table above).

### 3.5 Lunch — 4-Punch Model

```
morning_work   = LUNCH_OUT − IN
afternoon_work = cap(OUT, schedule_end) − LUNCH_IN
actual_lunch   = LUNCH_IN − LUNCH_OUT (measured, not assumed)
```

| actual_lunch | Impact                               |
| ------------ | ------------------------------------ |
| ≤ 60 min     | No penalty                           |
| > 60 min     | Excess minutes deducted as undertime |

**Fallback (missing lunch punches)**: If LUNCH_OUT or LUNCH_IN is missing:

- 60 minutes deducted if raw duration ≥ 5 hours AND work period overlaps 11:00 AM – 2:00 PM
- Otherwise 0 deduction

### 3.6 Full Scenario Matrix (daily_rate = ₱510)

| Scenario                       | Late | Worked | Computation    | daily_wage  |
| ------------------------------ | ---- | ------ | -------------- | ----------- |
| On time, full day              | 0    | 8h     | `510`          | **₱510.00** |
| On time, no uniform (₱20 fine) | 0    | 8h     | `510 − 20`     | **₱490.00** |
| Late 10 min                    | 10   | 7.83h  | `510 − 50`     | **₱460.00** |
| Late 15 min                    | 15   | 7.75h  | `510 − 75`     | **₱435.00** |
| Late 20 min                    | 20   | 7.67h  | `510 − 100`    | **₱410.00** |
| Late 25 min                    | 25   | 7.58h  | `510 − 105.31` | **₱404.69** |
| Late 45 min                    | 45   | 7.25h  | `510 − 126.56` | **₱383.44** |
| Late 60 min (1h)               | 60   | 7h     | `510 − 142.50` | **₱367.50** |
| Late 90 min (1.5h)             | 90   | 6.5h   | `510 − 174.38` | **₱335.62** |
| Late 120 min (2h)              | 120  | 6h     | `510 − 206.25` | **₱303.75** |
| Late 150 min (2.5h)            | 150  | 5.5h   | `510 − 238.13` | **₱271.87** |
| Late 180 min (3h)              | 180  | 5h     | `510 − 270.00` | **₱240.00** |
| Not late, left early (5h)      | 0    | 5h     | `63.75 × 5`    | **₱318.75** |
| Not late, left early (2h)      | 0    | 2h     | `63.75 × 2`    | **₱127.50** |

### 3.7 Holiday Pay

| Holiday Type | Worked? | Day-Before Status   | Pay Percent | Label                |
| ------------ | ------- | ------------------- | ----------- | -------------------- |
| Regular      | Yes     | —                   | 200%        | `Holiday Pay (200%)` |
| Regular      | No      | Present or on Leave | 100%        | `Holiday Pay (100%)` |
| Regular      | No      | Absent (unexcused)  | 0%          | Not shown            |
| Special      | Yes     | —                   | 130%        | `Holiday Pay (130%)` |
| Special      | No      | —                   | 0%          | Not shown            |

**Day-before lookback for regular unworked holidays**: Walk backward up to 14 days, skipping rest days, Sundays, and other holidays. Check the last working day's attendance sheet.

**Recurring holidays**: Holidays with `recurring=true` match month+day regardless of year.

**Branch scoping**: the holiday lookup (`Holiday::forDate($date, $employee->branch_id)`) is now branch-scoped — an employee only receives holiday pay for a holiday that is nationwide or explicitly scoped to their branch. The pay percentages above are unchanged; scoping only affects *whether* a holiday is found for that employee's date, not how much it pays once found. See §2.14 for the full tiebreak when a nationwide and a branch-local holiday both match the same date.

### 3.8 Government Deductions

Computed on **regular monthly salary (daily_rate × 26)**, NOT on variable attendance earnings. Same amount every week regardless of absences or OT.

#### SSS — Bracket-Based

```
monthly_salary = daily_rate × 26
bracket = find bracket where salary_min ≤ monthly_salary AND (salary_max IS NULL OR salary_max ≥ monthly_salary)
sss_weekly = (monthly_salary × bracket.employee_percentage / 100) / 4
```

- Table: `sss_contribution_brackets` — managed by superadmin
- Per-benefit conditional: only deducted if `sss_number` is filled

#### PhilHealth — Percentage-Based (50/50 Split)

```
phic_weekly = (daily_rate × 26 × premium_percent / 100 × 0.50) / 4
```

- Default premium: 5%, configurable in `company_configurations` key `philhealth_premium_percent`
- 50% employer / 50% employee split (hardcoded)
- Per-benefit conditional: only deducted if `philhealth_number` is filled

#### Pag-IBIG — Flat Amount

```
pagibig_weekly = monthly_employee_share / 4
```

- Default: ₱100/month, configurable in `company_configurations` key `pagibig_monthly_employee_share`
- Per-benefit conditional: only deducted if `pagibig_number` is filled

### 3.9 De Minimis Benefits (Non-Taxable Perks)

De minimis benefits are employer-provided, non-taxable perks added as earnings on payslips.

**Stored in `benefits` table with `type = 'perk'`**, assigned to employees via `benefit_employee` pivot.

```
weekly_amount = (custom_monthly_amount ?? benefit.monthly_amount) / 4
deminimis_earnings = sum of all active de minimis benefits for the period
```

**Qualification**: Employee must be present at least 1 day in the payroll period.

**Net pay formula with de minimis**:

```
gross_pay        = sum(daily_wage) + overtime_pay + holiday_pay
deminimis        = sum of de minimis benefits for period
total_gross      = gross_pay + deminimis

govt_deductions  = sss + philhealth + pagibig
net_pay          = total_gross − govt_deductions − ca_deduction
```

- Not subject to government deductions (SSS/PhilHealth/Pag-IBIG base remains `daily_rate × 26`)
- Configurable per-employee via `custom_monthly_amount`
- Filtered by `end_date IS NULL OR end_date > period_end`

### 3.10 Cash Advances

- **Maximum CA** = projected net receivable for current payroll period
- **One active CA at a time**: blocked if `remaining_balance > 0`
- **No interest**
- **Deducted from net pay**: after government contributions
- **Balance carries over**: if net_pay < remaining_balance, deduct all available, remainder to next period
- **Net pay ≥ 0**: deduction capped so net never goes negative
- **Deducted at generation time**: a cash advance is applied when the payroll period is generated. One granted _after_ a draft was generated is not reflected until the draft is **recomputed** (`payroll.periods.recompute`) or the period is regenerated. Approved/paid periods are locked; a later advance carries to the next period.

```
ca_deduction = min(remaining_balance, net_pay_before_ca)
```

### 3.11 Leave Rules

- **5 paid leaves per year**, tracked via employee balance
- **Balance resets January 1**
- **Admin discretion beyond 5**: System shows warning but does not block
- **Unpaid leave** does not count against the balance

**Leave Blending**:

| Leave Duration    | Actual Worked  | Result                                           |
| ----------------- | -------------- | ------------------------------------------------ |
| Full day (paid)   | Any (incl. 0)  | Full day credited, 100% paid                     |
| Full day (unpaid) | Any            | Hours credited, daily_wage = 0 for leave portion |
| Half-day AM       | Afternoon ≥ 4h | Full day (4h leave + 4h+ worked)                 |
| Half-day AM       | Afternoon < 4h | Leave 4h credited. Worked = proportional pay.    |
| Half-day PM       | Morning ≥ 4h   | Full day (4h+ worked + 4h leave)                 |
| Half-day PM       | Morning < 4h   | Leave 4h credited. Worked = proportional pay.    |

Leave types: `vacation`, `sick`, `emergency`, `maternity`, `paternity`, `bereavement`, `unpaid`

**Deleting a leave** (`DELETE /payroll/leave-requests/{lr}`, `leaves.destroy`, admins/superadmin only):

Use this when a leave was granted but will not be used — the employee changed their mind, or
was on leave for a date they actually came in and worked. Only `pending` and `approved` leaves
are deletable (`denied`/`cancelled` are rejected). The delete is a hard delete, which frees the
`unique(employee_id, date)` slot so the day can be re-requested.

On delete:

- **Balance refund** — if the leave was `approved` **and** `is_paid`, `paid_leave_balance` is
  incremented by 1 (mirrors `deny`). Pending or unpaid leaves change no balance.
- **Attendance reprocessed** — for an approved leave, `AttendanceService::processDailyAttendance`
  is re-run for the date so the day recomputes from the actual punches (real worked wage if the
  employee clocked in, absent if not) and the `leave_*` columns are cleared. Punches
  (`time_logs`) are never touched by the delete. This is what makes the "on leave but worked"
  case pay correctly. There is no date restriction — a past-dated leave can be deleted as long
  as the date's sheet is not yet locked.
- **Locked-period guard** — deleting an approved leave whose attendance sheet is locked inside a
  generated payroll period is refused (that pay is finalized; refunding a spent credit there
  would corrupt a closed period). Mirrors the `approve` lock guard.

### 3.12 Fines

- Per-day flat fines for policy violations (e.g., ₱20 for no uniform)
- Multiple fine types can be stacked in one day
- Admin marks violation on employee's daily record → triggers `processDailyAttendance()` recomputation

### 3.13 Employee Login Block (Deactivation)

| Status       | Login? | Description                                                                    |
| ------------ | ------ | ------------------------------------------------------------------------------ |
| `active`     | Yes    | Employee can use self-service punch via linked user                            |
| `inactive`   | No     | Temporarily deactivated (suspension, long leave). Can be reactivated directly. |
| `resigned`   | No     | Voluntary departure. `end_date` populated. Requires rehire to reactivate.      |
| `terminated` | No     | Involuntary separation. Requires rehire to reactivate.                         |

**Login block flow**: Fortify `authenticateUsing` callback checks `$user->canLogin()` which verifies:

1. If `user->employee_id` is null → allowed (unlinked user)
2. If employee is soft-deleted → blocked
3. If employee `status !== 'active'` → blocked

**Self-deactivation blocked**: Admin cannot set their own employee record to non-active status.

### 3.14 Payroll Period Generation Flow

1. Admin clicks **Generate Payroll Period** (available Saturday after shift)
2. System auto-selects completed work week (Mon–Sat)
3. `PayrollPeriodService` runs within transaction:
    - Locks all `attendance_sheets` in date range (`locked_at` set)
    - Creates `payroll_period` record (`status=draft`)
    - For each ACTIVE non-superadmin employee in branch:
        - Aggregates sheets into `payroll_period_item`
        - Computes: gross_pay, late/UT/OT, holiday pay, leave days, fines
        - Computes de minimis benefits (requires ≥1 day present)
        - Computes government deductions (SSS bracket lookup, PhilHealth %, Pag-IBIG flat)
        - Computes cash advance deduction
        - Stores `net_pay = gross_pay + deminimis − sss − philhealth − pagibig − ca`
4. Admin reviews → clicks **Approve**
5. Period `status` → `approved` (superadmin approval required)

**Void Flow (superadmin only)**:

1. Superadmin clicks **Void Period**
2. All sheets unlocked (`locked_at = null`)
3. Period `status` → `voided`
4. Corrections can be made → re-generate → re-approve

### 3.15 Employee Number Generation

```
Format: EMP-{YEAR}-{NNNN}
Sequence: Max existing number for current year + 1, padded to 4 digits
```

### 3.16 Salary History Pattern

```
Salary::createForEmployee(employee, dailyRate, effectiveDate, notes?):
  1. Find current salary for employee (end_date IS NULL)
  2. Set its end_date = effectiveDate (or now() if same date)
  3. Create new salary record with new daily_rate and effective_date
  4. Update employee.current_daily_rate

When employee is rehired:
  1. Set employee.status = 'active', employee.end_date = null
  2. Create new salary with new daily_rate and rehire_date
```

---

## 4. API Routes

All payroll routes are under `/payroll` prefix with `payroll.` name prefix. All require authentication.

### Dashboard

| Method | URI        | Name            | Auth |
| ------ | ---------- | --------------- | ---- |
| `GET`  | `/payroll` | `payroll.index` | Auth |

### Employees

| Method   | Path                                        | Name                              | Auth                 |
| -------- | ------------------------------------------- | --------------------------------- | -------------------- |
| `GET`    | `/payroll/employees`                        | `payroll.employees.index`         | Auth                 |
| `GET`    | `/payroll/employees/create`                 | `payroll.employees.create`        | Auth (admin+)        |
| `POST`   | `/payroll/employees`                        | `payroll.employees.store`         | Auth (admin+)        |
| `GET`    | `/payroll/employees/{employee}`             | `payroll.employees.show`          | Auth (branch-scoped) |
| `GET`    | `/payroll/employees/{employee}/edit`        | `payroll.employees.edit`          | Auth (branch-scoped) |
| `PUT`    | `/payroll/employees/{employee}`             | `payroll.employees.update`        | Auth (branch-scoped) |
| `DELETE` | `/payroll/employees/{employee}`             | `payroll.employees.destroy`       | Auth (superadmin)    |
| `POST`   | `/payroll/employees/{employee}/rehire`      | `payroll.employees.rehire`        | Auth (branch-scoped) |
| `POST`   | `/payroll/employees/{employee}/link-user`   | `payroll.employees.link-user`     | Auth (superadmin)    |
| `POST`   | `/payroll/employees/{employee}/unlink-user` | `payroll.employees.unlink-user`   | Auth (superadmin)    |
| `POST`   | `/payroll/employees/sync-user/{user}`       | `payroll.employees.sync-user`     | Auth (superadmin)    |
| `PUT`    | `/payroll/employee/profile`                 | `payroll.employee.profile.update` | Auth (self)          |

### Employee Schedules

| Method   | Path                                      | Name                                  | Auth               |
| -------- | ----------------------------------------- | ------------------------------------- | ------------------ |
| `POST`   | `/payroll/employees/{employee}/schedules` | `payroll.employees.schedules.store`   | Auth (same branch) |
| `PUT`    | `/payroll/employees/schedules/{schedule}` | `payroll.employees.schedules.update`  | Auth (same branch) |
| `DELETE` | `/payroll/employees/schedules/{schedule}` | `payroll.employees.schedules.destroy` | Auth (same branch) |

### Attendance (Time Logs)

| Method | Path                         | Name                        | Auth          |
| ------ | ---------------------------- | --------------------------- | ------------- |
| `GET`  | `/payroll/attendance`        | `payroll.attendance.index`  | Auth          |
| `POST` | `/payroll/attendance/punch`  | `payroll.attendance.punch`  | Auth (self)   |
| `POST` | `/payroll/attendance/manual` | `payroll.attendance.manual` | Auth (admin+) |

### Attendance Sheets

| Method | Path                                    | Name                              | Auth                 |
| ------ | --------------------------------------- | --------------------------------- | -------------------- |
| `GET`  | `/payroll/attendance-sheets`            | `payroll.attendance.sheets.index` | Auth (branch-scoped) |
| `GET`  | `/payroll/attendance-sheets/{employee}` | `payroll.attendance.sheets.show`  | Auth (branch-scoped) |

### Fines

| Method   | Path                    | Name                    | Auth          |
| -------- | ----------------------- | ----------------------- | ------------- |
| `POST`   | `/payroll/fines`        | `payroll.fines.store`   | Auth (admin+) |
| `DELETE` | `/payroll/fines/{fine}` | `payroll.fines.destroy` | Auth (admin+) |

### Holidays

| Method   | Path                          | Name                       | Auth              |
| -------- | ----------------------------- | -------------------------- | ----------------- |
| `GET`    | `/payroll/holidays`           | `payroll.holidays.index`   | Auth              |
| `POST`   | `/payroll/holidays`           | `payroll.holidays.store`   | Auth (admin+)     |
| `PUT`    | `/payroll/holidays/{holiday}` | `payroll.holidays.update`  | Auth (admin+)     |
| `DELETE` | `/payroll/holidays/{holiday}` | `payroll.holidays.destroy` | Auth (admin+)     |

### Payroll Periods

| Method | Path                                       | Name                        | Auth                      |
| ------ | ------------------------------------------ | --------------------------- | ------------------------- |
| `GET`  | `/payroll/periods`                         | `payroll.periods.index`     | Auth (admin+)             |
| `POST` | `/payroll/periods/generate`                | `payroll.periods.generate`  | Auth (admin+)             |
| `GET`  | `/payroll/periods/{period}`                | `payroll.periods.show`      | Auth (admin+)             |
| `POST` | `/payroll/periods/{period}/recompute`      | `payroll.periods.recompute` | Auth (admin+), draft only |
| `POST` | `/payroll/periods/{period}/approve`        | `payroll.periods.approve`   | Auth (superadmin)         |
| `POST` | `/payroll/periods/{period}/void`           | `payroll.periods.void`      | Auth (superadmin)         |
| `GET`  | `/payroll/periods/{period}/payslip/{item}` | `payroll.payslip`           | Auth (branch-scoped)      |
| `GET`  | `/payroll/my-payslip`                      | `payroll.my-payslip`        | Auth                      |

### Work Week Table

| Method | Path                       | Name                      | Auth          |
| ------ | -------------------------- | ------------------------- | ------------- |
| `GET`  | `/payroll/work-week`       | `payroll.work-week.index` | Auth (admin+) |
| `GET`  | `/payroll/work-week/print` | `payroll.work-week.print` | Auth (admin+) |

### Reports

| Method | Path                     | Name                    | Auth          |
| ------ | ------------------------ | ----------------------- | ------------- |
| `GET`  | `/payroll/reports`       | `payroll.reports.index` | Auth (admin+) |
| `GET`  | `/payroll/reports/print` | `payroll.reports.print` | Auth (admin+) |

### SSS Brackets

| Method   | Path                              | Name                           | Auth              |
| -------- | --------------------------------- | ------------------------------ | ----------------- |
| `GET`    | `/payroll/sss-brackets`           | `payroll.sss.brackets.index`   | Auth (superadmin) |
| `POST`   | `/payroll/sss-brackets`           | `payroll.sss.brackets.store`   | Auth (superadmin) |
| `PUT`    | `/payroll/sss-brackets/{bracket}` | `payroll.sss.brackets.update`  | Auth (superadmin) |
| `DELETE` | `/payroll/sss-brackets/{bracket}` | `payroll.sss.brackets.destroy` | Auth (superadmin) |

### Company Configuration

| Method | Path                      | Name                            | Auth              |
| ------ | ------------------------- | ------------------------------- | ----------------- |
| `GET`  | `/payroll/company-config` | `payroll.company.config.index`  | Auth              |
| `POST` | `/payroll/company-config` | `payroll.company.config.update` | Auth (superadmin) |

### Overtime Requests

| Method | Path                                      | Name                       | Auth                 |
| ------ | ----------------------------------------- | -------------------------- | -------------------- |
| `GET`  | `/payroll/overtime-requests`              | `payroll.overtime.index`   | Auth                 |
| `POST` | `/payroll/overtime-requests`              | `payroll.overtime.store`   | Auth                 |
| `POST` | `/payroll/overtime-requests/{ot}/approve` | `payroll.overtime.approve` | Auth (superior role) |
| `POST` | `/payroll/overtime-requests/{ot}/deny`    | `payroll.overtime.deny`    | Auth (superior role) |

### Leave Requests

| Method   | Path                                   | Name                     | Auth                 |
| -------- | -------------------------------------- | ------------------------ | -------------------- |
| `GET`    | `/payroll/leave-requests`              | `payroll.leaves.index`   | Auth                 |
| `POST`   | `/payroll/leave-requests`              | `payroll.leaves.store`   | Auth                 |
| `POST`   | `/payroll/leave-requests/{lr}/approve` | `payroll.leaves.approve` | Auth (superior role) |
| `POST`   | `/payroll/leave-requests/{lr}/deny`    | `payroll.leaves.deny`    | Auth (superior role) |
| `POST`   | `/payroll/leave-requests/{lr}/cancel`  | `payroll.leaves.cancel`  | Auth (owner/admin)   |
| `DELETE` | `/payroll/leave-requests/{lr}`         | `payroll.leaves.destroy` | Auth (superior role) |

### Correction Requests

| Method | Path                                        | Name                          | Auth                 |
| ------ | ------------------------------------------- | ----------------------------- | -------------------- |
| `GET`  | `/payroll/correction-requests`              | `payroll.corrections.index`   | Auth                 |
| `POST` | `/payroll/correction-requests`              | `payroll.corrections.store`   | Auth                 |
| `POST` | `/payroll/correction-requests/{cr}/approve` | `payroll.corrections.approve` | Auth (superior role) |
| `POST` | `/payroll/correction-requests/{cr}/deny`    | `payroll.corrections.deny`    | Auth (superior role) |

### Cash Advances

| Method | Path                                  | Name                            | Auth                 |
| ------ | ------------------------------------- | ------------------------------- | -------------------- |
| `GET`  | `/payroll/cash-advances`              | `payroll.cash-advances.index`   | Auth                 |
| `POST` | `/payroll/cash-advances`              | `payroll.cash-advances.store`   | Auth                 |
| `POST` | `/payroll/cash-advances/{ca}/approve` | `payroll.cash-advances.approve` | Auth (superior role) |
| `POST` | `/payroll/cash-advances/{ca}/deny`    | `payroll.cash-advances.deny`    | Auth (superior role) |

### Audit Logs

| Method | Path                  | Name                  | Auth                 |
| ------ | --------------------- | --------------------- | -------------------- |
| `GET`  | `/payroll/audit-logs` | `payroll.audit.index` | Auth (branch-scoped) |

---

## 5. RBAC & Authorization

### Roles

Roles live on `users.role`. Roles are: `staff`, `admin`, `superadmin`.

| Role         | Scope        | Key Abilities                                                                       |
| ------------ | ------------ | ----------------------------------------------------------------------------------- |
| `staff`      | Self only    | Punch, view own attendance/payslip, submit requests                                 |
| `admin`      | Branch only  | Manage branch employees, manual logs, approve staff requests, generate payroll      |
| `superadmin` | All branches | All admin abilities + approve payroll, void payroll, manage holidays, manage config |

### Branch Isolation

```
staff_1 (Branch A) → can see:     self only
staff_1 (Branch A) → cannot see:  staff_2 (Branch B)

admin_A (Branch A) → can see:     all employees in Branch A
admin_A (Branch A) → cannot see:  employees in Branch B (unless special group)

superadmin → can see:             all employees in all branches
```

### Special Group Branches

Branches in `config('company.special_group_branch_names')` share access. Admins from these branches can see/manage employees from all branches in the group.

### Superior-Only Approval Rule

```
Requestor → Approver
─────────────────────
Staff     → Admin (same branch) or Superadmin
Admin     → Superadmin (never self-approved)
```

### Policy Matrix

| Action                                        | Staff     | Admin                 | Superadmin                     |
| --------------------------------------------- | --------- | --------------------- | ------------------------------ |
| Punch IN/OUT                                  | Self only | Self only             | Self only                      |
| View own attendance                           | ✓         | ✓                     | ✓                              |
| View branch attendance                        | —         | Branch only           | All branches                   |
| Create manual time_log                        | —         | Branch employees only | All employees                  |
| Submit correction request                     | Self only | Branch + self         | All                            |
| Approve correction request                    | —         | Staff in branch       | All (incl. admin)              |
| Submit OT request                             | Self only | Self only             | Self only                      |
| Approve OT request                            | —         | Branch employees      | All                            |
| Submit leave request                          | Self only | Self only             | Self only                      |
| Approve leave request                         | —         | Branch employees      | All                            |
| Delete leave request                          | —         | Own + branch staff    | All                            |
| Request cash advance                          | Self only | Self only             | Self only                      |
| Approve cash advance                          | —         | Branch employees      | All                            |
| Manage employee schedules                     | —         | Branch employees      | All                            |
| Mark fine on employee                         | —         | Branch employees      | All                            |
| Manage fine types/amounts                     | —         | —                     | ✓                              |
| Generate payroll period                       | —         | Branch only           | All branches                   |
| Approve payroll period                        | —         | —                     | ✓                              |
| Void payroll period                           | —         | —                     | ✓                              |
| View payslip                                  | Own only  | Branch employees      | All                            |
| View employee profile                         | Own only  | Branch employees      | All                            |
| Create employee (onboarding)                  | —         | Branch only           | All branches                   |
| Update employee                               | —         | Branch only           | All branches                   |
| Deactivate employee                           | —         | Branch only           | All branches                   |
| Rehire employee                               | —         | Branch only           | All branches                   |
| Manage holidays                               | —         | ✓ (any branch)        | ✓                              |
| Edit company config (SSS/PhilHealth/Pag-IBIG) | —         | —                     | ✓                              |
| View audit logs                               | —         | Branch only           | All branches                   |
| View work week payroll table                  | —         | Branch only           | All (defaults to first branch) |

---

## 6. Edge Cases

### Punch-Related

| #   | Scenario                              | Behavior                                                                               |
| --- | ------------------------------------- | -------------------------------------------------------------------------------------- |
| E1  | Two IN punches within 5 min           | Earliest kept; later marked `duplicate_of`                                             |
| E2  | Two OUT punches within 5 min          | Same throttling                                                                        |
| E3  | Only IN, no OUT (day in progress)     | Sheet computed with estimated end; "Currently clocked in"                              |
| E4  | Only IN, no OUT (day closed)          | Marked unexcused absence; flagged for admin review                                     |
| E5  | Only OUT, no IN                       | Anomaly; 0 hours; admin review recommended                                             |
| E6  | Punch on rest day (no OT)             | Blocked: "Today is your rest day"                                                      |
| E7  | Punch on rest day (OT approved)       | Allowed; paid exactly like an ordinary working day (flat rate, 8h cap, OT via request) |
| E8  | Punch > 18 hours after schedule start | Logged but flagged as anomaly warning                                                  |

### Computation-Related

| #   | Scenario                        | Behavior                                                         |
| --- | ------------------------------- | ---------------------------------------------------------------- |
| E9  | Late 10 min                     | Deduction = 10 × ₱5 = ₱50                                        |
| E10 | Late 20 min                     | Deduction = 20 × ₱5 = ₱100                                       |
| E11 | Late 25 min                     | Deduction = 100 + (5 × 1.0625) = ₱105.31                         |
| E12 | Late 45 min                     | Deduction = 100 + (25 × 1.0625) = ₱126.56                        |
| E13 | Late 60 min (1 hour)            | Deduction = 100 + (40 × 1.0625) = ₱142.50                        |
| E14 | Late 90 min (1.5 hours)         | Deduction = 100 + (70 × 1.0625) = ₱174.38                        |
| E15 | Late 150 min (2.5 hours)        | Deduction = 100 + (130 × 1.0625) = ₱238.13                       |
| E16 | Not late, worked 5h             | Proportional: hourly_rate × 5                                    |
| E17 | Not late, worked 2h             | Proportional: hourly_rate × 2                                    |
| E18 | OT approved 3h, stayed 2h       | Lower-of-two: 120 min OT. Pay = 2 × hourly × 1.25 (regular day). |
| E19 | OT approved 1h, stayed 2.5h     | Lower-of-two: 60 min OT; 90 min discarded                        |
| E20 | OT approved but actual < 60 min | 0 OT awarded (minimum threshold)                                 |
| E21 | Schedule changed mid-week       | Uses schedule active on each date (effective_from/to)            |

### Holiday-Related

| #   | Scenario                      | Behavior                                    |
| --- | ----------------------------- | ------------------------------------------- |
| E22 | Holiday Mon, absent Fri       | Walk back → Friday, absent → 0% holiday pay |
| E23 | Holiday Tue, Mon also holiday | Skip Mon (holiday), check previous Fri      |
| E24 | Regular holiday, worked       | 200% pay regardless of day-before           |
| E25 | Special holiday, unworked     | 0% (no work, no pay)                        |
| E26 | Special holiday, worked       | 130% of daily rate                          |

### Leave Blending

| #   | Scenario                                | Behavior                                            |
| --- | --------------------------------------- | --------------------------------------------------- |
| E27 | Half-day AM leave + afternoon 4h        | Full day (4h leave + 4h worked)                     |
| E28 | Half-day AM leave + afternoon 3.5h      | 4h leave + 3.5h proportional = 7.5h                 |
| E29 | Half-day PM leave + morning 4h          | Full day (4h worked + 4h leave)                     |
| E30 | Full day unpaid leave + employee worked | Hours credited but daily_wage = 0 for leave portion |

### Fines

| #   | Scenario                     | Behavior                                          |
| --- | ---------------------------- | ------------------------------------------------- |
| E31 | No uniform, full day present | ₱20 fine deducted. daily_wage = ₱510 − ₱20 = ₱490 |
| E32 | No uniform + late 15 min     | ₱75 late + ₱20 fine = ₱415                        |
| E33 | Multiple fines in one day    | Stacked                                           |
| E34 | Fine amount configured to 0  | Effectively disabled                              |

### Government Contributions

| #   | Scenario                                    | Behavior                                          |
| --- | ------------------------------------------- | ------------------------------------------------- |
| E35 | Monthly salary on bracket boundary (₱4,250) | Falls in lower bracket                            |
| E36 | Monthly salary > highest bracket (₱21,000)  | Capped at highest bracket                         |
| E37 | SSS ID empty                                | SSS deduction skipped                             |
| E38 | PhilHealth ID empty                         | PhilHealth deduction skipped                      |
| E39 | Pag-IBIG ID empty                           | Pag-IBIG deduction skipped                        |
| E40 | PhilHealth percentage set to 0              | No deduction for any employee                     |
| E41 | Pag-IBIG share changed mid-month            | New amount applies next period                    |
| E42 | Employee daily rate changed (raise)         | Monthly salary recalculated; bracket re-looked up |

### De Minimis Benefits

| #   | Scenario                                            | Behavior                                                             |
| --- | --------------------------------------------------- | -------------------------------------------------------------------- |
| E43 | Employee assigned rice subsidy (₱2,000/month)       | ₱500/week added to earnings. Not subject to government deductions.   |
| E44 | Employee absent entire week, has de minimis benefit | De minimis skipped for this period (must be present at least 1 day). |
| E45 | Employee has custom de minimis amount (₱1,500)      | Overrides default. Weekly: ₱1,500 / 4 = ₱375.                        |
| E46 | Multiple de minimis benefits assigned               | All active perks summed. Each shown as separate line on payslip.     |
| E47 | Benefit end_date passed                             | Not included in period computation.                                  |

### Cash Advances

| #   | Scenario                       | Behavior                                            |
| --- | ------------------------------ | --------------------------------------------------- |
| E48 | Request CA > max receivable    | Blocked                                             |
| E49 | Request while CA still active  | Blocked: settle existing first                      |
| E50 | Net pay < remaining CA balance | Deduct all available; balance carries over; net ≥ 0 |
| E51 | CA fully deducted this period  | Status → `paid`; can request new CA                 |

### Employee Deactivation

| #   | Scenario                              | Behavior                                                      |
| --- | ------------------------------------- | ------------------------------------------------------------- |
| E52 | Admin deactivates employee (INACTIVE) | Linked user blocked from login. Attendance records unchanged. |
| E53 | Employee resigns (RESIGNED)           | Status set in edit form. Linked user blocked. `end_date` set. |
| E54 | Employee has no linked user           | Status change works normally. No login to block.              |
| E55 | Admin tries to deactivate own account | Blocked: "Cannot deactivate your own account."                |

### Concurrency & Integrity

| #   | Scenario                            | Protection                                            |
| --- | ----------------------------------- | ----------------------------------------------------- |
| E56 | Two admins approve same correction  | `lockForUpdate()` on correction row; second fails     |
| E57 | Payroll gen while sheet recomputing | Period gen locks sheets; recompute fails              |
| E58 | Correction for locked sheet         | Blocked: "Sheet locked in approved payroll period"    |
| E59 | Duplicate correction request        | Blocked: "Pending request exists"                     |
| E60 | Self-approval attempt               | Policy blocks: role must be strictly superior         |
| E61 | 200+ employee batch processing      | `chunk(50)`; individual failures don't rollback batch |

---

## 7. Payslip Design

### Header Section

```
┌──────────────────────────────────────────────────────────────────┐
│  PRINTING SHOP MANAGEMENT                                        │
│  Branch: Babak                                                   │
│                                                                  │
│  PAYSLIP — Weekly                                                │
│  Period: May 18–23, 2026 (Week 3 · Mon–Sat)                     │
│                                                                  │
│  Employee:  Juan Dela Cruz          Position:  Regular           │
│  Emp #:     EMP-2026-0001           Daily Rate: ₱510.00          │
│  SSS:       12-3456789-0            PhilHealth: 12-345678901-2   │
│  Pag-IBIG:  1234-5678-9012          TIN:        —                │
│  Monthly Salary: ₱13,260 (daily × 26)  ·  SSS Bracket #7         │
├──────────────────────────────────────────────────────────────────┤
│  Attendance Summary:  Present 5  Late 1 (15min)  OT 0h  Absent 0  Holiday 0 │
└──────────────────────────────────────────────────────────────────┘
```

### Body: Two-Column Layout (Earnings | Deductions)

```
┌──────────────────────────────┬──────────────────────────────────┐
│         EARNINGS             │           DEDUCTIONS              │
├──────────────────────────────┼──────────────────────────────────┤
│ Basic Pay         ₱2,868.75  │ Late (Tue, 15min × ₱5)  −₱75.00   │
│ Overtime (2h × 1.25x) ₱159.38│ Fine (No Uniform)      −₱20.00   │
│ Holiday Pay       —          │ SSS (5%)              −₱165.75   │
│ * Rice Subsidy    ₱500.00    │ PhilHealth (2.50%)     −₱82.88   │
│                              │ Pag-IBIG               −₱25.00   │
│                              │ Cash Advance           —          │
├──────────────────────────────┼──────────────────────────────────┤
│  GROSS PAY       ₱3,528.13   │  TOTAL DEDUCTIONS    −₱368.63    │
└──────────────────────────────┴──────────────────────────────────┘

                   * Non-taxable de minimis benefits

                          ┌───────────────────────┐
                          │  NET PAY  ₱3,159.50    │
                          └───────────────────────┘
```

### Footer

```
┌──────────────────────────────────────────────────────────────────┐
│  Generated: May 23, 2026 (Sat, after shift)   Period Status: Paid │
│                                                                  │
│  Employee: ____________________    Date: ___________________     │
│                                                                  │
│  Prepared by: _________________    Date: ___________________     │
│  Approved by: _________________    Date: ___________________     │
└──────────────────────────────────────────────────────────────────┘
```

### Design Rules

| Rule                | Detail                                                                                                                                                                                                                                 |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| IDs in header       | SSS, PhilHealth, Pag-IBIG, TIN displayed. Missing IDs shown as `—`.                                                                                                                                                                    |
| No daily rows       | Compact Attendance Summary line (Regular, Days Paid, Late, OT hours, Absent, Holiday).                                                                                                                                                 |
| Two-column body     | Earnings left (Basic Pay, OT, Holiday Pay, De Minimis perks). Deductions right (Late, Fines, Gov't, Cash Advance).                                                                                                                     |
| Basic Pay figure    | Back-solved from `gross_pay`, never `daily_rate × total_regular_days` — see §2.13 note. The "(Nd)" day count is the server-computed `basicPayDays` prop, not `total_regular_days` (which excludes worked holidays/rest days).           |
| OT visibility       | Shows hours worked × multiplier label (e.g., `2h × 1.25x` = `₱159.38`). Rates are labor law multipliers.                                                                                                                               |
| Holiday pay         | Shown as separate line item with percentage label (100%, 130%, 200%). 0% holidays not shown.                                                                                                                                           |
| De minimis benefits | Shown under Earnings with `*` prefix and "Non-taxable" footnote. Label from `payslip_label`. Prorated (÷4).                                                                                                                            |
| Deduction ordering  | Late + Fines first (behavioral), then statutory (SSS/PhilHealth/Pag-IBIG), then voluntary (Cash Advance).                                                                                                                              |
| Cash advance detail | Each cash advance deducted this period is itemized on its own line with the advance's reason (from the `cash_advance_deductions` ledger). When an advance is only partially settled, the remaining outstanding balance is shown below. |
| Missing gov't IDs   | Deduction line shown as `—` and no amount deducted.                                                                                                                                                                                    |
| Net pay ≥ 0         | Cash advance deduction capped so net pay never goes negative.                                                                                                                                                                          |
| Signature blocks    | Employee, preparer, and approver signature lines in footer.                                                                                                                                                                            |
| Contribution basis  | All government deductions computed on `daily_rate × 26`. NOT on variable attendance earnings.                                                                                                                                          |

---

## 8. Work Week Payroll Table

A read-only, live preview of payroll numbers for an arbitrary date range — an "Excel-style" scratch view admins/superadmins can use to sanity-check attendance, deductions, and net pay **before** committing to a formal `payroll.periods.generate` run. Unlike period generation, this page:

- Never creates a `PayrollPeriod` row.
- Never locks `attendance_sheets` (`locked_at` is never touched).
- Never mutates a `CashAdvance` balance — the Cash Advance column is a FIFO **preview** computed in memory, mirroring `PayrollPeriodService::computeCADeduction()`'s ordering/capping logic without calling `update()`.

### Filters

| Filter     | Admin                                                            | Superadmin                                                       |
| ---------- | ---------------------------------------------------------------- | ---------------------------------------------------------------- |
| Branch     | Locked to own branch, no picker                                  | Defaults to the alphabetically-first branch; can pick any branch |
| Date range | Defaults to last Saturday → this week's Friday (7 calendar days) | Same default, same picker                                        |

### Day-column contract

The grid shows **7 columns: Saturday, Sunday, Monday, Tuesday, Wednesday, Thursday, Friday** — the full payroll week in calendar order. A Sunday cell is usually a rest day (rendered with the rest-day glyph), and its activity (e.g. holiday work, approved OT) folds into the row and footer totals exactly like any other day. `dayColumns` is emitted by `WorkWeekTableController::dayColumns()` and the frontend `dayLabels` arrays (`index.tsx`, `print.tsx`) are paired to it by index.

### Day-cell status precedence

Evaluated in this exact order per `attendance_sheets` row:

| Order | Condition                    | Label                                         | Color        |
| ----- | ---------------------------- | --------------------------------------------- | ------------ |
| 1     | No sheet exists for the date | `—`                                           | Gray         |
| 2     | `is_rest_day = true`         | `Rest`                                        | Blue (muted) |
| 3     | `leave_type` is set          | `Leave`                                       | Purple       |
| 4     | `holiday_pay_percent > 0`    | `H`                                           | Blue         |
| 5a    | `is_present = true`          | `✔` (or `Half Day` when `hours_worked ≤ 4.5`) | Green        |
| 5b    | `is_present = false`         | `A`                                           | Red          |

Late (`late_minutes > 0`) and overtime (`overtime_minutes > 0`) are **annotations** on top of a Present cell, not separate exclusive states.

### Row & footer columns

Employee Name, Daily Rate, the 6 day cells, Total Fines, Total Late (mins), Total OT (hours), Holiday Count, Cash Advance (preview), SSS/PhilHealth/Pag-IBIG (same fixed per-week formula as §3.8, gated on the matching government ID being present), Total Deductions (SSS + PhilHealth + Pag-IBIG + CA preview — **not** late/undertime/fine, which are already inside `daily_wage`/gross per the invariant in §3.3), Gross Salary (`sum(daily_wage)` over the full range), Net Salary. Unlike a generated `PayrollPeriodItem`, this page does **not** compute de minimis benefits — a deliberate scope simplification for a scratch preview.

Footer totals (Gross Payroll, Total Deductions, Total Net Salary, Total Cash Advance, Total OT Hours, Total Holidays, Total Lates) are computed across **every** employee matching the branch filter, not just the visible page.

### Print behavior

"Print Payroll" opens a standalone route (`payroll.work-week.print`) in a new tab — the full, unpaginated employee set for the branch and range, rendered as one compact table with `@page`/`@media print` CSS (`break-inside: avoid` per row), following the same `window.print()` pattern as `payroll.reports.print`.

---

## 9. Incentive

An admin (or superadmin) can enter a per-day **incentive** amount directly on an employee's attendance sheet — e.g. a discretionary bonus for a specific day, separate from the unrelated monthly branch-manager `Incentive` model used elsewhere in the app.

- **Storage.** `attendance_sheets.incentive` (`decimal(10,2)`, default `0`). Set via `PATCH payroll/attendance-sheets/{employee}/incentive` (`payroll.attendance.incentive.update`), body `{ date, incentive }`. Non-staff only, gated by `attendance-sheets.show`, and audited (`incentive_updated`) via the `Auditable` trait.
- **Locked sheets are immutable.** If the attendance sheet for that date is already locked inside a generated payroll period (`locked_at` set), the update is rejected with a validation error — matching every other attendance mutation (punches, fines, corrections).
- **Flows into `daily_wage`.** `AttendanceService::processDailyAttendance` reads the sheet's existing `incentive` at the start of the run (before recomputing anything) and folds it back into the freshly-computed `daily_wage`, so the amount survives every reprocess (punch edits, correction approvals, OT/leave approvals, etc.) instead of being wiped out.
- **Flows into payroll automatically.** Because `daily_wage` is canonical and `gross_pay = SUM(daily_wage)`, an incentive already flows into `gross_pay` and `net_pay` with no extra math anywhere else. `PayrollPeriodService::generateItemForEmployee` additionally rolls the period's incentive total into `payroll_period_items.incentive` — a **display-only** column; it is not added a second time into `gross_pay`/`net_pay`.
- **Frontend.**
  - `payroll/attendance/sheet-detail.tsx` — a new "Incentive" card (above the Daily Wage card) shows the current value and, when editable and unlocked, a number input + Save button; the Daily Wage card also lists the incentive as its own line when non-zero.
  - `payroll/payroll/payslip.tsx` — an "Incentive" earnings line appears on the self-service/admin payslip when `item.incentive > 0`.
  - `payroll/reports/print.tsx` — the printable payslip report also shows an "Incentive" earnings row, and its `basicPay` derivation was updated to subtract `incentive` (in addition to `overtime_pay`/`holiday_pay`) so Basic isn't overstated now that `gross_pay` includes it.
- **Tests.** `tests/Feature/Payroll/AttendanceIncentiveTest.php` — incentive raises `daily_wage` by exactly its amount, survives reprocessing, rolls up into `PayrollPeriodItem.incentive`/`gross_pay`, and is rejected on a locked sheet.
