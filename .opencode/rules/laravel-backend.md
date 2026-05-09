# Laravel Backend Standards

- Define HTTP endpoints in `routes/web.php` and `routes/settings.php`; keep route names consistent with the existing domain naming and resource patterns.
- Validate request input with dedicated `FormRequest` classes in `app/Http/Requests/**`; keep controllers free of inline validation except for very small, clearly local cases.
- Put business/query logic in services, model methods, enums, or policies, not in large controller methods.
- Use explicit authorization checks through middleware, policies, or role-aware query scopes for branch-sensitive data.
- Wrap multi-write operations in `DB::transaction(...)` and log failures with actionable context before returning user-safe errors.
- Prefer enums and constants over raw status/payment strings when backend behavior depends on those values.
- Return Inertia responses with stable prop names and keep frontend contracts predictable; if a controller prop shape changes, update the corresponding TypeScript types in the same task.
- Preserve routing symmetry between backend and frontend: every new page action should have a named route, controller action, request validation, and typed client usage.
- Add focused Pest coverage in `tests/Feature` for behavior changes in payments, branch restrictions, status transitions, or other business rules; use `tests/Unit` only for isolated pure logic.
- Do not add new PHP or Node dependencies, and do not replace established Laravel/Inertia patterns with a new architecture.
