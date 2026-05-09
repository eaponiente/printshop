# Verification And Constraints

- Before finishing, run the smallest relevant verification set for the files you changed.
- For frontend changes, run `npm run format:check` and `npm run types:check`.
- For backend PHP changes, run `composer lint:check` and targeted `php artisan test` or the smallest relevant Pest test file.
- For cross-stack or risky changes, run `composer ci:check` when feasible.
- If you could not run a command, say so and explain why.
- Do not add new dependencies unless the task explicitly requires and approves them.
- Do not change existing CSS tokens, generated route helpers, or shared UI scaffolding unless the task directly targets those areas.
- Do not make broad refactors while implementing a feature or fix; keep changes scoped to the requested domain.
- When a task changes behavior, prefer adding or updating a focused test near the touched domain rather than skipping verification.
