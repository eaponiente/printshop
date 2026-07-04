# Project Structure

A printing-shop management system: **Laravel 12** backend + **Inertia.js / React 19 / TypeScript** frontend (Tailwind v4), with a bolted-on **Payroll** module that lives in its own top-level namespace. This document explains what each directory is *for* and how the pieces fit together, rather than cataloguing every file.

---

## Big picture

- **Monolith, server-driven SPA.** Laravel handles routing, auth, validation, and data; controllers return `Inertia::render('<page>', $props)` and React renders the matching page. There is almost no separate REST API — the page component and its props *are* the contract.
- **Two PHP namespaces** (see `composer.json` `autoload.psr-4`):
  - `App\` → `app/` — the standard Laravel application (Sales, Sublimations, Expenses, Purchase Orders, Users, Incentives).
  - `Payroll\` → `payroll/` — a self-contained payroll/HR domain kept *outside* `app/`. **Its Eloquent models are the exception**: they still live in `app/Models/Payroll/*`.
- **Authoritative contributor rules live in `AGENTS.md` and `CLAUDE.md`** (FormRequest mandate, Auditable trait, branch access tiers, "no DB enums", Manila-timezone rules, Pest/RefreshDatabase, Tailwind v4 only). Read those before changing conventions.

---

## Backend — `app/` (namespace `App\`)

| Directory | Purpose |
|---|---|
| `app/Http/Controllers/` | Thin controllers grouped by domain (`Sales/`, `Sublimations/`, `PurchaseOrders/`, `Incentives/`, `Users/`, `Settings/`, `Home/`, plus a `Payroll/` dashboard shim). They validate via FormRequests, delegate to Services, and return Inertia responses. |
| `app/Http/Requests/` | FormRequest classes — **all** validation + authorization lives here, one per action (e.g. `Transactions/GetTransactionsRequest`). Controllers should never validate inline. |
| `app/Http/Middleware/` | Request pipeline hooks, notably `HandleInertiaRequests` (shares `auth.user`, `pending_requests`, flash to every page) and `EnsureUserCanLogin`. |
| `app/Models/` | Eloquent models for the core app **and** payroll (`app/Models/Payroll/*`). This is the single source of DB truth. Rich models: `Transaction` (payments, `recordPayment`/`refundPayment`, invoice numbering), `Sublimation`, `Employee`. |
| `app/Services/` | Business logic that's too heavy for a controller: `Sales/SalesService` (transaction/payment queries + finance aggregates), `Sales/CashOnHandService`, `Incentives/`, `Files/` (S3 uploads), `Payroll/`. |
| `app/Enums/` | PHP backed enums grouped by domain (`Sales/TransactionStatus`, `Sublimations/SublimationStatus`, `Users/UserRole`, …). Used for casts and `->map()` helpers fed to the frontend. Note: enums back string columns — **no DB `ENUM` types**. |
| `app/Policies/` | Authorization policies for core models (Branch, Expense, PurchaseOrder, User, Incentive, PayrollSetting). Registered via `Gate::policy` in the providers. |
| `app/Concerns/` | Shared traits/mixins: `SaleFilterTrait` (date/branch query scopes reused by Sales & Expenses), `Sortable`, validation-rule traits. |
| `app/Actions/Fortify/` | Laravel Fortify auth actions (login, password reset, 2FA plumbing). |
| `app/Providers/` | Service providers — `AppServiceProvider` wires gates, `Date::use(CarbonImmutable)`, production guards (`DB::prohibitDestructiveCommands`). |
| `app/Console/` | Artisan command registration/scheduling. |

## Backend — `payroll/` (namespace `Payroll\`)

A parallel mini-application split by sub-domain. Each sub-domain carries its own `Controllers/`, `Requests/`, `Policies/`, and (where relevant) `Services/`, `Enums/`:

| Directory | Purpose |
|---|---|
| `payroll/Attendance/` | The heart of payroll. `Services/TimeLogService` (punch in/out), `Services/AttendanceService` (turns punches into a computed `attendance_sheets` row per employee/day), and `Services/PayrollPeriodService` (locks sheets and generates a `PayrollPeriodItem` per employee — gross pay, statutory deductions, cash advances, net pay). Also holds leave/overtime/correction/cash-advance/fine controllers and the RBAC policies. |
| `payroll/Employee/` | Employee CRUD + schedules: `Controllers/EmployeeController`, `Services/EmployeeService` (also provisions the linked login `User`), `Requests/`, `Enums/` (position, status). |
| `payroll/SewedItem/` | Piece-rate "sewed item" tracking and payslip generation for sublimation production. |
| `payroll/Audit/` | Audit-log model, controller, policy, and the `Auditable` trait that other payroll models use to record changes. |
| `payroll/Services/` | Cross-cutting payroll services not tied to one sub-domain. |

> The payroll **models** are not here — they're in `app/Models/Payroll/` (`AttendanceSheet`, `PayrollPeriod`, `PayrollPeriodItem`, `TimeLog`, `Employee`, `Salary`, `Benefit`, `CashAdvance`, `Holiday`, `SssContributionBracket`, …).

---

## Frontend — `resources/js/` (Inertia + React + TypeScript)

| Directory | Purpose |
|---|---|
| `pages/` | One folder per domain (`sales/`, `sublimations/`, `payroll/`, `purchase-orders/`, `expenses/`, `incentives/`, `auth/`, `settings/`, …). Each folder's top-level `*.tsx` is an Inertia **page** (rendered by name from a controller); a sibling `components/` folder holds page-specific children (dialogs, filters, tables). |
| `components/` | App-wide React components. `ui/` is the shadcn/Radix primitive library (button, dialog, table, select…); `shared/` and `charts/` are reusable composites; loose files like `app-sidebar.tsx`, `data-table.tsx`, `breadcrumbs.tsx` are the app chrome. `data-table.tsx` expects a Laravel paginator shape. |
| `layouts/` | Inertia layouts a page wraps itself in: `app-layout` (main sidebar shell), `payroll/*` (payroll sidebar shell), `auth/*`, `settings/`. |
| `types/` | Hand-written TypeScript types mirroring the props/models the backend serializes (`transaction.ts`, `employee.ts`, `sublimations.ts`, `navigation.ts`, …). |
| `routes/`, `actions/`, `wayfinder/` | **Generated** typed route/action helpers (Laravel Wayfinder). Combined with Ziggy's `route()`, these let `.tsx` call `route('sales.index')` etc. instead of hardcoding URLs — do not hand-edit. |
| `hooks/`, `utils/`, `lib/`, `constants/` | Client helpers: `utils/formatters` (currency), `utils/dateHelper` (`toManilaTime`), `printTable`, `cn()`, etc. |

The React Compiler is enabled in `vite.config.ts`, so manual `useMemo`/`useCallback` is rarely needed.

---

## Data, routing, config, tests

| Directory / file | Purpose |
|---|---|
| `routes/settings.php` | **Where most routes actually live** — the entire authenticated app *and* the `/payroll/*` tree (prefix `payroll`, name `payroll.`). `routes/web.php` is a thin shell that `require`s it. `routes/console.php` holds scheduled/artisan commands. |
| `database/migrations/` | Schema history (append-only). |
| `database/factories/` | Model factories for tests/seeding. |
| `database/seeders/` | Demo/reference data (`DatabaseSeeder` orchestrates Branch → Users → Customer → Transaction/Sublimation/Attendance seeders). |
| `config/` | Standard Laravel config plus app-specific `company.php` and `payroll.php`. `config('app.timezone') = 'Asia/Manila'`. |
| `tests/` | **Pest** tests. `Feature/` (HTTP + integration, grouped by domain — e.g. `Feature/Payroll/PayrollPeriodTest`, `Feature/Sales/SaleIndexTest`) is where most coverage lives; `Unit/` for isolated logic. Runs on in-memory SQLite (`phpunit.xml`) with `RefreshDatabase`. |
| `docs/` | Domain notes (`payroll.md`) and dated release notes. |
| `public/` | Web root + built Vite assets. `storage/` holds uploads/logs; `bootstrap/` boots the framework. |
| Root configs | `composer.json` (PHP deps + the two namespaces), `package.json` + `vite.config.ts` (frontend build), `tsconfig.json`, `eslint.config.js`, `pint.json` (PHP formatter), `components.json` (shadcn), `phpunit.xml`. `AGENTS.md`/`CLAUDE.md` are the contributor playbooks. |

---

## How it all connects

**A typical request (e.g. opening `/sales`):**
1. `routes/settings.php` maps the URL to `App\Http\Controllers\Sales\SaleController@index`.
2. A **FormRequest** (`GetTransactionsRequest`) validates/authorizes the query params.
3. The controller delegates to **`SalesService`** for the heavy queries (transaction/payment builders, finance aggregates) and reads models under `app/Models/`.
4. `HandleInertiaRequests` middleware adds shared props (`auth.user`, `pending_requests`).
5. The controller returns `Inertia::render('sales/list', $props)`.
6. Inertia loads `resources/js/pages/sales/list.tsx`, typed against `resources/js/types/*`, wrapped in a `layouts/` shell, rendered with `components/ui` primitives, and navigating via the generated `routes/`/`actions/` helpers.

**Where the two namespaces meet:** the app and payroll domains are separate code trees but share the **database and the `User` model**. A payroll `Employee` links to an `App\Models\User` (login/role/branch); branch scoping (`branch_id`) and roles (`UserRole`) drive access across both. The payroll frontend lives under `resources/js/pages/payroll/` and uses `layouts/payroll/`, but is served by the same Inertia pipeline.

**The payroll calculation spine** (most-touched, most-misunderstood — see `docs/payroll.md` and `CLAUDE.md`):
`TimeLog` punches → `AttendanceService` writes a canonical `attendance_sheets` row per employee/day → `PayrollPeriodService::generate` locks those sheets and produces one `PayrollPeriodItem` per employee (gross pay with late/undertime/fine already baked in, minus statutory deductions and cash advances) → surfaced on the admin period view and employee payslips. Locked sheets are immutable; only voiding/deleting a period unlocks them.

**Sales money flow:** `Transaction` (the sale) has many `Payment` rows; `recordPayment`/`refundPayment` transition its status (pending → partial → paid) and feed `CashOnHandService`. The Projects page tabs (Partial / Paid / Unpaid) and the finance breakdown are all built from these two tables via `SalesService`.

**Frontend ↔ backend typing:** because Inertia passes PHP arrays straight into React, the `resources/js/types/*` files and the generated `routes/`/`actions/` helpers are the glue that keeps the untyped boundary honest — when a controller's props or a route changes, those are the files to update alongside it.
