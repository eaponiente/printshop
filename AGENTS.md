# AGENTS.md

## Core Principles

- Keep responses concise and to the point unless the user explicitly asks for detail.
- Never assume features, UX, workflows, or designs that were not explicitly requested.
- Always consider edge cases and real-world usage scenarios.
- Prefer maintainability and clarity over clever abstractions.

---

# Stack

## Backend
- Laravel 12
- PHP 8.4
- Laravel Fortify
- SQLite (local/tests)
- MySQL (production)

## Frontend
- React
- TypeScript
- Inertia.js
- Tailwind CSS v4
- Vite
- Radix UI / shadcn-style components

## Testing
- Pest PHP
- RefreshDatabase

---

# Architecture Rules

## Controllers
Controllers must only:
- authorize,
- validate,
- delegate to services,
- return responses.

Do not place business logic inside controllers.
All models are inside the App\Models

---

## Services
- Use services for complex business processes.
- Split overly large service methods into smaller methods.
- Use pessimistic locking (`lockForUpdate()`) when updating financial or ledger-related records.

---

## Validation
- Always use FormRequest classes.
- Never place validation logic inline in controllers.
- Request classes should only handle validation and authorization.

---

## Authorization
- Always use policies/gates.
- Never perform manual role checks inline.

Bad:
```php
if ($user->role === 'superadmin')
```

Good:
```php
$this->authorize(...)
```

---

## Audit Logs
Every mutating action must create an audit log.

Examples:
- create
- update
- delete
- void
- rehire

Use:
```php
Payroll\Audit\Traits\Auditable
```

---

# Domain Constraints

## Transactions
- `amount_total` becomes immutable once payments exist.
- Refunds must use negative ledger entries.

---

## Sublimations
- Production flow cannot proceed until downpayment requirements are satisfied unless bypassed by Superadmin.
- Backend currently expects single-file uploads.

---

## Branch Access Rules

### Superadmin
- Full unrestricted access.

### Admin
- Limited to assigned branch.
- Special branch group sharing applies to:
  - Babak
  - Peñaplata
  - Tibungco

### Staff
- Limited to assigned branch.
- Can only access their own records.

---

# Database Rules

## Enums
Do not use database enums.

Bad:
```php
$table->enum(...)
```

Good:
```php
$table->string(...)
```

Use PHP backed enums in model casts.

---

## SQLite Compatibility
Avoid MySQL-specific SQL functions such as:
- `YEARWEEK()`

Ensure all logic works in SQLite tests.

---

## Transactions
Wrap multi-model mutations inside database transactions.

```php
DB::transaction(function () {
    //
});
```

---

# Frontend Rules

## TypeScript
- Always use `import type` for type-only imports.
- Avoid `any`.

---

## Components
- Components larger than ~100 lines should be split into smaller components.
- Place child components inside a `components/` folder.

---

## Routing
Always use Ziggy route helpers.

Good:
```ts
route('sales.index')
```

Bad:
```ts
'/sales'
```

---

## Dates
Always use:
```ts
toManilaTime()
```

Never use raw browser locale formatting.

---

## Styling
- Use Tailwind CSS v4 only.
- Do not introduce additional CSS frameworks.

---

## UI Components
Do not directly modify generated `ui/` shadcn primitive files.

---

# Project Structure Rules

## Payroll Domain
- Payroll backend code belongs inside the `Payroll\` namespace.
- Payroll frontend pages belong inside:

resources/js/payroll/pages

---

## File Organization
When a domain grows, split files into dedicated folders.

Examples:

App\Http\Controllers\Employee\CreateController.php
App\Http\Requests\Employee\StoreEmployeeRequest.php

---

# Testing Rules

Always write tests for:
- new features,
- modified behavior,
- authorization,
- edge cases,
- invalid workflows.

Before committing:

```bash
composer lint
npm run lint
php artisan test
```

---

# Pagination Rules

Always paginate lists unless:
- static datasets,
- enums,
- intentionally limited queries.

---

# Edit Mode Rules

When implementing planned changes:

- Identify work that can run in parallel.
- Use sub-agents for parallelizable tasks when possible.
- Separate backend, frontend, tests, and documentation work when practical.
- Avoid overlapping file edits between sub-agents.

---

# Performance & Safety

## Inertia Props
- Only expose necessary data.
- Never expose sensitive fields in shared auth props.

---

## React Optimization
- Manual `useMemo` and `useCallback` are usually unnecessary due to React compiler optimizations.

---

# Documentation Rules

- Document significant feature changes.
- Keep AGENTS.md focused on contributor and AI operating instructions.
- Move detailed business/domain documentation into `/docs`.

---

# Important Reminders

- Always think through edge cases before implementing changes.
- If a backend value is an enum, create matching frontend TS types.
- Prefer stable, maintainable solutions over shortcuts.
