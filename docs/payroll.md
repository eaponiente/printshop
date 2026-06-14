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

| Trigger                   | What Runs                                      | Purpose                                         |
| ------------------------- | ---------------------------------------------- | ----------------------------------------------- |
| Per punch (event-driven)  | `processDailyAttendance()` for employee + date | Real-time status: late warnings, daily wage, OT |
| On correction approval    | `processDailyAttendance()` for employee + date | Regenerate corrected sheet                      |
| Payroll period generation | Lock sheets, aggregate into period items       | Finalize pay period                             |
| Payroll period void       | Unlock all sheets in period                    | Enable corrections then re-generate             |

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

### 2.13 `payroll_period_items`

| Column                     | Type              | Notes                                 |
| -------------------------- | ----------------- | ------------------------------------- |
| `id`                       | bigint PK         |                                       |
| `payroll_period_id`        | foreignId         | FK to payroll_periods                 |
| `employee_id`              | foreignId         |                                       |
| `total_regular_days`       | integer           |                                       |
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
base_pay = isPresent ? (basePaidHours × hourly_rate) : 0
  - On regular days without approved OT, paid hours capped at max(480 − late_minutes, 240)
  - On rest days, base_pay = hours_worked × hourly_rate × 1.30

daily_wage = base_pay − undertime_deduction − fine_deduction + overtime_pay + holiday_pay
  floor: 0
```

**Partial day pay**: `hourly_rate × hours_worked`. No minimum threshold. Only 0 hours = absent.

### 3.4 Overtime Pay — Labor Law Multipliers

OT uses fixed Philippine labor law multipliers. No admin-configurable flat amounts, no rounding blocks.

```
hourly_rate = daily_rate / 8
ot_pay = ot_hours × hourly_rate × multiplier
```

**Lower-of-two rule**: `ot_worked_minutes = min(actual_extra_stay, approved_request_minutes)`. Unapproved extra minutes are discarded. OT must be ≥ 60 consecutive minutes AND an approved OT request must exist.

**OT threshold**: OT starts at `480 + unpaid_tail_minutes` total work minutes. The `unpaid_tail_minutes` is configured per schedule (default 30 minutes for 5:00–5:30 PM buffer).

| Day Type                         | OT Multiplier |
| -------------------------------- | ------------- |
| Ordinary working day             | **1.25x**     |
| Rest day                         | **1.69x**     |
| Special non-working day (worked) | **1.69x**     |
| Rest day + Special holiday       | **1.95x**     |
| Regular holiday (worked)         | **2.60x**     |
| Rest day + Regular holiday       | **3.38x**     |

**Examples (daily_rate = ₱510, hourly_rate = ₱63.75)**:

| Day Type     | OT Hours | Computation          | OT Pay  |
| ------------ | -------- | -------------------- | ------- |
| Ordinary day | 2.0      | `2 × 63.75 × 1.25`   | ₱159.38 |
| Ordinary day | 1.5      | `1.5 × 63.75 × 1.25` | ₱119.53 |
| Rest day     | 2.0      | `2 × 63.75 × 1.69`   | ₱215.48 |
| Reg. holiday | 1.0      | `1 × 63.75 × 2.60`   | ₱165.75 |

**No rate snapshot at approval time**: The multiplier is determined at computation time based on `shift_type` from the approved OT request.

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
| `POST`   | `/payroll/holidays`           | `payroll.holidays.store`   | Auth (superadmin) |
| `PUT`    | `/payroll/holidays/{holiday}` | `payroll.holidays.update`  | Auth (superadmin) |
| `DELETE` | `/payroll/holidays/{holiday}` | `payroll.holidays.destroy` | Auth (superadmin) |

### Payroll Periods

| Method | Path                                       | Name                       | Auth                 |
| ------ | ------------------------------------------ | -------------------------- | -------------------- |
| `GET`  | `/payroll/periods`                         | `payroll.periods.index`    | Auth (admin+)        |
| `POST` | `/payroll/periods/generate`                | `payroll.periods.generate` | Auth (admin+)        |
| `GET`  | `/payroll/periods/{period}`                | `payroll.periods.show`     | Auth (admin+)        |
| `POST` | `/payroll/periods/{period}/approve`        | `payroll.periods.approve`  | Auth (superadmin)    |
| `POST` | `/payroll/periods/{period}/void`           | `payroll.periods.void`     | Auth (superadmin)    |
| `GET`  | `/payroll/periods/{period}/payslip/{item}` | `payroll.payslip`          | Auth (branch-scoped) |
| `GET`  | `/payroll/my-payslip`                      | `payroll.my-payslip`       | Auth                 |

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

| Method | Path                                   | Name                     | Auth                 |
| ------ | -------------------------------------- | ------------------------ | -------------------- |
| `GET`  | `/payroll/leave-requests`              | `payroll.leaves.index`   | Auth                 |
| `POST` | `/payroll/leave-requests`              | `payroll.leaves.store`   | Auth                 |
| `POST` | `/payroll/leave-requests/{lr}/approve` | `payroll.leaves.approve` | Auth (superior role) |
| `POST` | `/payroll/leave-requests/{lr}/deny`    | `payroll.leaves.deny`    | Auth (superior role) |
| `POST` | `/payroll/leave-requests/{lr}/cancel`  | `payroll.leaves.cancel`  | Auth (owner/admin)   |

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

| Action                                        | Staff     | Admin                 | Superadmin        |
| --------------------------------------------- | --------- | --------------------- | ----------------- |
| Punch IN/OUT                                  | Self only | Self only             | Self only         |
| View own attendance                           | ✓         | ✓                     | ✓                 |
| View branch attendance                        | —         | Branch only           | All branches      |
| Create manual time_log                        | —         | Branch employees only | All employees     |
| Submit correction request                     | Self only | Branch + self         | All               |
| Approve correction request                    | —         | Staff in branch       | All (incl. admin) |
| Submit OT request                             | Self only | Self only             | Self only         |
| Approve OT request                            | —         | Branch employees      | All               |
| Submit leave request                          | Self only | Self only             | Self only         |
| Approve leave request                         | —         | Branch employees      | All               |
| Request cash advance                          | Self only | Self only             | Self only         |
| Approve cash advance                          | —         | Branch employees      | All               |
| Manage employee schedules                     | —         | Branch employees      | All               |
| Mark fine on employee                         | —         | Branch employees      | All               |
| Manage fine types/amounts                     | —         | —                     | ✓                 |
| Generate payroll period                       | —         | Branch only           | All branches      |
| Approve payroll period                        | —         | —                     | ✓                 |
| Void payroll period                           | —         | —                     | ✓                 |
| View payslip                                  | Own only  | Branch employees      | All               |
| View employee profile                         | Own only  | Branch employees      | All               |
| Create employee (onboarding)                  | —         | Branch only           | All branches      |
| Update employee                               | —         | Branch only           | All branches      |
| Deactivate employee                           | —         | Branch only           | All branches      |
| Rehire employee                               | —         | Branch only           | All branches      |
| Manage holidays                               | —         | —                     | ✓                 |
| Edit company config (SSS/PhilHealth/Pag-IBIG) | —         | —                     | ✓                 |
| View audit logs                               | —         | Branch only           | All branches      |

---

## 6. Edge Cases

### Punch-Related

| #   | Scenario                              | Behavior                                                  |
| --- | ------------------------------------- | --------------------------------------------------------- |
| E1  | Two IN punches within 5 min           | Earliest kept; later marked `duplicate_of`                |
| E2  | Two OUT punches within 5 min          | Same throttling                                           |
| E3  | Only IN, no OUT (day in progress)     | Sheet computed with estimated end; "Currently clocked in" |
| E4  | Only IN, no OUT (day closed)          | Marked unexcused absence; flagged for admin review        |
| E5  | Only OUT, no IN                       | Anomaly; 0 hours; admin review recommended                |
| E6  | Punch on rest day (no OT)             | Blocked: "Today is your rest day"                         |
| E7  | Punch on rest day (OT approved)       | Allowed; rest day OT rate applied                         |
| E8  | Punch > 18 hours after schedule start | Logged but flagged as anomaly warning                     |

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

| Rule                | Detail                                                                                                             |
| ------------------- | ------------------------------------------------------------------------------------------------------------------ |
| IDs in header       | SSS, PhilHealth, Pag-IBIG, TIN displayed. Missing IDs shown as `—`.                                                |
| No daily rows       | Compact Attendance Summary line (Present, Late, OT hours, Absent, Holiday).                                        |
| Two-column body     | Earnings left (Basic Pay, OT, Holiday Pay, De Minimis perks). Deductions right (Late, Fines, Gov't, Cash Advance). |
| OT visibility       | Shows hours worked × multiplier label (e.g., `2h × 1.25x` = `₱159.38`). Rates are labor law multipliers.           |
| Holiday pay         | Shown as separate line item with percentage label (100%, 130%, 200%). 0% holidays not shown.                       |
| De minimis benefits | Shown under Earnings with `*` prefix and "Non-taxable" footnote. Label from `payslip_label`. Prorated (÷4).        |
| Deduction ordering  | Late + Fines first (behavioral), then statutory (SSS/PhilHealth/Pag-IBIG), then voluntary (Cash Advance).          |
| Missing gov't IDs   | Deduction line shown as `—` and no amount deducted.                                                                |
| Net pay ≥ 0         | Cash advance deduction capped so net pay never goes negative.                                                      |
| Signature blocks    | Employee, preparer, and approver signature lines in footer.                                                        |
| Contribution basis  | All government deductions computed on `daily_rate × 26`. NOT on variable attendance earnings.                      |
