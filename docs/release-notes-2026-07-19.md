# What's New — July 19, 2026

Year-rollover hardening: invoice numbers no longer collide across the New Year, the holiday calendar can be seeded ahead for future years, and **admins** can now manage holidays directly — with passed holidays tidied to the bottom of the list.

---

## Sales

### Invoice numbers survive the year rollover

`Transaction::generateNumber()` now scopes the per-year sequence by the **year embedded in the invoice number** (`INV-{year}-{NNNNN}`) instead of by `created_at`. Previously, a row whose `created_at` landed in a different calendar year than its number (clock skew across midnight, a backdated/imported row, a timezone shift) could be excluded by the `whereYear('created_at', …)` filter — resetting the sequence back to `00001` and re-issuing a **duplicate** number that violates the unique `invoice_number` constraint and blocks the sale.

- Sequence is now derived from the latest number matching the `INV-{year}-` prefix (`withTrashed()` + `lockForUpdate()` to serialize concurrent generation).
- Format is documented as 5-digit zero-padded: `INV-2026-00001`.
- Covered by `tests/Feature/Sales/InvoiceNumberTest.php` — increments within a year, resets to `00001` on the first invoice of the new year, and the regression case: no duplicate when `created_at`'s year lags the number's year.

### Yearly filter accepts a full date

The `yearly` arm of the Sales/payment date filters now accepts either a bare year or a full date string (`is_numeric($date) ? $date : Carbon::parse($date)->year`) in `SaleFilterTrait` and `SalesService`, so a date-shaped filter value no longer silently mismatches.

---

## Payroll / Attendance

### Holiday calendar can be seeded for future years

- `Holiday::defaultsForYear($year)` / `Holiday::seedYear($year)` produce and idempotently persist the canonical Philippine calendar for any year. Fixed-date holidays are `recurring` (resolve by month+day even when a year was never explicitly seeded); the movable **National Heroes Day** (last Monday of August) is written as a concrete, non-recurring row per year.
- New command: **`php artisan holidays:seed {year?}`** (defaults to the current year, idempotent, rejects implausible years). It also reminds you that proclamation-based movable holidays (Eid'l Fitr, Eid'l Adha, Chinese New Year) must be added manually once proclaimed.
- Covered by `tests/Feature/Payroll/HolidaySeedTest.php`.

### Admins can manage holidays

The **Holidays** page moved from the superadmin-only _Administration_ menu into the **Management** menu, and `HolidayPolicy` now lets **admin** (in addition to superadmin) add/edit/delete — so branch admins can enter newly proclaimed special holidays or backfill a missed one without waiting on a superadmin. Staff still cannot write. Covered by `tests/Feature/Payroll/HolidayAccessTest.php`.

### Cleaner Holidays list

- **Passed holidays sink to the bottom.** The list sorts upcoming holidays first (today counts as upcoming), chronologically; holidays whose date has already passed drop below and are muted with a **Passed** tag. Ordering is done in the query so it holds across pagination.
- **Dates show as `Month Day`** (e.g. `August 21`) instead of the full `YYYY-MM-DD`.

### Notes for reviewers

- `payroll/Attendance/Policies/HolidayPolicy.php` — `create`/`update`/`delete` now `isSuperAdmin() || isAdmin()`.
- `resources/js/layouts/payroll/payroll-sidebar.tsx` — **Holidays** now lives in `managementNav` (visible to admin + superadmin); no duplication for superadmin.
- `payroll/Attendance/Controllers/HolidayController.php` — `index()` orders `CASE WHEN date < today THEN 1 ELSE 0 END`, then `date`.
- `resources/js/pages/payroll/holidays/list.tsx` — `Month Day` formatting (parsed UTC to avoid drift), `Passed` tag, and muted styling for past rows; "today" computed in Manila.
- Full suite green (471 passing).
