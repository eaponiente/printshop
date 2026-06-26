# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Read these first

- **`AGENTS.md`** — the authoritative contributor playbook (controller rules, FormRequest mandate, Auditable trait, branch access tiers, "no DB enums", `toManilaTime()` rule, Pest/RefreshDatabase, Tailwind v4 only, etc.). Follow it verbatim — this file does not repeat what's there.
- **`docs/payroll.md`** — domain workflow notes for Sales, Sublimations, Expenses.

## Common commands

| Task | Command |
|---|---|
| Boot full dev stack (server + queue + pail + vite) | `composer dev` |
| SSR dev stack | `composer dev:ssr` |
| Pint format (auto-edits files) | `composer lint` |
| Pint check (read-only) | `composer lint:check` |
| Full PHP test suite | `php artisan test` |
| Single test file | `php artisan test tests/Feature/Payroll/PunchEndpointTest.php` |
| Single test by name | `php artisan test --filter="self-service profile"` |
| Frontend ESLint (auto-fix) | `npm run lint` |
| Frontend ESLint (read-only) | `npm run lint:check` |
| TypeScript check | `npm run types:check` |
| Prettier write | `npm run format` |
| Full pre-commit gate (lint+format+types+tests) | `composer ci:check` |

`composer test` runs `config:clear`, `lint:check`, then `php artisan test` — that ordering matters because stale config can mask real failures.

Tests run on SQLite (`phpunit.xml` sets `DB_CONNECTION=sqlite` with in-memory `DB_DATABASE=testing`). Per AGENTS.md, avoid MySQL-only SQL functions (e.g. `YEARWEEK`).

## Architecture

### Two-namespace PSR-4 layout

`composer.json` registers **two** top-level namespaces:

- `App\` → `app/` (standard Laravel)
- `Payroll\` → `payroll/` (separate top-level directory, **not** inside `app/`)

The Payroll namespace is split by sub-domain:

```
payroll/
  Attendance/  Controllers/, Services/, Policies/, Requests/, Enums/
  Audit/       audit log + Auditable trait
  Employee/    Controllers, Services, Policies, Requests
  SewedItem/
  Services/
```

**Models still live in `app/Models`** even for Payroll (`App\Models\Payroll\*`). AGENTS.md is explicit: "All models are inside the `App\Models`."

### Routes

Most application routes — including the entire `/payroll/*` tree — are defined in **`routes/settings.php`**, not `routes/web.php`. Look there first when wiring or auditing a route. Route name examples: `payroll.attendance.punch`, `payroll.periods.show`, `payroll.employee.profile.update`.

Ziggy generates the JS route helpers; use `route('payroll.x.y')` in `.tsx` files instead of literal paths.

### Frontend

Inertia.js + React 19 + Tailwind v4. The React compiler is in use (Babel plugin enabled in `vite.config`), so manual `useMemo`/`useCallback` is almost never needed (AGENTS.md spells this out).

- Page components live under `resources/js/pages/<domain>/`.
- Each domain page that needs its own children uses a sibling `components/` folder (e.g. `resources/js/pages/payroll/employees/components/employee-form.tsx`).
- DataTable component expects a Laravel paginator shape; if you need to render a fixed array, server-side paginate (see `PayrollPeriodController::show` → `paginate(50)`).

### Payroll calculation flow

This is the part that's most often touched and most often misunderstood. The data path is:

```
TimeLog punches (IN, LUNCH_OUT/IN, OUT, OVERTIME_IN/OUT)
        │
        ▼
AttendanceService::processDailyAttendance($employee, $date)
        │   writes one row per (employee_id, date) into attendance_sheets
        │   columns are canonical: daily_wage, late_deduction,
        │   undertime_deduction, fine_deduction, overtime_pay, holiday_pay …
        ▼
PayrollPeriodService::generate($branch, $start, $end)
        │   pre-fetches all sheets + benefits in batched queries
        │   one PayrollPeriodItem per employee
        │   gross_pay  = SUM(daily_wage)              ← late/undertime/fine already inside
        │   net_pay    = gross_pay + deminimis
        │               − sss − philhealth − pagibig − cash_advance
        ▼
PayrollPeriodItem rows surface on:
  • period-show (admin)            ← Deductions column shows POST-gross only
                                     (SSS + PhilHealth + Pag-IBIG + CA)
  • my-payslip / payslip detail    ← employee self-service
```

Invariants the test suite enforces — do not break:

1. `daily_wage` is canonical. Frontend must call `formatCurrency(sheet.daily_wage)` rather than re-deriving the number from its components.
2. `late_deduction`, `undertime_deduction`, and `fine_deduction` are **already inside** `gross_pay`. Adding them to any "Deductions" total double-charges the employee. The post-gross deductions are only SSS / PhilHealth / Pag-IBIG / CA. Covered by `tests/Feature/Payroll/PayrollPeriodTest.php` ("net_pay equals gross_pay + deminimis minus statutory and cash advance only").
3. Overtime minutes come from the `OVERTIME_IN`→`OVERTIME_OUT` punch diff; an approved `OvertimeRequest` is the fallback when no OT punches exist. There is no longer a 1-hour OT floor.
4. `unpaid_tail_minutes` only applies when the schedule's paid work would otherwise exceed 480 min — see `AttendanceService::processDailyAttendance` ("effective tail" derivation).
5. A locked attendance sheet (i.e. inside a generated period) is immutable. `AttendanceService` short-circuits on locked sheets; `PayrollPeriodService::void`/`delete` are the only paths that unlock them, and both run inside `DB::transaction`.
6. `PayrollPeriodService::generate` has a query-count regression test (`< 150` queries for 10 employees × 5 days). When adding new lookups inside the loop, pre-fetch them in `generate()` and pass slices into `generateItemForEmployee` rather than querying per-row.

### Authentication & policies

- Laravel Fortify provides auth + 2FA.
- `EnsureUserCanLogin` middleware blocks deactivated users at login.
- Attendance gates are action-based (`Gate::define('time-logs.punch', …)`) and registered in `app/Providers/AppServiceProvider.php`. The corresponding policies live under `payroll/<Domain>/Policies/`.
- Self-service "edit my own profile" is gated through `UpdateSelfEmployeeRequest`; the rules whitelist defines what's editable (phone, address, birth_date, SSS/PhilHealth/Pag-IBIG) — name/email/TIN are intentionally locked.

### Routes worth knowing

| Name | Purpose |
|---|---|
| `payroll.attendance.index` | Employee "My Attendance" page (TimeLogController::index) |
| `payroll.attendance.punch` | Self-service punch endpoint (throttle: 30/min) |
| `payroll.attendance.manual` | Admin manual log |
| `payroll.periods.generate` | Generate a payroll period for a branch+date range |
| `payroll.periods.show` | Period detail; items are server-side paginated |
| `payroll.my-payslip` | Employee payslip list |
| `payroll.employee.profile.update` | Self-service profile update |

### Production data caveats

`DB::prohibitDestructiveCommands` is enabled in production (`AppServiceProvider::configureDefaults`). Pint and migrations should never be run with destructive flags on prod.

`config('app.timezone') = 'Asia/Manila'`. `Date::use(CarbonImmutable::class)` is set. Always use `toManilaTime()` on the frontend per AGENTS.md.
