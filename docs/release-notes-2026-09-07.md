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
