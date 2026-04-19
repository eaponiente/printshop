# 🔍 Full-Stack Codebase Audit — Printing Shop Management
> Laravel + Inertia.js + React · Audit Date: 2026-04-20 · Auditor: Antigravity

---

## Table of Contents
1. [Architecture & Code Structure](#1-architecture--code-structure)
2. [Backend — Laravel](#2-backend--laravel)
3. [Frontend — React + Inertia](#3-frontend--react--inertia)
4. [Inertia.js Integration](#4-inertiajs-integration)
5. [Performance](#5-performance)
6. [Security Audit](#6-security-audit)
7. [Testing & Reliability](#7-testing--reliability)
8. [Code Quality & Consistency](#8-code-quality--consistency)
9. [Edge Cases / Missed Use Cases](#9-edge-cases--missed-use-cases)
10. [Commonly Missed Issues](#10-commonly-missed-issues)

---

## 1. Architecture & Code Structure

### ✅ Strengths
- Clean separation into Controllers → Services → Models
- Feature-based folder structure for React pages (`/pages/sales`, `/pages/sublimations`)
- Enums are used properly for status values
- `SaleFilterTrait` and `Sortable` traits cleanly extract reusable query scope logic
- `CashOnHandService` and `SalesService` show a healthy move away from fat controllers

---

### 🔴 Issue 1.1 — `SublimationController` is in the wrong namespace
**Severity: Medium**

`SublimationController` lives under `App\Http\Controllers\Settings\` but manages a core domain entity (Sublimations), not settings. Same for `SublimationImageController`, `SublimationTagController`, `TagController`. This breaks the conceptual model of your routing structure.

**Recommended Fix:**
Move to `App\Http\Controllers\Sublimations\` and update routes accordingly:
```php
// routes/settings.php
use App\Http\Controllers\Sublimations\SublimationController;
```

---

### 🟡 Issue 1.2 — Missing Policy for Sublimations and Expenses
**Severity: Medium**

`UserPolicy`, `BranchPolicy`, and `PurchaseOrderPolicy` exist, but there is **no policy for Sublimation, Transaction, or Expense**. Authorization logic for these is sprinkled inline:

```php
// SublimationController.php line 67
if (auth()->user()->role === 'superadmin') {
    return true;
}
```

This is a maintenance and security liability. Authorization rules become hard to trace and test.

**Recommended Fix:**
```bash
php artisan make:policy SublimationPolicy --model=Sublimation
php artisan make:policy TransactionPolicy --model=Transaction
```

---

### 🟡 Issue 1.3 — `SalesService::createTransaction` is a trivial wrapper
**Severity: Low**

```php
// SalesService.php line 103
public function createTransaction($data)
{
    return Transaction::create($data);
}
```

This method provides no value beyond calling `Transaction::create()` directly. It suggests the service was started but not fully utilized. The `SublimationController` uses it to create a transaction during `updateStatus`, which adds unnecessary indirection.

**Recommended Fix:** Either expand the method to encapsulate the full transaction-creation logic from `updateStatus`, or call `Transaction::create()` directly.

---

### 🟡 Issue 1.4 — No Repository layer for complex query building
**Severity: Low**

`SublimationController::index()` builds 40+ lines of query logic inline. This violates SRP. The `SalesService::getTransactionQuery()` pattern is better — apply it to sublimations too.

**Recommended Fix:** Create `SublimationService` (similar to `SalesService`) that encapsulates the index query:
```php
class SublimationService {
    public function getQuery(array $filters, User $user): Builder { ... }
}
```

---

## 2. Backend — Laravel

### 🔴 Issue 2.1 — Race Condition in `Transaction::generateNumber()`
**Severity: High**

```php
// Transaction.php line 52
$lastInvoiceNumber = self::whereYear('created_at', $year)
    ->where('invoice_number', 'like', "{$prefix}%")
    ->latest('id')
    ->value('invoice_number');
```

This is a classic **check-then-act** race condition. Under concurrent requests, two transactions can read the same `lastInvoiceNumber` simultaneously, generate the same candidate, and then both pass the `!self::where('invoice_number', $candidate)->exists()` check before either has committed. This produces **duplicate invoice numbers**, which is catastrophic for financial records.

**Recommended Fix:** Use a database-level sequence or a `transactions` lock:
```php
public static function generateNumber(): string
{
    return DB::transaction(function () {
        // Lock the last row to serialize concurrent reads
        $last = self::whereYear('created_at', now()->year)
            ->lockForUpdate()
            ->latest('id')
            ->value('invoice_number');
        
        $sequence = $last ? ((int) substr($last, -5)) + 1 : 1;
        $prefix = 'INV-' . now()->year . '-';
        return $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    });
}
```
Also add a **unique database index** on `invoice_number` as a final safety net (it already has `->unique()` in the migration, which is the DB-level safeguard).

---

### 🔴 Issue 2.2 — `recordPayment` uses stale `amount_paid` without a lock
**Severity: High**

```php
// Transaction.php line 88
$newTotalPaid = $this->amount_paid + $paymentAmount;
```

If two payment requests hit the server simultaneously for the same transaction, both read the same `amount_paid`, both pass the overpayment check, and both write conflicting values. The `DB::transaction()` wrapper doesn't prevent this — it only ensures atomicity of the writes, not isolation of the reads.

**Recommended Fix:** Use `lockForUpdate()` to get a fresh, locked version of the record:
```php
public function recordPayment(float $paymentAmount, string $paymentType): void
{
    DB::transaction(function () use ($paymentAmount, $paymentType) {
        // Re-fetch with a pessimistic write lock
        $fresh = self::lockForUpdate()->findOrFail($this->id);

        $newTotalPaid = $fresh->amount_paid + $paymentAmount;

        if ($newTotalPaid > $fresh->amount_total) {
            throw new \Exception("Payment exceeds balance.");
        }
        // ... rest of logic using $fresh
    });
}
```

---

### 🔴 Issue 2.3 — `UpdateTransactionPaymentRequest` validates against `amount_total`, not the remaining `balance`
**Severity: High**

```php
// UpdateTransactionPaymentRequest.php line 38
'amount_paid' => [
    'required', 'numeric', 'min:1',
    'lte:' . $transaction->amount_total,  // ❌ Wrong! Should be balance
],
```

The validation allows a user to submit a payment equal to the full `amount_total` even if half of it was already paid. The business logic in `recordPayment()` catches this, but it returns a generic `\Exception` whose message is then thrown to the user. Validation should be the **first gate**.

**Recommended Fix:**
```php
'amount_paid' => [
    'required', 'numeric', 'min:0.01',
    'lte:' . $transaction->balance,  // ✅ validate against remaining balance
],
```

---

### 🟡 Issue 2.4 — N+1 Query in `SublimationController::destroy()`
**Severity: Medium**

```php
// SublimationController.php line 150
foreach ($sublimation->images as $image) {
    if (Storage::disk('public')->exists($image->url)) {
        Storage::disk('public')->delete($image->url);
    }
}
```

The `$sublimation->images` relationship is NOT eagerly loaded before `destroy()` is called (it's triggered by route model binding alone). While this is N+1 only on deletion, the Storage call inside a loop can block the response for O(n) images.

**Recommended Fix:** Add eager loading explicitly, or better, create a cascade-aware observer:
```php
// In SublimationObserver::deleting()
$sublimation->load('images');
Storage::disk('s3')->delete($sublimation->images->pluck('url')->toArray());
```
This also reduces storage operations from N to 1 batch delete call.

---

### 🟡 Issue 2.5 — `SublimationController::index()` double-applies branch filter
**Severity: Medium**

```php
// SublimationController.php line 34
$query->when($request->filled('branch_id') && $request->branch_id !== 'all', function ($q) use ($request) {
    $q->where('branch_id', $request->branch_id);  // Applied here...
});

// Then again at line 40-51
$query->where(function ($query) use ($filters) {
    if ($user->role !== 'superadmin') {
        $query->where('branch_id', $user->branch_id);
    } elseif ($filterId && $filterId !== 'all') {
        $query->where('branch_id', $filterId);  // ...and again here
    }
});
```

The first `when()` block at line 34 is completely redundant — the logic is duplicated in the second block. This adds an unnecessary `AND branch_id = ?` condition to every query.

**Recommended Fix:** Remove the first `when()` block at lines 34-36 entirely.

---

### 🟡 Issue 2.6 — `updateStatus` creates a transaction without wrapping the whole operation in a DB transaction
**Severity: Medium**

```php
// SublimationController.php line 179-196
if (! $sublimation->transaction()->exists()) {
    // ... creates Transaction ...
    $sublimation->transaction_id = $transaction->id;
}
$sublimation->status = $newStatus;
$sublimation->save(); // ← If this fails, the Transaction was already created but is orphaned
```

If `$sublimation->save()` throws an exception after the `Transaction::create()` call, you end up with an orphaned transaction record with no linked sublimation.

**Recommended Fix:**
```php
DB::transaction(function () use ($sublimation, $newStatus) {
    if ($newStatus === SublimationStatus::WAITING_FOR_DP && !$sublimation->transaction()->exists()) {
        $transaction = $this->salesService->createTransaction(...);
        $sublimation->transaction_id = $transaction->id;
    }
    $sublimation->status = $newStatus;
    $sublimation->save();
});
```

---

### 🟡 Issue 2.7 — `SublimationController::update()` has an ordering bug in the amount check
**Severity: Medium**

```php
// SublimationController.php lines 112-128
if ($sublimation->isDirty('amount_total')) {
    // 1. Check if transaction-linked and not PENDING → block
    if ($sublimation->transaction()->exists()) {
        if ($sublimation->transaction->status != TransactionStatus::PENDING->value) {
            return back()->withErrors(['message' => 'Cannot change amount on processed sublimations.']);
        }
        $sublimation->transaction->update(['amount_total' => $sublimation->amount_total]);
    }
    
    // 2. Also block if in production phase
    if ($sublimation->status->isProductionPhase()) {
        return back()->withErrors(['message' => 'Cannot change amount on processed sublimations.']);  // ← Unreachable
    }
}
```

The production-phase check at line 126 **can never return an error** for a sublimation that has a transaction (it only runs if somehow neither check above it caught it). However, a sublimation in production phase **without** a transaction would hit this check. The ordering is logical but leads to confusing UX since the error messages are identical. Also, when a transaction exists, the linked transaction is updated **before** the production-phase check — meaning if the production check triggered, the transaction amount would already be mutated in memory (though not yet saved in this case since `$sublimation->save()` comes later).

**Recommended Fix:** Consolidate the checks before any mutations:
```php
if ($sublimation->isDirty('amount_total')) {
    $hasTransaction = $sublimation->transaction()->exists();
    $transactionNotPending = $hasTransaction && $sublimation->transaction->status != TransactionStatus::PENDING->value;
    $inProduction = $sublimation->status->isProductionPhase();
    
    if ($transactionNotPending || $inProduction) {
        return back()->withErrors(['message' => 'Cannot change amount—sublimation has been processed.']);
    }

    if ($hasTransaction) {
        $sublimation->transaction->update(['amount_total' => $sublimation->amount_total]);
    }
}
```

---

### 🟡 Issue 2.8 — Missing foreign key constraints on `sublimations` table
**Severity: Medium**

```php
// create_sublimations_table.php
$table->integer('branch_id');       // ← No foreignId(), no constraint
$table->integer('customer_id');     // ← No foreignId(), no constraint
$table->integer('user_id');         // ← No foreignId(), no constraint
$table->integer('transaction_id')->nullable(); // ← No constraint
```

Unlike the `transactions` table which uses `foreignId()->constrained()`, the `sublimations` table uses raw integers with no FK constraints. This means you can delete a branch, customer, or user and have dangling references in sublimations.

**Recommended Fix:** Add proper FK constraints in a migration:
```php
$table->foreignId('branch_id')->constrained()->cascadeOnDelete();
$table->foreignId('customer_id')->constrained()->nullOnDelete();
$table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
$table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
```

---

### 🟡 Issue 2.9 — `SaleFilterTrait::applyDateFilter` uses raw SQL for weekly filter
**Severity: Medium**

```php
// SaleFilterTrait.php line 41
'weekly' => $query->whereRaw("YEARWEEK($column, 1) = ?", [str_replace('-W', '', $date)]),
```

`YEARWEEK()` is a **MySQL-specific function**. The app uses SQLite locally (`database.sqlite` is present) and would fail or silently return wrong results on the weekly filter when running tests or development against SQLite. The column name is also interpolated directly into the raw SQL rather than wrapped in backticks, creating a potential SQL injection vector if `$column` were ever user-controlled (it isn't currently, but it's a fragile pattern).

**Recommended Fix:** Use Carbon-based date ranges instead of raw SQL:
```php
'weekly' => (function () use ($query, $column, $date) {
    $start = Carbon::parse($date)->startOfWeek();
    $end = Carbon::parse($date)->endOfWeek();
    $query->whereBetween($column, [$start, $end]);
})(),
```

---

### 🟡 Issue 2.10 — `ExpenseController::void()` has incomplete rollback logic
**Severity: Medium**

```php
// ExpenseController.php line 122-123
if ($expense->status === 'void') {
    return back()->withErrors(['message' => 'Expense is already voided.']);
}
```

This check compares against a string literal `'void'` while the rest of the codebase uses `ExpenseStatus::VOID->value`. If the enum value changes, this check silently breaks. Additionally, the void reversal only handles Cash; GCash/card expenses are voided on-screen but the accounting is not reversed.

**Recommended Fix:**
```php
if ($expense->status === ExpenseStatus::VOID) {  // Compare against enum
    return back()->withErrors(['message' => 'Expense is already voided.']);
}
```

---

### 🟡 Issue 2.11 — `CashOnHand` table has no `date` column (per-day tracking is impossible)
**Severity: Medium**

```php
// CashOnHandService.php line 19
$record = CashOnHand::firstOrCreate(
    ['branch_id' => $branchId],
    ['amount' => 0]
);
```

The service finds-or-creates a `CashOnHand` record **by branch_id only**, with no date column. This means:
1. Cash-on-hand is a **single ever-growing number**, not a per-day balance.
2. Running a "today's cash drawer" report is impossible.
3. Historical cash balances are lost.

The `SalesService::getCashOnHandTotal()` also sums all records with no date filter, so it's showing an all-time cumulative sum, not a daily snapshot.

**Recommended Fix:** Add a `date` column to the `cash_on_hands` table and use `firstOrCreate` with both `branch_id` and `date:today` as the key.

---

### 🟡 Issue 2.12 — `SublimationImageController::store` accepts only one image at a time
**Severity: Low**

The frontend `SublimationGallery` makes **sequential individual HTTP requests** for each file in the selection:
```tsx
// sublimation-gallery.tsx line 82
for (const file of validFiles) {
    // Makes one POST per file
    const response = await axios.post(`/sublimations/${sublimationId}/images`, formData, ...);
}
```

This means uploading 10 images makes 10 round-trips to S3. The backend also validates only a single `image` key.

**Recommended Fix (user requested):** Update the backend to accept multiple images in one request:
```php
$request->validate([
    'images' => ['required', 'array', 'max:10'],
    'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
]);
```
And batch the uploads with `collect($request->file('images'))->map(fn($f) => ...)`.

---

### 🟡 Issue 2.13 — `SublimationImageController::destroy` doesn't use authorization
**Severity: Medium**

```php
// SublimationImageController.php line 99
if ($image->imageable_id !== $sublimation->id || $image->imageable_type !== Sublimation::class) {
    abort(403, 'Unauthorized access to this image.');
}
```

This manual ownership check is correct, but the controller has no check for whether the **authenticated user** is allowed to delete images from this sublimation at all. A staff member from Branch A could delete images from Branch B's sublimation if they know the IDs.

**Recommended Fix:** Add a policy check or a branch check:
```php
// In a SublimationPolicy::deleteImage method
public function deleteImage(User $user, Sublimation $sublimation): bool
{
    return $user->role === 'superadmin' || $user->branch_id === $sublimation->branch_id;
}
```

---

### 🔵 Issue 2.14 — `StoreSublimationRequest` validates a `notes` field that no longer exists
**Severity: Low**

```php
// StoreSublimationRequest.php line 26
'notes' => 'nullable|string',
```

The `notes` column was dropped in migration `2026_04_16_073505_remove_notes_from_sublimations_table.php`. The validation rule is now dead code but harmless (it would simply validate a field that never gets used).

**Recommended Fix:** Remove the `notes` rule from `StoreSublimationRequest`.

---

### 🔵 Issue 2.15 — `transactions` table has a legacy `payment_type` column
**Severity: Low**

```php
// create_transactions_table.php line 32
$table->string('payment_type')->nullable();
```

After migrating to a 1:N payments architecture (the `payments` table), the `payment_type` column on `transactions` appears to be a legacy holdover. If it's not being read from or written to, it should be dropped to avoid confusion.

**Recommended Fix:** Verify usage with a search:
```bash
grep -rn "payment_type" app/ --include="*.php" | grep -v "Payments"
```
If unused on the Transaction model directly, drop it in a migration.

---

## 3. Frontend — React + Inertia

### 🔴 Issue 3.1 — `console.log` left in production code
**Severity: Medium**

```tsx
// sublimations/list.tsx line 108
console.log('filters', filters);
```

Debug logs left in a page-level component will log on every filter interaction in production. This is both a performance issue and a data exposure risk (filter state including branch IDs, user IDs, and dates is printed to the browser console).

**Recommended Fix:** Remove immediately.

---

### 🟡 Issue 3.2 — `SaleIndex` uses `any` types pervasively
**Severity: Medium**

```tsx
// sales/list.tsx
const [getTransaction, setTransaction] = useState<any | null>(null);
const openEditForm = (transaction: any) => { ... }
const openDetailsForm = (transaction: any) => { ... }
const columns: ColumnDef<unknown, any>[] = [...]
```

Heavy use of `any` defeats the purpose of TypeScript. Typing bugs (e.g., `user.fullname` vs `user.full_name`) will only surface at runtime.

**Recommended Fix:** Type all state and callbacks with the `Transaction` type already defined in `@/types/transaction`:
```tsx
const [getTransaction, setTransaction] = useState<Transaction | null>(null);
const openEditForm = (transaction: Transaction) => { ... }
```

---

### 🟡 Issue 3.3 — Duplicate `due_at` field in `SublimationDialog`
**Severity: Low**

```tsx
// sublimation-dialog.tsx lines 257-290
{isEdit && (
    <div>
        <Label>Due Date</Label>
        <Input type="date" ... />  {/* Rendered if editing */}
    </div>
)}

{!isEdit && (
    <div>
        <Label>Due Date</Label>
        <Input type="date" ... />  {/* Rendered if creating */}
    </div>
)}
```

The two branches are **identical rendering logic**. This is pure DRY violation — if you ever change the due date field UI, you must change it in two places.

**Recommended Fix:** Remove the conditional split — just render the field once:
```tsx
<div className="grid gap-2">
    <Label htmlFor="due_at">Due Date</Label>
    <Input id="due_at" type="date" value={data.due_at} onChange={...} />
    <InputError message={errors.due_at} />
</div>
```

---

### 🟡 Issue 3.4 — `SublimationGallery` is fetching images via `axios` with a hard-coded URL path
**Severity: Medium**

```tsx
// sublimation-gallery.tsx line 33
const response = await axios.get(`/sublimations/${sublimationId}/images`);
```

Hard-coded URL strings bypass Ziggy's route resolution and will silently break if the route prefix changes. They also make the component untestable in isolation.

**Recommended Fix:** Use Ziggy's `route()` helper consistently:
```tsx
const response = await axios.get(route('sublimations.images.index', { sublimation: sublimationId }));
```

---

### 🟡 Issue 3.5 — `SublimationDialog` `amount_total` is conditionally disabled by status, but the user requested it to be **always editable when pending**
**Severity: Medium (Feature Gap)**

```tsx
// sublimation-dialog.tsx line 299-302
disabled={
    isEdit &&
    !prePaymentKeys.includes(data.status)
}
```

The user explicitly requested: *"sublim amount can be editable while still being pending"*. The current logic disables the field when the status is not in `prePaymentKeys = ['for_approval', 'done_layout', 'waiting_for_dp']`. However, on the **backend**, the `SublimationController::update()` checks `$sublimation->status->isProductionPhase()` and the transaction status. The frontend restriction is **stricter than the backend** — i.e., if a sublimation is in `WAITING_FOR_DP` state, the frontend allows editing but the linked transaction is now created, so the backend check on the transaction status (`!= PENDING`) would block it.

This needs a coordinated fix:
- Backend: Allow amount changes while the transaction is PENDING.
- Frontend: Remove the `disabled` condition entirely (let the backend be the authoritative gate), or align the condition with what the backend actually permits.

---

### 🟡 Issue 3.6 — Delete dialog in `sales/list.tsx` (Alert Dialog) has no confirm action
**Severity: High (Functional Bug)**

```tsx
// sales/list.tsx lines 295-318
<AlertDialog>
    <AlertDialogTrigger asChild>
        <Button variant="ghost" size="sm"><Trash2 /></Button>
    </AlertDialogTrigger>
    <AlertDialogContent>
        ...
        <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            {/* ❌ No AlertDialogAction — the "Continue" button is missing */}
        </AlertDialogFooter>
    </AlertDialogContent>
</AlertDialog>
```

The delete confirmation dialog in the sales list has only a "Cancel" button and **no action button**. The Trash icon button opens a dialog, the user sees "Are you absolutely sure?" but can only cancel. There is no delete route being called. This is a broken UI.

**Recommended Fix:** Add the delete handler and action button (note: also requires a `sales.destroy` route):
```tsx
<AlertDialogAction
    className="bg-destructive text-white hover:bg-destructive/90"
    onClick={() => router.delete(route('sales.destroy', row.original.id), {
        onSuccess: () => toast.success('Transaction deleted.')
    })}
>
    Delete
</AlertDialogAction>
```

---

### 🟡 Issue 3.7 — `AddGuestModal` has a stale effect dependency warning risk
**Severity: Low**

```tsx
// add-guest-dialog.tsx line 51
}, [form, open, searchQuery]);
```

`form` is the entire `useForm` return object. Including the whole object as a dependency is unsafe — the `form` reference changes on every render in Inertia's `useForm`, causing the effect to re-run even when `open` hasn't changed. The `wasOpen.current` ref prevents the actual bug, but it's a fragile pattern that will cause issues if someone removes the ref guard.

**Recommended Fix:** Use `form.setData` as the dependency (it's stable) or use `useCallback`:
```tsx
}, [form.setData, open, searchQuery]);
```

---

### 🔵 Issue 3.8 — `sublimation-dialog.tsx` does not reset form data across invocations
**Severity: Medium**

When the dialog is opened for sublimation A, closed, then opened for sublimation B, the `useForm` initial state is set only at mount time. Since the dialog is conditionally rendered (`{isDialogOpen && <SublimationDialog .../>}`), this is actually fine — React will unmount and remount the component. However, if you ever switch to keeping it mounted (for animation purposes), stale data will persist. Document this assumption explicitly.

---

## 4. Inertia.js Integration

### 🟡 Issue 4.1 — Global shared `auth.user` exposes sensitive fields to every page
**Severity: Medium**

```php
// HandleInertiaRequests.php line 42
'auth' => [
    'user' => $request->user()
        ? $request->user()->load('branch')
        : null,
],
```

The entire user model (minus `$hidden`) is sent to every Inertia page on every request. This includes `role`, `branch_id`, `two_factor_confirmed_at`, and **all relationships** like `branch`. While passwords are hidden, role data (used for frontend RBAC) can be a source of privilege escalation bugs if the frontend is relied upon for authorization alone.

**Recommended Fix:** Limit the data sent:
```php
'auth' => [
    'user' => $request->user()?->only(['id', 'first_name', 'last_name', 'role', 'branch_id', 'fullname'])
        + ['branch' => $request->user()?->branch?->only(['id', 'name'])],
],
```

---

### 🟡 Issue 4.2 — Over-fetching: All users sent to the sublimation list page
**Severity: Medium**

```php
// SublimationController.php line 82
$users = User::whereIn('role', ['admin', 'staff'])->get();
```

All admin/staff users are fetched for the frontend filter dropdown, regardless of which branch is selected. In a large organization this is N users, all serialized and sent over the wire on every page load.

**Recommended Fix:** Either:
1. Paginate or scope the user list by the currently authenticated user's accessible branches.
2. Or use a lazy API endpoint (like the `api.customers.index` pattern) to fetch users per branch on-demand.

---

### 🟡 Issue 4.3 — `SalesService::index()` runs 5 separate database queries
**Severity: Medium**

```php
// SaleController.php lines 32-41
$query = $this->salesService->getTransactionQuery($filters);       // Query built
$aggregates = $this->salesService->getPaymentAggregates($query);  // DB hit 1 (clone)
$cashOnHand = $this->salesService->getCashOnHandTotal(...);        // DB hit 2
// Inside Inertia::render:
$transactions = $query->paginate(30)                               // DB hit 3
'customers' => $this->salesService->searchCustomers(...)          // DB hit 4
$this->salesService->getFinanceSummary($filters)                  // DB hit 5 (x2 internally)
```

That's 6+ queries on the most-visited page. Consider grouping summaries into a single aggregate query or using query result caching.

---

### 🔵 Issue 4.4 — The `flash.message` key is in shared data but is never used on the frontend
**Severity: Low**

```php
// HandleInertiaRequests.php line 49
'message' => $request->session()->get('message'),
```

Controllers use `->with('success', '...')` not `->with('message', '...')`. The shared `flash.message` key is populated with `null` on every request but consumed nowhere. This is dead configuration.

---

## 5. Performance

### 🟡 Issue 5.1 — Eager loading on `SublimationController::index()` is too broad
**Severity: Medium**

```php
// SublimationController.php line 32
$query = Sublimation::with('tags', 'branch', 'user', 'customer', 'transaction');
```

The `transaction` relationship is only needed to display the invoice link. Loading full `Transaction` objects (with all their columns) for every sublimation in the list adds unnecessary memory usage and data transfer. The `user` relationship loads the full user model when only `first_name`/`last_name` are displayed.

**Recommended Fix:** Use constrained eager loading:
```php
$query = Sublimation::with([
    'tags:id,name,color',
    'branch:id,name',
    'user:id,first_name,last_name',
    'customer:id,first_name,last_name',
    'transaction:id,invoice_number,status',
]);
```

---

### 🟡 Issue 5.2 — Image S3 temporary URLs generated one-by-one in a loop
**Severity: Medium**

```php
// SublimationImageController.php line 17
$images = $sublimation->images->map(function ($image) {
    return [
        'url' => Storage::disk('s3')->temporaryUrl($image->url, now()->addHours(3)),
        ...
    ];
});
```

Each `temporaryUrl()` call makes a **separate AWS SDK signing operation** (AWS STS call for presigned URLs). For a gallery with 20 images, this is 20 synchronous operations before a response is returned. While signing is CPU-bound (not a network call), it still adds latency.

**Recommended Fix:** If using S3-compatible storage (like Cloudflare R2 or MinIO), generate all URLs in one collection pass. For true AWS S3, presigned URL signing is local (no network call), so the issue is mainly CPU. Consider caching the signed URLs in Redis with the URL's expiry (minus a buffer).

---

### 🟡 Issue 5.3 — `Tag::all()` sent to the sublimation list on every page load
**Severity: Low**

```php
// SublimationController.php line 86
'availableTags' => Tag::all(['id', 'name', 'color']),
```

`Tag::all()` queries every tag and sends them to the frontend on every sublimation list page load. The tags are passed as `availableTags` in the Inertia response, but looking at `SublimationIndex` (frontend), `availableTags` is in the `SublimationIndexProps` interface but **not destructured or used** in the component body. This is dead data being transferred on every request.

**Recommended Fix:** Remove the `availableTags` key from the `SublimationController::index()` response, or confirm if it's used elsewhere.

---

### 🔵 Issue 5.4 — No HTTP caching headers for static/reference data
**Severity: Low**

Every request to `/sublimations` or `/sales` fetches branches, statuses, and payment types from the database — data that rarely changes. Adding cache headers or using Laravel's `Cache::remember()` for these reference lists would reduce database load significantly.

---

## 6. Security Audit

### 🔴 Issue 6.1 — S3 config is leaked in error responses (even in debug mode check is incorrectly scoped)
**Severity: High**

```php
// SublimationImageController.php lines 80-85
'config_check' => [
    'bucket' => config('filesystems.disks.s3.bucket'),
    'endpoint' => config('filesystems.disks.s3.endpoint'),
    'region' => config('filesystems.disks.s3.region'),
    'url' => config('filesystems.disks.s3.url'),
]
```

This config dump is inside the **error logging context** (sent to `Log::error()`), which is safe if logs are properly secured. However, the `'debug'` field in the JSON response:

```php
'debug' => config('app.debug') ? $e->getMessage() : 'Check server logs'
```

...can still expose S3 SDK error messages (e.g., SignatureMismatch with the request URL and decoded query params) in production if `APP_DEBUG=true`. **Never deploy with `APP_DEBUG=true`.** Remove the config array from the log context entirely — the bucket/endpoint should not be in logs.

**Recommended Fix:** Remove the `config_check` array from the log context, and ensure `APP_DEBUG=false` in production `.env`.

---

### 🔴 Issue 6.2 — Authorization is missing on `updateStatus` and `update` for Sublimations
**Severity: High**

```php
// SublimationController.php
public function updateStatus(Request $request, Sublimation $sublimation): RedirectResponse
// No authorization check at all!

public function update(UpdateSublimationRequest $request, Sublimation $sublimation): RedirectResponse
// No authorization check at all!
```

Any authenticated user — regardless of branch or role — can:
- Change any sublimation's status
- Update any sublimation's amount, description, or customer

Compared with `destroy()` which at least calls `$this->authorize('delete', auth()->user())` (though against the wrong policy — see Issue 6.3).

**Recommended Fix:** Add authorization to all mutating actions:
```php
public function update(UpdateSublimationRequest $request, Sublimation $sublimation)
{
    $this->authorize('update', $sublimation);
    // ...
}
```

---

### 🔴 Issue 6.3 — `authorize('delete', auth()->user())` calls the wrong policy
**Severity: High**

```php
// SublimationController.php line 143
$this->authorize('delete', auth()->user());

// ExpenseController.php line 105
$this->authorize('delete', auth()->user());
```

`$this->authorize('delete', auth()->user())` resolves to `UserPolicy::delete()`, which checks `$authUser->role === 'superadmin'`. This means:
1. Only superadmin can delete sublimations or expenses — which may be intentional, but...
2. The semantics are wrong: `authorize('delete', $sublimation)` would use `SublimationPolicy` (which doesn't exist), while `authorize('delete', auth()->user())` uses `UserPolicy`. This is accidentally doing the right thing (restricting to superadmin) through the wrong mechanism.
3. If someone creates a `SublimationPolicy` later, this code will **silently stop using it**.

**Recommended Fix:** Create `SublimationPolicy` and use it explicitly:
```php
$this->authorize('delete', $sublimation);
```

---

### 🟡 Issue 6.4 — `UpdateTransactionPaymentRequest::authorize()` always returns `true`
**Severity: Medium**

```php
// UpdateTransactionPaymentRequest.php line 14
public function authorize(): bool
{
    return true; // "Usually true, or check if user owns the transaction"
}
```

The comment says "or check if user owns the transaction" but the check was never implemented. Any authenticated user can post a payment to any transaction.

**Recommended Fix:**
```php
public function authorize(): bool
{
    $transaction = $this->route('transaction');
    $user = $this->user();

    return $user->role === 'superadmin'
        || $user->branch_id === $transaction->branch_id;
}
```

---

### 🟡 Issue 6.5 — RBAC is implemented as raw string comparisons throughout the codebase
**Severity: Medium**

Role checks are scattered as string comparisons:
```php
// Sublimation.php
if (auth()->user()->role === 'superadmin') { ... }

// SublimationController.php
if ($user->role !== 'superadmin') { ... }

// UserPolicy.php
return $user->role === 'superadmin' || $user->role === 'admin';
```

Magic string role values are a maintenance and security risk. A typo (`'superAdmin'` instead of `'superadmin'`) passes validation and grants unintended access.

**Recommended Fix:** Create a `UserRole` enum:
```php
enum UserRole: string {
    case SUPERADMIN = 'superadmin';
    case ADMIN = 'admin';
    case STAFF = 'staff';
}
```
And add helper methods to the `User` model:
```php
public function isSuperAdmin(): bool { return $this->role === UserRole::SUPERADMIN->value; }
public function isAdmin(): bool { return in_array($this->role, [UserRole::SUPERADMIN->value, UserRole::ADMIN->value]); }
```

---

### 🟡 Issue 6.6 — `SublimationController::updateStatus()` does not validate the status value early enough
**Severity: Medium**

```php
// SublimationController.php line 168
$newStatus = SublimationStatus::from($request->status);
```

If `$request->status` contains an invalid value, `SublimationStatus::from()` throws a `ValueError` (an unchecked exception), which is caught by the generic `catch(\Exception $e)` at line 198. This swallows the true error and returns a misleading "status change is not allowed" message.

**Recommended Fix:** Use `SublimationStatus::tryFrom()` with explicit validation:
```php
$newStatus = SublimationStatus::tryFrom($request->status);
if (!$newStatus) {
    return back()->withErrors(['status' => 'Invalid status provided.']);
}
```

---

## 7. Testing & Reliability

### 🔴 Issue 7.1 — Zero tests for critical financial paths
**Severity: High**

The tests directory contains only:
- `DashboardTest.php` (likely just loads the page)
- `ExampleTest.php` (placeholder)

There are **no tests** for:
- `Transaction::generateNumber()` uniqueness under concurrency
- `Transaction::recordPayment()` overpayment prevention
- `SublimationController::updateStatus()` state machine transitions
- Payment amount validation logic
- Branch-scoped data isolation

**Critical tests to add:**
```php
// tests/Feature/TransactionPaymentTest.php
it('prevents overpayment', function () {
    $transaction = Transaction::factory()->create(['amount_total' => 100, 'amount_paid' => 80]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('sales.update-payment', $transaction), [
            'amount_paid' => 30,
            'payment_type' => 'cash',
            'status' => 'partial',
        ])
        ->assertSessionHasErrors('amount_paid');
});

it('prevents duplicate invoice numbers');
it('restricts non-superadmin to their own branch data');
it('blocks status transition from completed to any state');
```

---

### 🟡 Issue 7.2 — Error handling swallows useful information in `updateStatus`
**Severity: Medium**

```php
// SublimationController.php lines 198-200
} catch (\Exception $e) {
    return back()->withErrors(['status' => 'The status change is not allowed at this time.']);
}
```

The catch block does not log the exception. If an unexpected DB error occurs during status update, it will silently fail with a misleading "not allowed" message.

**Recommended Fix:**
```php
} catch (\Exception $e) {
    Log::error("Failed to update sublimation status #{$sublimation->id}: " . $e->getMessage());
    return back()->withErrors(['status' => 'An error occurred. Please try again.']);
}
```

---

## 8. Code Quality & Consistency

### 🟡 Issue 8.1 — `Transaction` type on frontend has a stale `guest_name` field
**Severity: Low**

```ts
// types/transaction.ts line 23
guest_name: string;   // ← This field does not exist in the DB or backend model
```

The `transactions` table has no `guest_name` column. This is dead type noise that suggests a schema that was never finalized or was replaced.

---

### 🟡 Issue 8.2 — Inconsistent user name field convention
**Severity: Low**

The backend exposes `fullname` as an accessor:
```php
// User.php
protected $appends = ['fullname'];
```

But the `Customer` model apparently has a `full_name` attribute (different snake-case style), and the frontend `collect-payment-dialog.tsx` uses `transaction.payments.map(...)` while the frontend `Transaction` type uses `user.name` (which doesn't match backend's `fullname`).

In `sublimation-dialog.tsx`:
```tsx
{u.first_name} {u.last_name}  // Concatenated on frontend
```

On the sublimation list:
```tsx
accessorKey: 'user.fullname'  // Using the computed attribute
```

This inconsistency will cause subtle undefined renders.

---

### 🟡 Issue 8.3 — `"use client"` directive in `collect-payment-dialog.tsx`
**Severity: Low**

```tsx
// collect-payment-dialog.tsx line 1
"use client"
```

This is a **Next.js** directive, not an Inertia/Vite directive. It's completely meaningless and ignored in this codebase. It suggests code was copied from a Next.js project without cleanup.

**Recommended Fix:** Remove it.

---

### 🔵 Issue 8.4 — `Sublimation::$table` is redundant
**Severity: Low**

```php
// Sublimation.php line 11
public $table = 'sublimations';
```

Laravel already defaults to the snake_case plural of the model name. Explicitly setting `$table = 'sublimations'` for a model named `Sublimation` is redundant.

---

### 🔵 Issue 8.5 — `transaction_type` in `Sublimation` type is typed as `number` but should be `string`
**Severity: Low**

```ts
// sublimations.ts line 28
transaction_type: number;  // ❌ Should be string ('retail' | 'purchase_order')
```

---

## 9. Edge Cases / Missed Use Cases

### 🔴 Issue 9.1 — What happens when a sublimation is deleted but its linked transaction persists?
**Severity: High**

```php
// SublimationController::destroy()
$sublimation->delete();
```

When a sublimation is deleted, its linked `Transaction` (if any) is **not deleted or unlinked**. The transaction will remain in the sales list pointing to a non-existent sublimation. Clicking its "View Invoice" link from the sublimation page would break. Any cascade-through reporting would produce stale data.

**Recommended Fix:** In the `destroy` method, also handle the linked transaction:
```php
if ($sublimation->transaction()->exists()) {
    // Either delete or void the transaction
    $sublimation->transaction->delete();
}
```

---

### 🟡 Issue 9.2 — Status machine has no guard for "completed → any" when `canMoveTo` is bypassed
**Severity: Medium**

```php
// Sublimation.php line 67-68
if (auth()->user()->role === 'superadmin') {
    return true;  // Superadmin bypasses all guards
}
```

Superadmins can move a `COMPLETED` sublimation back to `FOR_APPROVAL`. This may be intentional for corrections, but there's no audit trail. If a superadmin reverses a completed order, the linked transaction remains in `PAID` state — creating a desync between the sublimation status and the financial record.

---

### 🟡 Issue 9.3 — No handling for `amount_total = 0` on sublimations
**Severity: Medium**

A sublimation can be created with `amount_total = 0` (validation allows `min:0`). When `updateStatus` triggers to `WAITING_FOR_DP`, it creates a transaction with `amount_total = 0`. A transaction with total=0 immediately satisfies `PAID` status on the next payment of even ₱0.01, which is nonsensical. There's also a division by zero risk in the frontend progress bar:
```tsx
// sales/list.tsx line 493
width: `${Math.min((net_income / total_sales) * 100, 100)}%`
// If total_sales = 0, this is 0/0 = NaN → width: "NaN%"
```

---

### 🟡 Issue 9.4 — `due_at` validation uses `after:today` which may cause timezone issues
**Severity: Medium**

```php
// StoreSublimationRequest.php line 23
'due_at' => ['nullable', 'date', 'after:today'],
```

`after:today` uses the server's local timezone to determine "today." If the server is in UTC and the user is in Asia/Manila (UTC+8), a date that is "tomorrow" in Manila may be "today" or even "yesterday" on the server, causing false validation failures.

**Recommended Fix:** Set `APP_TIMEZONE=Asia/Manila` in `.env` and `config/app.php`:
```php
'timezone' => env('APP_TIMEZONE', 'Asia/Manila'),
```

---

### 🟡 Issue 9.5 — No handling for S3 upload failure leaving orphaned DB records
**Severity: Medium**

```php
// SublimationImageController.php
$path = $file->store('sublimation_images', ['disk' => 's3', ...]);
$image = $sublimation->images()->create(['url' => $path]);  // Saved to DB
// If the pre-signed URL generation below fails, the DB record exists but the URL can't be served
return response()->json([
    'url' => Storage::disk('s3')->temporaryUrl($path, now()->addHours(3)),
]);
```

If `temporaryUrl()` throws (e.g., credentials expired mid-request), the image record exists in the database pointing to a valid S3 path but the response fails. The gallery will then show this image on next load (since the DB record exists) but may generate a fresh URL successfully — which is actually OK. However, if the `$file->store()` partially fails, you could get a partial state. Wrapping in a transaction with proper rollback would handle this better.

---

### 🔵 Issue 9.6 — No pagination for the gallery images — all loaded at once
**Severity: Low**

If a sublimation accumulates 50+ images, they're all fetched and rendered simultaneously. No lazy loading, no virtualization. Both the S3 temporary URL generation loop and the frontend image grid will scale poorly.

---

## 10. Commonly Missed Issues

### 🔴 Issue 10.1 — Hidden N+1: `SublimationController::index()` with `images` relationship not loaded
**Severity: Medium**

Although `images` is not in the `with()` — which is good since you don't show them in the list — the `destroy()` action triggers `$sublimation->images` which is a lazy load **after route model binding**. If you ever call `destroy()` in a batch context (bulk delete), this would be a classic N+1.

---

### 🔴 Issue 10.2 — Memory leak risk: `SublimationGallery` component has no abort controller for fetch
**Severity: Medium**

```tsx
// sublimation-gallery.tsx
useEffect(() => {
    const fetchImages = async () => {
        setIsLoading(true);
        const response = await axios.get(`/sublimations/${sublimationId}/images`);
        setImages(response.data);  // ← setState called after potential unmount
        setIsLoading(false);
    };
    fetchImages();
}, [sublimationId]);
```

If the dialog is closed before the `axios.get()` resolves, `setImages` and `setIsLoading` are called on an **unmounted component**, causing a React memory leak warning and potential state corruption.

**Recommended Fix:**
```tsx
useEffect(() => {
    const controller = new AbortController();
    
    const fetchImages = async () => {
        setIsLoading(true);
        try {
            const response = await axios.get(route('sublimations.images.index', sublimationId), {
                signal: controller.signal
            });
            setImages(response.data);
        } catch (err) {
            if (!axios.isCancel(err)) {
                toast.error('Failed to load images.');
            }
        } finally {
            setIsLoading(false);
        }
    };
    
    fetchImages();
    return () => controller.abort();  // Cleanup on unmount
}, [sublimationId]);
```

---

### 🟡 Issue 10.3 — Race condition in sequential image uploads
**Severity: Medium**

```tsx
// sublimation-gallery.tsx lines 82-103
for (const file of validFiles) {
    const response = await axios.post(...);
    setImages((prev) => [...prev, response.data]);  // OK: functional update
}
setIsUploading(false);  // ← Set after the loop
```

The `setIsUploading(false)` at line 105 is called after the loop — which is correct. However, there's no error-recovery path: if file 3 of 5 fails, the loop continues and `isUploading` becomes false at the end, even though 2 files are still pending. The user sees partial upload success with no clear indication of which files failed permanently.

---

### 🟡 Issue 10.4 — Stale closure in debounce effect
**Severity: Medium**

```tsx
// sales/list.tsx lines 99-111
useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
        if (searchTerm !== (filters.search || '')) {
            router.get(route('sales.index'), { ...filters, search: searchTerm, page: 1 }, ...);
        }
    }, 300);
    return () => clearTimeout(delayDebounceFn);
}, [filters, searchTerm]);
```

`filters` is included in the dependency array. This means the effect (and therefore the 300ms debounce timer) fires both when `searchTerm` changes AND when `filters` changes (e.g., after a filter dropdown change). This can cause a double navigation: first from the dropdown change, then again 300ms later from the debounce re-triggering. The stale closure comparison `searchTerm !== (filters.search || '')` might prevent the second navigation in some cases, but not reliably.

**Recommended Fix:** Separate the search debounce from the filters effect:
```tsx
const filtersRef = useRef(filters); // Stable reference
useEffect(() => { filtersRef.current = filters; }, [filters]);

useEffect(() => {
    const timer = setTimeout(() => {
        if (searchTerm !== (filtersRef.current.search || '')) {
            router.get(route('sales.index'), { ...filtersRef.current, search: searchTerm, page: 1 }, ...);
        }
    }, 300);
    return () => clearTimeout(timer);
}, [searchTerm]);  // Only searchTerm as dep
```

---

### 🟡 Issue 10.5 — Timezone inconsistency between frontend and backend
**Severity: Medium**

- Backend stores `transaction_date` and `expense_date` using `now()` (server time — likely UTC)
- `SaleFilterTrait::applyDateFilter` uses `whereDate($column, $date)` (compares date parts in server timezone)
- Frontend converts to Manila time via `toManilaTime()` for display

A transaction created at 11:30 PM Manila time = 3:30 PM UTC. This transaction would be stored with `transaction_date = "2026-04-20 15:30:00"`. The daily filter (`whereDate('transaction_date', '2026-04-20')`) in UTC would include it. But if someone in Manila filters for April 20, and the server is UTC, there's a 8-hour window of mismatch at day boundaries.

**Recommended Fix:** Set `APP_TIMEZONE=Asia/Manila` in production config.

---

### 🟡 Issue 10.6 — Unhandled promise rejection in `removeImage`
**Severity: Low**

```tsx
// sublimation-gallery.tsx line 127
const removeImage = async (idToRemove: string | number) => {
    try {
        await axios.delete(`/sublimations/${sublimationId}/images/${idToRemove}`);
        setImages((prev) => prev.filter((img) => img.id !== idToRemove));
        toast.success('Image removed from server.');
    } catch (err) {
        console.error('Failed to remove image', err);
        toast.error('Failed to remove image.');
    }
};
```

This is adequately handled. However, the `onClick` in `SublimationGallery` does not `await` this function:
```tsx
onClick={(e) => {
    e.stopPropagation();
    removeImage(image.id);  // ← No await, no .catch()
}}
```

Uncaught rejections from `removeImage` (if the try/catch itself throws) would become unhandled promise rejections.

**Recommended Fix:** Either add `void removeImage(image.id)` to explicitly ignore the return, or handle it:
```tsx
onClick={(e) => {
    e.stopPropagation();
    removeImage(image.id).catch(console.error);
}}
```

---

## Summary: Priority Matrix

| Priority | Issue | Impact |
|----------|-------|--------|
| 🔴 P0 | Race condition in invoice number generation (2.1) | Data corruption |
| 🔴 P0 | Race condition in recordPayment without lock (2.2) | Overpayment accepted |
| 🔴 P0 | Payment validation validates against total not balance (2.3) | Business rule bypass |
| 🔴 P0 | No authorization on updateStatus and update (6.2) | Cross-branch data mutation |
| 🔴 P0 | Wrong policy called on destroy (6.3) | Latent authorization bug |
| 🔴 P0 | Delete button in sales list has no action (3.6) | Broken feature |
| 🔴 P1 | Orphaned transaction after sublimation delete (9.1) | Data integrity |
| 🟡 P1 | Missing FK constraints on sublimations table (2.8) | DB integrity |
| 🟡 P1 | S3 config in error logs (6.1) | Security |
| 🟡 P1 | Memory leak in SublimationGallery useEffect (10.2) | Stability |
| 🟡 P1 | CashOnHand has no date column (2.11) | Wrong financial data |
| 🟡 P2 | RBAC as magic strings (6.5) | Maintainability |
| 🟡 P2 | Stale closure in debounce effect (10.4) | UX bug |
| 🟡 P2 | Over-fetching all users to sublimation list (4.2) | Performance |
| 🟡 P2 | Timezone inconsistency (10.5) | Wrong daily totals |
| 🔵 P3 | Dead `notes` validation rule (2.14) | Cleanliness |
| 🔵 P3 | "use client" directive (8.3) | Cleanliness |
| 🔵 P3 | Duplicate due_at field in dialog (3.3) | DRY violation |

---

*End of Audit — Total Issues Found: 38 across 10 categories.*
