# What's New — July 7, 2026

A new payroll dashboard for reviewing a week's numbers before generating an official payroll period.

---

## Payroll

### Work Week Payroll Table
- Added a new **Work Week Payroll Table** page (admin and superadmin only) — a compact, Excel-style view with one row per employee: daily rate, a color-coded attendance cell for each work day (Saturday, Monday–Friday), total fines, total late minutes, total OT, holiday count, cash advance, SSS/PhilHealth/Pag-IBIG, total deductions, gross salary, and net salary.
- **This is a live preview, not a real payroll run** — it never creates a payroll period, never locks attendance, and never touches a cash advance balance. The cash advance figure shown is a computed estimate of what would be deducted.
- Defaults to last Saturday through this Friday. Superadmins default to the first branch alphabetically and can switch branches; admins are limited to their own branch.
- Attendance cells are color-coded: green for present, yellow for late, red for absent, blue for holiday/rest day, purple for leave.
- Footer row totals gross payroll, total deductions, total net salary, total cash advance, total OT hours, total holidays, and total lates across every employee in the branch — not just the visible page.
- Includes a **Print Payroll** button that opens a dedicated print-friendly view of the full table.

### Payslip cash advance breakdown
- The payslip now **itemizes each cash advance** deducted in a period on its own line with the advance's reason (e.g. "Cash Advance — Tuition"), instead of collapsing multiple advances into a single lumped line.
- When an advance is only partially paid off, the payslip now shows the **remaining outstanding balance** after the period's deduction, so employees can see how much they still owe.

### Recompute draft payroll periods
- Cash advances are deducted when a payroll period is generated. Previously, a cash advance **granted after** a draft period was generated would not appear on that period's payslips (it only applied to the next payroll).
- Draft periods now have a **Recompute** button. It rebuilds the period's items from the current attendance, cash advances, and benefits — so a newly-granted cash advance immediately shows up in the deductions and net pay. Approved/paid periods stay locked and are unaffected.
