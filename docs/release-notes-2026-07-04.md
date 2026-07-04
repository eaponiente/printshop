# What's New — July 4, 2026

A summary of recent improvements to Employees, Payroll, and Projects (Sales).

---

## Employees & Users

### Login account merged into the employee form
- Creating or editing an employee now includes a **Login Account** section right on the same form — username, password, and role. There's no more separate "Add/Edit User" step.
- The standalone **Users** management page has been removed. The sidebar's "Users" entry is now a direct link to **Customers**; managing an employee's login is done from **Add/Edit Employee**.

### Fixed weekly statutory deductions
- Employees now have three new fields: **SSS**, **PhilHealth**, and **Pag-IBIG deduction per week**. These are required whenever the matching government ID number is filled in.
- Payroll now deducts these fixed weekly amounts directly instead of computing them from brackets/percentages — what you enter on the employee is exactly what gets deducted each pay period.

---

## Payroll & Attendance

### Late arrivals of 1 hour or more become a half day
- If an employee punches in **1 hour or later** than their shift start, the morning is now treated as unpaid and **no late deduction** is applied for it — only the afternoon session is paid (e.g. an 8am–5pm shift pays 1pm–5pm).
- If the employee is already on a half day and punches in **after the afternoon session starts** (e.g. 1:10pm for a 1pm afternoon start), they still get the full half-day wage, but a late deduction is applied for those afternoon minutes — capped at the same threshold used for regular lateness (no extra hourly penalty beyond it).
- The 1-hour threshold is configurable from **Payroll Settings**.

---

## Projects (Sales)

### Payments are no longer grouped by transaction
- On the **Partial** and **Paid** tabs, each payment now shows as its own row. If a project was paid in two installments, you'll see two separate entries instead of one combined row — 1 payment = 1 entry.
