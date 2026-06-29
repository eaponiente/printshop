# What's New — June 30, 2026

A summary of recent improvements across Sublimations, Sewed Items, Attendance, Payroll, Audit Logs, and Sales.

---

## Sublimations

### Per-Category Quantity
- When creating or editing a sublimation, you can now set a quantity **per category** (e.g. Logo ×2, Sleeve ×1) instead of one overall quantity.
- The total quantity is calculated automatically from the sum of all category quantities — no need to enter it separately.
- On the sublimations list, each category pill now shows its quantity (`×N`). Click the `×N` on a pill to adjust it inline.
- Adding a tag from the list still defaults the quantity to 1 — you can bump it right after.

### Sublimations List
- **New "Created At" column** — sortable ascending/descending so you can quickly see the newest or oldest entries.
- **Staff users** now land on the page already filtered to their own assigned sublimations. They can still switch the User filter to see other people's or all sublimations.

### Duplicate Sublimation
- When duplicating, you now set the quantity **per category** (just like create). The new sublimation's total quantity is summed automatically.
- Existing per-tag values are pre-filled, so you can usually just confirm or tweak a single number.

### Collect Payment Dialog (in Sales)
- Sublimation transactions now display each category as a colored pill with its quantity, instead of one combined number — easier to verify what's being paid for.

---

## Sewed Items

### Create Sewed Item Dialog
- The Quantity column is now **pre-filled from the sublimation's per-category quantities** and locked from editing. This prevents accidentally entering the wrong count.
- Price per piece is still editable as before.

### Delete Sewed Items
- **Admins and Superadmins** can now delete a sewed item entry directly from the list (with a confirmation prompt). Useful for cleaning up mistakes.

---

## Attendance

### Punch Labels Renamed
- **"Lunch Out"** is now labeled **"Start Break"**.
- **"Lunch In"** is now labeled **"End Break"**.
- Applies to both the self-service punch screen and the admin attendance sheet.

### Late / Undertime Deduction Fix
- Late and undertime deductions were sometimes producing fractional pesos (e.g. ₱10.67 for a 1-minute late instead of ₱10.00). This was caused by counting partial seconds.
- Deductions are now correctly calculated to the whole minute. Overtime, half-day, rest-day, and lunch-related calculations also now ignore stray seconds.
- Superadmins have an **admin tool to recalculate late deductions** on all unlocked attendance sheets so existing affected entries can be corrected without manual edits.

---

## Audit Logs

### Self-Correction Flag
- When an admin makes an attendance correction on their **own** time logs (added or removed a punch), the audit log row now displays a **⚠ Self** badge next to the action.
- Action labels are easier to read (e.g. "admin correction added" instead of "admin\_correction\_added") and use distinct color badges so corrections stand out.
- Older audit entries created before this update will not show the flag — the badge only appears for corrections made going forward.

---

## Notes

- Existing sublimations were backfilled: the previous overall quantity was assigned to the first category on each record, so no data was lost.
- Sublimations without any categories are unaffected by the backfill (the overall quantity remains as-is).
