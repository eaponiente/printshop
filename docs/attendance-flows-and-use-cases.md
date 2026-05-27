# Attendance Module — Flows & Use Cases

**Printing Shop Management System** · May 22, 2026 · v3.0

---

## Contents

1. [System-Level Data Flow](#1-system-level-data-flow)
2. [Flow 1: Daily Self-Service Punch](#2-flow-1-daily-self-service-punch)
3. [Daily Wage Computation Rules](#3-daily-wage-computation-rules)
4. [Flow 2: Manual Attendance Correction (Approval-Gated)](#4-flow-2-manual-attendance-correction-approval-gated)
5. [Flow 3: Admin Direct Manual Log](#5-flow-3-admin-direct-manual-log)
6. [Flow 4: Payroll Period Generation](#6-flow-4-payroll-period-generation)
7. [Flow 5: Overtime Approval](#7-flow-5-overtime-approval)
8. [Flow 6: Leave Request](#8-flow-6-leave-request)
9. [Flow 7: Holiday Pay Resolution](#9-flow-7-holiday-pay-resolution)
10. [Flow 8: Cash Advance Request & Deduction](#10-flow-8-cash-advance-request--deduction)
11. [Payslip Design](#11-payslip-design)
12. [Use Cases by Role](#12-use-cases-by-role)
13. [System (Automated) Use Cases](#13-system-automated-use-cases)
14. [Edge Cases](#14-edge-cases)

---

## 1. System-Level Data Flow

```
                          ┌─────────────────────┐
     Self-Service Punch ──┤                     │
     Biometric Device  ──┤     time_logs        │
     Admin Manual Log  ──┤  (immutable ledger)  │
     Approved Correction──┤                     │
                          └──────────┬──────────┘
                                     │
          ┌──────────────────────────┼──────────────────────────┐
          │                          │                          │
          ▼                          ▼                          ▼
   employee_schedule          overtime_requests            leave_requests
   company_config             holidays
          │                          │                          │
          └──────────────────────────┼──────────────────────────┘
                                     │
                                     ▼
                      processDailyAttendance()
                                     │
                                     ▼
                          attendance_sheets
                          (daily worksheets)
                                     │
                                     ▼
                          payroll_period_items
                          (period summaries)
```

**3-layer architecture:**

- **Layer 1**: `time_logs` — raw punch records, append-only, never mutated
- **Layer 2**: `attendance_sheets` — daily computed worksheets, re-computable until locked
- **Layer 3**: `payroll_period_items` — aggregated per pay period, permanently locked on approval

---

## 2. Flow 1: Daily Self-Service Punch

### Primary Actor

Employee (staff/admin/superadmin)

### Steps (4-Punch Model)

1. Employee navigates to **My Attendance** portal
2. Portal displays: today's date, scheduled shift, last punch status, 4 buttons
3. Employee clicks **PUNCH IN** (start of day, e.g., 8:00 AM)
    - System validates: today is a scheduled work day (not a rest day, unless OT is pre-approved)
    - System validates: no existing IN punch for today
    - 5-minute duplicate throttle runs (`lockForUpdate()`)
    - `time_log` written: `type=IN, source=self_service`
4. Employee clicks **LUNCH OUT** (lunch break begins, e.g., 12:00 PM)
    - System validates: an unmatched IN or LUNCH_IN exists
    - `time_log` written: `type=LUNCH_OUT, source=self_service`
5. Employee clicks **LUNCH IN** (lunch break ends, e.g., 1:00 PM)
    - System validates: an unmatched LUNCH_OUT exists
    - `time_log` written: `type=LUNCH_IN, source=self_service`
6. Employee clicks **PUNCH OUT** (end of day, e.g., 5:30 PM)
    - System validates: an unmatched IN or LUNCH_IN exists
    - 5-minute duplicate throttle runs
    - `time_log` written: `type=OUT, source=self_service`
7. After each punch: `processDailyAttendance()` runs for that employee + date
    - **Lunch computation:** `actual_lunch = LUNCH_IN − LUNCH_OUT`. If ≤ 60 min → no penalty. If > 60 min → excess deducted as undertime.
    - **Fallback:** If LUNCH_OUT or LUNCH_IN is missing → falls back to auto-deduction (60 min if span ≥5h + overlaps 11am–2pm).
    - Loads: schedule, config, OT requests, leave requests, holidays
    - Computes: late, undertime (including over-lunch), OT, holiday pay, leave blending
    - Upserts `attendance_sheet`
8. Portal refreshes to show updated status

### Post-conditions

- `time_log` row created (immutable)
- `attendance_sheet` updated with daily wage, late/UT/OT breakdown

### Variations

- **Rest day punch (no OT approved)**: Blocked — "Today is your rest day"
- **Rest day punch (OT approved)**: Allowed, processed with rest day OT rate
- **Punch with partial day worked (any hours)**: Proportional pay applies — `hourly_rate × hours_worked`. No minimum threshold. Only 0 hours = absent.

### When Computation Runs

- **Per-punch (event-driven):** `processDailyAttendance()` runs for the employee + date immediately after every punch. Portal shows real-time status — late warnings, daily wage estimate, OT.
- **Batch sweep (Saturday after shift):** `processBranchAttendance()` re-verifies all sheets for the week. Days with only IN (no OUT) and definitively closed → marked as unexcused absent. Admin shown an alert list of incomplete days to review before approving payroll. Also catches: edge cases, holiday day-before rule lookups that now have complete prior-day data.
- **On correction approval:** Re-runs for that employee + date to regenerate the corrected sheet.

---

## 3. Daily Wage Computation Rules

### Base Formula

```
hourly_rate = daily_rate / 8
```

### Late Deduction — 3-Tier System (No Grace Period)

| Late Minutes | Deduction Formula                           | Example (daily = ₱510, hourly = ₱63.75) |
| ------------ | ------------------------------------------- | --------------------------------------- |
| 0            | ₱0                                          | ₱0                                      |
| 1–19         | `late_min × ₱5`                             | 15 min → ₱75                            |
| 20–59        | Flat ₱100                                   | 25 min → ₱100                           |
| 60+          | `₱100 + floor(late_min / 60) × hourly_rate` | 90 min → ₱100 + ₱63.75 = ₱163.75        |

**Key detail:** Fractional hours past each full hour of lateness are not additionally penalized. 90 min late costs the same as 60 min late: `floor(90/60) = 1`. 150 min late costs the same as 120 min late: `floor(150/60) = 2`.

### Daily Wage Formula

```
# Base pay (late or not)
if late_minutes > 0:
    base_pay = daily_rate − late_deduction
else:
    base_pay = hourly_rate × regular_hours

# Fines (per-day, configurable)
fine_deduction = daily_fines (e.g., ₱20 for no uniform)

# OT pay — 2-block model with rounding:
if ot_worked_minutes == 0:
    ot_pay = 0
else:
    full_hours = floor(ot_worked_minutes / 60)
    remainder  = ot_worked_minutes % 60
    ot_pay = (full_hours × ot_amount_hour) + round_block(remainder)

where round_block(minutes):
    if minutes == 0:       return 0
    elif minutes ≤ 30:     return ot_amount_30min
    elif minutes < 60:     return ot_amount_hour       # round up to 1 hour
```

**Rounding rule:** Any remainder > 30 min rounds to full hour. Any remainder ≤ 30 min pays as a 30-min block.

### Overtime Rates — 2-Block Configuration

```
┌──────────────────────────────────────────────────────────────────────┐
│  Overtime Rates Configuration (per shift type)                       │
├──────────────────────────────────────────────────────────────────────┤
│  Regular Day:      30-min block: [₱50.00]   1-hour block: [₱70.00]  │
│  Rest Day:         30-min block: [₱65.00]   1-hour block: [₱90.00]  │
│  Regular Holiday:  30-min block: [₱100.00]  1-hour block: [₱140.00] │
│  Special Holiday:  30-min block: [₱65.00]   1-hour block: [₱90.00]  │
│  Rest + Holiday:   30-min block: [₱100.00]  1-hour block: [₱140.00] │
│                                                                      │
│  Admin enters two ₱ amounts per shift type. No multipliers.          │
│  Snapshot captured on OT request approval.                           │
│                                            [Save Configuration]      │
└──────────────────────────────────────────────────────────────────────┘
```

| Config Key (per shift type) | Default | Description                  |
| --------------------------- | ------- | ---------------------------- |
| `ot_amount_30min`           | ₱50.00  | Pay for a 30-minute OT block |
| `ot_amount_hour`            | ₱70.00  | Pay for a 1-hour OT block    |

**Examples (regular day, 30min=₱50, 1h=₱70):**

| OT Worked | Computation                            | Paid As | Pay  |
| --------- | -------------------------------------- | ------- | ---- |
| 30 min    | `1 × ₱50`                              | 30 min  | ₱50  |
| 35 min    | `remainder=35, >30 → 1h`               | 1 hour  | ₱70  |
| 40 min    | `remainder=40, >30 → 1h`               | 1 hour  | ₱70  |
| 60 min    | `1 × ₱70`                              | 1 hour  | ₱70  |
| 65 min    | `1 × ₱70 + remainder=5 → 30min block`  | 1h30min | ₱120 |
| 80 min    | `1 × ₱70 + remainder=20 → 30min block` | 1h30min | ₱120 |
| 90 min    | `1 × ₱70 + remainder=30 → 30min block` | 1h30min | ₱120 |
| 95 min    | `1 × ₱70 + remainder=35 → 1h`          | 2 hours | ₱140 |
| 120 min   | `2 × ₱70`                              | 2 hours | ₱140 |
| 150 min   | `2 × ₱70 + remainder=30 → 30min block` | 2h30min | ₱190 |

### Fines

- **Per-day flat fines** for policy violations. Example: ₱20 fine if employee did not wear uniform.
- Configurable per fine type in `company_configurations`. Multiple fine types can be stacked.
- Applied as a deduction on the daily attendance sheet.
- Fines are triggered by admin marking the violation on the employee's daily record (via attendance sheets page or correction flow).

### Full Scenario Matrix (daily_rate = ₱510)

| #   | Scenario                  | Late | Worked | Computation          | daily_wage  |
| --- | ------------------------- | ---- | ------ | -------------------- | ----------- |
| 1   | On time, full day         | 0    | 8h     | `510`                | **₱510.00** |
| 1a  | On time, no uniform (₱20) | 0    | 8h     | `510 − 20`           | **₱490.00** |
| 2   | Late 10 min               | 10   | 7.83h  | `510 − 50`           | **₱460.00** |
| 3   | Late 15 min               | 15   | 7.75h  | `510 − 75`           | **₱435.00** |
| 4   | Late 19 min               | 19   | 7.68h  | `510 − 95`           | **₱415.00** |
| 5   | Late 20 min               | 20   | 7.67h  | `510 − 100`          | **₱410.00** |
| 6   | Late 45 min               | 45   | 7.25h  | `510 − 100`          | **₱410.00** |
| 7   | Late 60 min (1h)          | 60   | 7h     | `510 − 100 − 63.75`  | **₱346.25** |
| 8   | Late 90 min (1.5h)        | 90   | 6.5h   | `510 − 100 − 63.75`  | **₱346.25** |
| 9   | Late 120 min (2h)         | 120  | 6h     | `510 − 100 − 127.50` | **₱282.50** |
| 10  | Late 150 min (2.5h)       | 150  | 5.5h   | `510 − 100 − 127.50` | **₱282.50** |
| 11  | Late 180 min (3h)         | 180  | 5h     | `510 − 100 − 191.25` | **₱218.75** |
| 12  | Not late, left early (5h) | 0    | 5h     | `63.75 × 5`          | **₱318.75** |
| 13  | Not late, left early (2h) | 0    | 2h     | `63.75 × 2`          | **₱127.50** |

### Lunch — 4-Punch Measured Model

Shift is **8:00 AM – 5:00 PM** (paid). **5:00–5:30 PM** is outside the paid shift — unpaid unless OT is approved.

**Punches:** `IN` → `LUNCH_OUT` → `LUNCH_IN` → `OUT`

```
morning_work   = LUNCH_OUT − IN
afternoon_work = cap(OUT, schedule_end 5:00 PM) − LUNCH_IN
actual_lunch   = LUNCH_IN − LUNCH_OUT (measured, not assumed)
```

**Lunch rules:**

| actual_lunch | Impact                                   |
| ------------ | ---------------------------------------- |
| ≤ 60 min     | Normal — no penalty                      |
| > 60 min     | Excess minutes **deducted as undertime** |

Example: lunch 11:00 AM – 12:45 PM = 1h45min → 45 min over → paid = 8h − 0.75h = 7.25h

**Fallback (missing lunch punches):** If LUNCH_OUT or LUNCH_IN is missing → falls back to auto-deduction:

- 60 minutes deducted if raw duration ≥ 5 hours AND work period overlaps **11:00 AM – 2:00 PM**
- Otherwise 0 deduction

### Government Deductions (SSS, PhilHealth, Pag-IBIG)

**Computed on regular monthly salary (daily_rate × 26), NOT on variable attendance earnings.** Same deduction every week regardless of absences, overtime, or partial days.

#### SSS — Bracket-Based

```
monthly_salary = daily_rate × 26
bracket = find bracket where salary_min ≤ monthly_salary ≤ salary_max
sss_weekly = (monthly_salary × bracket.employee_percentage / 100) / 4
```

| Field      | Value                                                                  |
| ---------- | ---------------------------------------------------------------------- |
| Employee % | 5.00 (per bracket, admin-configurable)                                 |
| Employer % | 10.00 (per bracket, tracked, not deducted from employee)               |
| Table      | `sss_contribution_brackets` — 20 brackets up to ₱20,000 monthly salary |

**SSS Bracket Management Form (Superadmin only):**

```
┌──────────────────────────────────────────────────────────────────────┐
│  SSS Contribution Brackets                                           │
│  Effective from: [2026-01-01]                                        │
├──────────────────────────────────────────────────────────────────────┤
│  #  │ Salary Min    │ Salary Max    │ Employee %  │ Employer %      │
│  1  │ [₱1.00    ]   │ [₱4,250.00]  │ [4.50   ]   │ [8.50   ]       │
│  2  │ [₱4,251.00]   │ [₱5,250.00]  │ [4.50   ]   │ [8.50   ]       │
│  3  │ [₱5,251.00]   │ [₱6,250.00]  │ [4.50   ]   │ [8.50   ]       │
│  …  │               │              │             │                 │
│  20 │ [₱19,751.00]  │ [₱20,000.00] │ [4.50   ]   │ [8.50   ]       │
├──────────────────────────────────────────────────────────────────────┤
│  [+ Add Bracket]                              [Save All Brackets]   │
└──────────────────────────────────────────────────────────────────────┘
```

- Admin can add/remove rows. Empty Salary Max defaults to unbounded.
- Changes take effect from `effective_from` date. Historical brackets preserved for past payrolls.

#### PhilHealth — Percentage-Based (50/50 Split)

```
monthly_salary = daily_rate × 26
phic_weekly = (monthly_salary × philhealth_premium_percentage / 100 × 0.50) / 4
```

| Field     | Value                                              |
| --------- | -------------------------------------------------- |
| Premium % | 5.00 (configurable via `company_configurations`)   |
| Split     | 50% employer / 50% employee (hardcoded per PH law) |

#### Pag-IBIG — Flat Amount

```
pagibig_weekly = pagibig_monthly_employee_share / 4
```

| Field          | Value                                                                         |
| -------------- | ----------------------------------------------------------------------------- |
| Employee Share | ₱100/month (configurable via `company_configurations`)                        |
| Employer Share | ₱100/month (configurable via `company_configurations`, tracked, not deducted) |

**PhilHealth & Pag-IBIG Config Form (Superadmin — Company Configuration page):**

```
┌──────────────────────────────────────────────────────────────────────┐
│  Government Contributions Configuration                              │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  PhilHealth                                                          │
│    Premium Percentage:  [5.00  ] %  (split: 50% employer / 50% employee) │
│                                                                      │
│  Pag-IBIG                                                            │
│    Monthly Employee Share:  [₱100.00]                                │
│    Monthly Employer Share:  [₱100.00]  (tracked, not deducted)       │
│                                                                      │
│  ────────────────────────────────────────────────────────────────    │
│  Monthly salary = daily_rate × 26                                     │
│  All deductions ÷ 4 for weekly payroll                                │
│                                                                      │
│                                        [Save Configuration]          │
└──────────────────────────────────────────────────────────────────────┘
```

#### Rules

- **Deducted every week.** Amounts fixed; not divided differently in 4-week vs 5-week months.
- **Per-benefit conditional:** Only deducted if the employee has filled up the corresponding ID number (`sss_number` / `philhealth_number` / `pagibig_number`). Empty ID → that specific deduction skipped.
- **SSS brackets managed by superadmin** on a dedicated page. PhilHealth/Pag-IBIG amounts on company config page.
- Employer shares tracked for reporting but NOT deducted from employee pay.

### Cash Advances (CA)

- **Maximum CA = receivable for the current payroll period.** Employee cannot borrow more than what they would earn this period.
- **Approval-required:** Employee requests CA → admin/superadmin approves. Reason required.
- **One active CA at a time:** Employee can only have one pending or unpaid CA. New requests blocked until existing CA is fully paid.
- **Deducted from net pay:** Deduction happens after government contributions during payroll computation.
- **No interest.** Unpaid balance carries over to the next payroll period until fully settled.
- **Net pay formula:** `net_pay = gross_pay − govt_deductions − ca_deduction`

---

## 4. Flow 2: Manual Attendance Correction (Approval-Gated)

### Primary Actors

Requestor: Staff or Admin
Approver: Admin (for staff) or Superadmin (for admin)

### Steps

1. **Submit Request**
    - Requestor navigates to **My Attendance** (or employee's record if admin)
    - Clicks **Report Issue** / **Request Correction** on a specific date
    - Fills form: correction type, requested time(s), reason (required)
    - Submits → `attendance_correction_request` created with `status=pending`

2. **System Validates**
    - Only one pending request per `(employee_id, date, correction_type)`
    - Requestor scoping: staff = self-only, admin = anyone in branch

3. **Admin/Superadmin Reviews**
    - Navigates to **Corrections** queue (sidebar badge shows pending count)
    - Sees request: employee name, date, type, requested times, reason
    - Self-approval blocked: "You cannot approve your own request"
    - Superior-only enforced: staff request → admin must approve; admin request → superadmin must approve

#### If APPROVED:

4. System creates `time_log`(s) with `source=correction`
5. Links `correction.resolved_time_log_id` to created log
6. Re-runs `processDailyAttendance()` for that employee + date
7. `attendance_sheet` regenerated with corrected data
8. Correction status → `approved`, `reviewed_by` + `reviewed_at` set

#### If DENIED:

4. Reviewer must provide `denial_reason` (required)
5. Correction status → `denied`
6. Requester notified (toast/message)

### Correction Types

| Type                | What it means                              | Time logs created on approval         |
| ------------------- | ------------------------------------------ | ------------------------------------- |
| `missed_punch_in`   | Employee forgot to punch IN                | Creates IN log at requested time      |
| `missed_punch_out`  | Employee forgot to punch OUT               | Creates OUT log at requested time     |
| `time_adjustment`   | Existing punch time is wrong               | Creates both IN and OUT override logs |
| `absent_to_present` | Employee was marked absent but was present | Creates both IN and OUT logs          |

### Post-conditions

- Approved: new `time_log`(s), updated `attendance_sheet`, completed correction request
- Denied: reason stored, no attendance changes

---

## 5. Flow 3: Admin Direct Manual Log

### Primary Actor

Admin (branch) or Superadmin

### Trigger

Biometric device down, supervisor physically verified attendance, or any scenario where admin needs to directly record a punch without going through approval.

### Steps

1. Admin navigates to **Attendance Sheets** view
2. Selects date, finds employee row
3. Clicks **Add Manual Log**
4. Fills: punch type (IN/OUT), timestamp, optional note
5. Submits → `time_log` created with `source=manual` (no approval needed)
6. Audit log auto-generated via `Auditable` trait
7. `processDailyAttendance()` re-runs for that employee + date

### Post-conditions

- Trusted `time_log` created immediately
- Audit trail recorded (who created it, when, old sheet state)
- Attendance sheet updated

### Constraints

- Staff cannot create manual logs
- Admin scoped to own branch only
- Available even on rest days (admin override)

---

## 6. Flow 4: Payroll Period Generation

### Primary Actor

Admin (branch) or Superadmin

### Schedule

- **Weekly, every Saturday after shift.** Payroll covers the completed work week (Monday–Saturday).
- Admin triggers generation after Saturday's shift ends and all attendance sheets are finalized.

### Steps

1. Admin clicks **Generate Payroll Period** (available Saturday after shift)
2. System auto-selects the just-completed week (Mon–Sat) as the period range
3. `PayrollPeriodService` runs within a transaction:
    - Locks all `attendance_sheets` in the date range (sets `locked_at`)
    - Creates `payroll_period` record (`status=draft`)
    - For each ACTIVE employee in branch:
        - Aggregates all locked sheets into one `payroll_period_item`
        - Computes: total_regular_days, absent_days, late/UT/OT minutes, holiday_pay_days, leave_paid_days
        - Computes: gross_pay, late_deduction, undertime_deduction, overtime_pay, holiday_pay, fine_deduction
        - **Government deductions — computed on regular monthly salary (daily_rate × 26), not on variable attendance earnings:**
            - **SSS:** `(daily_rate × 26 × bracket.employee_percentage / 100) / 4` — bracket looked up from `sss_contribution_brackets`.
            - **PhilHealth:** `(daily_rate × 26 × philhealth_premium_percentage / 100 × 0.50) / 4` — 50/50 split.
            - **Pag-IBIG:** `pagibig_monthly_employee_share / 4` — flat ₱100/month.
            - Per-benefit conditional — only deducted if employee has filled up the corresponding ID number. Same amount every week regardless of absences or OT.
        - **Cash advance deduction:** Deducted if employee has an active CA. Amount = min(remaining_balance, net_receivable). Balance carries over.
        - Stores: `net_pay` = `gross_pay − govt_deductions − ca_deduction`
4. System shows **incomplete days alert** — list of employees with days flagged as absent due to missing OUT punch (IN only, no OUT). Admin can review and manually correct before proceeding.
5. Admin reviews line items on the period detail page
6. Admin clicks **Approve**
7. Period `status` → `approved`, `approved_by` + `approved_at` set
8. Items permanently locked

### Void Flow (Superadmin only)

1. Superadmin navigates to approved/paid period
2. Clicks **Void Period**
3. All constituent `attendance_sheets` unlocked (`locked_at` = null)
4. Period `status` → reverted to `draft` (or marked as voided)
5. Corrections can now be made → re-generate → re-approve

### Constraints

- Sheets cannot be individually unlocked — only full period voidal
- Only superadmin can void
- Void requires audit logging

---

## 7. Flow 5: Overtime Approval

### Primary Actors

Requestor: Employee or Admin
Approver: Admin or Superadmin

### Steps

1. **Request OT**
    - Requestor selects: date, hours needed, reason
    - System auto-resolves `shift_type` by checking:
        - Is the date a rest day? → `rest_day`
        - Is the date a regular holiday? → `regular_holiday`
        - Is the date a special holiday? → `special_holiday`
        - Otherwise → `regular_day`
    - `overtime_request` created (`status=pending`)

2. Admin/superadmin reviews and **approves**
    - `ot_amount_30min` and `ot_amount_hour` snapshots captured at approval time (prevents rate changes from affecting historical OT)
    - `shift_type` + `approved_by` + `approved_at` recorded

3. **Later: processDailyAttendance()** runs
    - Finds approved OT request for employee + date
    - Compares actual extra stay against approved minutes
    - **Lower-of-two rule**: `ot_worked_minutes = min(actual_extra, approved_request)`
    - Unapproved extra minutes are discarded
    - OT must also meet `ot_threshold_minutes` (e.g., 60 consecutive minutes)
    - **Rounding rule:** After threshold met, pay computed as:
        - Full hours × `ot_amount_hour`
        - Remainder ≤ 30 min → + `ot_amount_30min`
        - Remainder 31–59 min → + `ot_amount_hour` (rounds up to 1 hour)

### OT Rate Selection (2-Block, Flat Amounts)

```
30-min block = ₱50 | 1-hour block = ₱70 (regular day defaults)
```

| Shift Type         | 30-min Block      | 1-hour Block      |
| ------------------ | ----------------- | ----------------- |
| regular_day        | ₱50.00            | ₱70.00            |
| rest_day           | ₱65.00            | ₱90.00            |
| regular_holiday    | ₱100.00           | ₱140.00           |
| special_holiday    | ₱65.00            | ₱90.00            |
| rest_day + holiday | higher of the two | higher of the two |

| OT Worked | Computation                  | Paid As | Pay  |
| --------- | ---------------------------- | ------- | ---- |
| 30 min    | 1 × ₱50                      | 30 min  | ₱50  |
| 40 min    | 1 × ₱70                      | 1 hour  | ₱70  |
| 80 min    | 1 × ₱70 + remaining 20 → ₱50 | 1h30min | ₱120 |
| 95 min    | 1 × ₱70 + remaining 35 → ₱70 | 2 hours | ₱140 |
| 150 min   | 2 × ₱70 + remaining 30 → ₱50 | 2h30min | ₱190 |

### Post-conditions

- Approved OT request exists for cross-reference during attendance computation
- OT rate frozen at approval time

---

## 8. Flow 6: Leave Request

### Primary Actors

Requestor: Employee or Admin
Approver: Admin or Superadmin

### Steps

1. **Request Leave**
    - Requestor selects: date, leave type, duration, paid/unpaid, reason
    - `leave_request` created (`status=pending`)

2. Admin/superadmin **approves** or **denies**

3. **Later: processDailyAttendance()** blends leave with worked hours

### Leave Blending Rules

| Leave Duration     | Actual Worked       | Result                                                          |
| ------------------ | ------------------- | --------------------------------------------------------------- |
| Full day (paid)    | Any (incl. 0)       | Full day credited, 100% paid                                    |
| Full day (unpaid)  | Any                 | Hours credited, daily_wage = 0 for leave portion                |
| Half-day morning   | Afternoon ≥ 4 hours | Full day (4h leave + 4h+ worked)                                |
| Half-day morning   | Afternoon < 4 hours | Leave portion credited (4h). Worked portion = proportional pay. |
| Half-day afternoon | Morning ≥ 4 hours   | Full day (4h+ worked + 4h leave)                                |
| Half-day afternoon | Morning < 4 hours   | Leave portion credited. Worked portion = proportional pay.      |

### Leave Types

- vacation, sick, emergency, maternity, paternity, bereavement, unpaid

### Leave Balance

- **5 paid leaves per year.** Tracked via `leaves_used_this_year` on employee. Decremented on each approved paid leave.
- **Admin discretion beyond 5:** If balance is 0, admin can still approve. System shows "Balance: 0 of 5 used" as a warning but does not block approval.
- **Unpaid leave** does not count against the 5-leave balance.
- **Balance resets January 1** each year.

---

## 9. Flow 7: Holiday Pay Resolution

### Context

`processDailyAttendance()` checks holidays whenever it runs.

### Regular Holiday — Unworked

```
Check: Did the employee work on the holiday date?
  → NO (unworked)

Find last scheduled working day before the holiday:
  Walk backward, skip: rest days, Sundays, other holidays

Check last working day's attendance sheet:
  → is_present OR absence_type = 'approved_leave'
    → holiday_pay = 100% of daily rate
  → absent without leave
    → holiday_pay = 0%
```

**Example:** Holiday on Monday → walks back: Sun (rest) → Sat (rest) → Friday (check Friday's sheet)

### Regular Holiday — Worked

- `holiday_pay_percent = 200` (regardless of day-before status)

### Special Non-Working Day

- Unworked → `holiday_pay_percent = 0` (no work, no pay)
- Worked → `holiday_pay_percent = 130`

---

## 10. Flow 8: Cash Advance Request & Deduction

### Primary Actors

Requestor: Employee or Admin
Approver: Admin or Superadmin

### Request Flow

1. Employee (or admin on their behalf) requests cash advance
2. System calculates **maximum CA** = projected net receivable for the current payroll period
3. Employee selects amount (≤ maximum), provides reason
4. Admin/superadmin approves or denies
5. If approved: CA record created, `status = unpaid`, `remaining_balance = amount`

### Deduction Flow (during Payroll Period Generation)

1. For each employee with an active CA (`remaining_balance > 0`):
    - Deduction = `min(remaining_balance, net_pay_before_ca)`
    - Net pay after deduction must not go below 0
2. `remaining_balance` reduced by the deducted amount
3. If `remaining_balance = 0` → CA marked as `paid`
4. If `remaining_balance > 0` → balance carries over to next payroll period

### Constraints

- **One active CA at a time:** New requests blocked until existing CA is fully paid
- **Maximum = receivable for current period:** Prevents over-borrowing
- **No interest**
- **Balance carries over** automatically across payroll periods

---

## 11. Payslip Design

### Header Section

```
┌──────────────────────────────────────────────────────────────────┐
│  PRINTING SHOP MANAGEMENT                                        │
│  Branch: Babak                                                  │
│                                                                  │
│  PAYSLIP — Weekly                                                │
│  Period: May 18–23, 2026 (Week 3 · Mon–Sat)                     │
│                                                                  │
│  Employee:  Juan Dela Cruz          Position:  Regular           │
│  Emp #:     EMP-2026-0001           Daily Rate: ₱510.00          │
│  SSS:       12-3456789-0            PhilHealth: 12-345678901-2   │
│  Pag-IBIG:  1234-5678-9012          TIN:        —                │
│  Monthly Salary: ₱13,260 (daily × 26)  ·  SSS Bracket #7         │
│  -----------------------------------------------------------------│
│  Attendance Summary:  Present 5  Late 1 (15min)  OT 0h  Absent 0  Holiday 0     │
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
│  Generated: May 23, 2026 (Sat, after shift)   Period Status: Paid/
│                                                                  │
│  Employee: ____________________    Date: ___________________     │
│                                                                  │
│  Prepared by: _________________    Date: ___________________     │
│  Approved by: _________________    Date: ___________________     │
└──────────────────────────────────────────────────────────────────┘
```

### Variant: Employee with Missing Government IDs

```
┌──────────────────────────────────────────────────────────────────┐
│  Employee:  Maria Santos            Position:  Contractual        │
│  Emp #:     EMP-2026-0004           Daily Rate: ₱510.00           │
│  SSS:       12-3456789-0            PhilHealth: —                 │
│  Pag-IBIG:  —                       TIN:        —                 │
│  -----------------------------------------------------------------│
│  Attendance Summary:  Present 5  Late 0  OT 0h  Absent 0          │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────┬──────────────────────────────────┐
│         EARNINGS             │           DEDUCTIONS              │
├──────────────────────────────┼──────────────────────────────────┤
│ Basic Pay         ₱2,550.00  │ SSS (5%)              −₱165.75   │
│ Overtime          —          │ Fine (No Uniform)      −₱20.00   │
│                              │ PhilHealth             —          │
│                              │ Pag-IBIG               —          │
│                              │ Cash Advance           —          │
├──────────────────────────────┼──────────────────────────────────┤
│  GROSS PAY       ₱2,550.00   │  TOTAL DEDUCTIONS    −₱185.75    │
└──────────────────────────────┴──────────────────────────────────┘

                          ┌───────────────────────┐
                          │  NET PAY  ₱2,364.25    │
                          └───────────────────────┘
```

### Variant: Holiday Week (Regular Holiday — Worked)

```
┌──────────────────────────────────────────────────────────────────┐
│  Employee:  Juan Dela Cruz          Position:  Regular           │
│  Emp #:     EMP-2026-0001           Daily Rate: ₱510.00          │
│  SSS:       12-3456789-0            PhilHealth: 12-345678901-2   │
│  Pag-IBIG:  1234-5678-9012          TIN:        —                │
│  -----------------------------------------------------------------│
│  Attendance Summary:  Present 3  Late 1  OT 0h  Absent 0  Holiday 1│
│  (Araw ng Kagitingan — Apr 9 — Regular Holiday, Worked)          │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────┬──────────────────────────────────┐
│         EARNINGS             │           DEDUCTIONS              │
├──────────────────────────────┼──────────────────────────────────┤
│ Basic Pay         ₱2,040.00  │ Late (Tue, 15min × ₱5)  −₱75.00   │
│ Overtime          —          │ Fine (No Uniform)      −₱20.00   │
│ Holiday Pay (200%)+₱1,020.00 │ SSS (5%)              −₱165.75   │
│                              │ PhilHealth (2.50%)     −₱82.88   │
│                              │ Pag-IBIG               −₱25.00   │
│                              │ Cash Advance           −₱200.00  │
├──────────────────────────────┼──────────────────────────────────┤
│  GROSS PAY       ₱3,060.00   │  TOTAL DEDUCTIONS    −₱568.63    │
└──────────────────────────────┴──────────────────────────────────┘

                          ┌───────────────────────┐
                          │  NET PAY  ₱2,491.37    │
                          └───────────────────────┘
```

### Variant: Holiday Week (Regular Holiday — Unworked, Day-Before Present)

```
┌──────────────────────────────────────────────────────────────────┐
│  Employee:  Pedro Luna              Position:  Project-Based      │
│  Emp #:     EMP-2026-0007           Daily Rate: ₱510.00           │
│  -----------------------------------------------------------------│
│  Attendance Summary:  Present 4  Late 0  OT 0h  Absent 0  Holiday 1│
│  (Regular Holiday — Unworked. Day-before present → 100% paid.)    │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────┬──────────────────────────────────┐
│         EARNINGS             │           DEDUCTIONS              │
├──────────────────────────────┼──────────────────────────────────┤
│ Basic Pay         ₱2,040.00  │ SSS (5%)              −₱165.75   │
│ Overtime          —          │ PhilHealth (2.50%)     −₱82.88   │
│ Holiday Pay (100%)  +₱510.00 │ Pag-IBIG               −₱25.00   │
│                              │ Cash Advance           —          │
├──────────────────────────────┼──────────────────────────────────┤
│  GROSS PAY       ₱2,550.00   │  TOTAL DEDUCTIONS    −₱273.63    │
└──────────────────────────────┴──────────────────────────────────┘

                          ┌───────────────────────┐
                          │  NET PAY  ₱2,276.37    │
                          └───────────────────────┘
```

### Holiday Pay Scenarios

| Holiday Type | Worked? | Day-Before          | Label on Payslip     | Amount                    |
| ------------ | ------- | ------------------- | -------------------- | ------------------------- |
| Regular      | Yes     | —                   | `Holiday Pay (200%)` | daily_rate × 2.00         |
| Regular      | No      | Present or on Leave | `Holiday Pay (100%)` | daily_rate × 1.00         |
| Regular      | No      | Absent (unexcused)  | `Holiday Pay (0%)`   | ₱0 (not shown on payslip) |
| Special      | Yes     | —                   | `Holiday Pay (130%)` | daily_rate × 1.30         |
| Special      | No      | —                   | Not shown            | ₱0 (no work, no pay)      |

### Design Rules

| Rule                | Detail                                                                                                                 |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| IDs in header       | SSS, PhilHealth, Pag-IBIG, TIN displayed. Missing IDs shown as `—`.                                                    |
| No daily rows       | Replaced by compact Attendance Summary line (Present, Late, OT hours, Absent, Holiday).                                |
| Two-column body     | Earnings on left (Basic Pay, Overtime, Holiday Pay). Deductions on right (Late, Fines, Gov't, Cash Advance).           |
| Holiday pay         | Shown as separate line item under Earnings with percentage label (100%, 130%, 200%). 0% holidays not shown.            |
| Deduction ordering  | Late + Fines first (behavioral), then statutory (SSS/PhilHealth/Pag-IBIG), then voluntary (Cash Advance).              |
| Missing gov't IDs   | Deduction line shown as `—` and no amount deducted. Only benefits with filled IDs are deducted.                        |
| Net pay ≥ 0         | Cash advance deduction capped so net pay never goes negative. Remaining balance carries over.                          |
| Overtime visibility | 2-block rates shown (e.g., `30min=₱50, 1h=₱70`) and billed duration (e.g., `1h30min`).                                 |
| Signature blocks    | Employee, preparer, and approver signature lines in footer.                                                            |
| Contribution basis  | All government deductions computed on `daily_rate × 26` (regular monthly salary). NOT on variable attendance earnings. |
| Delivery            | PDF download available from portal + view in-portal. Generated via Chrome headless on period approval.                 |

---

## 12. Use Cases by Role

### Branch Scoping Rule

> **Admins operate ONLY on employees belonging to their assigned branch.** Admin cannot access, modify, or approve records for employees from other branches. The only exception is the Special Group (Babak, Peñaplata, Tibungco) where admins share access across these three branches.

### 12.1 Staff Use Cases

| #   | Use Case                    | Frequency  | Branch Scope | Notes                                            |
| --- | --------------------------- | ---------- | ------------ | ------------------------------------------------ |
| S1  | Punch IN at start of shift  | Daily      | Self only    | Blocked on rest days                             |
| S2  | Punch OUT at end of shift   | Daily      | Self only    | Needs unmatched IN                               |
| S3  | View own attendance history | Weekly     | Self only    | Calendar + recent history on My Attendance page  |
| S4  | Submit correction request   | Occasional | Self only    | Self-only; requires reason; needs admin approval |
| S5  | Submit OT request           | Occasional | Self only    | Self only; needs admin approval                  |
| S6  | Submit leave request        | Occasional | Self only    | Self only; needs admin approval                  |
| S7  | View own payslip            | Weekly     | Self only    | After payroll period approved (Sat)              |
| S8  | Request cash advance        | Occasional | Self only    | Max = receivable for current period. Self only.  |

### 12.2 Admin Use Cases

| #   | Use Case                                   | Frequency    | Branch Scope | Notes                                                                                    |
| --- | ------------------------------------------ | ------------ | ------------ | ---------------------------------------------------------------------------------------- |
| A1  | Punch IN/OUT (self)                        | Daily        | Self only    | Same as staff flow                                                                       |
| A2  | View branch attendance sheets              | Daily/weekly | Branch only  | Employees in admin's branch only (or special group).                                     |
| A3  | Create manual time_log for branch employee | Occasional   | Branch only  | Direct, no approval; for biometric-down scenarios. Employee must belong to admin branch. |
| A4  | Submit correction for any branch employee  | Occasional   | Branch only  | Needs superadmin approval (superior-only rule).                                          |
| A5  | Review + approve/deny staff corrections    | Daily        | Branch only  | Sidebar badge shows pending count. Staff must be in admin branch.                        |
| A6  | Review + approve/deny OT requests          | Weekly       | Branch only  | Requestor must be in admin branch.                                                       |
| A7  | Review + approve/deny leave requests       | Weekly       | Branch only  | Requestor must be in admin branch. Shows leave balance. Approve past 0 at discretion.    |
| A8  | Manage employee schedules                  | Occasional   | Branch only  | Create/edit/assign schedule templates for branch employees.                              |
| A9  | Generate payroll period                    | Weekly (Sat) | Branch only  | Locks sheets, creates period + items for branch employees.                               |
| A10 | Approve payroll period                     | Weekly (Sat) | Branch only  | Finalizes period for branch.                                                             |
| A11 | View branch payslips                       | Weekly       | Branch only  | After period approved, per branch employee.                                              |
| A12 | Approve/deny cash advance requests         | Occasional   | Branch only  | Requestor must be in admin branch. Enforce max amount limit.                             |
| A13 | Mark fine on employee daily record         | Occasional   | Branch only  | Employee must be in admin branch.                                                        |
| A14 | View employee detail (full profile)        | Occasional   | Branch only  | Salary history, gov't IDs, schedule, leave balance — for branch employees.               |
| A15 | View daily attendance summary dashboard    | Daily        | Branch only  | Present/absent/late counts, clocked-in employees, pending corrections for branch.        |
| A16 | Deactivate / rehire employee               | Rare         | Branch only  | Employee must be in admin branch.                                                        |

### 12.3 Superadmin Use Cases

| #    | Use Case                               | Frequency  | Branch Scope  | Notes                                                                               |
| ---- | -------------------------------------- | ---------- | ------------- | ----------------------------------------------------------------------------------- |
| SA1  | All admin use cases (global scope)     | —          | All branches  | Across all branches                                                                 |
| SA2  | Manage holidays                        | Annual     | All branches  | Add/edit regular + special holidays                                                 |
| SA3  | Edit company configuration             | Rare       | Global config | Late deduction tiers, OT flat per-hour amounts, lunch deduction, fines, holiday pay |
| SA4  | Approve admin-submitted corrections    | Occasional | All branches  | Superior-only rule: admin needs superadmin                                          |
| SA5  | Approve admin-submitted OT/leave       | Occasional | All branches  | When admin submits for themselves                                                   |
| SA6  | Void payroll period                    | Rare       | All branches  | Unlocks all sheets in period; enables corrections                                   |
| SA7  | Approve admin cash advance requests    | Occasional | All branches  | When admin submits for themselves (superior-only)                                   |
| SA8  | View global attendance across branches | As needed  | All branches  | Unfiltered branch scope                                                             |
| SA9  | Manage government contributions        | Annual     | Global config | Edit SSS brackets, PhilHealth premium %, Pag-IBIG share.                            |
| SA10 | Manage users (create/edit/deactivate)  | Rare       | All branches  | Add/remove admin and staff accounts, reset passwords. Branch assignment.            |

---

## 13. Role-Based Access Control (RBAC) — Payroll Domain

### Policy Files

All payroll/attendance RBAC is enforced through dedicated Policy classes registered in `AppServiceProvider`. Each policy defines `viewAny`, `view`, `create`, `update`, `delete`, and domain-specific gate methods (e.g., `approve`, `void`, `rehire`).

| Policy                    | Model               | Registered At                  | Key Rules                                                                                |
| ------------------------- | ------------------- | ------------------------------ | ---------------------------------------------------------------------------------------- |
| `EmployeePolicy`          | `Employee`          | `payroll/Employee/Policies/`   | Branch-scoped view/update for admin; create for admin+superadmin; delete superadmin only |
| `TimeLogPolicy`           | `TimeLog`           | `payroll/Attendance/Policies/` | Staff: self punch only. Admin: manual log for branch employees. Superadmin: all.         |
| `AttendanceSheetPolicy`   | `AttendanceSheet`   | `payroll/Attendance/Policies/` | Staff: view own. Admin: view/edit branch. Superadmin: all.                               |
| `OvertimeRequestPolicy`   | `OvertimeRequest`   | `payroll/Attendance/Policies/` | Staff: submit self. Admin: approve branch + submit self. Superadmin: approve all.        |
| `LeaveRequestPolicy`      | `LeaveRequest`      | `payroll/Attendance/Policies/` | Staff: submit self. Admin: approve branch + submit self. Superadmin: approve all.        |
| `CorrectionRequestPolicy` | `CorrectionRequest` | `payroll/Attendance/Policies/` | Staff: submit self. Admin: submit for branch + approve staff. Superadmin: approve all.   |
| `CashAdvancePolicy`       | `CashAdvance`       | `payroll/Attendance/Policies/` | Staff: request self. Admin: approve branch. Superadmin: approve all.                     |
| `PayrollPeriodPolicy`     | `PayrollPeriod`     | `payroll/Attendance/Policies/` | Admin: generate + approve + view branch. Superadmin: all + **void**.                     |
| `HolidayPolicy`           | `Holiday`           | `payroll/Attendance/Policies/` | Superadmin only: create, update, delete. Admin/Staff: view only.                         |
| `CompanyConfigPolicy`     | `CompanyConfig`     | `payroll/Attendance/Policies/` | Superadmin only: edit OT rates, SSS brackets, fines, PhilHealth, Pag-IBIG.               |
| `FinePolicy`              | `Fine`              | `payroll/Attendance/Policies/` | Admin: mark branch employee. Superadmin: mark any. Superadmin: manage fine types.        |
| `AuditLogPolicy`          | `AuditLog`          | `payroll/Audit/Policies/`      | Branch-scoped viewing. No create/update/delete.                                          |

### RBAC Matrix (Complete)

| Action                             | Staff     | Admin                 | Superadmin        |
| ---------------------------------- | --------- | --------------------- | ----------------- |
| Punch IN/OUT                       | Self only | Self only             | Self only         |
| View own attendance                | ✓         | ✓                     | ✓                 |
| View branch attendance             | —         | Branch only           | All branches      |
| Create manual time_log             | —         | Branch employees only | All employees     |
| Submit correction request          | Self only | Branch + self         | All               |
| Approve correction request         | —         | Staff in branch       | All (incl. admin) |
| Submit OT request                  | Self only | Self only             | Self only         |
| Approve OT request                 | —         | Branch employees      | All               |
| Submit leave request               | Self only | Self only             | Self only         |
| Approve leave request              | —         | Branch employees      | All               |
| Request cash advance               | Self only | Self only             | Self only         |
| Approve cash advance               | —         | Branch employees      | All               |
| Manage employee schedules          | —         | Branch employees      | All               |
| Mark fine on employee              | —         | Branch employees      | All               |
| Manage fine types/amounts          | —         | —                     | ✓                 |
| Generate payroll period            | —         | Branch only           | All branches      |
| Approve payroll period             | —         | Branch only           | All branches      |
| Void payroll period                | —         | —                     | ✓                 |
| View payslip                       | Own only  | Branch employees      | All               |
| View employee profile              | Own only  | Branch employees      | All               |
| Create employee (onboarding)       | —         | Branch only           | All branches      |
| Update employee                    | —         | Branch only           | All branches      |
| Deactivate employee                | —         | —                     | ✓                 |
| Rehire employee                    | —         | Branch only           | All branches      |
| Manage holidays                    | —         | —                     | ✓                 |
| Edit company config (OT/SSS/PHIC/) | —         | —                     | ✓                 |
| View audit logs                    | —         | Branch only           | All branches      |

### Branch Isolation Guarantee

```
staff_1 (Branch A) → can see:     self only
staff_1 (Branch A) → cannot see:  staff_2 (Branch B) or any other branch

admin_A (Branch A) → can see:     all employees in Branch A
admin_A (Branch A) → cannot see:  employees in Branch B, Davao, etc.

superadmin → can see:             all employees in all branches
```

### Superior-Only Approval Rule

```
Requestor → Approver
─────────────────────
Staff     → Admin (same branch) or Superadmin
Admin     → Superadmin (never self-approved)
```

---

## 14. Tradeoffs & Implementation Notes

### Changes When Adding RBAC

| Area                | Before (No RBAC)                                | After (With Policies)                                  |
| ------------------- | ----------------------------------------------- | ------------------------------------------------------ |
| Code size           | Controllers grow with inline role checks        | Policies + controller calls: `$this->authorize()`      |
| Security            | Relies on query scoping + controller if-checks  | Gate-enforced at policy layer, fails closed            |
| Debugging           | Inline checks scattered across 10+ methods      | Centralized in ~10 policy files                        |
| Refactoring         | Change one rule = hunt through all controllers  | Change one policy method                               |
| Onboarding new devs | Must understand every controller's inline logic | Read one policy file per resource                      |
| Audit readiness     | Manual — missed checks are silent gaps          | Every action is explicitly authorized before execution |

### Tradeoffs

**Pros:**

- **Fail-closed**: Unauthorized actions are blocked at the gate, not just hidden in UI
- **Single source of truth**: Policy files document who can do what — no guessing from controller code
- **Testable in isolation**: Policy unit tests are fast (~2ms each), don't need HTTP layer
- **Reusable across channels**: Same policies protect web, API, and commands

**Cons:**

- **More files**: ~10 policy files vs inline checks in controllers
- **Policy-model coupling**: Policy methods reference model attributes (e.g., `$timeLog->employee->branch_id`) — the model shape must be stable
- **Superadmin bypasses**: Every policy has `$user->isSuperAdmin()` early returns — care needed to not accidentally open doors
- **Special group complexity**: Babak/Peñaplata/Tibungco sharing adds an extra branch check in `canAccessBranch()` on every policy

### Why Policy Over Middleware/Scopes

| Approach          | Branch Scoping | Approval Gates        | Testability |
| ----------------- | -------------- | --------------------- | ----------- |
| Query scopes only | ✓ (read paths) | ✗                     | Low         |
| Middleware        | Partial        | ✗                     | Medium      |
| Policies (chosen) | ✓ (all paths)  | ✓ (approve/void/etc.) | High        |

Policies are the only approach that covers both branch scoping and action-specific gates (approve, void, rehire) in one framework-native pattern.

---

## 15. System (Automated) Use Cases

| #   | Use Case                                  | Trigger                  | Notes                                                                         |
| --- | ----------------------------------------- | ------------------------ | ----------------------------------------------------------------------------- |
| SY1 | Auto-detect duplicate punches             | Per punch                | 5-min window. Keeps earliest punch, marks later as `duplicate_of`.            |
| SY2 | Auto-resolve shift type for OT requests   | Per OT request           | Checks if date is rest day / regular holiday / special holiday / regular day. |
| SY3 | Auto-capture OT rate snapshot on approval | Per OT approval          | Freezes `ot_amount_30min` + `ot_amount_hour` from config onto the OT request. |
| SY4 | Auto-reset leave balance on January 1     | Jan 1 at midnight        | Sets `leaves_used_this_year = 0` for all employees.                           |
| SY5 | Auto-generate employee number on create   | Per employee creation    | Format: `EMP-YYYY-NNNN`. Increments sequence within year.                     |
| SY6 | Auto-close previous salary on rate change | Per salary create/update | Sets `end_date` on the prior active salary record when a new rate is created. |

---

## 16. Edge Cases

### Punch-Related

| #   | Scenario                              | Behavior                                             |
| --- | ------------------------------------- | ---------------------------------------------------- |
| E1  | Two IN punches within 5 minutes       | Earliest kept; later marked `duplicate_of`           |
| E2  | Two OUT punches within 5 minutes      | Same throttling                                      |
| E3  | Only IN, no OUT (day in progress)     | No sheet generated; "Currently clocked in" on portal |
| E4  | Only IN, no OUT (day closed)          | Marked as unexcused absence; "Missing OUT punch"     |
| E5  | Only OUT, no IN                       | Anomaly; 0 hours; admin review recommended           |
| E6  | Punch on rest day (no OT)             | Blocked: "Today is your rest day"                    |
| E7  | Punch on rest day (OT approved)       | Allowed; rest day OT rate applied                    |
| E8  | Punch > 18 hours after schedule start | Logged but flagged as anomaly warning                |

### Computation-Related

| #   | Scenario                           | Behavior                                                 |
| --- | ---------------------------------- | -------------------------------------------------------- |
| E9  | Late 10 min                        | Deduction = 10 × ₱5 = ₱50                                |
| E10 | Late 19 min                        | Deduction = 19 × ₱5 = ₱95                                |
| E11 | Late 20 min                        | Flat ₱100 deduction (cap at 20 min)                      |
| E12 | Late 45 min                        | Flat ₱100 deduction                                      |
| E13 | Late 60 min (1 hour)               | ₱100 + 1 × hourly_rate. Example: ₱100 + ₱63.75 = ₱163.75 |
| E14 | Late 90 min (1.5 hours)            | ₱100 + floor(90/60) × hourly = ₱100 + ₱63.75 = ₱163.75   |
| E15 | Late 150 min (2.5 hours)           | ₱100 + floor(150/60) × hourly = ₱100 + ₱127.50 = ₱227.50 |
| E16 | Not late, left early (worked 5h)   | Proportional: hourly_rate × 5. No late penalty.          |
| E17 | Not late, worked only 2h           | Proportional: hourly_rate × 2. No minimum threshold.     |
| E18 | OT approved 3h, stayed 2h          | Lower-of-two: 120 minutes OT worked                      |
| E19 | OT approved 1h, stayed 2.5h        | Lower-of-two: 60 minutes OT; 90 min discarded            |
| E20 | OT approved but actual < threshold | 0 OT awarded (must meet minimum threshold)               |
| E21 | OT 35 min (regular day)            | Remainder 35 > 30 → rounds to 1h. Pay = ₱70.             |
| E22 | OT 65 min (regular day)            | 1h(₱70) + remainder 5 ≤ 30 → +₱50. Pay = ₱120.           |
| E23 | OT 95 min (regular day)            | 1h(₱70) + remainder 35 > 30 → +₱70. Pay = ₱140.          |
| E24 | OT 150 min (regular day)           | 2h(₱140) + remainder 30 ≤ 30 → +₱50. Pay = ₱190.         |
| E21 | Schedule changed mid-week          | Uses schedule active on each date (effective_from/to)    |

### Holiday-Related

| #   | Scenario                      | Behavior                                    |
| --- | ----------------------------- | ------------------------------------------- |
| E19 | Holiday Mon, absent Fri       | Walk back → Friday, absent → 0% holiday pay |
| E20 | Holiday Tue, Mon also holiday | Skip Mon (holiday), check previous Fri      |
| E21 | Regular holiday, worked       | 200% pay regardless of day-before status    |
| E22 | Special holiday, unworked     | 0% (no work, no pay)                        |
| E23 | Special holiday, worked       | 130% of daily rate                          |

### Leave Blending

| #   | Scenario                                | Behavior                                                          |
| --- | --------------------------------------- | ----------------------------------------------------------------- |
| E27 | Half-day AM leave + afternoon 4h        | Full day (4h leave + 4h worked = 8h)                              |
| E28 | Half-day AM leave + afternoon 3.5h      | Leave: 4h credited. Worked: 3.5h = proportional pay. Total: 7.5h. |
| E29 | Half-day PM leave + morning 4h          | Full day (4h worked + 4h leave)                                   |
| E30 | Full day unpaid leave + employee worked | Hours credited but daily_wage = 0 for leave portion               |

### Fines

| #   | Scenario                     | Behavior                                            |
| --- | ---------------------------- | --------------------------------------------------- |
| E41 | No uniform, full day present | ₱20 fine deducted. daily_wage = ₱510 − ₱20 = ₱490   |
| E42 | No uniform + late 15 min     | ₱75 late + ₱20 fine. daily_wage = ₱510 − ₱95 = ₱415 |
| E43 | Multiple fines in one day    | Stacked. Each fine type adds its penalty.           |
| E44 | Fine amount configured to 0  | No deduction. Fine type effectively disabled.       |

### Government Contributions

| #   | Scenario                                                  | Behavior                                                                 |
| --- | --------------------------------------------------------- | ------------------------------------------------------------------------ |
| E45 | Monthly salary exactly on bracket boundary (e.g., ₱4,250) | Falls in the lower bracket (`salary_min ≤ salary ≤ salary_max`).         |
| E46 | Monthly salary exceeds highest bracket (e.g., ₱21,000)    | Falls in the highest bracket. Salary capped at max bracket bound.        |
| E47 | SSS ID empty, but bracket exists                          | SSS deduction skipped for this employee. No error.                       |
| E48 | PhilHealth percentage set to 0                            | No PhilHealth deduction for any employee. Effectively disabled.          |
| E49 | Pag-IBIG share changed mid-month                          | New amount applies to next payroll period. No retroactive adjustment.    |
| E50 | Employee daily rate changed (raise)                       | New monthly salary computed. Bracket auto-re-looked up next payroll run. |

### Cash Advances

| #   | Scenario                         | Behavior                                                                        |
| --- | -------------------------------- | ------------------------------------------------------------------------------- |
| E37 | Request CA > max receivable      | Blocked: "Cannot exceed projected net pay for this period"                      |
| E38 | Request CA while CA still active | Blocked: "You have an unpaid cash advance. Settle it first."                    |
| E39 | Net pay < remaining CA balance   | Deduct all available net pay. Balance carries over. Net pay never goes below 0. |
| E40 | CA fully deducted this period    | Status → `paid`. Employee can request a new CA.                                 |

### RBAC Enforcement

| #   | Scenario                                       | Behavior                                                           |
| --- | ---------------------------------------------- | ------------------------------------------------------------------ |
| E51 | Admin views employees from another branch      | Blocked by `canAccessBranch()` — returns empty/page with 403       |
| E52 | Admin tries to approve staff from other branch | Policy returns false — 403 Forbidden                               |
| E53 | Staff tries to view another staff's payslip    | Blocked — self-only policy                                         |
| E54 | Admin tries to void payroll period             | Blocked — void gate is superadmin-only                             |
| E55 | Admin self-approves own correction             | Blocked — superior-only rule enforced in `CorrectionRequestPolicy` |
| E56 | Staff tries direct manual punch for another    | Blocked — manual log requires admin role                           |
| E57 | Admin from Babak accesses Peñaplata employees  | Allowed — special group sharing                                    |
| E58 | Admin from Babak accesses Davao employees      | Blocked — Davao not in special group                               |
| E59 | Superadmin accesses any employee data          | Allowed — superadmin bypasses all branch checks                    |
| E60 | Staff tries to mark fine on another employee   | Blocked — fine marking requires admin role + branch match          |
| E61 | Admin marks fine on employee from other branch | Blocked — branch mismatch                                          |
| E62 | Non-superadmin tries to edit holiday dates     | Blocked — `HolidayPolicy` restricts to superadmin only             |
| E63 | Non-superadmin tries to change SSS brackets    | Blocked — `CompanyConfigPolicy` restricts to superadmin only       |

### Concurrency & Integrity

| #   | Scenario                            | Protection                                                    |
| --- | ----------------------------------- | ------------------------------------------------------------- |
| E31 | Two admins approve same correction  | `lockForUpdate()` on correction row; second fails             |
| E32 | Payroll gen while sheet recomputing | Period gen locks sheets; recomputation fails on locked sheet  |
| E33 | Correction for already-locked sheet | Blocked: "Sheet locked in approved payroll period"            |
| E34 | Duplicate correction request        | Blocked: "Pending request already exists"                     |
| E35 | Self-approval attempt               | Policy blocks: role must be strictly superior                 |
| E36 | 200+ employee batch processing      | `chunk(50)`; individual failures don't roll back entire batch |
