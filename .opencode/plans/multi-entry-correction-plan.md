# Plan: Multi-Entry Time Adjustment for Correction Requests

## Context

User wants the **Time Adjustment** correction type (and all other correction types) to support multiple IN/OUT entries with radio buttons and an "Add another" button. When approved, each entry creates its own `time_log`.

## Decisions from User

1. **Scope:** All correction types move to the multi-entry pattern.
2. **Backend:** New child table `correction_request_items` (normalization).
3. **Review Queue:** Compact summary display (e.g., "2 entries: IN 08:15, OUT 17:30").
4. **Approval:** Create individual `time_log` records per item.

---

## Backend Changes

### 1. New Model: `CorrectionRequestItem`

- Path: `app/Models/Payroll/CorrectionRequestItem.php`
- Fields: `id`, `correction_request_id`, `punch_type` (in/out/lunch_in/lunch_out), `requested_time`, timestamps
- Relationship: `belongsTo(CorrectionRequest::class)`

### 2. Migration A: Create `correction_request_items` Table

- Path: `database/migrations/2026_06_04_000001_create_correction_request_items_table.php`
- Columns: `id`, `correction_request_id` FK cascade, `punch_type` string(20), `requested_time` datetime, timestamps

### 3. Migration B: Migrate Existing Data

- Path: `database/migrations/2026_06_04_000002_migrate_correction_requested_time_to_items.php`
- Logic:
    - `missed_punch_in` → 1 item (`punch_type='in'`, time = `requested_time`)
    - `missed_punch_out` → 1 item (`punch_type='out'`, time = `requested_time`)
    - `time_adjustment` / `absent_to_present` → 2 items (`in` at `requested_time`, `out` at `requested_time + 9 hours` to match current controller behavior)

### 4. Update `CorrectionRequest` Model

- Path: `app/Models/Payroll/CorrectionRequest.php`
- Add `items(): HasMany` relationship to `CorrectionRequestItem`
- Remove `requested_time` cast (optional, for cleanup later)

### 5. Update `CorrectionRequestController`

- Path: `payroll/Attendance/Controllers/CorrectionRequestController.php`

#### `store()`

- Validate `items` as array (required for all types)
- Each item requires `punch_type` (in, out, lunch_in, lunch_out) and `requested_time` (time string)
- Create `CorrectionRequest`, then loop `items` to create `CorrectionRequestItem` children
- Keep the unique check `['employee_id', 'date', 'correction_type']`

#### `approve()`

- Loop `$correction->items` and create one `TimeLog` per item:
    - `type` mapped from `punch_type`
    - `timestamp` = `$correction->date` + item's `requested_time`
    - `source = PunchSource::CORRECTION`
- After all logs created, **do NOT run `AttendanceService::processDailyAttendance()`** — attendance processing is handled separately on another page
- Update `resolved_time_log_id` to the first created log's ID (or null and add a new `resolved_time_log_ids` JSON field if needed — but keeping first log is fine for backward compat)
- Denial also does not trigger any attendance processing

#### `index()`

- Eager load `items` with the query so frontend can render summaries

### 6. Update Validation Rules

- `items` → `required`, `array`, `min:1`
- `items.*.punch_type` → `required`, `string`, `in:in,out,lunch_in,lunch_out`
- `items.*.requested_time` → `required`, `date_format:H:i`

---

## Frontend Changes

### 1. `my-attendance.tsx` — Correction Form

Currently uses `REQUEST_CONFIGS` static field definitions and generic `RequestTab`. Need to build a custom correction form UI.

#### New UI Pattern for Corrections

- **Date picker** (shared)
- **Type select** (shared)
- **Adjustments section** (dynamic):
    - Each entry has:
        - Radio group: `IN` | `OUT`
        - Time input: `requested_time`
        - Remove button (if more than 1 entry)
    - "+ Add another adjustment" button
- Default entries per type:
    - `missed_punch_in` → 1 entry, `in` pre-selected
    - `missed_punch_out` → 1 entry, `out` pre-selected
    - `time_adjustment` → 2 entries (1 `in`, 1 `out`) or 1 empty? **Decision: 1 empty entry, user adds more.**
    - `absent_to_present` → 2 entries (1 `in`, 1 `out`) pre-filled to encourage both
- Form submission sends `items` array to backend.

#### Implementation Approach

- Extract the correction form from `RequestTab` into a dedicated `CorrectionForm` component (or inline in `RequestTab` with type-check)
- Use `useState` for `items` array
- On type change, reset/adjust default items
- Serialize items into POST body (not FormData) so array structure is preserved

### 2. `corrections.tsx` — Review Queue

- Update the list to show compact item summary
- Example: "Time Adjustment — 2 entries (IN 08:15, OUT 17:30)"
- Access `request.items` array from Inertia props

---

## Tests

### Pest Tests Needed

1. **Store correction with items** → assert items created
2. **Store with invalid item punch_type** → assert 422
3. **Approve creates time_logs per item** → assert N logs created
4. **Index returns items in response** → assert items array present
5. **Unique constraint still works** → assert duplicate pending blocked

---

## Verification Steps

1. `composer lint:check`
2. `npm run types:check`
3. `npm run format:check`
4. `php artisan test --filter=CorrectionRequest`

---

## Files to Create

1. `app/Models/Payroll/CorrectionRequestItem.php`
2. `database/migrations/2026_06_04_000001_create_correction_request_items_table.php`
3. `database/migrations/2026_06_04_000002_migrate_correction_requested_time_to_items.php`

## Files to Modify

1. `app/Models/Payroll/CorrectionRequest.php`
2. `payroll/Attendance/Controllers/CorrectionRequestController.php`
3. `resources/js/pages/payroll/attendance/my-attendance.tsx`
4. `resources/js/pages/payroll/requests/corrections.tsx`
5. `tests/Feature/CorrectionRequestTest.php` (new or existing)

## Rollback Strategy

- Drop `correction_request_items` table
- Restore `requested_time` usage in controller (but data in `requested_time` remains intact since we didn't drop the column)
