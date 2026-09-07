# What's New — September 7, 2026

Two payroll fixes today, both in the same area: a rest day worked entirely as an overtime call-in — no regular punch-in at all, just an `OVERTIME_IN`/`OVERTIME_OUT` pair — was paying a full flat daily rate with **zero** overtime, and the sheet was permanently flagged "No punch-in recorded" even though the punches were complete; and, found during review of that first fix, two consecutive midnight-crossing overtime nights back-to-back could leave the third day with a spurious "Overtime punch-in missing" flag even though pay was correct.

---

## Payroll / Attendance

### OT-only call-in days now pay overtime instead of a flat day with none

An employee who is called in on a rest day purely for overtime — punching only `OVERTIME_IN` and `OVERTIME_OUT`, with no regular `IN`/`OUT`/lunch punches at all — was being paid a full flat daily rate and **no overtime pay**, while the attendance sheet sat flagged "No punch-in recorded" indefinitely.

**The reported scenario**: an employee on a ₱600 daily rate (₱75/hr) was called in on a Sunday rest day and worked purely on overtime — `OVERTIME_IN` at 19:44, `OVERTIME_OUT` at 00:20 the next calendar date (a real span of 4h36m / 276 minutes). The sheet showed `hours_worked = 0`, `overtime_minutes = 0`, `overtime_pay = 0`, was flagged incomplete for a missing punch-in that was never going to happen, and still paid the full ₱600 flat rate — the employee's actual overtime work was invisible and unpaid. The correct numbers are `overtime_minutes = 276`, `overtime_pay = ₱431.25` (4.6h × ₱75 × 1.25), `daily_wage = ₱431.25` (no flat day — none was worked), and no incomplete flag, since the overtime pair is complete.

Three separate bugs combined to produce this:

1. **The entire pay calculation — including overtime — only ran when a regular `IN` punch existed.** With no punch-in, `hours_worked`, `overtime_minutes`, and `overtime_pay` all silently stayed at their zero defaults, no matter what overtime punches existed.
2. **Punches were only ever looked up for their own calendar date.** An `OVERTIME_OUT` genuinely stamped after midnight (a different `date` value from the `OVERTIME_IN`, not the same-date encoding the existing midnight-rollover fix from a previous release already handled) simply never reached the shift's own sheet — it landed on the *next* day's sheet instead, as an orphan punch with no matching `OVERTIME_IN`.
3. **Any punch at all — even a single one — was enough to mark the day "present" and pay the full flat daily rate**, with no check for whether a regular shift was actually worked.

**The fix**, in `AttendanceService::processDailyAttendance`:

- The overtime calculation is no longer nested inside the "has a regular punch-in" check — it now runs whenever overtime punches (or an approved `OvertimeRequest`) exist, independent of whether a regular shift was also worked.
- A bounded lookahead: when a day's `OVERTIME_IN` has no same-date `OVERTIME_OUT`, the service now also checks for one on the *next* calendar date, stamped before **06:00** (the default schedule starts at 08:00, so no legitimate next-day shift begins that early). The exclusion is symmetric — the next day drops that early punch from its own set first, so it's never *also* read as that day's own orphan overtime-out, which would otherwise double-count the minutes and falsely flag the next day too.
- The flat daily rate is now withheld specifically (and only) when the day's *entire* punch set is the overtime pair — no `IN`, `OUT`, `LUNCH_OUT`, or `LUNCH_IN` at all. Every other incomplete shape (e.g. a punch-out with a missing punch-in) is unaffected and still pays the full flat rate exactly as before — this was the riskiest part of the change and was scoped narrowly on purpose.
- A day whose only punches are a *matched* `OVERTIME_IN`/`OVERTIME_OUT` pair is now recognized as a complete call-in day, not flagged "No punch-in recorded". Incomplete is still raised when the pair is broken (one side missing) or the existing overtime sanity cap trips.

`hours_worked` intentionally keeps its existing meaning (in→out punch span only) and is **not** widened to include overtime minutes — an OT-only day correctly reads `hours_worked = 0` with the worked time entirely in `overtime_minutes`. Folding overtime into `hours_worked` would risk mislabeling a day as a "half day" under the work-week table's `hours_worked <= 4.5` heuristic, which was intentionally left untouched.

### A chain of consecutive midnight-crossing OT nights could still misflag the last day

While verifying the fix above, a second, narrower defect turned up: it only shows up across **two or more consecutive** midnight-crossing overtime nights back-to-back.

**The scenario**: an employee is called in for overtime on three consecutive nights, each one closing after midnight —

```
2026-08-10 22:00  OVERTIME_IN
2026-08-11 01:00  OVERTIME_OUT   (closes 08-10: 180 min)
2026-08-11 22:00  OVERTIME_IN
2026-08-12 02:00  OVERTIME_OUT   (closes 08-11: 240 min)
```

08-10 and 08-11 computed correctly (180 and 240 minutes, neither flagged). 08-12, however, came out `overtime_minutes = 0` and flagged **"Overtime punch-in missing"** — permanently, since nothing about that flag would ever resolve itself. Pay was not wrong (no minutes were double-counted anywhere), but the sheet sat under a spurious review flag for no reason.

The cause: the fix above teaches each day to recognize when its own early-morning overtime punch actually belongs to *yesterday's* overtime session, so it isn't double-read. To do that, it has to ask "does yesterday already have its own closing punch?" — and that check was answered with a plain "does yesterday have any `OVERTIME_OUT` on record", which doesn't hold up the moment yesterday's own `OVERTIME_OUT` is itself a hand-me-down from the day *before* yesterday. On 08-12, the check found 08-11's 01:00 punch and assumed it was 08-11's own close — but that punch actually belongs to 08-10's session, so 08-11 was never really "closed" from 08-12's point of view, and 08-12's own 02:00 punch was wrongly left unclaimed.

The fix: "yesterday has its own close" now also checks whether yesterday's `OVERTIME_OUT` is itself an early (pre-06:00) punch *and* the day before yesterday has an `OVERTIME_IN` — if both hold, that punch is a hand-me-down, not yesterday's own, and today's punch is correctly recognized as belonging to yesterday's session too. One extra day of lookback is enough — the check never needs to recurse further back than that, since each day only ever needs to know about the *immediately* preceding night.

### Notes for reviewers (OT-only fix)

- `payroll/Attendance/Services/AttendanceService.php` (`processDailyAttendance`) — the overtime block (punch-diff primary, `OvertimeRequest` fallback, midnight rollover, sanity cap) is hoisted out of the `if ($inPunch)` guard. New `NEXT_DAY_OVERTIME_LOOKAHEAD_CUTOFF = '06:00'` class constant backs both the forward lookahead (pull tomorrow's early `OVERTIME_OUT` onto today's sheet when today's `OVERTIME_IN` has no same-date close) and its symmetric exclusion (drop that same punch from tomorrow's own set so it isn't double-read). `base_pay` is zeroed only when `hasRegularPunch` (`IN || OUT || LUNCH_OUT || LUNCH_IN`) is false — every other incomplete shape keeps its current pay unchanged. The incomplete-reason cascade now branches on `hasRegularPunch` first: a MATCHED OT-only pair falls through with no reason set; a broken OT-only pair still reports the appropriate `"Overtime punch-out/in missing"` reason.
- `payroll/Attendance/Services/PayrollPeriodService.php` (`findIncompleteSheets`) — mirrors the lookahead, the symmetric exclusion, and the "matched OT-only pair is complete" rule so the period's Check Payroll warnings agree with the underlying sheets. The batch `TimeLog` query (already fetching the whole period in one query for this method) is widened by one day on each side instead of adding a query per sheet — the `< 150` query-count regression test on `PayrollPeriodService::generate` is a separate method and untouched by this change.
- `resources/js/pages/payroll/attendance/sheet-detail.tsx` — the "Hours Worked" row now reads "Overtime only" instead of a bare "0h" when `hours_worked = 0` and `overtime_minutes > 0`, so the detail view doesn't read like a punch-tracking gap. No other frontend changes were needed — `my-attendance.tsx`'s table already shows Hours and OT as separate columns side by side, which is already unambiguous.
- Extended `tests/Feature/Payroll/RestDayPayTest.php` (+2) and `tests/Feature/Payroll/AttendanceOvertimeMidnightTest.php` (+3): the reported rest-day scenario (276 min, 1.25x, ₱431.25, not incomplete); confirmation the leaked cross-midnight punch does not appear as an orphan on the next day's sheet; the identical OT-only shape on an ordinary weekday (rest-day work has no special pay treatment, so it must behave identically); a regression guard confirming a punch-out with no punch-in still pays the full flat rate (the narrow scope guard); and a boundary check confirming a next-day punch stamped *at or after* 06:00 is not swallowed by the lookahead.
- Full suite (`php artisan test`) passes at 517 tests / 2,402 assertions after this change, including all 8 new tests above.
- Docs: `docs/payroll.md` §3.3 (Daily Wage Formula — the `isOtOnlyDay` exception and a worked example) and §3.4 (Overtime — the bounded 06:00 lookahead, the OT-only `is_incomplete` rule, and a note that `hours_worked` is deliberately unaffected by overtime).

### Notes for reviewers (chain fix)

- `payroll/Attendance/Services/AttendanceService.php` — the "does yesterday have its own overtime-out" check no longer stops at a raw existence query. It now fetches yesterday's `OVERTIME_OUT` punch (if any) and additionally checks whether *that* punch is itself an early (pre-06:00) carry-over from the day before yesterday (an `OVERTIME_IN` on that earlier date). Only when it's genuinely yesterday's own does the exclusion stay dormant.
- `payroll/Attendance/Services/PayrollPeriodService.php` (`findIncompleteSheets`) — mirrors the same one-day-further-back check using the already-batched `TimeLog` collection (no added query). The batch window is widened to **two** days before `periodStart` (was one) so the lookup resolves in memory for the period's first sheet too; the window after `periodEnd` is unchanged. Separately, the "no punches at all" fallback reason ("No punch-in recorded") now keys off the *raw* pre-exclusion punch set rather than the post-exclusion one — otherwise a day whose only punch turned out to be entirely a carry-over (post-exclusion: empty) would be misread as "no real activity that day" and flagged, when in fact it correctly has nothing left to report.
- Added a permanent regression test for the exact three-night chain above to both `tests/Feature/Payroll/AttendanceOvertimeMidnightTest.php` (asserting 180 / 240 / 0 minutes and `is_incomplete === false` on all three days) and `tests/Feature/Payroll/PayrollPeriodTest.php` (asserting `findIncompleteSheets` emits no warning row at all for the period, with the query window deliberately starting on the second night so the two-day-back lookup for the first night is exercised via the widened window rather than an in-range sheet).
- Confirmed the pre-existing same-date manual-entry case (`AttendanceOvertimeMidnightTest.php`'s first test — OT-in 19:58 / OT-out 01:15 both stamped with the shift's own date) is unaffected: that case never reaches the new one-day-further-back check at all, because the same-date encoding means `firstWhere` already finds the OT-out on the shift's own day and the existing rollover handles it before the lookahead/exclusion logic is ever consulted.
- Full suite (`php artisan test`) passes at 519 tests / 2,409 assertions after this second fix (517 / 2,402 after the first fix above, +2 for this one).
- Docs: `docs/payroll.md` §3.4 — new paragraph spelling out the carry-over-chain rule and the two-day batch-window widening in `PayrollPeriodService`.

---

## Payroll / Attendance — Time & Timezone Integrity

A third, unrelated defect surfaced today: an employee's laptop clock was running roughly 15 days ahead of real time, and the "My Attendance" page showed that wrong date on its hero clock — because the page read the device clock directly, and because a client-supplied timestamp was (under a feature flag) able to override the server's own clock on the punch endpoint itself. Two changes close this out completely: punch timestamps are now stamped by the server, unconditionally, with no client input accepted at all; and every date/time rendered anywhere in the payroll UI now displays in Asia/Manila regardless of the viewer's device timezone, instead of quietly deferring to whatever timezone the browser happens to be in.

### Punch timestamps are now server-authoritative — no exceptions

Self-service punches used to accept an optional client-supplied `timestamp`, honored whenever a feature flag (`ENABLE_CUSTOM_PUNCH_TIME` / `config('app.enable_custom_punch_time')`) was on and a `time-logs.useCustomTimestamp` gate passed (which was true for any user once the flag was on, or for superadmins regardless). In production the flag was off, so this specific bug wasn't reachable end-to-end there — but the underlying gap was real: the punch endpoint had a code path that trusted a client-supplied clock at all, and the flag was `true` in the local `.env`. The entire path is now gone: `PunchRequest` no longer has a `timestamp` rule, `TimeLogController::punch` no longer reads one, and `TimeLogService::punch` no longer accepts one — it always stamps `now()`. There is no configuration that can turn this back on.

Admin backdating (correcting a missed clock-in, recording an off-hours arrival, etc.) was never meant to go through the self-service punch screen anyway — it now has one clear, admin-only home: `payroll.attendance.manual`. That endpoint's `type` rule was widened from `in,lunch_out,lunch_in,out` to also accept `overtime_in,overtime_out`, so admins can backdate overtime punches too (previously impossible through this endpoint). Its inline `$request->validate([...])` was also extracted into `App\Http\Requests\Payroll\Attendance\ManualTimeLogRequest`, matching the FormRequest convention every other payroll endpoint follows.

### Every date/time in the payroll UI now genuinely renders in Asia/Manila

`resources/js/utils/dateHelper.ts`'s `toManilaTime()` had been broken since it was written: it imported and extended dayjs with the `utc`/`timezone` plugins, then never actually called `.tz()` — so `dayjs(date).format(format)` silently formatted in whatever timezone the *viewer's device* was set to. For a viewer in the same timezone as the shop (or with a correctly-set clock) this was invisible. For the employee with the 15-day-ahead clock, it meant the page's own "My Attendance" hero clock read from `new Date()` directly, showing a date over two weeks in the future.

`toManilaTime()` now does what its name says: a bare date-only string (`YYYY-MM-DD`, e.g. a `birth_date`) is parsed *as* Manila local time so it never shifts a day for a viewer in another zone, and everything else is treated as an instant and converted to Asia/Manila for display. A new `toManilaClock()` helper (`hh:mm A`) covers time-of-day rendering so callers stop hand-rolling `toLocaleTimeString` formats.

The "My Attendance" hero clock (the one the employee actually saw wrong) no longer reads the device clock at all. The page now receives a `serverNow` prop (`TimeLogController::index` adds `'serverNow' => now()->toIso8601String()`), and the client computes a server-vs-client offset once on mount (`Date.parse(serverNow) - Date.now()`); every subsequent tick renders `Date.now() + offset` through `toManilaTime`/`toManilaClock`. A wrong device clock can move neither the displayed time nor the date. The entire feature-flagged "Set Punch Time" picker UI (date/time inputs, the "Now" reset button, and the `payload.timestamp` it built) was deleted along with the flag — the punch payload now carries only `type` and the geolocation fields.

Two backend response shapes were also silently timezone-ambiguous: `TimeLogService::punchSequenceForDate`'s `last_punch.timestamp`, `AttendanceSheetController::show`'s `lockedAt`, and `PayrollPeriodController::show`'s `checked_at` were all serialized with `->toDateTimeString()` (e.g. `"2026-09-07 08:00:00"`, no offset), which browsers parse as device-local time. All three now use `->toIso8601String()` (e.g. `2026-09-07T08:00:00+08:00`), so the instant they represent is unambiguous regardless of the viewer's browser. (Eloquent model timestamps handed straight to Inertia — e.g. the raw `TimeLog` collection in `punchSequenceForDate` — were already fine; Laravel's default JSON serialization for `Carbon` already emits UTC ISO-8601 with a `Z`.)

Every remaining raw `toLocaleTimeString`/`toLocaleDateString`/`toLocaleString`/`new Date(...).getFullYear()`-style device-local formatting in the payroll attendance pages was swept and replaced with `toManilaTime`/`toManilaClock`, per the AGENTS.md rule against raw browser locale formatting:

- `resources/js/pages/payroll/attendance/my-attendance.tsx` — the hero clock, the attendance-history date column, the grouped recent-logs date headers and times, the in/out summary, and "Today's Punches". `groupTimeLogsByDate` now buckets by the Manila calendar date (`toManilaTime(timestamp, 'YYYY-MM-DD')`) instead of the device-local `getFullYear()/getMonth()/getDate()`, so day headers can no longer land on the wrong day for an off-zone viewer.
- `resources/js/pages/payroll/attendance/geo.tsx` — the Date and Time columns on the attendance-geolocation table.
- `resources/js/pages/payroll/attendance/sheet-detail.tsx` — the per-punch time in the admin sheet-detail punch list.
- `resources/js/pages/payroll/payroll/period-show.tsx` — "Last checked" now renders `checked_at` (now offset-bearing) through `toManilaTime` instead of a bare `new Date(...).toLocaleString()`.

`resources/js/pages/payroll/holidays/list.tsx` was deliberately left untouched — it already passes `timeZone: 'Asia/Manila'` explicitly and was never broken.

### Notes for reviewers

- Files changed: `app/Http/Requests/Payroll/Attendance/PunchRequest.php`, `app/Http/Requests/Payroll/Attendance/ManualTimeLogRequest.php` (new), `payroll/Attendance/Controllers/TimeLogController.php`, `payroll/Attendance/Controllers/AttendanceSheetController.php`, `payroll/Attendance/Controllers/PayrollPeriodController.php`, `payroll/Attendance/Services/TimeLogService.php`, `payroll/Attendance/Policies/TimeLogPolicy.php`, `app/Providers/AppServiceProvider.php`, `config/app.php`, `.env.example` (+ local `.env`), `resources/js/utils/dateHelper.ts`, `resources/js/pages/payroll/attendance/my-attendance.tsx`, `resources/js/pages/payroll/attendance/geo.tsx`, `resources/js/pages/payroll/attendance/sheet-detail.tsx`, `resources/js/pages/payroll/payroll/period-show.tsx`.
- `tests/Feature/Payroll/PunchEndpointTest.php`: the three tests exercising the removed custom-timestamp feature flag were replaced with a single `travelTo()`-frozen-time test asserting a client-supplied timestamp 15 days in the future is ignored and the stored `TimeLog.timestamp` equals the frozen server time; a new test confirms the manual endpoint now accepts `overtime_in` and backdates it correctly.
- Full suite (`php artisan test`) passes at 518 tests / 2,409 assertions (519 → 518: −3 removed custom-timestamp tests, +2 new tests). `composer lint:check` (Pint) and `npm run lint:check` (ESLint, including the React Compiler purity rule — the offset clock had to be computed inside a `useEffect` rather than a `useRef` initializer, since `Date.now()` during render is flagged as impure) both pass clean on every file this change touches. `npm run types:check` and `npm run format:check` show only pre-existing, unrelated failures (confirmed identical on the base commit via `git stash`) — a handful of files missing the global Ziggy `route()` type, and ~22 files across the repo that predate this change's Prettier formatting pass.
- Docs: `docs/payroll.md` — new paragraphs under "Attendance (Time Logs)" documenting that punch timestamps are server-authoritative, that backdating goes through `payroll.attendance.manual`, and the Manila-only frontend rendering rule.

---

## Two date form-defaults were still reading the device clock

The same wrong-laptop-clock incident above also exposed a narrower, second bug class: two "today" form defaults were still built with `new Date()` on the client rather than trusting the server, so they inherited the same failure mode — a wrong device clock writes the wrong date straight into the database. Worse, both used `toISOString()`, which reads UTC — so even a *correctly* set clock prefilled **yesterday's** date for anyone submitting the form between midnight and 8am Manila.

**The fix**: a new shared Inertia prop, `serverToday` (`HandleInertiaRequests::share()` → `now()->toDateString()`, i.e. Manila, since `config('app.timezone')` is `Asia/Manila`), is now available on every page and typed on `sharedPageProps` in `resources/js/types/global.d.ts`. Two call sites were switched to read it instead of constructing a client-side date:

- `resources/js/pages/payroll/employees/create.tsx` — `hire_date` now defaults to `serverToday` (read via `usePage().props`) instead of `new Date().toISOString().slice(0, 10)`.
- `resources/js/pages/expenses/expenses-dialog.tsx` — `expense_date` had two bugs, both fixed: the create-branch default now uses `serverToday` instead of `new Date().toISOString().split('T')[0]`; the edit-branch default, which round-tripped an already-stored date through `new Date(...).toISOString()` (risking a day-shift for an off-zone viewer), now uses the existing `toDateInput()` helper from `@/utils/dateHelper` — `expenses.expense_date` is a plain `date` column (see `database/migrations/2026_03_19_110134_create_expenses_table.php`), so the stored value is already a bare `YYYY-MM-DD` string with no time-of-day component to convert.

**Swept and deliberately left alone**: `resources/js/pages/payroll/holidays/list.tsx` (already correct — passes `timeZone: 'Asia/Manila'` explicitly), and three "Printed on" / "Generated" footer timestamps (`resources/js/utils/printTable.ts`, `resources/js/pages/payroll/payroll/payslip.tsx`, `resources/js/pages/payroll/reports/print.tsx`) — these are display-only strings never submitted to the server, so they don't write a wrong date into the database; they were left as a cosmetic-only, separate concern rather than folded into this fix.

### Notes for reviewers (serverToday fix)

- Files changed: `app/Http/Middleware/HandleInertiaRequests.php` (new `serverToday` shared prop), `resources/js/types/global.d.ts` (typed on `sharedPageProps`), `resources/js/pages/payroll/employees/create.tsx`, `resources/js/pages/expenses/expenses-dialog.tsx`.
- New test: `tests/Feature/SharedInertiaPropsTest.php` — freezes time with `travelTo()` and asserts the `serverToday` prop on a rendered page equals `now()->toDateString()`.
- Docs: `docs/payroll.md` — new paragraph under "Attendance (Time Logs)" documenting the `serverToday` shared prop and the rule that date form-defaults must come from the server, never the device clock.

---

## `toManilaTime()` still had one narrow gap: offset-less datetimes

Today's `serverToday`/Manila-rendering work above fixed `toManilaTime()`'s big miss (it never called `.tz()` at all). It left one narrower gap behind: the date-only regex it used to decide "parse this AS Manila" vs. "treat this as an instant and convert" only matched a bare `YYYY-MM-DD`, not an offset-less *datetime*. Any string like `"2026-09-07 08:00:00"` — which has no timezone designator and is therefore already Manila wall time (the backend's app timezone) — fell into the instant branch instead, and got parsed in the *viewer's device* timezone before being shifted to Manila. That double-counts the offset: a UTC device saw 08:00 AM render as 04:00 PM; a US Pacific device saw it render as 11:00 PM the *previous* day.

This reaches production through `payroll/Audit/Models/AuditLog.php`'s `serializeDate()`, which deliberately emits the offset-less `"Y-m-d H:i:s"` shape (not the default Carbon ISO-8601), rendered through `toManilaTime` on `resources/js/pages/payroll/audit/list.tsx`. Any off-Manila-timezone viewer of the Audit Logs page was seeing wrong timestamps.

**The fix**: widen the pattern from date-only to "date, optionally followed by a time-of-day, with no timezone designator" —

```ts
const MANILA_LOCAL_PATTERN = /^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/;
```

(renamed from `DATE_ONLY_PATTERN`, since it's no longer date-only). Anything the pattern matches is parsed as Manila local time; anything carrying a `Z` or an explicit `±HH:MM` offset still takes the instant-and-convert branch exactly as before — an ISO string like `"2026-09-07T00:00:00.000000Z"` or `"2026-09-07T08:00:00+08:00"` is unaffected by this change.

### JS now has a test runner

There was no JavaScript test infrastructure in this repo at all before today — no Vitest/Jest, no config, no `test` script. This bug was exactly the kind that's easy to miss without one, since it's invisible whenever the developer's own machine (or CI runner) happens to already be set to Asia/Manila. Added:

- **Vitest** as a devDependency, configured via a `test` block in the existing `vite.config.ts` (using vitest's `/// <reference types="vitest/config" />` triple-slash directive rather than a separate `vitest.config.ts`, since the project already has one Vite config and this keeps it in one place) — `environment: 'node'` is sufficient since these are pure function tests with no DOM.
- `npm run test:js` (`vitest run`) and `npm run test:js:watch` (`vitest`) scripts.
- `resources/js/utils/dateHelper.test.ts`, covering `toManilaTime` and `toManilaClock`, importing `describe`/`it`/`expect` explicitly from `vitest` (rather than global test types) so neither `tsconfig.json` nor `eslint.config.js` needed to change. The suite forces `process.env.TZ = 'UTC'` at the very top of the file, before dayjs is imported anywhere — this whole bug class is invisible under a Manila (+8) test runner, so pinning a non-Manila device timezone is the point. Cases: date-only unaffected in any device timezone; the offset-less-datetime regression case (asserted 08:00 AM); a `Z`-suffixed ISO instant; an explicit-offset ISO instant; null/undefined/empty → `'N/A'`; `toManilaClock`'s `hh:mm A` format. Verified directly (not assumed) that this suite fails against the old `DATE_ONLY_PATTERN` — the offset-less-datetime case reports `04:00 PM` instead of `08:00 AM` under `TZ=UTC` — and passes against the fix.

### Notes for reviewers (toManilaTime offset-less-datetime fix)

- Files changed: `resources/js/utils/dateHelper.ts` (`DATE_ONLY_PATTERN` → `MANILA_LOCAL_PATTERN`, widened regex, updated JSDoc), `vite.config.ts` (Vitest `test` config), `package.json` (`vitest` devDependency, `test:js`/`test:js:watch` scripts).
- New file: `resources/js/utils/dateHelper.test.ts`.
- `npm run test:js` passes (8/8). `npm run lint:check` and `composer lint:check` stay clean. `npm run types:check` shows only the same 5 pre-existing `Cannot find name 'route'` errors (unrelated, not touched by this change). `npm run format:check` shows the same ~22 pre-existing unformatted files as before this change, plus none new (the new test file was formatted with Prettier before landing). `php artisan test` is unaffected by this JS-only change — still 519 passed / 2429 assertions.
- Docs: `docs/payroll.md` — "Attendance (Time Logs)" section expanded with the precise timezone contract (no-designator ⇒ Manila wall time, designator ⇒ instant) and a pointer to the new `npm run test:js` suite.
