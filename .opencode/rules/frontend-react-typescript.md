# Frontend Standards

- Use TypeScript strictly: no `any`, no unsafe casts, and no untyped page props. Define or extend types in `resources/js/types/`.
- Prefer `@/` imports and `import type` for type-only imports.
- New screens must be Inertia pages in `resources/js/pages/**`; shared widgets belong in `resources/js/components/**`, not copied between pages.
- Keep page files thin: extract reusable view logic to page-local `components/` or shared hooks/utilities.
- Reuse existing UI primitives from `resources/js/components/ui/` and existing helpers like `submitFormOptions`; do not introduce a second component library.
- Forms should stay aligned with Laravel validation. If client-side schema validation is needed, only adopt Zod after explicit approval because it is not a current dependency.
- Handle all async mutations explicitly with `onSuccess`, `onError`, loading states, and user feedback (`sonner` toasts or field errors); never swallow errors.
- Use named routes via Ziggy/Wayfinder instead of hard-coded endpoints, except where an existing local pattern already requires otherwise.
- Do not modify generated/vendor-like frontend code in `resources/js/components/ui/*`, `resources/js/routes/**`, `resources/js/wayfinder/**`, or `resources/js/actions/**` unless the task explicitly targets it.
- Do not change existing CSS tokens or theme foundations in `resources/css/app.css` just to solve a feature request; prefer layout/component-level fixes.
