# AGENTS.md

## 1. Project Overview & Business Logic
### What is this application?
This is a **Printing Shop Management System** designed to track print operations, custom sublimation orders, customer accounts, store transactions (invoices, payments, and refunds), business expenses, cash-on-hand tracking, employee records, incentives, and payroll.
### Core Business Domains & Workflows
#### 1. Custom Sublimation Orders
- **Status Lifecycle Phases**:
  - **Pre-Payment Phase**: `FOR_APPROVAL` (for_approval) → `DONE_LAYOUT` (done_layout) → `WAITING_FOR_DP` (waiting_for_dp).
  - **Production Phase**: `DOWNPAYMENT_COMPLETE` (downpayment_complete) → `FOR_SIZING` (for_sizing) → `DONE_SIZING` (done_sizing) → `PRINTED` (printed) → `CUT` (cut) → `SEWING` (sewing) → `SEWED` (sewed) → `CHECKED` (checked) → `READY_FOR_PICKUP` (ready_for_pickup) → `CLAIMED` (claimed) → `COMPLETED` (completed).
- **Trigger Gates**:
  - Transitioning status to `WAITING_FOR_DP` auto-creates a linked `Transaction` with a matching `amount_total`.
  - Sublimations cannot proceed into the production phase (anything from `DOWNPAYMENT_COMPLETE` onwards) until downpayment is paid, or the sublimation is linked to a `PurchaseOrder`, or a Superadmin bypasses the check. Check validation is performed via `Sublimation::canMoveTo()`.
#### 2. Transactions & Ledger
- **Invoice Tracking**: Central ledger for all sales and customer billing.
- **Transaction Status**: `PENDING` (pending) → `PARTIAL` (partial) → `PAID` (paid).
- **Financial Lock**: A transaction's `amount_total` becomes **locked** and cannot be altered once payment records are attached.
- **Refunds**: Payments can be partially or fully refunded. Refunds write negative amounts in the payments ledger to ensure aggregate calculations balance.
#### 3. Expense Management
- **Cash Outflow**: Track business operating costs.
- **Expense Status**: `PENDING` (pending) → `PAID` (paid) | `REJECTED` (rejected) | `VOID` (void).
- **Drawer Impact**: Voiding an expense triggers a reverse accounting impact in the branch cash drawer via `CashOnHandService`.
#### 4. Payroll, Employees & Incentives
- **Employee Lifecycle**: Manage employee positions, statuses (`ACTIVE` | `INACTIVE`), hire dates, rehiring events, and current daily salary rates.
- **Incentive System**: Commission/incentive tracker for design/production work.
- **Benefits & Projects**: Association of employees with active project work and benefit deductions.
### Target Audience / Role-Based Access Control (RBAC)
- **Superadmin**: Global dashboard access across all branches. Bypasses all validation gates, accesses database configuration checks, manages payroll records, and reviews system-wide Audit Logs.
- **Admin**: Can view and manage employee records, expenses, and transactions within their assigned branch.
  - *Special Group Branch Rule*: Admins from the special branches (`Babak`, `Peñaplata`, `Tibungco`) share access to records from other branches in this group.
- **Staff**: Restricted to front-counter operations at their assigned branch. Staff can only access their own records and cannot view other employees' records.
---
## 2. Tech Stack & Architecture
### Core Frameworks
- **Backend**: Laravel 12 (PHP 8.4) with **Laravel Fortify** for authentication, **Ziggy** for frontend route binding, and **Wayfinder** for type and controller route/action code-generation.
- **Frontend**: **React** + **TypeScript** powered by **Vite** and **Tailwind CSS v4** styling. Uses **Radix UI** primitives styled like shadcn.
- **Auto-Memoization**: Frontend utilizes `babel-plugin-react-compiler` for automatic React component memoization. Manual `useMemo` and `useCallback` declarations are generally redundant.
### State Management & Data Flow
- **Monolithic Inertia.js**: Inertia.js acts as the bridge. Controllers return Inertia views with typed props.
- **No Global Client-Side Store**: UI components rely on Inertia's page props and form helper states (`useForm`).
- **Imports**: TypeScript imports resolve using the `@/` alias (mapping to the `resources/js/` directory).
### Database & Storage
- **Database**: **SQLite** is used locally (`database/database.sqlite`), while **MySQL** is configured for Sail (Docker) and production.
- **Pessimistic Locking**: Key ledger mutations (payment logging, invoice generation) use pessimistic locking (`lockForUpdate()`) to prevent race conditions during concurrent requests.
- **Storage**: Custom file/image uploads (like Sublimation blueprints) target AWS S3 (via `league/flysystem-aws-s3-v3`).
  - Storage paths utilize signed temporary URLs (`Storage::disk('s3')->temporaryUrl(...)`) to secure raw file references.
  - The local `public` disk is used for local fallbacks.
### Testing
- **PHP Unit/Feature Testing**: **Pest PHP** is configured.
- **State Preservation**: Feature tests use the `RefreshDatabase` trait (declared in `tests/Pest.php`) and execute against an in-memory SQLite schema.
---
## 3. Directory Structure & Key Files
```
.
├── app/                           # Core Laravel app namespace
│   ├── Concerns/                  # Reusable Eloquent traits (e.g., SaleFilterTrait, Sortable)
│   ├── Enums/                     # Castable Enums grouped by domain subdirectories
│   │   ├── Expenses/              # ExpenseStatus, ExpenseTypeOfPaymentEnum
│   │   ├── Sales/                 # TransactionStatus, TransactionTypeOfPaymentEnum
│   │   └── Sublimations/          # SublimationStatus
│   ├── Http/
│   │   ├── Controllers/           # Controllers grouped by domain folders
│   │   └── Requests/              # Request classes grouped by domain folders
│   ├── Models/                    # Eloquent Database models
│   ├── Policies/                  # Authorization policies (e.g., ExpensePolicy, UserPolicy)
│   └── Services/                  # Core Business Services (SalesService, CashOnHandService)
│
├── payroll/                       # Isolated Domain Module (autoloaded PSR-4 Payroll\)
│   ├── Audit/                     # Audit logger, Auditable trait, AuditLog model
│   ├── Employee/                  # Employee-specific controllers, requests, and policies
│   └── Services/                  # Salary & payroll computation services
│
├── resources/                     # Frontend assets
│   ├── css/
│   │   └── app.css                # Tailwind base CSS
│   └── js/
│       ├── components/            # Reusable React components
│       │   └── ui/                # Read-only generated shadcn primitives (DO NOT EDIT)
│       ├── layouts/               # Dashboard layouts
│       ├── pages/                 # Inertia page entrypoints grouped by domain folders
│       │   ├── sales/             # Page: list, components/collect-payment-dialog.tsx
│       │   ├── sublimations/      # Page: list, components/sublimation-dialog.tsx
│       │   └── payroll/           # Payroll dashboards and employee management
│       ├── types/                 # TypeScript interfaces (e.g., transaction.ts)
│       └── utils/                 # Frontend helper scripts (e.g., dateHelper.ts)
│
├── routes/
│   ├── web.php                    # Application entrypoint
│   └── settings.php               # Core settings, settings panel, domain, and API routes
│
└── tests/
    └── Feature/                   # Pest test files grouped by domain
```
### Core Configuration / Entrypoint Files
- `routes/settings.php`: Main router file containing all application page endpoints.
- `resources/js/app.tsx`: Frontend bootstrap entrypoint.
- `resources/js/ssr.tsx`: Server-Side Rendering entrypoint.
- `vite.config.ts`: Vite compilation config.
- `eslint.config.js` / `.prettierrc`: ESLint and Prettier rules.
---
## 4. Coding Standards & Conventions
### Naming Conventions
- **Backend (PHP)**:
  - **Controllers & Requests**: Organized in separate subdirectories for each domain. If a domain component increases in size, break controllers into single-action classes under that folder (e.g., `App\Http\Controllers\Employee\CreateController.php`).
  - **Models**: StudlyCase singular (e.g., `PurchaseOrderDetail.php`).
  - **Database columns**: snake_case.
- **Frontend (TS/React)**:
  - **Components**: kebab-case filenames for dialogs and widgets (e.g., `collect-payment-dialog.tsx`), PascalCase folders matching Inertia router patterns.
  - **Types**: Always use `import type` for type-only declarations. Avoid using the `any` type.
### Design Patterns & Architectures
- **Thin Controllers**: Controllers must only authorize the action, validate the request inputs, and return responses. All business operations, DB changes, and aggregate queries must be delegated to the **Service Layer** (e.g., `SalesService`).
- **Form Request Validation**: Never perform raw inline validation inside controllers. Utilize FormRequest classes (`StoreEmployeeRequest`) to filter inputs.
- **DB Transactions**: Wrap all operations modifying multiple models in a database transaction block:
  ```php
  DB::transaction(function () use ($data) {
      // Create models...
      // Update balances...
  });
  ```
- **Audit Logging**: For every mutating action (create, update, delete, void, rehire), an audit log record must be generated.
  - Use the `Payroll\Audit\Traits\Auditable` trait.
  - Call `$this->audit('action_name', $model, $beforeAttributes, $afterAttributes)` within the mutating block.
- **Inertia Props**: Always supply stable, filtered Inertia properties. Avoid over-fetching relationships (e.g., restrict user lists or branches to what the authenticated user is authorized to interact with).
### Preferred Implementations
- **Routing**: Always generate frontend paths using Ziggy's `route()` helper (e.g., `route('sales.index')`). Never hard-code URI paths.
- **Timezones**: All date rendering in TSX views must use the `toManilaTime()` helper from `@/utils/dateHelper` to prevent local browser timezone shifts.
- **Pessimistic DB Locks**: Use `lockForUpdate()` when reading rows that will be updated in the same request to prevent race conditions.
- **Pest Assertions**: Write comprehensive Pest feature assertions (e.g., testing overpayment blocks, cross-branch leakage blocks, or invalid status progressions).
---
## 5. Critical Domain Rules & Constraints
### ⚠️ Absolute "Must-Follows"
#### 1. Database Migrations & Enums
- **No SQLite-broken functions**: Never use MySQL-specific raw SQL statements like `YEARWEEK()` directly in concerns or scopes; compile date ranges programmatically via Carbon to prevent tests/SQLite environments from failing.
- **String Enums**: Do NOT use `$table->enum()` in migrations. Keep migrations as `$table->string('status')` and bind/cast the state using standard PHP Backed Enums inside the Eloquent model casts array.
- **Destructive Command Gate**: Destructive migrations are prohibited on production environments (handled automatically via `DB::prohibitDestructiveCommands()`).
#### 2. Scope & Security
- **Branch Scoping**: Staff can only access sublimations and transactions belonging to their own branch. Superadmins bypass this. Admins have access to their own branch (or their special group branches). Ensure query scopes enforce this filtering dynamically.
- **Policy Enforcement**: Avoid checking roles manually inline using string comparison (e.g., `if (auth()->user()->role === 'superadmin')`). Instead, register a policy class and call `$this->authorize('action', $model)`.
#### 3. Component Size Limits
- On React (`.tsx`) files, if a component is deemed too large (exceeding 100 lines), it **MUST** be split into smaller, focused child components. Put these inside a `components/` subfolder in the corresponding page domain.
#### 4. Pre-commit Code Verification
- Before committing any changes, you must run:
  ```bash
  composer lint         # Run PHP Pint auto-formatter
  npm run lint          # ESLint typescript/javascript checker
  php artisan test      # Verify all backend feature suites pass
  ```
---
## Gotchas & Architecture Caveats
1. **Transaction Payments Lock**: The `amount_total` field on a `Transaction` becomes immutable once `payments` count > 0.
2. **Sublimation Image Batching**: The backend expects single file validation uploads. Multi-file uploads must loop request posting from the client, or the backend schema must be updated with array uploads.
3. **HandleInertiaRequests auth.user**: The shared Inertia session passes the logged-in user context. Ensure sensitive attributes (like passwords or unneeded relations) are hidden or filtered out during response serialization.
4. **Wayfinder Generation**: Adding new routes or actions requires rebuilding generated types and hooks. Run `npm run dev` or a build pass to compile type outputs when route structures change.

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
- Use services when the process becomes too big.
- If a method in a service becomes too big then split it to multiple methods so that its easier to maintain.
- Dont use enum on migrations so that the column can be used by sqlite as well.