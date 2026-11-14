# What's New — November 14, 2026

Fixed the half-day session split for schedules that include an unpaid tail (for example 08:00–17:30). Afternoon half-days on those schedules were being undercounted.

---

## Payroll

### Half-day split now ignores the unpaid tail

**What was wrong**

- The lunch/afternoon boundary was computed from the schedule's paid minutes *including* the unpaid tail.
- On an 08:00–17:30 schedule (30-min tail) the boundaries landed at 12:15 and 13:15 instead of 12:00 and 13:00.
- An afternoon half-day (in 13:00, out 17:30, daily rate ₱600) was treated as 225 minutes worked: 255 min undertime (₱318.75), daily wage ₱281.25.
- It now correctly counts 240 min undertime (₱300.00) and a flat half-day wage of ₱300.00.

**Who is affected**

- Only schedules longer than 9 hours, i.e. those with an unpaid tail.
- Schedules such as 08:00–17:00 are unchanged.

**Side effects on affected schedules**

- The morning-half boundary also moves 15 minutes earlier (12:15 becomes 12:00).
- Afternoon lateness is now measured from 13:00 instead of 13:15, so an employee arriving at 13:10 is 10 minutes late (previously 0).

---

## Notes for administrators

- Locked attendance sheets inside already generated payroll periods are **not** recalculated. Only unlocked sheets, or sheets that are reprocessed, pick up the corrected split.
- To correct a past period, void it, reprocess the affected sheets, and regenerate.
