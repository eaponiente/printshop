# Plan: Move Request Submission Forms from My Attendance to Dedicated Pages

## Context

Currently, overtime, leave, correction, and cash-advance submission forms live as tabs inside `My Attendance`. The user wants:

1. Remove these 4 tabs from `My Attendance`
2. Move each submission form to its dedicated request page (Overtime Requests, Leave Requests, Corrections, Cash Advances)
3. Make the **Requests** sidebar group visible to **all roles** (staff, admin, superadmin)
4. Only **admin/superadmin** can see approve/deny buttons; staff can only view their own requests + submit new ones
5. Forms should appear inside a **Dialog**, triggered by a **"New Request"** button

---

## Files to Modify

### Backend

#### 1. `payroll/Attendance/Controllers/TimeLogController.php`

- Remove lazy props: `recentOvertime`, `recentLeaves`, `recentCorrections`, `recentCashAdvances`
- Keep: `punchState`, `employee`, `activeSchedule`, `weekStart`, `weekEnd`, `weekSheets`, `recentTimeLogs`

#### 2. `payroll/Attendance/Controllers/*RequestController.php` (4 controllers)

- No backend changes needed for access — current index methods already filter by role
- Approve/deny gates already restrict to admin/superadmin
- **No pay computation on approval** — verify all approve methods only update status/approver fields:
    - `OvertimeRequestController::approve()` → updates status only ✅
    - `LeaveRequestController::approve()` → updates status only ✅
    - `CashAdvanceController::approve()` → updates status only ✅
    - `CorrectionRequestController::approve()` → already fixed in previous work (no `processDailyAttendance()`) ✅

### Frontend

#### 3. `resources/js/layouts/payroll/payroll-sidebar.tsx`

- Restructure sidebar groups:
    - **Attendance**: visible to `!isSuperAdmin` (keep current)
    - **Requests**: visible to **ALL roles** (move OUT of `canManageAttendance` block)
    - **Management**: visible to `isAdmin || isSuperAdmin` (keep inside `canManageAttendance`)
    - **Administration**: visible to `isSuperAdmin` (keep current)

#### 4. `resources/js/pages/payroll/attendance/my-attendance.tsx`

- Remove tabs: `overtime`, `leave`, `correction`, `cash-advance`
- Remove from `Tab` union type
- Remove from tab switcher array
- Remove `TAB_PROPS` entries for removed tabs
- Remove `recentOvertime`, `recentLeaves`, `recentCorrections`, `recentCashAdvances` from `Props` type
- Remove `RequestTab` component entirely (or keep only if reused — better to extract)
- Remove `REQUEST_CONFIGS` entirely
- Clean up unused imports: `CircleDollarSign`, `Timer`, `FileEdit`, `FileText`, `Select`, `SelectContent`, `SelectItem`, `SelectTrigger`, `SelectValue`, etc.

#### 5. `resources/js/pages/payroll/requests/overtime.tsx`

- Add **"New Request"** button at top that opens a Dialog
- Extract/create `OvertimeRequestForm` component (date, hours_needed, reason)
- Form submits to `/payroll/overtime-requests` via `router.post`
- Hide approve/deny action buttons if `auth.user.role === 'staff'`
- Pass `auth` from `usePage().props`

#### 6. `resources/js/pages/payroll/requests/leaves.tsx`

- Add **"New Request"** button + Dialog
- Extract/create `LeaveRequestForm` component (date, leave_type, duration, is_paid, reason)
- Hide approve/deny buttons for staff

#### 7. `resources/js/pages/payroll/requests/corrections.tsx`

- Add **"New Request"** button + Dialog
- Import and use existing `CorrectionForm` component (already created in `components/CorrectionForm.tsx`)
- Hide approve/deny buttons + DenyDialog for staff
- Note: DenyDialog currently exists inline; keep it but conditionally render based on role

#### 8. `resources/js/pages/payroll/requests/cash-advances.tsx`

- Add **"New Request"** button + Dialog
- Extract/create `CashAdvanceForm` component (amount, reason)
- Hide approve/deny buttons for staff

### New Components (Extracted Forms)

#### 9. `resources/js/pages/payroll/requests/components/OvertimeRequestForm.tsx`

- Date input, Hours Needed (number), Reason (textarea)
- Submit to `/payroll/overtime-requests`
- Success callback clears form + closes dialog

#### 10. `resources/js/pages/payroll/requests/components/LeaveRequestForm.tsx`

- Date, Leave Type select, Duration select, Is Paid checkbox, Reason textarea
- Submit to `/payroll/leave-requests`

#### 11. `resources/js/pages/payroll/requests/components/CashAdvanceForm.tsx`

- Amount (number), Reason (textarea)
- Submit to `/payroll/cash-advances`

Note: `CorrectionForm` already exists at `resources/js/pages/payroll/attendance/components/CorrectionForm.tsx` — move or re-export from `requests/components/`.

---

## Role-Based Visibility Matrix (Frontend)

| Page                 | Staff | Admin      | Superadmin |
| -------------------- | ----- | ---------- | ---------- |
| View list (own)      | ✓     | ✓ (branch) | ✓ (all)    |
| View list (others)   | —     | ✓ (branch) | ✓ (all)    |
| Submit new request   | ✓     | ✓          | ✓          |
| Approve/Deny buttons | —     | ✓          | ✓          |

This is enforced by:

- **Backend gates** on approve/deny/store endpoints
- **Frontend conditional rendering** of action buttons based on `auth.user.role`

---

## Tests Needed

1. **Sidebar visibility**: Staff sees Requests group
2. **Staff access to request index pages**: Can view own requests
3. **Staff cannot see approve/deny buttons** on frontend (already blocked by backend gates too)
4. **My Attendance no longer has request tabs**

---

## Verification Steps

1. `composer lint:check`
2. `npm run types:check`
3. `npm run format:check`
4. `php artisan test`

## Rollback Strategy

- Restore tabs and `REQUEST_CONFIGS` in `my-attendance.tsx`
- Re-add lazy props to `TimeLogController`
- Revert sidebar grouping
