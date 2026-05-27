# Payroll System — Workflows & Use Cases

**Printing Shop Management System** · May 24, 2026 · v1.0

---

## Contents

1. [Attendance & Punching](#1-attendance--punching)
2. [Late Deduction](#2-late-deduction)
3. [Lunch Tracking](#3-lunch-tracking)
4. [Undertime & Partial Days](#4-undertime--partial-days)
5. [Overtime](#5-overtime)
6. [Leave](#6-leave)
7. [Holiday Pay](#7-holiday-pay)
8. [Corrections](#8-corrections)
9. [Fines](#9-fines)
10. [Cash Advances](#10-cash-advances)
11. [Government Deductions](#11-government-deductions)
12. [Weekly Payroll & Payslip](#12-weekly-payroll--payslip)
13. [Use Cases by Role](#13-use-cases-by-role)

---

## 1. Attendance & Punching

### Shift

- **8:00 AM – 5:00 PM** (paid). **5:00–5:30 PM** is outside the paid shift — unpaid unless OT is approved.
- Paid hours per full day: **8 hours**.
- Work week: **Monday – Saturday**.

### Punch Flow (4-Punch Model)

```
Step 1. PUNCH IN       →  Start of day (e.g., 8:00 AM)
Step 2. LUNCH OUT      →  Lunch break begins (e.g., 12:00 PM)
Step 3. LUNCH IN       →  Lunch break ends (e.g., 1:00 PM)
Step 4. PUNCH OUT      →  End of day (e.g., 5:30 PM)
```

After each punch, the system recalculates the employee's daily wage and updates their status on the portal.

### Duplicate Prevention

If the same punch type is submitted within 5 minutes, the earliest is kept. Later duplicates are logged but ignored for computation.

### Rest Days

- Punching on a rest day is blocked unless a pre-approved OT request exists.
- Admin can manually log punches on any day.

### Portal Status

The portal shows which punches have been completed today and which are pending — e.g., "Clocked In — Lunch Pending" or "Lunch Done — Awaiting Punch Out."

---

## 2. Late Deduction

### Rule

If an employee punches in after their scheduled start time (8:00 AM), late minutes are calculated. No grace period.

### 3-Tier Deduction

| Late Minutes | Deduction                                       |
| ------------ | ----------------------------------------------- |
| 0            | ₱0                                              |
| 1–19         | `late_minutes × ₱5`                             |
| 20–59        | Flat ₱100                                       |
| 60+          | `₱100 + floor(late_minutes / 60) × hourly_rate` |

### Key Detail

Fractional hours past each full hour are not penalized. Being 90 minutes late costs the same as 60 minutes late. Being 150 minutes late costs the same as 120 minutes late.

### Examples (daily_rate = ₱510, hourly = ₱63.75)

| Late    | Deduction | Daily Wage |
| ------- | --------- | ---------- |
| 0 min   | ₱0        | ₱510.00    |
| 10 min  | ₱50       | ₱460.00    |
| 15 min  | ₱75       | ₱435.00    |
| 25 min  | ₱100      | ₱410.00    |
| 45 min  | ₱100      | ₱410.00    |
| 60 min  | ₱163.75   | ₱346.25    |
| 90 min  | ₱163.75   | ₱346.25    |
| 120 min | ₱227.50   | ₱282.50    |
| 150 min | ₱227.50   | ₱282.50    |

---

## 3. Lunch Tracking

### Measured Model (4-Punch)

The system measures actual lunch duration using the LUNCH OUT and LUNCH IN punches.

| Measured Lunch | Impact                               |
| -------------- | ------------------------------------ |
| ≤ 60 minutes   | Normal — no penalty                  |
| > 60 minutes   | Excess minutes deducted as undertime |

### Example

Lunch from 11:00 AM to 12:45 PM = 1 hour 45 minutes. Excess = 45 minutes deducted from pay.

### Fallback (Missing Lunch Punches)

If LUNCH OUT or LUNCH IN is missing, the system falls back to auto-deduction:

- Deducts 60 minutes if the work period spans 11:00 AM – 2:00 PM AND total raw duration is ≥ 5 hours.
- Otherwise deducts 0.

---

## 4. Undertime & Partial Days

### Proportional Pay

Any hours worked are paid proportionally. No minimum threshold. No penalty for working less than 4 hours.

```
daily_wage = hourly_rate × hours_worked
```

### Examples (hourly_rate = ₱63.75)

| Hours Worked   | Daily Wage  |
| -------------- | ----------- |
| 8.0 (full day) | ₱510.00     |
| 5.0            | ₱318.75     |
| 4.0            | ₱255.00     |
| 2.0            | ₱127.50     |
| 1.0            | ₱63.75      |
| 0 (no punches) | ₱0 (absent) |

### Absence

An employee is absent when they have **zero hours worked and no approved leave** for the day. Zero pay.

---

## 5. Overtime

### Request → Approval → Computation

```
Step 1. Employee or admin submits OT request: date, hours needed, reason.
Step 2. System auto-resolves shift type (regular day / rest day / holiday).
Step 3. Admin or superadmin reviews and approves or denies.
Step 4. On approval: OT rates (30-min block + 1-hour block) are frozen on the request.
Step 5. After the work day: system compares actual extra stay vs approved minutes.
        Lower-of-two rule applies — the lesser amount is used.
```

### Lower-of-Two Rule

If OT approved for 3 hours but employee only stayed 2 hours → paid for 2 hours. If OT approved for 1 hour but employee stayed 2.5 hours → paid for 1 hour. Unapproved extra time is discarded.

### OT Threshold

Admin sets a minimum consecutive minutes (e.g., 60 minutes). Extra time below this threshold = 0 OT, even if approved.

### OT Pay — 2-Block Model

OT is paid using two configurable flat amounts per shift type:

**Config (regular day defaults):**

| Block      | Rate   |
| ---------- | ------ |
| 30 minutes | ₱50.00 |
| 1 hour     | ₱70.00 |

**Rounding Rule:**

- Full hours are counted first.
- The remaining minutes are rounded:
    - ≤ 30 minutes → adds one 30-min block
    - 31–59 minutes → rounds up to one 1-hour block

**Examples (regular day, ₱50/30min, ₱70/hr):**

| Worked  | Computation    | Paid As | Pay  |
| ------- | -------------- | ------- | ---- |
| 30 min  | 1 × ₱50        | 30 min  | ₱50  |
| 35 min  | rounds to 1h   | 1 hour  | ₱70  |
| 40 min  | rounds to 1h   | 1 hour  | ₱70  |
| 60 min  | 1 × ₱70        | 1 hour  | ₱70  |
| 65 min  | 1h + 5min→₱50  | 1h30min | ₱120 |
| 80 min  | 1h + 20min→₱50 | 1h30min | ₱120 |
| 90 min  | 1h + 30min→₱50 | 1h30min | ₱120 |
| 95 min  | 1h + 35min→₱70 | 2 hours | ₱140 |
| 120 min | 2 × ₱70        | 2 hours | ₱140 |
| 150 min | 2h + 30min→₱50 | 2h30min | ₱190 |

### OT Rates Per Shift Type

Admin configures two amounts for each shift type:

| Shift Type         | 30-min Block      | 1-hour Block      |
| ------------------ | ----------------- | ----------------- |
| Regular Day        | ₱50.00            | ₱70.00            |
| Rest Day           | ₱65.00            | ₱90.00            |
| Regular Holiday    | ₱100.00           | ₱140.00           |
| Special Holiday    | ₱65.00            | ₱90.00            |
| Rest Day + Holiday | higher of the two | higher of the two |

### Rate Snapshot

When an OT request is approved, the current rates are captured on the request. If the admin changes rates later, previously approved OT is unaffected.

---

## 6. Leave

### Request → Approval → Blending

```
Step 1. Employee or admin submits leave request: date, type, duration, paid/unpaid, reason.
Step 2. Admin or superadmin reviews and approves or denies.
        System shows remaining leave balance (5/year).
Step 3. On the leave date: processDailyAttendance blends leave hours with actual hours worked.
```

### Leave Balance

- **5 paid leaves per year**, any type (vacation / sick / emergency / etc.)
- Tracked per employee. Resets every January 1.
- If balance is 0, admin can still approve at their discretion (system shows a warning).
- Unpaid leave does not count against the balance.

### Leave Types

Vacation, Sick, Emergency, Maternity, Paternity, Bereavement, Unpaid.

### Leave Blending

| Leave Duration     | Actual Worked       | Result                                                |
| ------------------ | ------------------- | ----------------------------------------------------- |
| Full day (paid)    | Any (including 0)   | Full day credited, 100% paid. No late/UT.             |
| Full day (unpaid)  | Any                 | Hours credited but daily_wage = ₱0 for leave portion. |
| Half-day morning   | Afternoon ≥ 4 hours | Full day (4h leave + 4h+ worked).                     |
| Half-day morning   | Afternoon < 4 hours | 4h leave credited. Worked portion = proportional pay. |
| Half-day afternoon | Morning ≥ 4 hours   | Full day (4h+ worked + 4h leave).                     |
| Half-day afternoon | Morning < 4 hours   | 4h leave credited. Worked portion = proportional pay. |

### Leave on a Holiday

If an employee has approved leave on a holiday, holiday rules still apply (see Holiday Pay below).

---

## 7. Holiday Pay

### Holiday Calendar

Superadmin maintains a list of Philippine holidays. Each holiday is either:

- **Regular Holiday**
- **Special Non-Working Day**

### Regular Holiday — Unworked

```
Step 1. Employee did NOT work on the holiday date.
Step 2. System checks the last scheduled working day BEFORE the holiday.
        Walks backward, skipping rest days, Sundays, and other holidays.
Step 3. If the employee was Present OR on Approved Paid Leave on that day:
        → Holiday pay = 100% of daily rate.
        If the employee was Absent without leave on that day:
        → Holiday pay = 0%.
```

**Example:** Holiday on Monday → check Friday (skip Saturday rest day, skip Sunday). If present Friday → paid 100%. If absent Friday → paid 0%.

### Regular Holiday — Worked

If the employee worked on a regular holiday → **200% of daily rate** for the first 8 hours. Day-before status does not matter when worked.

### Special Non-Working Day

| Scenario               | Pay                                       |
| ---------------------- | ----------------------------------------- |
| Unworked               | 0% — no work, no pay                      |
| Worked (first 8 hours) | 130% of daily rate                        |
| Worked + overtime      | 130% base + OT at special holiday OT rate |

### Holiday Pay on Payslip

Shown as a separate line item under Earnings with a percentage label: `Holiday Pay (100%)`, `Holiday Pay (130%)`, `Holiday Pay (200%)`. 0% holidays are not shown.

---

## 8. Corrections

### When an Employee Needs a Fix

If an employee forgot to punch, punched at the wrong time, or was incorrectly marked absent, they submit a correction request.

### Correction Types

| Type              | Meaning                                              |
| ----------------- | ---------------------------------------------------- |
| Missed Punch IN   | Employee forgot to punch in.                         |
| Missed Punch OUT  | Employee forgot to punch out.                        |
| Time Adjustment   | Existing punch time is wrong.                        |
| Absent to Present | Employee was marked absent but was actually present. |

### Flow

```
Step 1. Employee (or admin on their behalf) submits a correction request:
        - Selects the date, correction type, proposed corrected time(s).
        - Provides a reason (required).
Step 2. System creates a pending correction request.
Step 3. Admin or superadmin reviews the request:
        - Self-approval is blocked (cannot approve your own request).
        - Staff requests → approved by admin.
        - Admin requests → approved by superadmin.
Step 4a. If APPROVED:
        - System creates corrected time_log entries.
        - Re-calculates the attendance sheet for that date.
        - Marks the correction as resolved.
Step 4b. If DENIED:
        - Reviewer provides a denial reason (required).
        - Employee is notified.
```

### Admin Direct Override

Admin can also create manual time_logs directly (no approval needed). This is a trusted action for scenarios like biometric device failure or supervisor-verified attendance. Staff cannot create manual logs.

---

## 9. Fines

### Purpose

Policy violations — for example, not wearing a uniform.

### Rule

- **Per-day flat fine**, e.g., ₱20.
- Configurable via system settings (admin can change the amount).
- Applied by admin on the employee's daily attendance record.
- Multiple fine types can stack if multiple violations occur on the same day.

### Flow

```
Step 1. Admin opens the employee's daily attendance record.
Step 2. Selects a fine type (e.g., "No Uniform") and provides a reason.
Step 3. Deduction applied to that day's daily_wage.
```

### Examples (daily_rate = ₱510)

| Scenario                 | Late | Fine | Daily Wage |
| ------------------------ | ---- | ---- | ---------- |
| Full day, no uniform     | ₱0   | ₱20  | ₱490.00    |
| Late 15 min + no uniform | ₱75  | ₱20  | ₱415.00    |

---

## 10. Cash Advances

### Rule

- Employee can request a cash advance before payday.
- **Maximum amount:** projected net receivable for the current payroll period.
- **One active CA at a time:** cannot request a new CA until the existing one is fully paid.
- **No interest.** Unpaid balance carries over to the next payroll period.
- Approver: Admin or Superadmin.

### Request Flow

```
Step 1. Employee (or admin on their behalf) requests a CA: selects amount, provides reason.
Step 2. System validates: amount ≤ projected net receivable, no existing unpaid CA.
Step 3. Admin or superadmin approves or denies.
```

### Deduction Flow (During Payroll)

```
Step 1. For each employee with an active CA (remaining_balance > 0):
        - Deduction = min(remaining_balance, net_pay_before_ca).
        - Net pay never goes below ₱0.
Step 2. remaining_balance reduced by the deducted amount.
Step 3. If remaining_balance reaches ₱0 → CA marked as paid.
        If remaining_balance > 0 → balance carries to the next period.
```

---

## 11. Government Deductions

All computed on the employee's **regular monthly salary** (`daily_rate × 26`), not on variable attendance earnings. Same amount every week regardless of absences or overtime.

### SSS — Bracket-Based

```
Step 1. Compute monthly salary: daily_rate × 26.
Step 2. Find the matching bracket in the SSS contribution table.
Step 3. Weekly deduction = (monthly_salary × employee_percentage / 100) / 4.
```

- Employee share: **5%** (configurable per bracket).
- Employer share: **10%** (configurable, tracked, not deducted from employee).
- 20 brackets up to ₱20,000 monthly salary, managed by superadmin.

### PhilHealth — Percentage-Based

```
Weekly deduction = (monthly_salary × premium_percentage / 100 × 50%) / 4
```

- Premium percentage: **5%** (configurable).
- Split: 50% employer / 50% employee (fixed per law).

### Pag-IBIG — Flat Amount

```
Weekly deduction = monthly_employee_share / 4
```

- Employee share: **₱100/month** (configurable).
- Employer share: ₱100/month (configurable, tracked, not deducted).

### Per-Benefit Conditional

Each benefit is only deducted if the employee has filled in the corresponding ID number:

- SSS number empty → skip SSS deduction.
- PhilHealth number empty → skip PhilHealth deduction.
- Pag-IBIG number empty → skip Pag-IBIG deduction.

---

## 12. Weekly Payroll & Payslip

### Payroll Schedule

- **Every Saturday after shift.** Covers Monday – Saturday of the completed week.
- Admin triggers generation. System auto-selects the just-completed week.

### Payroll Generation Flow

```
Step 1. Admin clicks "Generate Payroll Period" on Saturday after shift.
Step 2. System locks all attendance sheets for the week.
Step 3. System shows an alert list of incomplete days (missing OUT punches) for admin review.
Step 4. For each active employee:
        - Aggregates daily wages into gross pay.
        - Computes total late deductions, fines, overtime pay, holiday pay.
        - Computes government deductions (SSS bracket lookup, PhilHealth %, Pag-IBIG flat).
        - Computes cash advance deduction (if applicable).
        - Calculates final net pay.
Step 5. Admin reviews the period summary.
Step 6. Admin clicks "Approve" → period is finalized.
Step 7. Payslips are generated and available to employees.
```

### Void / Rollback

Only superadmin can void an approved payroll period. This unlocks all sheets, allowing corrections. The period must then be re-generated and re-approved. Individual sheet unlocking is not allowed.

### Saturday Sweep

During payroll generation, the system re-checks all attendance sheets:

- Days with only a PUNCH IN and no PUNCH OUT (definitively closed) → marked as absent.
- Admin is shown a list of these incomplete days for review before approving payroll.

### Payslip

Generated as PDF (downloadable) and viewable in-portal.

**Header:** Branch, period dates, employee name, employee number, daily rate, SSS / PhilHealth / Pag-IBIG / TIN numbers, monthly salary and SSS bracket.

**Attendance Summary:** Present count, Late count, OT hours, Absent count, Holiday count.

**Body — Two Columns:**

| Earnings                 | Deductions              |
| ------------------------ | ----------------------- |
| Basic Pay                | Late (reason + amount)  |
| Overtime (hours + rate)  | Fines (reason + amount) |
| Holiday Pay (percentage) | SSS                     |
|                          | PhilHealth              |
|                          | Pag-IBIG                |
|                          | Cash Advance            |

**Footer:** Gross pay, total deductions, net pay, generation date, period status, signature lines.

Each government deduction shows its rate (e.g., `SSS (5%)`, `PhilHealth (2.50%)`). Deductions are ordered: behavioral (late, fines) first, then statutory (SSS, PhilHealth, Pag-IBIG), then voluntary (cash advance). Missing government IDs show `—` and no deduction.

---

## 13. Use Cases by Role

### Staff Use Cases

| #   | Use Case                                        | Frequency  |
| --- | ----------------------------------------------- | ---------- |
| S1  | Punch IN at start of shift                      | Daily      |
| S2  | Punch LUNCH OUT                                 | Daily      |
| S3  | Punch LUNCH IN                                  | Daily      |
| S4  | Punch OUT at end of shift                       | Daily      |
| S5  | View own attendance history (calendar + recent) | Weekly     |
| S6  | Submit correction request                       | Occasional |
| S7  | Submit OT request                               | Occasional |
| S8  | Submit leave request                            | Occasional |
| S9  | View own leave balance                          | Occasional |
| S10 | View own payslip (current + history)            | Weekly     |
| S11 | View own salary + contribution breakdown        | Rare       |

### Admin Use Cases

| #   | Use Case                                                                     | Frequency  |
| --- | ---------------------------------------------------------------------------- | ---------- |
| A1  | Punch IN / LUNCH OUT / LUNCH IN / OUT (self)                                 | Daily      |
| A2  | View daily attendance dashboard (branch summary)                             | Daily      |
| A3  | View branch attendance sheets (per date)                                     | Daily      |
| A4  | Create manual time_log for any branch employee                               | Occasional |
| A5  | Mark fine on employee daily record                                           | Occasional |
| A6  | Review and approve/deny staff correction requests                            | Daily      |
| A7  | Submit correction for any branch employee (needs superadmin approval)        | Occasional |
| A8  | Review and approve/deny OT requests                                          | Weekly     |
| A9  | Review and approve/deny leave requests (see balance)                         | Weekly     |
| A10 | Approve/deny cash advance requests                                           | Occasional |
| A11 | Manage employee schedules (create/assign)                                    | Occasional |
| A12 | View employee detail (full profile, salary history, schedule, leave balance) | Occasional |
| A13 | Create employee (with user account)                                          | Rare       |
| A14 | Edit employee (update info, salary, government IDs)                          | Occasional |
| A15 | Deactivate / rehire employee                                                 | Rare       |
| A16 | Generate payroll period (Saturday after shift)                               | Weekly     |
| A17 | Review and approve payroll period                                            | Weekly     |
| A18 | View branch payslips (per employee)                                          | Weekly     |
| A19 | View own attendance history + payslips                                       | Weekly     |

### Superadmin Use Cases

| #    | Use Case                                                                     | Frequency  |
| ---- | ---------------------------------------------------------------------------- | ---------- |
| SA1  | All admin use cases (global scope — all branches)                            | —          |
| SA2  | Manage holidays (add/edit regular + special)                                 | Annual     |
| SA3  | Edit company configuration (late tiers, OT rates, lunch, fines, holiday pay) | Rare       |
| SA4  | Manage SSS contribution brackets                                             | Annual     |
| SA5  | Manage PhilHealth percentage + Pag-IBIG share                                | Annual     |
| SA6  | Approve admin-submitted corrections (superior-only)                          | Occasional |
| SA7  | Approve admin-submitted OT / leave / CA (superior-only)                      | Occasional |
| SA8  | Void payroll period                                                          | Rare       |
| SA9  | Manage users (create/edit/deactivate accounts)                               | Rare       |
| SA10 | View global attendance + payroll across all branches                         | As needed  |

### System (Automated)

| #   | Use Case                                         | Trigger               |
| --- | ------------------------------------------------ | --------------------- |
| SY1 | Auto-detect duplicate punches (5-min throttle)   | Per punch             |
| SY2 | Auto-resolve shift type for OT requests          | Per OT request        |
| SY3 | Auto-capture OT rate snapshot on approval        | Per OT approval       |
| SY4 | Auto-reset leave balance on January 1            | Jan 1 midnight        |
| SY5 | Auto-generate employee number on create          | Per employee creation |
| SY6 | Auto-close previous salary record on rate change | Per salary update     |
