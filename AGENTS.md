# AGENTS.md

## Project Overview

**Java Print** — a printing/sublimation shop management system built with **Laravel 12** (PHP) + **React 19** (TypeScript) glued together via **Inertia.js v2**.

- **PHP**: 8.4+, Laravel Fortify for auth (username-based, 2FA, email verification)
- **Frontend**: React 19 + TypeScript 5.7 (strict), Tailwind CSS v4, shadcn/ui (New York style), TanStack Table, Recharts
- **Build**: Vite 7 with `laravel-vite-plugin`, Inertia SSR enabled
- **Database**: MySQL 8.4 (production), SQLite (dev/testing). Cache, sessions, and queue are all database-driven.
- **File Storage**: AWS S3 (production) with 3-hour signed URLs, local disk (dev)
- **Testing**: Pest PHP v4, GitHub Actions CI (PHP 8.4 + 8.5 matrix)
- **Linting**: Laravel Pint (PHP), ESLint flat config + Prettier (JS/TS)

## Development Commands

```bash
composer run dev          # Start dev server + queue + Vite concurrently
composer run dev:ssr      # Same but with Inertia SSR
composer run setup        # Full project boot (install, env, migrate, build)
composer run ci:check     # Full CI pipeline (lint + format + type-check + test)
composer run test         # Config clear + lint check + Pest tests

npm run dev               # Vite dev server only
npm run build             # Production build

php artisan migrate:fresh --seed   # Fresh DB with branch seeds
```

Run individual Pest tests:
```bash
php artisan test --filter=CollectPaymentTest
```

### Before Committing
Always run `composer run ci:check` to verify linting, formatting, type-checking, and tests all pass.

## Architecture

This is a **server-rendered SPA** — controllers pass data to React page components as Inertia props. There is no traditional REST API except `/api/customers` for autocomplete search. All form submissions, filtering, and pagination go through Inertia `router` (GET/POST/PATCH/DELETE) with server-side validation via Laravel Form Requests.

### Directory Layout

```
app/
  Actions/Fortify/          # CreateNewUser, ResetUserPassword
  Concerns/
    SaleFilterTrait.php     # Date/branch scoping for queries
    Sortable.php            # Sort query helper
  Enums/                    # Status/role/payment-type enums (8 total)
  Http/
    Controllers/            # Organized by domain: Home, Sales, PurchaseOrders, Sublimations, Settings, Users
    Middleware/              # HandleInertiaRequests (shared props), auth, verified, etc.
    Requests/               # Form validation by domain
  Models/                   # 14 Eloquent models (Transaction = "Sale")
  Providers/                # AppServiceProvider, FortifyServiceProvider
  Services/
    Files/FileUploadService.php
    Sales/SalesService.php, CashOnHandService.php

resources/js/
  app.tsx                   # CSR entry point (Inertia + React)
  ssr.tsx                   # SSR entry point
  components/
    ui/                     # ~35 shadcn/ui primitives
    charts/                 # MonthlyPieChart, TransactionBarChart
    shared/                 # SearchCustomersField, SubmitFormOptions
    data-table.tsx          # Generic TanStack data table
    app-shell.tsx, app-sidebar.tsx, nav-main.tsx, etc.
  layouts/
    app-layout.tsx          # Resolves to AppSidebarLayout
    app/app-sidebar-layout.tsx, app/app-header-layout.tsx
    auth-layout.tsx, auth/  # Auth page layouts
  pages/                    # Inertia page components, organized by domain
    auth/                   # login, register, forgot/reset-password, 2FA, etc.
    dashboard/
    sales/                  # list, dialog + 6 subcomponents
    sublimations/           # list, dialog, gallery, tags + subcomponents
    expenses/               # list, dialog, expense-actions
    purchase-orders/        # list, dialog + subcomponents
    customers/, users/, branches/, endorsements/, tags/
    settings/               # profile, security, appearance
  types/                    # TypeScript interfaces per domain + global.d.ts
  hooks/, lib/, utils/

routes/
  web.php                  # Root route (/) + includes settings.php
  settings.php             # All authenticated routes, grouped by middleware
```

## Database Schema

### Core Tables

| Table | Purpose | Notable Columns |
|---|---|---|
| `transactions` | Central sales ledger (the "Sale") | `invoice_number` (INV-YYYY-00001), `amount_total`, `amount_paid`, `balance` (virtual: total - paid), `status` (pending/partial/paid), `branch_id`, `customer_id`, `staff_id`, `attachment_path` |
| `payments` | Immutable payment ledger | `transaction_id`, `amount` (negative for refunds), `payment_type`, `staff_id` |
| `customers` | Client records | `first_name`, `last_name`, `company`, softDeletes |
| `sublimations` | Custom sublimation orders | 20-phase status workflow, `branch_id`, `customer_id`, `user_id`, `transaction_id` (nullable, auto-created on production), `production_authorized` |
| `tags` | Categorization tags | `name`, `color` |
| `sublimation_tag` | Many-to-many pivot | `sublimation_id`, `tag_id` |
| `images` | Polymorphic attachments | `url`, `imageable_id`, `imageable_type` |
| `purchase_orders` | Supplier POs | `po_number`, `grand_total`, `status`, `branch_id`, softDeletes |
| `purchase_order_details` | PO line items | `quantity`, `unit_price`, `total_cost` (virtual: qty * price) |
| `expenses` | Business outflows | `amount`, `status` (paid/voided), `payment_type`, `branch_id`, `receipt`, softDeletes |
| `branches` | Store locations | `name` |
| `users` | Staff accounts | `username` (unique), `role` (admin/superadmin/staff), `branch_id`, softDeletes |
| `cash_on_hands` | Per-branch cash drawer | `branch_id`, `amount` |
| `endorsements` | Endorsement records | `branch_id`, `user_id`, `amount` |

### Key Schema Details
- `transactions.balance` is a MySQL **virtual generated column** (`amount_total - amount_paid`)
- `purchase_order_details.total_cost` is a **virtual generated column** (`quantity * unit_price`)
- Payments use an **immutable ledger pattern** — refunds append negative amounts, never delete rows
- Invoice numbers generated with `lockForUpdate()` inside a DB transaction to prevent duplicates

## Routes

All routes are named routes (accessed via Ziggy's `route()` helper on frontend). Two files: `routes/web.php` (root only) and `routes/settings.php` (everything else, behind `auth` middleware).

### Key Routes

| Route | Method | Controller | Notes |
|---|---|---|---|
| `/` | GET | — | Login page (Inertia) |
| `/dashboard` | GET | `Home\DashboardController` | Analytics dashboard |
| `/sales` | Resource | `Sales\SaleController` | CRUD |
| `/sales/payment/{transaction}` | PATCH | `SaleController::updatePayment` | Collect payment |
| `/sales/refund/{transaction}` | PATCH | `SaleController::refundPayment` | Refund |
| `/sales/{sale}/attachment` | POST/DELETE | `SaleController` | Upload/delete attachment |
| `/expenses` | Resource | `Sales\ExpenseController` | CRUD |
| `/expenses/{expense}/void` | PATCH | `ExpenseController::void` | Void (immutable) |
| `/purchase-orders` | Resource | `PurchaseOrders\PurchaseOrderController` | CRUD |
| `/purchase-orders/{po}/status` | PATCH | `PurchaseOrderController::updateStatus` | |
| `/purchase-orders/{po}/transactions` | POST | `PurchaseOrderController::createTransaction` | Link to sale |
| `/sublimations` | Resource | `Sublimations\SublimationController` | CRUD |
| `/sublimation/{id}/update-status` | PATCH | `SublimationController::updateStatus` | Phase transition |
| `/sublimation/{id}/update-staff` | PATCH | `SublimationController::updateStaff` | Reassign |
| `/sublimation/{id}/update-duedate` | PATCH | `SublimationController::updateDueDate` | |
| `/sublimations/{id}/tags` | POST/DELETE | `Sublimations\SublimationTagController` | |
| `/sublimations/{id}/images` | GET/POST/DELETE | `Sublimations\SublimationImageController` | |
| `/customers` | Resource | `Users\CustomerController` | CRUD |
| `/users` | Resource | `Users\UserController` | |
| `/branches` | Resource | `Users\BranchController` | |
| `/tags` | Resource | `Settings\TagController` | |
| `/endorsements` | Resource | `Users\EndorsementController` | |
| `/settings/profile` | GET/PATCH/DELETE | `Settings\ProfileController` | |
| `/settings/security` | GET | `Settings\SecurityController` | |
| `/settings/password` | PUT | `SecurityController::update` | |
| `/api/customers` | GET | `CustomerController::indexApiList` | Search autocomplete |

## Business Domains

### 1. Sales (Transactions)
Central accounting ledger. Creates invoice numbers (`INV-{YYYY}-{00001}`), tracks payments split by type (cash, GCash, card, check, bank_transfer, debit), auto-transitions status based on amount_paid vs amount_total (pending → partial → paid). Supports partial refunds as negative payment entries. Automatically adjusts branch cash-on-hand for cash payments/refunds.

### 2. Sublimations
Custom order production with a **20-phase state machine** enforced via `Sublimation::canMoveTo()`:
- **Phase 1 (Pre-Pay):** for_approval → done_layout → waiting_for_dp
- **Phase 2 (Production):** downpayment_complete → for_sizing → done_sizing → printed → cut → sewing → sewed → checked → ready_for_pickup → claimed
- **Phase 3 (Completion):** completed
- Auto-creates a linked Transaction when crossing into production phase
- Role-based overrides: superadmins can skip phase restrictions

### 3. Purchase Orders
Supplier procurement with line-item details (`PurchaseOrderDetail`). Status tracking. Can be linked to a Transaction.

### 4. Expenses
Business cash outflows. Receipt uploads. Immutable voiding (voided expenses stay in DB, marked `voided` with reason).

### 5. Cash on Hand
Per-branch physical cash drawer. `CashOnHandService` adjusts balances on cash payments, cash expenses, and cash refunds.

### 6. User Roles
- **superadmin**: full access, cross-branch visibility, can override sublimation phase restrictions
- **admin**: branch-level management
- **staff**: own transactions only

### 7. Branch Scoping
`SaleFilterTrait` forces non-superadmin users to only see their branch's data via query scoping.

## Key Architectural Decisions

1. **No REST API** — Data flows via Inertia props, not fetch/axios calls. The only exception is `/api/customers` for search.
2. **Immutable payment ledger** — Refunds create negative entries; original records are never modified or deleted.
3. **Virtual computed columns** — `balance` and `total_cost` computed at DB level, not application level.
4. **Invoice generation** — Uses `lockForUpdate()` to prevent race conditions under concurrent access.
5. **Lazy-loaded dialogs** — Form dialogs use `React.lazy()` + `Suspense` to reduce initial bundle size.
6. **Signed S3 URLs** — Sale attachment files served via 3-hour signed URLs, cached. Staff role users don't receive attachment URLs.
7. **Transaction = Sale** — Route model binding uses `Transaction $sale`; there is no separate "Sale" model.
8. **Wayfinder** — Auto-generated typed route helpers in `resources/js/actions/`.

## Naming Conventions

- **Controllers**: Domain-subfolder, singular (`Sales\SaleController`, `Users\CustomerController`)
- **Models**: Singular, PascalCase (`Transaction`, `PurchaseOrder`)
- **Enums**: PascalCase under `App\Enums\{Domain}\` (`TransactionStatus`, `SublimationStatus`)
- **Frontend pages**: Domain folder, kebab-case files (`sales/sales-dialog.tsx`, `sublimations/sublimation-list.tsx`)
- **Route model binding**: Controllers use descriptive parameter names (`$sale` for Transaction, `$sublimation` for Sublimation)
- **Database**: snake_case tables, Eloquent's default conventions

## Testing

- Pest PHP v4 with RefreshDatabase trait in feature tests
- `tests/Feature/Sales/CollectPaymentTest.php` — prevents overpayment, restricts cross-branch, records valid payment
- `tests/Feature/Sales/RefundPaymentTest.php` — refund scenarios
- GitHub Actions runs PHP 8.4 + 8.5 matrix on push/PR to develop/main/master

Don't run any tests after the prompts unless the user explicitly ask.