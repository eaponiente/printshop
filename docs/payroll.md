# Payroll System — Architecture & Implementation Spec

**Standalone reference for building a payroll system from scratch.**

---

## Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Database Schema](#2-database-schema)
3. [Business Rules](#3-business-rules)
4. [API Routes](#4-api-routes)
5. [RBAC & Authorization](#5-rbac--authorization)
6. [Edge Cases](#6-edge-cases)
7. [Payslip Design](#7-payslip-design)

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

| Trigger                            | What Runs                                      | Purpose                                                  |
| ---------------------------------- | ---------------------------------------------- | -------------------------------------------------------- |
| Per punch (event-driven)           | `processDailyAttendance()` for employee + date | Real-time status: late warnings, daily wage estimate, OT |
| Batch sweep (Saturday after shift) | `processBranchAttendance()` for all employees  | Re-verify week's sheets, flag incomplete days            |
| On correction approval             | `processDailyAttendance()` for employee + date | Regenerate corrected sheet                               |
| Payroll period generation          | Lock sheets, aggregate into period items       | Finalize pay period                                      |

---

## 2. Database Schema

### 2.1 `employees`

The `employees` table is the single source of truth for all person data — identity, auth credentials, role, branch, employment details, and government IDs. A slim `users` table (see 2.2) stores only email and password for authentication, linked via `id`.

| Column                     | Type               | Default                   | Notes                                                    |
| -------------------------- | ------------------ | ------------------------- | -------------------------------------------------------- |
| `id`                       | bigint PK          | auto                      | Used as FK by `users` table for auth linkage             |
| `employee_number`          | string(50), unique | auto: `EMP-{YEAR}-{0001}` | Also used as default `username` for login                |
| `username`                 | string(50), unique | auto (employee_number)    | Login identifier. Defaults to employee_number on create. |
| `role`                     | string(20)         | `'staff'`                 | `staff`, `admin`, `superadmin`. Determines all RBAC.     |
| `branch_id`                | foreignId          | required                  | FK to branches. Scopes all data access.                  |
| `first_name`               | string(100)        | required                  |                                                          |
| `last_name`                | string(100)        | required                  |                                                          |
| `middle_name`              | string(100)        | nullable                  |                                                          |
| `phone`                    | string(20)         | nullable                  |                                                          |
| `address`                  | string(500)        | nullable                  |                                                          |
| `birth_date`               | date               | nullable                  |                                                          |
| `hire_date`                | date               | required                  |                                                          |
| `end_date`                 | date               | nullable                  | Set on resignation/termination                           |
| `position`                 | string(50)         | `'regular'`               | `regular`, `contractual`, `project_based`                |
| `status`                   | string(20)         | `'active'`                | `active`, `resigned`, `terminated`                       |
| `current_daily_rate`       | decimal(10,2)      | required                  | Synced from latest salary record                         |
| `sss_number`               | string(20)         | nullable                  | Required for SSS deduction                               |
| `philhealth_number`        | string(20)         | nullable                  | Required for PhilHealth deduction                        |
| `pagibig_number`           | string(20)         | nullable                  | Required for Pag-IBIG deduction                          |
| `tin_number`               | string(20)         | nullable                  |                                                          |
| `leaves_used_this_year`    | integer            | `0`                       | Resets Jan 1. Max 5 paid leaves/year.                    |
| `notes`                    | text               | nullable                  |                                                          |
| `deleted_at`               | timestamp          | nullable                  | Soft deletes                                             |
| `created_at`, `updated_at` | timestamps         |                           |                                                          |

Indexes: `username` (unique), `role`, `status`, `position`, `branch_id`

**Important**: Use `$table->string()` not `$table->enum()` for SQLite compatibility. Bind enums in the model casts.

**Employee ↔ Auth Relationship**: Each employee row is the master record. The `users` table (auth) has `employee_id` FK → `employees.id`. User authentication uses `employees.username` + `users.password`. See section 2.2.

### 2.2 `users` (Auth-only)

A slim table holding only authentication credentials. All person/role data lives on `employees`.

| Column                      | Type                          | Notes                                    |
| --------------------------- | ----------------------------- | ---------------------------------------- |
| `id`                        | bigint PK                     |                                          |
| `employee_id`               | foreignId, unique             | FK to employees. One auth per employee.  |
| `email`                     | string(255), nullable, unique | For login + password reset               |
| `password`                  | string(255), nullable         | Hashed. Admin sets on create.            |
| `email_verified_at`         | timestamp, nullable           |                                          |
| `remember_token`            | string(100), nullable         |                                          |
| `two_factor_secret`         | text, nullable                |                                          |
| `two_factor_recovery_codes` | text, nullable                |                                          |
| `is_enabled`                | boolean                       | Default `true`. Admin can disable login. |
| `last_login_at`             | timestamp, nullable           |                                          |
| `created_at`, `updated_at`  | timestamps                    |                                          |

**Onboarding flow**:

1. Admin creates `users` row (email + password) → auto-creates `employees` row with `username = employee_number`, `role = staff`, `branch_id` from admin context.
2. Employee logs in → fills personal details (name, phone, address, gov IDs) via self-service profile
3. Admin later updates `employees.role` to promote to admin as needed

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

**Salary history pattern**: Each employee has multiple salary records forming a timeline. When a new salary is created, the previous record's `end_date` is set to the day before the new `effective_date`. The current rate is the row where `end_date IS NULL`.

### 2.4 `time_logs`

| Column         | Type                | Notes                                       |
| -------------- | ------------------- | ------------------------------------------- |
| `id`           | bigint PK           |                                             |
| `employee_id`  | foreignId           | FK to employees                             |
| `branch_id`    | foreignId           | FK to branches                              |
| `type`         | string(20)          | `in`, `out`, `lunch_out`, `lunch_in`        |
| `source`       | string(20)          | `self_service`, `manual`, `correction`      |
| `punched_at`   | datetime            | Actual punch timestamp                      |
| `duplicate_of` | foreignId, nullable | FK to time_logs. Set for duplicate punches. |
| `created_at`   | timestamp           | No updated_at (immutable)                   |

Indexes: `[employee_id, punched_at]`, `[employee_id, type, punched_at]`

**Immutable**: Once created, never updated. Corrections create new rows with `source=correction`.

### 2.5 `attendance_sheets`

| Column                      | Type                   | Notes                                                                              |
| --------------------------- | ---------------------- | ---------------------------------------------------------------------------------- |
| `id`                        | bigint PK              |                                                                                    |
| `employee_id`               | foreignId              |                                                                                    |
| `branch_id`                 | foreignId              |                                                                                    |
| `date`                      | date                   | The work date                                                                      |
| `schedule_start`            | time                   | e.g., `08:00`                                                                      |
| `schedule_end`              | time                   | e.g., `17:00`                                                                      |
| `is_rest_day`               | boolean                |                                                                                    |
| `time_in`                   | time, nullable         |                                                                                    |
| `time_out`                  | time, nullable         |                                                                                    |
| `lunch_out`                 | time, nullable         |                                                                                    |
| `lunch_in`                  | time, nullable         |                                                                                    |
| `regular_hours`             | decimal(4,2)           | Hours worked (after late/undertime/lunch deductions)                               |
| `late_minutes`              | integer                |                                                                                    |
| `undertime_minutes`         | decimal(5,2)           | Includes over-lunch minutes                                                        |
| `overtime_minutes`          | integer                | lower-of-two: min(actual, approved)                                                |
| `is_present`                | boolean                |                                                                                    |
| `absence_type`              | string(30), nullable   | `unexcused`, `approved_leave`                                                      |
| `has_leave`                 | boolean                |                                                                                    |
| `leave_type`                | string(20), nullable   | `vacation`, `sick`, `emergency`, `maternity`, `paternity`, `bereavement`, `unpaid` |
| `leave_duration`            | string(15), nullable   | `full_day`, `half_day_am`, `half_day_pm`                                           |
| `leave_hours_worked`        | decimal(4,2), nullable | Hours worked during leave                                                          |
| `is_holiday`                | boolean                |                                                                                    |
| `holiday_type`              | string(10), nullable   | `regular`, `special`                                                               |
| `holiday_worked`            | boolean                |                                                                                    |
| `day_before_present`        | boolean, nullable      | For regular unworked holidays                                                      |
| `overtime_approved_minutes` | integer                | From approved OT request                                                           |
| `ot_rate_30min`             | decimal(8,2), nullable | Rate snapshot at OT approval time                                                  |
| `ot_rate_1hour`             | decimal(8,2), nullable | Rate snapshot at OT approval time                                                  |
| `gross_pay`                 | decimal(10,2)          |                                                                                    |
| `late_deduction`            | decimal(10,2)          |                                                                                    |
| `undertime_deduction`       | decimal(10,2)          |                                                                                    |
| `overtime_pay`              | decimal(10,2)          |                                                                                    |
| `holiday_pay`               | decimal(10,2)          |                                                                                    |
| `holiday_pay_percent`       | decimal(5,2), nullable | 0, 100, 130, 200                                                                   |
| `locked_at`                 | timestamp, nullable    | Set on payroll period generation                                                   |
| `created_at`, `updated_at`  | timestamps             |                                                                                    |

Unique index: `[employee_id, date]`

### 2.6 `employee_schedules`

| Column                     | Type           | Notes                                   |
| -------------------------- | -------------- | --------------------------------------- |
| `id`                       | bigint PK      |                                         |
| `employee_id`              | foreignId      |                                         |
| `schedule_start`           | time           | e.g., `08:00`                           |
| `schedule_end`             | time           | e.g., `17:00`                           |
| `rest_days`                | json           | `["sunday"]` or `["saturday","sunday"]` |
| `effective_from`           | date           |                                         |
| `effective_to`             | date, nullable | NULL = currently active                 |
| `created_at`, `updated_at` | timestamps     |                                         |

### 2.7 `overtime_requests`

| Column                     | Type                   | Notes                                                           |
| -------------------------- | ---------------------- | --------------------------------------------------------------- |
| `id`                       | bigint PK              |                                                                 |
| `employee_id`              | foreignId              |                                                                 |
| `branch_id`                | foreignId              |                                                                 |
| `date`                     | date                   | Date of OT                                                      |
| `requested_minutes`        | integer                |                                                                 |
| `reason`                   | text                   |                                                                 |
| `shift_type`               | string(20)             | `regular_day`, `rest_day`, `regular_holiday`, `special_holiday` |
| `ot_amount_30min`          | decimal(8,2), nullable | Rate snapshot at approval                                       |
| `ot_amount_1hour`          | decimal(8,2), nullable | Rate snapshot at approval                                       |
| `status`                   | string(20)             | `pending`, `approved`, `denied`                                 |
| `approved_by`              | foreignId, nullable    | FK to employees                                                 |
| `approved_at`              | timestamp, nullable    |                                                                 |
| `denial_reason`            | text, nullable         |                                                                 |
| `created_at`, `updated_at` | timestamps             |                                                                 |

### 2.8 `leave_requests`

| Column                     | Type                | Notes                                                                              |
| -------------------------- | ------------------- | ---------------------------------------------------------------------------------- |
| `id`                       | bigint PK           |                                                                                    |
| `employee_id`              | foreignId           |                                                                                    |
| `branch_id`                | foreignId           |                                                                                    |
| `date`                     | date                |                                                                                    |
| `leave_type`               | string(20)          | `vacation`, `sick`, `emergency`, `maternity`, `paternity`, `bereavement`, `unpaid` |
| `duration`                 | string(15)          | `full_day`, `half_day_am`, `half_day_pm`                                           |
| `is_paid`                  | boolean             |                                                                                    |
| `reason`                   | text                |                                                                                    |
| `status`                   | string(20)          | `pending`, `approved`, `denied`                                                    |
| `approved_by`              | foreignId, nullable | FK to users                                                                        |
| `approved_at`              | timestamp, nullable |                                                                                    |
| `denial_reason`            | text, nullable      |                                                                                    |
| `created_at`, `updated_at` | timestamps          |                                                                                    |

### 2.9 `attendance_correction_requests`

| Column                     | Type                | Notes                                                                         |
| -------------------------- | ------------------- | ----------------------------------------------------------------------------- |
| `id`                       | bigint PK           |                                                                               |
| `employee_id`              | foreignId           |                                                                               |
| `branch_id`                | foreignId           |                                                                               |
| `date`                     | date                |                                                                               |
| `correction_type`          | string(25)          | `missed_punch_in`, `missed_punch_out`, `time_adjustment`, `absent_to_present` |
| `requested_in`             | time, nullable      |                                                                               |
| `requested_out`            | time, nullable      |                                                                               |
| `reason`                   | text                | Required                                                                      |
| `status`                   | string(20)          | `pending`, `approved`, `denied`                                               |
| `resolved_time_log_id`     | foreignId, nullable | FK to time_logs (the created correction log)                                  |
| `reviewed_by`              | foreignId, nullable | FK to employees                                                               |
| `reviewed_at`              | timestamp, nullable |                                                                               |
| `denial_reason`            | text, nullable      | Required on denial                                                            |
| `created_at`, `updated_at` | timestamps          |                                                                               |

Unique index: `[employee_id, date, correction_type]` where status = pending

### 2.10 `cash_advances`

| Column                     | Type                | Notes                                             |
| -------------------------- | ------------------- | ------------------------------------------------- |
| `id`                       | bigint PK           |                                                   |
| `employee_id`              | foreignId           |                                                   |
| `branch_id`                | foreignId           |                                                   |
| `amount`                   | decimal(10,2)       | Original loan amount                              |
| `remaining_balance`        | decimal(10,2)       | Amount still unpaid                               |
| `reason`                   | text                | Required                                          |
| `status`                   | string(20)          | `pending`, `approved`, `denied`, `unpaid`, `paid` |
| `requested_by`             | foreignId           | FK to employees                                   |
| `approved_by`              | foreignId, nullable | FK to users                                       |
| `approved_at`              | timestamp, nullable |                                                   |
| `denial_reason`            | text, nullable      |                                                   |
| `created_at`, `updated_at` | timestamps          |                                                   |

### 2.11 `payroll_periods`

| Column                     | Type                | Notes                         |
| -------------------------- | ------------------- | ----------------------------- |
| `id`                       | bigint PK           |                               |
| `branch_id`                | foreignId           |                               |
| `period_start`             | date                | Monday                        |
| `period_end`               | date                | Saturday                      |
| `status`                   | string(20)          | `draft`, `approved`, `voided` |
| `approved_by`              | foreignId, nullable |                               |
| `approved_at`              | timestamp, nullable |                               |
| `created_at`, `updated_at` | timestamps          |                               |

### 2.12 `payroll_period_items`

| Column                     | Type          | Notes                            |
| -------------------------- | ------------- | -------------------------------- |
| `id`                       | bigint PK     |                                  |
| `payroll_period_id`        | foreignId     | FK to payroll_periods            |
| `employee_id`              | foreignId     |                                  |
| `daily_rate`               | decimal(10,2) | Rate snapshot at generation time |
| `total_regular_days`       | integer       |                                  |
| `absent_days`              | integer       |                                  |
| `holiday_days`             | integer       |                                  |
| `late_minutes`             | integer       | Total across period              |
| `undertime_minutes`        | decimal(5,2)  | Total across period              |
| `overtime_minutes`         | integer       | Total across period              |
| `gross_pay`                | decimal(10,2) |                                  |
| `late_deduction`           | decimal(10,2) |                                  |
| `undertime_deduction`      | decimal(10,2) |                                  |
| `overtime_pay`             | decimal(10,2) |                                  |
| `holiday_pay`              | decimal(10,2) |                                  |
| `fine_deduction`           | decimal(10,2) |                                  |
| `sss_deduction`            | decimal(10,2) |                                  |
| `philhealth_deduction`     | decimal(10,2) |                                  |
| `pagibig_deduction`        | decimal(10,2) |                                  |
| `cash_advance_deduction`   | decimal(10,2) |                                  |
| `net_pay`                  | decimal(10,2) | gross_pay − deductions           |
| `created_at`, `updated_at` | timestamps    |                                  |

### 2.13 `holidays`

| Column                     | Type        | Notes                      |
| -------------------------- | ----------- | -------------------------- |
| `id`                       | bigint PK   |                            |
| `name`                     | string(255) | e.g., "Araw ng Kagitingan" |
| `date`                     | date        |                            |
| `type`                     | string(10)  | `regular`, `special`       |
| `created_at`, `updated_at` | timestamps  |                            |

### 2.14 `sss_contribution_brackets`

| Column                     | Type          | Notes                        |
| -------------------------- | ------------- | ---------------------------- |
| `id`                       | bigint PK     |                              |
| `salary_min`               | decimal(10,2) |                              |
| `salary_max`               | decimal(10,2) | Highest bracket has null max |
| `employee_percentage`      | decimal(5,2)  | e.g., `4.50`                 |
| `employer_percentage`      | decimal(5,2)  | e.g., `8.50`                 |
| `effective_from`           | date          |                              |
| `created_at`, `updated_at` | timestamps    |                              |

### 2.15 `company_configurations`

| Column                     | Type                | Notes                              |
| -------------------------- | ------------------- | ---------------------------------- |
| `id`                       | bigint PK           |                                    |
| `key`                      | string(100), unique | e.g., `philhealth_premium_percent` |
| `value`                    | string(255)         |                                    |
| `created_at`, `updated_at` | timestamps          |                                    |

### 2.16 `fines`

| Column                     | Type         | Notes              |
| -------------------------- | ------------ | ------------------ |
| `id`                       | bigint PK    |                    |
| `employee_id`              | foreignId    |                    |
| `branch_id`                | foreignId    |                    |
| `date`                     | date         |                    |
| `fine_type`                | string(50)   | e.g., `no_uniform` |
| `amount`                   | decimal(8,2) |                    |
| `reason`                   | text         | Required           |
| `marked_by`                | foreignId    | FK to employees    |
| `created_at`, `updated_at` | timestamps   |                    |

### 2.17 `ot_rate_configs`

| Column                     | Type         | Notes                                                           |
| -------------------------- | ------------ | --------------------------------------------------------------- |
| `id`                       | bigint PK    |                                                                 |
| `shift_type`               | string(20)   | `regular_day`, `rest_day`, `regular_holiday`, `special_holiday` |
| `ot_amount_30min`          | decimal(8,2) |                                                                 |
| `ot_amount_1hour`          | decimal(8,2) |                                                                 |
| `created_at`, `updated_at` | timestamps   |                                                                 |

---

## 3. Business Rules

### 3.1 Base Formula

```
hourly_rate = daily_rate / 8
monthly_salary = daily_rate × 26  (for government deduction computation only)
```

### 3.2 Late Deduction — 3-Tier System (No Grace Period)

| Late Minutes | Deduction Formula                           | Example (daily = ₱510, hourly = ₱63.75) |
| ------------ | ------------------------------------------- | --------------------------------------- |
| 0            | ₱0                                          | ₱0                                      |
| 1–19         | `late_min × ₱5`                             | 15 min → ₱75                            |
| 20–59        | Flat ₱100                                   | 25 min → ₱100                           |
| 60+          | `₱100 + floor(late_min / 60) × hourly_rate` | 90 min → ₱100 + ₱63.75 = ₱163.75        |

**Key**: Fractional hours past each full hour of lateness are NOT additionally penalized. 60 min costs the same as 90 min: `floor(90/60) = 1`. 120 min costs the same as 150 min: `floor(150/60) = 2`.

### 3.3 Daily Wage Formula

```
if late_minutes > 0:
    base_pay = daily_rate − late_deduction
else:
    base_pay = hourly_rate × regular_hours

gross_pay = base_pay + overtime_pay + holiday_pay − fine_deduction
```

**Partial day pay**: `hourly_rate × hours_worked`. No minimum threshold. Only 0 hours = absent.

### 3.4 Overtime Pay — 2-Block Flat-Rate Model

```
if ot_worked_minutes == 0:
    ot_pay = 0
else:
    full_hours = floor(ot_worked_minutes / 60)
    remainder  = ot_worked_minutes % 60
    ot_pay = (full_hours × ot_amount_hour) + round_block(remainder)

round_block(minutes):
    if minutes == 0:       return 0
    elif minutes ≤ 30:     return ot_amount_30min
    elif minutes < 60:     return ot_amount_hour       # round up to 1 hour
```

**Lower-of-two rule**: `ot_worked_minutes = min(actual_extra_stay, approved_request_minutes)`. Unapproved extra minutes are discarded.

**OT Rate Configuration** (2 amounts per shift type, admin enters flat PHP values):

| Shift Type      | 30-min Block      | 1-hour Block      |
| --------------- | ----------------- | ----------------- |
| Regular Day     | ₱50.00            | ₱70.00            |
| Rest Day        | ₱65.00            | ₱90.00            |
| Regular Holiday | ₱100.00           | ₱140.00           |
| Special Holiday | ₱65.00            | ₱90.00            |
| Rest + Holiday  | Higher of the two | Higher of the two |

**OT Pay Examples (regular day)**:

| OT Worked | Computation                      | Paid As | Pay  |
| --------- | -------------------------------- | ------- | ---- |
| 30 min    | `1 × ₱50`                        | 30 min  | ₱50  |
| 35 min    | remainder=35, >30 → 1h           | 1 hour  | ₱70  |
| 40 min    | remainder=40, >30 → 1h           | 1 hour  | ₱70  |
| 60 min    | `1 × ₱70`                        | 1 hour  | ₱70  |
| 65 min    | `1 × ₱70 + remainder=5 → 30min`  | 1h30min | ₱120 |
| 80 min    | `1 × ₱70 + remainder=20 → 30min` | 1h30min | ₱120 |
| 90 min    | `1 × ₱70 + remainder=30 → 30min` | 1h30min | ₱120 |
| 95 min    | `1 × ₱70 + remainder=35 → 1h`    | 2 hours | ₱140 |
| 120 min   | `2 × ₱70`                        | 2 hours | ₱140 |
| 150 min   | `2 × ₱70 + remainder=30 → 30min` | 2h30min | ₱190 |

### 3.5 Lunch — 4-Punch Model

Shift is 8:00 AM – 5:00 PM (paid). 5:00–5:30 PM is outside the paid shift.

```
morning_work   = LUNCH_OUT − IN
afternoon_work = cap(OUT, schedule_end 5:00 PM) − LUNCH_IN
actual_lunch   = LUNCH_IN − LUNCH_OUT (measured, not assumed)
```

| actual_lunch | Impact                               |
| ------------ | ------------------------------------ |
| ≤ 60 min     | No penalty                           |
| > 60 min     | Excess minutes deducted as undertime |

**Fallback (missing lunch punches)**: If LUNCH_OUT or LUNCH_IN is missing:

- 60 minutes deducted if raw duration ≥ 5 hours AND work period overlaps 11:00 AM – 2:00 PM
- Otherwise 0 deduction

### 3.6 Full Scenario Matrix (daily_rate = ₱510, hourly = ₱63.75)

| Scenario                       | Late | Worked | Computation          | daily_wage  |
| ------------------------------ | ---- | ------ | -------------------- | ----------- |
| On time, full day              | 0    | 8h     | `510`                | **₱510.00** |
| On time, no uniform (₱20 fine) | 0    | 8h     | `510 − 20`           | **₱490.00** |
| Late 10 min                    | 10   | 7.83h  | `510 − 50`           | **₱460.00** |
| Late 15 min                    | 15   | 7.75h  | `510 − 75`           | **₱435.00** |
| Late 19 min                    | 19   | 7.68h  | `510 − 95`           | **₱415.00** |
| Late 20 min                    | 20   | 7.67h  | `510 − 100`          | **₱410.00** |
| Late 45 min                    | 45   | 7.25h  | `510 − 100`          | **₱410.00** |
| Late 60 min (1h)               | 60   | 7h     | `510 − 100 − 63.75`  | **₱346.25** |
| Late 90 min (1.5h)             | 90   | 6.5h   | `510 − 100 − 63.75`  | **₱346.25** |
| Late 120 min (2h)              | 120  | 6h     | `510 − 100 − 127.50` | **₱282.50** |
| Late 150 min (2.5h)            | 150  | 5.5h   | `510 − 100 − 127.50` | **₱282.50** |
| Late 180 min (3h)              | 180  | 5h     | `510 − 100 − 191.25` | **₱218.75** |
| Not late, left early (5h)      | 0    | 5h     | `63.75 × 5`          | **₱318.75** |
| Not late, left early (2h)      | 0    | 2h     | `63.75 × 2`          | **₱127.50** |

### 3.7 Holiday Pay

| Holiday Type | Worked? | Day-Before Status   | Pay Percent | Label                |
| ------------ | ------- | ------------------- | ----------- | -------------------- |
| Regular      | Yes     | —                   | 200%        | `Holiday Pay (200%)` |
| Regular      | No      | Present or on Leave | 100%        | `Holiday Pay (100%)` |
| Regular      | No      | Absent (unexcused)  | 0%          | Not shown            |
| Special      | Yes     | —                   | 130%        | `Holiday Pay (130%)` |
| Special      | No      | —                   | 0%          | Not shown            |

**Day-before lookback for regular unworked holidays**: Walk backward from the holiday date, skip rest days, Sundays, and other holidays. Check the last working day's attendance sheet. Example: Holiday on Monday → walks back: Sun (rest) → Sat (rest) → Friday (check Friday's sheet).

### 3.8 Government Deductions

Computed on **regular monthly salary (daily_rate × 26)**, NOT on variable attendance earnings. Same deduction every week regardless of absences or OT.

#### SSS — Bracket-Based

```
monthly_salary = daily_rate × 26
bracket = find bracket where salary_min ≤ monthly_salary ≤ salary_max
sss_weekly = (monthly_salary × bracket.employee_percentage / 100) / 4
```

- Table: `sss_contribution_brackets` — managed by superadmin
- 20 brackets up to ₱20,000 monthly salary
- Per-benefit conditional: only deducted if `sss_number` is filled

#### PhilHealth — Percentage-Based (50/50 Split)

```
phic_weekly = (daily_rate × 26 × premium_percent / 100 × 0.50) / 4
```

- Default premium: 5%, configurable in `company_configurations`
- 50% employer / 50% employee split (hardcoded)
- Per-benefit conditional: only deducted if `philhealth_number` is filled

#### Pag-IBIG — Flat Amount

```
pagibig_weekly = monthly_employee_share / 4
```

- Default: ₱100/month (configurable)
- Per-benefit conditional: only deducted if `pagibig_number` is filled

### 3.9 Cash Advances

- **Maximum CA** = projected net receivable for current payroll period
- **One active CA at a time**: blocked if `remaining_balance > 0`
- **No interest**
- **Deducted from net pay**: after government contributions
- **Balance carries over**: if net_pay < remaining_balance, deduct all available, remainder to next period
- **Net pay ≥ 0**: deduction capped so net never goes negative

```
net_pay = gross_pay − govt_deductions − fine_deduction − ca_deduction
ca_deduction = min(remaining_balance, net_pay_before_ca)
```

### 3.10 Leave Rules

- **5 paid leaves per year**, tracked via `leaves_used_this_year`
- **Balance resets January 1** (set to 0 for all employees)
- **Admin discretion beyond 5**: System shows warning but does not block
- **Unpaid leave** does not count against the 5-leave balance

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

### 3.11 Fines

- Per-day flat fines for policy violations (e.g., ₱20 for no uniform)
- Configurable per fine type in `company_configurations`
- Multiple fine types can be stacked in one day
- Admin marks violation on employee's daily record → auto-applied as deduction

### 3.12 Payroll Period Generation Flow

1. Admin clicks **Generate Payroll Period** (available Saturday after shift)
2. System auto-selects completed work week (Mon–Sat)
3. `PayrollPeriodService` runs within transaction:
    - Locks all `attendance_sheets` in date range (`locked_at` set)
    - Creates `payroll_period` record (`status=draft`)
    - For each ACTIVE employee in branch:
        - Aggregates sheets into `payroll_period_item`
        - Computes: late/UT/OT minutes, holiday pay, fines
        - Computes government deductions (based on daily_rate × 26)
        - Computes cash advance deduction
        - Stores `net_pay`
4. Shows **incomplete days alert** — days with only IN (no OUT) → admin reviews
5. Admin reviews → clicks **Approve**
6. Period `status` → `approved`

**Void Flow (superadmin only)**:

1. Superadmin clicks **Void Period**
2. All sheets unlocked (`locked_at = null`)
3. Period `status` → reverted to `draft`
4. Corrections can be made → re-generate → re-approve

### 3.13 Employee Number & Username Generation

```
Employee number format: EMP-{YEAR}-{NNNN}
Username default:       same as employee_number (can be changed later)
Role default:           staff (admin promotes as needed)

Generation logic:
1. Get current year
2. Find latest employee_number matching "EMP-{YEAR}-%" (including soft-deleted)
3. Extract sequence: last 4 digits + 1 (or start at 1)
4. Pad to 4 digits with leading zeros
5. Set username = employee_number
```

### 3.14 Salary History Pattern

```
When new salary is created (createForEmployee):
  1. Find current salary for employee (end_date IS NULL)
  2. Set its end_date = NOW()
  3. Create new salary record with new daily_rate and effective_date
  4. Update employee.current_daily_rate to sync

When employee is rehired:
  1. Set employee.status = 'active', employee.end_date = null
  2. Create new salary with new daily_rate and rehire_date
```

---

## 4. API Routes

### Employee CRUD

| Method   | Path                                   | Name                | Auth                 |
| -------- | -------------------------------------- | ------------------- | -------------------- |
| `GET`    | `/payroll`                             | `payroll.index`     | Auth                 |
| `GET`    | `/payroll/employees`                   | `employees.index`   | Auth                 |
| `GET`    | `/payroll/employees/create`            | `employees.create`  | Auth (admin+)        |
| `POST`   | `/payroll/employees`                   | `employees.store`   | Auth (admin+)        |
| `GET`    | `/payroll/employees/{employee}`        | `employees.show`    | Auth (same branch)   |
| `GET`    | `/payroll/employees/{employee}/edit`   | `employees.edit`    | Auth (same branch)   |
| `PUT`    | `/payroll/employees/{employee}`        | `employees.update`  | Auth (same branch)   |
| `DELETE` | `/payroll/employees/{employee}`        | `employees.destroy` | Auth (superadmin)    |
| `POST`   | `/payroll/employees/{employee}/rehire` | `employees.rehire`  | Auth (same branch)   |
| `GET`    | `/payroll/audit-logs`                  | `audit.index`       | Auth (branch-scoped) |

### Attendance

| Method | Path                 | Name                      |
| ------ | -------------------- | ------------------------- |
| `POST` | `/attendance/punch`  | `attendance.punch`        |
| `GET`  | `/attendance/my`     | `attendance.my`           |
| `GET`  | `/attendance/sheets` | `attendance.sheets.index` |

### Overtime

| Method  | Path                                         | Name               |
| ------- | -------------------------------------------- | ------------------ |
| `GET`   | `/attendance/overtime-requests`              | `overtime.index`   |
| `POST`  | `/attendance/overtime-requests`              | `overtime.store`   |
| `PATCH` | `/attendance/overtime-requests/{id}/approve` | `overtime.approve` |
| `PATCH` | `/attendance/overtime-requests/{id}/deny`    | `overtime.deny`    |

### Leave

| Method  | Path                                      | Name            |
| ------- | ----------------------------------------- | --------------- |
| `GET`   | `/attendance/leave-requests`              | `leave.index`   |
| `POST`  | `/attendance/leave-requests`              | `leave.store`   |
| `PATCH` | `/attendance/leave-requests/{id}/approve` | `leave.approve` |
| `PATCH` | `/attendance/leave-requests/{id}/deny`    | `leave.deny`    |

### Corrections

| Method  | Path                                   | Name                  |
| ------- | -------------------------------------- | --------------------- |
| `GET`   | `/attendance/corrections`              | `corrections.index`   |
| `POST`  | `/attendance/corrections`              | `corrections.store`   |
| `PATCH` | `/attendance/corrections/{id}/approve` | `corrections.approve` |
| `PATCH` | `/attendance/corrections/{id}/deny`    | `corrections.deny`    |

### Cash Advances

| Method  | Path                                     | Name                    |
| ------- | ---------------------------------------- | ----------------------- |
| `GET`   | `/attendance/cash-advances`              | `cash-advances.index`   |
| `POST`  | `/attendance/cash-advances`              | `cash-advances.store`   |
| `PATCH` | `/attendance/cash-advances/{id}/approve` | `cash-advances.approve` |
| `PATCH` | `/attendance/cash-advances/{id}/deny`    | `cash-advances.deny`    |

### Payroll Period

| Method | Path                                | Name                       |
| ------ | ----------------------------------- | -------------------------- |
| `GET`  | `/payroll/periods`                  | `payroll.periods.index`    |
| `POST` | `/payroll/periods`                  | `payroll.periods.generate` |
| `GET`  | `/payroll/periods/{period}`         | `payroll.periods.show`     |
| `POST` | `/payroll/periods/{period}/approve` | `payroll.periods.approve`  |
| `POST` | `/payroll/periods/{period}/void`    | `payroll.periods.void`     |
| `GET`  | `/payroll/payslips/{employee}`      | `payroll.payslips.show`    |

### Admin

| Method   | Path                        | Name                  |
| -------- | --------------------------- | --------------------- |
| `GET`    | `/admin/holidays`           | `holidays.index`      |
| `POST`   | `/admin/holidays`           | `holidays.store`      |
| `PUT`    | `/admin/holidays/{holiday}` | `holidays.update`     |
| `DELETE` | `/admin/holidays/{holiday}` | `holidays.destroy`    |
| `GET`    | `/admin/config`             | `config.index`        |
| `PUT`    | `/admin/config`             | `config.update`       |
| `GET`    | `/admin/sss-brackets`       | `sss-brackets.index`  |
| `PUT`    | `/admin/sss-brackets`       | `sss-brackets.update` |

---

## 5. RBAC & Authorization

### Roles

Roles live on `employees.role`. There is no separate user/role table — every person in the system is an employee first.

| Role         | Scope        | Key Abilities                                                                  |
| ------------ | ------------ | ------------------------------------------------------------------------------ |
| `staff`      | Self only    | Punch, view own attendance/payslip, submit requests                            |
| `admin`      | Branch only  | Manage branch employees, manual logs, approve staff requests, generate payroll |
| `superadmin` | All branches | All admin abilities + void payroll, manage holidays, edit config, manage users |

### Branch Isolation

```
staff_1 (Branch A) → can see:     self only
staff_1 (Branch A) → cannot see:  staff_2 (Branch B)

admin_A (Branch A) → can see:     all employees in Branch A
admin_A (Branch A) → cannot see:  employees in Branch B (unless special group)

superadmin → can see:             all employees in all branches
```

### Special Group Branches

Branches in `config('company.special_group_branch_names')` (default: `Babak`, `Peñaplata`, `Tibungco`) share access with each other. An admin from any of these branches can see/manage employees from all three.

### Superior-Only Approval Rule

```
Requestor → Approver
─────────────────────
Staff     → Admin (same branch) or Superadmin
Admin     → Superadmin (never self-approved)
```

### Policy Matrix

| Action                                     | Staff     | Admin                  | Superadmin        |
| ------------------------------------------ | --------- | ---------------------- | ----------------- |
| Punch IN/OUT                               | Self only | Self only              | Self only         |
| View own attendance                        | ✓         | ✓                      | ✓                 |
| View branch attendance                     | —         | Branch only            | All branches      |
| Create manual time_log                     | —         | Branch employees only  | All employees     |
| Submit correction request                  | Self only | Branch + self          | All               |
| Approve correction request                 | —         | Staff in branch        | All (incl. admin) |
| Submit / approve OT request                | Self / —  | Self + branch / branch | All               |
| Submit / approve leave request             | Self / —  | Self + branch / branch | All               |
| Request / approve cash advance             | Self / —  | Self + branch / branch | All               |
| Manage employee schedules                  | —         | Branch employees       | All               |
| Mark fine on employee                      | —         | Branch employees       | All               |
| Manage fine types                          | —         | —                      | ✓                 |
| Generate payroll period                    | —         | Branch only            | All branches      |
| Approve payroll period                     | —         | Branch only            | All branches      |
| Void payroll period                        | —         | —                      | ✓                 |
| View payslip                               | Own only  | Branch employees       | All               |
| View employee profile                      | Own only  | Branch employees       | All               |
| Create employee (onboarding)               | —         | Branch only            | All branches      |
| Update employee                            | —         | Branch only            | All branches      |
| Deactivate employee                        | —         | —                      | ✓                 |
| Rehire employee                            | —         | Branch only            | All branches      |
| Manage holidays                            | —         | —                      | ✓                 |
| Edit company config (OT/SSS/PHIC/Pag-IBIG) | —         | —                      | ✓                 |

---

## 6. Edge Cases

### Punch-Related

| #   | Scenario                              | Behavior                                      |
| --- | ------------------------------------- | --------------------------------------------- |
| E1  | Two IN punches within 5 min           | Earliest kept; later marked `duplicate_of`    |
| E2  | Two OUT punches within 5 min          | Same throttling                               |
| E3  | Only IN, no OUT (day in progress)     | No sheet generated; "Currently clocked in"    |
| E4  | Only IN, no OUT (day closed)          | Marked unexcused absence; "Missing OUT punch" |
| E5  | Only OUT, no IN                       | Anomaly; 0 hours; admin review recommended    |
| E6  | Punch on rest day (no OT)             | Blocked: "Today is your rest day"             |
| E7  | Punch on rest day (OT approved)       | Allowed; rest day OT rate applied             |
| E8  | Punch > 18 hours after schedule start | Logged but flagged as anomaly warning         |

### Computation-Related

| #   | Scenario                         | Behavior                                              |
| --- | -------------------------------- | ----------------------------------------------------- |
| E9  | Late 10 min                      | Deduction = 10 × ₱5 = ₱50                             |
| E10 | Late 19 min                      | Deduction = 19 × ₱5 = ₱95                             |
| E11 | Late 20 min                      | Flat ₱100 deduction                                   |
| E12 | Late 45 min                      | Flat ₱100 deduction                                   |
| E13 | Late 60 min                      | ₱100 + 1 × hourly_rate                                |
| E14 | Late 90 min                      | ₱100 + floor(90/60) × hourly = same as 60 min         |
| E15 | Late 150 min                     | ₱100 + floor(150/60) × hourly = same as 120 min       |
| E16 | Not late, worked 5h              | Proportional: hourly_rate × 5                         |
| E17 | Not late, worked 2h              | Proportional: hourly_rate × 2                         |
| E18 | OT approved 3h, stayed 2h        | Lower-of-two: 120 min OT                              |
| E19 | OT approved 1h, stayed 2.5h      | Lower-of-two: 60 min OT; 90 min discarded             |
| E20 | OT approved < threshold (60 min) | 0 OT awarded                                          |
| E21 | Schedule changed mid-week        | Uses schedule active on each date (effective_from/to) |

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

### Cash Advances

| #   | Scenario                       | Behavior                                            |
| --- | ------------------------------ | --------------------------------------------------- |
| E43 | Request CA > max receivable    | Blocked                                             |
| E44 | Request while CA still active  | Blocked: settle existing first                      |
| E45 | Net pay < remaining CA balance | Deduct all available; balance carries over; net ≥ 0 |
| E46 | CA fully deducted this period  | Status → `paid`; can request new CA                 |

### Concurrency & Integrity

| #   | Scenario                            | Protection                                            |
| --- | ----------------------------------- | ----------------------------------------------------- |
| E47 | Two admins approve same correction  | `lockForUpdate()` on correction row; second fails     |
| E48 | Payroll gen while sheet recomputing | Period gen locks sheets; recompute fails              |
| E49 | Correction for locked sheet         | Blocked: "Sheet locked in approved payroll period"    |
| E50 | Duplicate correction request        | Blocked: "Pending request exists"                     |
| E51 | Self-approval attempt               | Policy blocks: role must be strictly superior         |
| E52 | 200+ employee batch processing      | `chunk(50)`; individual failures don't rollback batch |

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
│ Overtime          —          │ Fine (No Uniform)      −₱20.00   │
│ Holiday Pay       —          │ SSS (5%)              −₱165.75   │
│                              │ PhilHealth (2.50%)     −₱82.88   │
│                              │ Pag-IBIG               −₱25.00   │
│                              │ Cash Advance           —          │
├──────────────────────────────┼──────────────────────────────────┤
│  GROSS PAY       ₱2,868.75   │  TOTAL DEDUCTIONS    −₱368.63    │
└──────────────────────────────┴──────────────────────────────────┘

                           ┌───────────────────────┐
                           │  NET PAY  ₱2,500.12    │
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

| Rule                | Detail                                                                                                      |
| ------------------- | ----------------------------------------------------------------------------------------------------------- |
| IDs in header       | SSS, PhilHealth, Pag-IBIG, TIN displayed. Missing IDs shown as `—`.                                         |
| No daily rows       | Compact Attendance Summary line (Present, Late, OT hours, Absent, Holiday).                                 |
| Two-column body     | Earnings left (Basic Pay, Overtime, Holiday Pay). Deductions right (Late, Fines, Gov't, Cash Advance).      |
| Holiday pay         | Shown as separate line item under Earnings with percentage label (100%, 130%, 200%). 0% holidays not shown. |
| Deduction ordering  | Late + Fines first (behavioral), then statutory (SSS/PhilHealth/Pag-IBIG), then voluntary (Cash Advance).   |
| Missing gov't IDs   | Deduction line shown as `—` and no amount deducted.                                                         |
| Net pay ≥ 0         | Cash advance deduction capped so net pay never goes negative.                                               |
| Overtime visibility | 2-block rates shown (e.g., `30min=₱50, 1h=₱70`) and billed duration (e.g., `1h30min`).                      |
| Signature blocks    | Employee, preparer, and approver signature lines in footer.                                                 |
| Contribution basis  | All government deductions computed on `daily_rate × 26`. NOT on variable attendance earnings.               |
