# Project Architecture

- Stack: Laravel 12, PHP 8.4, Inertia.js React, TypeScript, Vite, Tailwind CSS v4, Radix UI/shadcn-style components, Ziggy, Wayfinder, Pest.
- Frontend entrypoints live in `resources/js/app.tsx` and `resources/js/ssr.tsx`; Inertia pages resolve from `resources/js/pages/**/*.tsx`.
- Backend HTTP flow is `routes/*.php` -> controller in `app/Http/Controllers` -> request validation in `app/Http/Requests` -> domain/query logic in `app/Services` or model methods.
- Use named Laravel routes and client helpers (`route(...)`, Wayfinder) instead of hard-coded URLs whenever possible.
- Put new Inertia pages under `resources/js/pages/<domain>/`; domain-specific child components belong in `resources/js/pages/<domain>/components/`.
- Put shared reusable UI in `resources/js/components/`, shared hooks in `resources/js/hooks/`, shared types in `resources/js/types/`, and shared utilities in `resources/js/utils/`.
- Put new backend services in `app/Services/<Domain>/`, request classes in `app/Http/Requests/<Domain>/`, policies in `app/Policies/`, and enums in `app/Enums/<Domain>/`.
- Keep frontend and backend naming aligned by domain: `sales`, `expenses`, `sublimations`, `purchase-orders`, `customers`, `endorsements`, `branches`, `tags`, `settings`.
- When adding a new feature, update all touched layers deliberately: route, controller, request, domain/service logic, page/dialog/component, and typed frontend props.
- Preserve existing design tokens and shared UI primitives; extend through composition instead of creating a parallel design system.
