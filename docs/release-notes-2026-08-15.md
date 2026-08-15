# What's New — August 15, 2026

Holidays can now be scoped to a specific branch, so a city-only special non-working day no longer has to apply company-wide.

---

## Payroll / Attendance

### Branch-scoped special holidays

Branches sit in different cities, and each city can declare its own local special non-working day — a fiesta, a local proclamation — that has nothing to do with the other branches. Holidays used to be all-or-nothing: adding one meant every branch got it, whether or not it applied there.

- When adding or editing a holiday, admins and superadmins now see an **"Applies to"** branch picker — but only when **Type** is set to **Special**. Leaving it unchecked (or setting Type to Regular) applies the holiday everywhere, exactly like before. **Regular holidays can never be branch-scoped** — the form and the server both reject an attempt to do so, since regular holidays (Independence Day, Christmas, etc.) are national by law.
- The holidays list now shows a **Branches** column: a "Nationwide" badge for company-wide holidays, or the specific branch name(s) for a scoped one.
- Existing holidays are unaffected — all 13 seeded national holidays (New Year's Day, Independence Day, Christmas, etc.) continue to apply nationwide with no migration step needed, since "nationwide" simply means the holiday has no branch attached to it.
- The moment a holiday is saved or removed, any already-computed but not-yet-locked attendance sheet on an affected date is **recalculated immediately**, so holiday pay shows up (or disappears) right away instead of waiting for the next attendance run. Attendance sheets already folded into a generated payroll period are locked and are never touched by this.
- Adding, editing, or deleting a holiday is now audit-logged (who changed what, and when), matching how other payroll changes are already tracked.

### Notes for reviewers

- Migration: `database/migrations/2026_08_15_000000_create_branch_holiday_table.php` — new `branch_holiday` pivot table (`branch_id`, `holiday_id`, unique on the pair, indexed on `holiday_id`). No changes to the existing `holidays` table and no backfill required.
- `app/Models/Payroll/Holiday.php` — new `branches()` relation; `isNationwide()` / `appliesToBranch()` / `applyBranchScope()` helpers; `forDate()` gained a `?int $branchId` parameter (`null` = nationwide-only, never "no filter") and a four-level tiebreak (regular-before-special, branch-before-nationwide, exact-date-before-recurring, id) for when more than one holiday matches a date+branch. Deliberately does not stack regular+special into "double holiday" pay — unchanged from before this change.
- `app/Models/Branch.php` — inverse `holidays()` relation.
- New `payroll/Attendance/Services/HolidayService.php` — wraps create/update/delete in `DB::transaction`, writes `holiday_created`/`holiday_updated`/`holiday_deleted` audit entries (via the `Auditable` trait), and reprocesses unlocked attendance sheets across the union of a holiday's old and new scope after every write. Recurring-holiday reprocessing is bounded to the last year, since there's no queue in this repo and the work runs synchronously.
- New `payroll/Attendance/Requests/StoreHolidayRequest.php` and `UpdateHolidayRequest.php` replace the controller's inline `$request->validate()` calls; both normalize `branch_ids` to a clean int array and reject `branch_ids` on a `type=regular` holiday.
- `payroll/Attendance/Controllers/HolidayController.php` — now delegates to the FormRequests and `HolidayService` instead of validating and writing inline; `index()` eager-loads `branches` and passes the branch list to the page.
- `payroll/Attendance/Services/PayrollPeriodService.php` (`resolveHolidayDates`) and `payroll/Attendance/Services/AttendanceService.php` — both now pass the employee's `branch_id` into the holiday lookup instead of ignoring branch entirely.
- `database/seeders/HolidaySeeder.php` and `app/Console/Commands/GenerateDemoPayrollData.php` — their `firstOrCreate` seeding lookups are now constrained to nationwide rows (`whereDoesntHave('branches')`), so a branch-local holiday sharing a date can no longer silently suppress creation of the national one.
- `HolidayPolicy` is unchanged (superadmin + admin manage holidays, staff cannot) — worth noting that an admin can therefore scope a holiday to a branch other than their own.
- Frontend: `resources/js/pages/payroll/holidays/list.tsx`, new `resources/js/pages/payroll/holidays/components/{holiday-dialog,branches-cell,delete-holiday-dialog}.tsx`, new shared `resources/js/components/shared/branch-multi-select.tsx`.
- Docs: `docs/payroll.md` §2.14 (`holidays` / new `branch_holiday` spec) and §3.7 (Holiday Pay).
- New tests: `tests/Feature/Payroll/HolidayBranchScopeTest.php` (18) — resolution, all four tiebreak rules, CRUD + validation over HTTP, pivot cascade on delete, staff-403-before-validation, audit rows, and `seedYear()` co-existing with a branch-local holiday on the same date. `tests/Feature/Payroll/HolidayReprocessTest.php` (7) — branch isolation, locked-sheet immutability, delete restoring `daily_wage`, narrowing-from-nationwide clearing the other branch, date-move clearing the old date, recurring holidays crossing years while skipping a locked sheet, and a guard that reprocessing never creates a sheet.
- Extended: `PayrollPeriodTest.php` (+2 — branch-isolated holiday pay through `generate()`, plus a guard that an out-of-period, other-branch holiday can't leak through `resolveHolidayDates()`) and `OvertimeRequestTest.php` (+1 — `resolveShiftType` in and out of scope). The existing `< 150` query-count regression test is untouched and still passes. `HolidaySeedTest.php` needed no changes — `forDate()`'s new parameter defaults to `null` (nationwide-only), so its existing one-arg calls still resolve correctly.
- Bug caught by the new tests and fixed before merge: the FormRequests originally guarded the regular-holidays-are-nationwide rule with `$this->filled('branch_ids')`. Laravel's `isEmptyString()` short-circuits on `is_array()`, so `filled()` returns `true` for an empty array — meaning a regular holiday submitted with `branch_ids: []` (exactly what the form sends) was rejected. Now compares `$this->input('branch_ids', []) !== []`.
