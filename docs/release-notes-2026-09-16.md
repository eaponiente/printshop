# What's New — September 16, 2026

Employees have been losing punch-ins. Not occasionally — between 40% and 87% of punches on any given day were failing to get a GPS fix, and because the punch was only sent *after* that fix resolved, each failure cost the employee ten silent seconds on a button that gave no sign it had been pressed. Anyone who gave up in that window lost the punch entirely, then filed a correction request the next morning to recover it. Punching no longer waits for GPS. Separately, the date fields on those correction forms are now bounded, because a mistyped month had been booking corrected punches months into the future.

---

## Payroll / Attendance

### Punching in no longer waits for a GPS fix

The punch is now recorded the moment you confirm it. Your location follows a fraction of a second later, in a separate request, and if it never arrives that is fine — the punch is already saved.

**What was happening.** The punch request was issued from *inside* the browser's `getCurrentPosition` callback, configured with `enableHighAccuracy: true`, `maximumAge: 0` and `timeout: 10000`. That combination demands a fresh, uncached, high-accuracy satellite fix on every single punch and forbids reusing one from thirty seconds ago. Indoors, at 8am, on a phone that has just been taken out of a pocket, that routinely runs the full ten seconds and then fails. Nothing was sent to the server until it did. The button had no pending state, so for those ten seconds it looked exactly as it had before being pressed.

**What the production data showed**, for 2026-09-01 through 09-16:

| Day | Punch-ins recorded | No location | Rate |
| --- | --- | --- | --- |
| 09-09 | 40 | 29 | 73% |
| 09-10 | 74 | 44 | 59% |
| 09-11 | 38 | 16 | 42% |
| 09-12 | 44 | 24 | 55% |
| 09-14 | 57 | 25 | 44% |
| **09-15** | **15** | **13** | **87%** |
| 09-16 | 47 | 19 | 40% |

Two things stand out. The failure rate never drops below 40% on any day of the month — this was not a regression, it had always been this way. And 2026-09-15, the day with the worst fix rate, recorded **15 punch-ins against a weekday baseline of 38–74**. Roughly forty people did not manage to punch in that day. The same failure is visible from the other side on days where punch-*outs* outnumber punch-ins — 2026-09-09 recorded 40 in against 53 out.

Those missing punch-ins are the origin of the `missed_punch_in` correction requests the office has been processing every morning, in employees' own words: *"dili ma pislit ang punch in"* — the punch-in can't be pressed.

**What changed:**

- The punch POST goes out immediately on confirm. Coordinates are attached afterwards through a new endpoint, `payroll.attendance.punch.location`, which matches them to your most recent `in`/`out`/`overtime_in`/`overtime_out` punch from the last 5 minutes that has no location yet. A fix that arrives later than that is discarded rather than attached to the wrong punch, and an existing location is never overwritten.
- Geolocation is now requested as `enableHighAccuracy: false`, `maximumAge: 60000`, `timeout: 15000`. A coarse fix cached for a minute is more than accurate enough for a 100m geofence and usually returns instantly. The longer timeout costs nothing now that nothing waits on it.
- The punch buttons show a spinner and disable as a group while a punch is in flight, so pressing one always does something visible.

Punches that still end up without a location behave exactly as before — they save normally and the log note reads "📍 Location not provided". Break punches (`lunch_out`, `lunch_in`) never carried a location and are unchanged.

### Corrections and manual logs can no longer be dated in the future

Four punches in the production database sat months ahead of the day they were created, all of them `source = correction`:

| Approved | Correction was dated |
| --- | --- |
| 2026-09-11 | **2026-11-09** |
| 2026-09-12 | **2026-12-09** |
| 2026-09-14 | 2026-12-14 |
| 2026-09-02 | 2026-09-22 |

The first two are plain day/month transpositions — `09-11` entered as `11-09`, `09-12` as `12-09`. A native date input renders in the *browser's* locale, so the same form presents MM/DD/YYYY on one device and DD/MM/YYYY on another; someone entering the day first on a field expecting the month first produces exactly this. Nothing in the stack objected: the form had no bound, validation was a bare `['required', 'date']`, and approval checked only whether the attendance sheet was locked.

This was never the punch clock's doing. One employee filed a correction for 2026-12-14 at 08:11 and a correct one for 2026-09-14 at 08:29 — eighteen minutes apart, on the same machine — which rules out a wrong device clock and leaves plain data entry.

**What changed:**

| Endpoint | New rule |
| --- | --- |
| `payroll.corrections.store` | `date` must be `before_or_equal:today` |
| `payroll.attendance.manual` | `timestamp` must be `before:tomorrow` (all of today still allowed) |
| `payroll.attendance.logs.store` | `date` must be `before_or_equal:today` |

Correction request validation also moves out of the controller into a `StoreCorrectionRequest` form request, per the contributor playbook.

`CorrectionRequestController::approve` re-checks the date rather than trusting it from submission time, so a request filed before today's bound existed cannot still be approved into a future-dated punch. Approvers get a clear message naming the offending date and are told to deny it and ask for a corrected one.

On the form itself, the date field is now seeded from the server's date and carries `max`, so the calendar opens on the right month and refuses to move past today. An empty date input opens on the *device's* current month, which was the last remaining way a wrong device clock could steer a date even after punch timestamps became server-authoritative in the September 7 release.

---

## Notes for administrators

Existing future-dated punches are **not** cleaned up by this release. They are still in `time_logs`, and each one has produced an `attendance_sheets` row for its date, which will be swept into a payroll period if one is ever generated across that range. They are worth removing before then. Only `PayrollPeriodService::void` / `delete` can unlock a sheet once a period has locked it.

If you want to check your own numbers, the query behind the table above is:

```sql
SELECT DATE(timestamp) d, type, COUNT(*) total,
       SUM(note LIKE '%not provided%') AS no_location
FROM time_logs
WHERE source = 'self_service' AND type IN ('in','out')
  AND timestamp >= '2026-09-01'
GROUP BY d, type ORDER BY d DESC;
```

One caveat when reading `time_logs` directly in a SQL client: `timestamp` is a `DATETIME` and `created_at` is a `TIMESTAMP`, and MySQL converts only the latter using the session timezone. In a client whose session is UTC, `created_at` renders eight hours behind Manila while `timestamp` does not — a punch at 08:00 Manila shows `created_at` of `00:00`. The rows are correct; the two columns are simply not being displayed in the same zone.
