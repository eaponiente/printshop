# What's New — July 13, 2026

Refunded payments no longer double up on the Sales page. After a refund, the Partial/Paid list and the daily totals now show only the money the customer has actually paid since that refund — not the earlier payments the refund already reversed.

---

## Sales

### Refunds no longer inflate the payment list or the sales totals

A refund keeps the original payments on record (as an immutable ledger) and appends negative entries that cancel them. Previously the Sales page still listed those cancelled payments as if they were live, so a re-paid transaction showed **every** payment ever taken and the daily **Total Sales** counted them all.

- **Example:** a ₱500 sale paid ₱250 + ₱250, refunded, then paid ₱500 again used to show **three** rows (250, 250, 500) and count as **₱1,000** in the daily total. It now shows a **single ₱500** row and counts as **₱500**.
- A transaction that is refunded and **not** re-paid drops off the Partial/Paid tabs entirely and returns to **Unpaid/pending**, as before.
- The **Total Sales**, per–payment-type breakdown, and **Net Income** summaries all follow the same rule now — only payments taken after the most recent refund are counted.

### Refunding a re-paid transaction reverses the right amount

- Refunding a transaction that had already been refunded once and re-paid now reverses **only the currently-held amount**. Previously a second refund re-summed every historical payment and over-refunded (and pushed cash-on-hand negative). Cash-on-hand now stays correct across repeated refund/re-pay cycles.

### Notes for reviewers

- New `Payment::scopeLive()` (`app/Models/Payment.php`) is the single definition of a "live" payment: a positive row that has not been reversed by a refund. Reversal is recorded **explicitly** — `Transaction::refundPayment()` stamps a new `payments.refunded_at` column on every row it reverses — so the scope is a plain indexed `where amount > 0 and refunded_at is null` (no correlated subquery, no dependence on id ordering). See the *Refund tracking hardening* note below for why this replaced an earlier id-based approach.
- Three call sites now use it: `SalesService::getPaymentQuery()` (`->where('amount','>',0)` → `->live()`) fixes the listing and — because `getPaymentAggregatesFromPayments`/`getFinanceSummaryFromPayments` clone that query — both summaries at once; `Transaction::refundPayment()` and `SaleController::refundPayment()`'s cash-on-hand calc both switch their positive-payment fetch to `->live()`, fixing the second-refund over-reversal.
- **Intended semantic:** the filter is retroactive across days — once a refund exists, the payments it reversed no longer appear in the payment view or summaries for the day they were originally taken (the cash-on-hand adjustment still lands on the refund day). This matches "refunded money is not sales."
- Covered by `tests/Feature/Sales/LivePaymentAfterRefundTest.php` (single live row + summaries = 500 not 1000; fully-refunded/no-repay excluded; `refunded_at` stamped on reversed rows only; classification is by `refunded_at` not id ordering; second refund reverses only the held amount and keeps cash-on-hand correct). Existing `RefundPaymentTest` and `SaleIndexTest` remain green (444 passing overall).

### Refund tracking hardening (`refunded_at` column)

The first cut of `scopeLive` *inferred* which payments a refund had reversed with a correlated subquery (`payments.id > max(id) of the transaction's negative rows`). It worked but was fragile: it assumed refunds are always full and that ids strictly increase in payment order, and it ran a per-row subquery in the listing and the summary `GROUP BY`. This change removes both risks by recording the reversal explicitly.

- **Migration** `2026_07_13_000000_add_refunded_at_to_payments.php` adds a nullable `payments.refunded_at` timestamp plus an index on `(transaction_id, refunded_at)`. It **backfills** existing data so behaviour is unchanged: every positive row a later refund already reversed (same id-heuristic the old scope used) is stamped with that refund's `created_at`. Without the backfill, legacy reversed payments would read as `refunded_at IS NULL` (live) and wrongly reappear in `/sales`.
- The backfill is written in PHP query-builder form, **not** a single `UPDATE ... SELECT` from the same table, because MySQL (error 1093) forbids a subquery selecting from the table being updated. The PHP form runs identically on MySQL (production) and SQLite (tests).
- `Transaction::refundPayment()` now stamps `refunded_at = now()` on exactly the rows it reverses (inside its existing transaction), so second-refund correctness is structural rather than inferred.
- **Why this matters going forward:** classification no longer depends on id ordering (safe against data imports/backfills that insert out-of-order ids), and a future *partial*-refund feature becomes a localized change — stamp only the rows it touches — instead of silently invalidating unrelated live payments. Actually implementing partial refunds (reversing part of a single payment) is out of scope here.
- **Verification note:** the SQLite test suite runs migrations against an empty table, so the backfill loop itself is exercised only on staging data — spot-check against a DB snapshot with pre-existing refunds that `/sales` totals are unchanged after migrating. Local MySQL was unreachable from the dev sandbox, so the migration was validated on SQLite and by review for MySQL 1093-safety.
