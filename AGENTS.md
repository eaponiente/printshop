# AGENTS.md

## Stack

- **Backend**: Laravel 12, PHP 8.4, SQLite (local) / MySQL (Sail/prod)
- **Frontend**: Inertia.js React, TypeScript, Vite, Tailwind CSS v4, Radix UI/shadcn-style
- **Testing**: Pest (PHP), RefreshDatabase on Feature tests
- **Auth**: Laravel Fortify
- **Other**: Ziggy (route helpers), Wayfinder (generated types/actions/routes), babel-plugin-react-compiler (auto-memo)

## Commands

```bash
# Full setup (install deps, generate key, migrate, build assets)
composer setup

# Start all dev servers (laravel serve + queue + pail + vite)
composer dev

# Run PHP tests
php artisan test

# Run a single test file
php artisan test --filter=SaleIndexTest

# PHP lint (Pint — auto-fix)
composer lint

# PHP lint check only (no auto-fix, fails if issues found)
composer lint:check

# Frontend type check
npm run types:check

# Run Pest directly (used in CI)
./vendor/bin/pest
```

## Architecture

### Backend Flow
`routes/settings.php` → Controller (`app/Http/Controllers`) → FormRequest (`app/Http/Requests`) → Service/Model logic

- All routes are in `routes/settings.php` (required from `routes/web.php`). There is no typical `web.php` with many routes — just an Inertia login page and `require settings.php`.
- Business logic lives in `app/Services/` (e.g. `CashOnHandService`, `SalesService`, `Files/`). Keep controllers thin.
- Enums in `app/Enums/<Domain>/` — use them instead of raw status/payment strings. Key enums: `SublimationStatus`, `TransactionStatus`, `ExpenseStatus`, `UserRole`.
- Reusable traits in `app/Concerns/` (`SaleFilterTrait`, `Sortable`).
- Policies in `app/Policies/` — use `$this->authorize(...)` in controllers, not inline role checks.

### Frontend Flow
Inertia pages resolve from `resources/js/pages/<domain>/` matching route names. Props are typed in `resources/js/types/`.

Domain-level component placement:
- `resources/js/pages/<domain>/` — page entrypoints
- `resources/js/pages/<domain>/components/` — domain-specific child components
- `resources/js/components/` — shared/reusable UI
- `resources/js/components/ui/` — **generated** shadcn primitives (do not edit)

Domains: `sales`, `expenses`, `sublimations`, `purchase-orders`, `customers`, `endorsements`, `branches`, `tags`, `settings`, `home`.

### Key Models & State Machines
- **Transaction**: Central ledger for billable services. Status: `PENDING` → `PARTIAL` → `PAID`. Payments go through `$transaction->recordPayment()`. Total amount is locked once payments exist.
- **Sublimation**: Custom orders with status phases (Pre-Payment → Production → Post-Production). Transition logic in `Sublimation::canMoveTo()`. When status reaches `DOWNPAYMENT_COMPLETE`, a linked Transaction is auto-created.
- **Expense**: Cash outflows. Void pattern uses `ExpenseController@void` with reason; reverses cash impact through `CashOnHandService`.
- **PurchaseOrder**: Links to Transactions and Sublimations. Acts as an override for sublimation phase gates.
- **CashOnHandService**: Singleton tracking branch cash drawer balances — used by Sales, Expenses, and Sublimations.

## Conventions

### Must Follow
- Use `route()` (Ziggy) for all frontend URL generation — never hard-code paths.
- Use `@/` imports (resolves to `resources/js/`).
- Use `import type` for type-only imports. No `any` in TypeScript.
- Align frontend/backend naming by domain (same domain prefixes everywhere).
- Wrap multi-write DB operations in `DB::transaction(...)`.
- Every new page needs: named route, controller action, request validation, typed Inertia props, and focused Pest test(s).
- Return Inertia responses with stable prop names. Update TypeScript types when prop shape changes.

### Never
- Do not add new dependencies unless the task explicitly requires them.
- Do not use `$table->enum()` in migrations — use `$table->string()` and keep the enum only at the Eloquent model level (enum cast in `casts()`).
- Do not modify generated code in `resources/js/components/ui/*`, `resources/js/routes/**`, `resources/js/wayfinder/**`, or `resources/js/actions/**`.
- Do not change `resources/css/app.css` theme foundations or CSS tokens for feature requests.
- Do not make broad refactors during a feature/fix — keep changes scoped.
- Do not commit `.env` files.
- Always write a descriptive commit message summarizing the changes based on `git diff`.

### Formatting
- 4-space indentation (`.editorconfig`, `.prettierrc`)
- PHP: Pint with `laravel` preset
- JS/TS: Prettier (single quotes, semicolons, 80 print width) + ESLint (curlies required, import ordering, brace-style 1tbs)

## Gotchas

- **Local DB is SQLite** (`database/database.sqlite`). MySQL-native functions like `YEARWEEK()` won't work in local/test environments.
- **DB destructive commands are prohibited in production** (`DB::prohibitDestructiveCommands`).
- **Sail** is configured via `compose.yaml` for Docker-based local dev with MySQL.
- **Generated route types** come from Wayfinder/Laravel Vite Plugin — routes are resolved at build time, so run `npm run dev` or build after adding routes.
- **SSR** is configured (`resources/js/ssr.tsx`, `bootstrap/ssr` in `.gitignore`). The `dev:ssr` script runs with Inertia SSR enabled.
- **Feature tests use RefreshDatabase** (declared in `tests/Pest.php`). Tests run against in-memory SQLite.
- **`.npmrc`** sets `public-hoist-pattern[]=@inertiajs/core` — needed for pnpm compatibility.
- **`react-compiler`** is enabled via babel plugin in Vite config — React components get auto-memoized, so manual `useMemo`/`useCallback` may be redundant.
- **The `/add-user` route** (in `routes/settings.php`) creates a superadmin user (`username: superadmin`, `password: password`) and seeds branches. Use this for initial setup.

## Notes
- Payroll stuff should go into Payroll Domain namespace.
- Payroll js/tsx files should go into payroll/pages.
- Always think of the use cases and edge cases when creating features or making changes to existing features.
- Always write tests for new features or changes to existing features.
- Always document the new features or changes to existing features.
- Always use pagination on lists except if coming from enums or other queries with limit.
- Always put the validation logic in Request classes and keep them only for validation purpose, no business logic should be there.
- When making class create separate folder for each class. This applies to controllers, models, requests, etc. Example: App\Http\Controllers\Employee\CreateController.php and App\Http\Requests\Employee\StoreEmployeeRequest.php
- Use Tailwind CSS v4 for styling. No need for bootstrap or any other css framework.
- On tsx files, if a component is deemed too big (exceeds 100 lines), split it into smaller components.
- Always run lint and artisan test when committing files.
- Always run `composer lint` and `npm run lint` before committing PHP/JS changes.
- All date displays in frontend must use `toManilaTime()` from `@/utils/dateHelper` — never use `new Date().toLocaleString()` or raw date strings.
- Make sure admin can employee records within their branch.
- Staff can only access their own records.
- Superadmin can access everything.
- For every endpoint that has mutation, make sure there is an audit log.
- If the value is an enum, make a type ts for that.