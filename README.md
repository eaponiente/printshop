# Printing Shop Management System

This document explains how every part of the system works, written for people without a technical background.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Roles & Permissions](#roles--permissions)
3. [Device Management](#device-management)
4. [Dashboard](#dashboard)
5. [Sales / Projects](#sales--projects)
6. [Sublimation Orders](#sublimation-orders)
7. [Purchase Orders](#purchase-orders)
8. [Expenses](#expenses)
9. [Endorsements](#endorsements)
10. [Customers](#customers)
11. [Users](#users)
12. [Branches](#branches)
13. [Tags](#tags)
14. [Settings](#settings)
15. [Cash Tracking](#cash-tracking)

---

## Getting Started

### Creating the first user

Visit `/add-user` once to create the superadmin account:
- Username: `superadmin`
- Password: `password`
- Role: Superadmin (highest access)

This also runs the branch seeder to create the initial branch list.

### Feature flags

Set these in your `.env` file to turn features on or off:

| Variable | Default | What it does |
|---|---|---|
| `DEVICE_AUTH_ENABLED` | `true` | Turn device check on or off. Set to `false` to disable entirely. |

---

## Roles & Permissions

There are three levels of users in the system. Each level sees different parts of the system and different amounts of data.

| Role | What they can do |
|---|---|
| **Superadmin** | Full access to everything. Can see all branches, all data. Can approve devices, manage branches, delete sales. |
| **Admin** | Manages their own branch. Can see users, expenses, purchase orders, endorsements, and settings. Cannot manage branches or devices. |
| **Staff** | Can only see their own sales and sublimation orders. Cannot see financial reports, user lists, expenses, or settings. |

**Important:** Three branches — Peñaplata, Babak, and Tibungco — share sublimation data with each other. A user in any of these branches can see sublimation orders from all three.

---

## Device Management

### What it does

The system only allows access from computers or laptops that have been approved by the superadmin. If someone tries to log in from an unregistered device, they will be blocked and asked to register.

### First-time login (any user)

1. The user logs in with their username and password as normal.
2. The system checks: "Is this device recognized?"
3. If the device is unknown, the user sees a page that says **"Register This Device"**.
4. The user types a name for the device (e.g. "Branch A Terminal", "Office Laptop") and clicks Register.
5. The system saves the device and shows: **"Awaiting superadmin approval"**.
6. The user cannot access the app until the superadmin approves their device.

### Superadmin approval

1. The superadmin logs in (superadmins always bypass the device check).
2. The superadmin goes to **Settings > Devices** in the sidebar.
3. Two tabs are shown:
   - **Pending** — devices waiting for approval. Shows device name, user, branch, and registration date.
   - **Approved** — devices that are already allowed.
4. For each pending device, the superadmin can:
   - **Approve** — the device is now allowed. The user can log in and use the system.
   - **Reject** — the device registration is deleted. The user must re-register.
5. For approved devices, the superadmin can **Deactivate** — this blocks the device until it is re-registered.

### Returning user (approved device)

1. The user logs in with their username and password.
2. The system recognizes the device automatically (via a stored cookie).
3. The user goes straight to the dashboard — no extra steps needed.

### Who can do what

| Action | Who can do it |
|---|---|
| Register a device | Any logged-in user |
| Approve / Reject / Deactivate | Superadmin only |
| Auto-approval | Superadmins are auto-approved when they register their own device |

---

## Dashboard

**Who can access:** Everyone (staff, admin, superadmin)

The dashboard gives a quick summary of the business. It shows:

- **Revenue this month** — total sales amount this month, plus the increase or decrease compared to last month
- **New customers this month** — how many new customers were added, compared to last month
- **Total sales count** — number of sales made this month
- **Pending jobs** — how many jobs are still unpaid or partially paid, and how many were added today
- **Recent 5 transactions** — the five most recent sales with customer name and amount
- **Daily revenue chart** — a bar chart showing revenue for each day of the last 30 days
- **Monthly revenue chart** — a pie chart breaking down revenue by month for the last 6 months

Revenue counts only completed or partial sales. Pending (not yet paid) sales are excluded from revenue totals.

---

## Sales / Projects

**Who can access:** Everyone. Staff see only their own sales. Admins see their branch. Superadmins see everything.

This is where you record every job or sale made to a customer. The system calls these "Projects" in the sidebar.

### Creating a sale

- Enter the customer name, total amount, and description of the job.
- Each sale gets an automatic invoice number (e.g. `INV-2026-00001`).
- While a sale has NOT received any payment yet (status is "Pending"), you can still edit the total amount.

### Recording payments

- A sale can be paid in parts — this is called split payment.
- Each payment is recorded with a payment type (Cash, GCash, Card, Check, Bank Transfer, Debit) and the amount paid.
- The system tracks how much has been paid and how much is left.
- **You cannot overpay** — the system will reject any payment that would exceed the total.

### How status changes

Sales move through three statuses automatically based on payments:

| Status | What it means |
|---|---|
| **Pending** | No payment received yet. The amount can still be edited. |
| **Partial** | Some payment received, but not the full amount. The amount is now locked. |
| **Paid** | Full payment received. The sale is complete and marked with a fulfilled date. |

### Refunding a payment

- Payments can be refunded. You must provide a reason.
- Only **Partial** transactions can be refunded (not Pending or already Paid).
- The refund creates a negative entry in the payment history — the original payment is preserved for audit.
- If the refunded payment was Cash, the money is deducted from the branch's cash balance.

### Uploading attachments

- Admins and superadmins can attach an image (receipt, proof) to a sale.
- Staff can attach to their own sales.
- Maximum file size: 5MB. Supported formats: JPEG, PNG, WebP.

### Viewing sales

Two view modes:
- **Payments tab** — shows individual payment records, one row per payment. Can filter by date.
- **Unpaid tab** — shows transactions that still need payment.

Date filter modes: Daily, Weekly, Monthly, Yearly.

The page also shows financial summaries:
- Total collected by each payment type
- Total expenses
- Net income (revenue minus expenses)
- Current cash-on-hand balance for the branch

---

## Sublimation Orders

**Who can access:** Everyone. Staff see only their own orders. Superadmins see all across branches.

This module tracks custom printing orders (sublimation) that go through multiple production stages — from layout design all the way to customer pickup.

### The 14 production stages

Sublimation orders move through three phases:

**Phase 1 — Pre-Production (Planning):**
1. **For Approval** — The order is awaiting approval.
2. **Done Layout** — The layout design is completed.
3. **Waiting for DP** — Waiting for downpayment. (Moving to this stage automatically creates a linked Sale/Transaction.)

**Phase 2 — Production:**
4. **Downpayment Complete** — The downpayment has been collected.
5. **For Sizing** — Ready for size measurement.
6. **Done Sizing** — Sizing completed.
7. **Printed** — The item has been printed.
8. **Cut** — The material has been cut.
9. **Sewing** — Currently being sewn.
10. **Sewed** — Sewing completed.
11. **Checked** — Quality check done.
12. **Ready for Pickup** — The customer can pick up the item.

**Phase 3 — Finalization:**
13. **Claimed** — The customer has picked up the item.
14. **Completed** — The order is fully complete. Cannot be changed after this.

### How stages work

- Orders must move through stages in order (you cannot skip stages).
- Once an order leaves Phase 1 (pre-production), it cannot go back.
- A downpayment must be recorded before entering Phase 2 (production).
- Once an order reaches "Claimed", the only next step is "Completed".
- **Superadmins** can move orders to any stage at any time (except from "Completed").
- Orders marked as "Purchase Order" type can bypass all stage restrictions.

### Changing the amount

- The order's total amount is locked once the order enters Phase 2 (production).
- In Phase 1, the amount can still be changed.

### Linked sales

- When a sublimation order moves to "Waiting for DP" (stage 3), the system automatically creates a Sale/Transaction.
- This links the order to the main accounting ledger.
- The payment for the order is tracked through the Sales module.

### Images

- Up to 10 images can be uploaded per sublimation order.
- Images are stored securely and have a limited-time viewing link.
- Supported formats: JPEG, PNG, WebP. Maximum 5MB each.

### Tags

- Sublimation orders can have color-coded tags for categorization.
- Tags are managed in the Settings page.
- Tags help organize and filter orders.

### Deleting an order

- Orders can only be deleted if they are in Phase 1 (pre-production) AND their linked sale is still Pending.
- Deleting an order also removes all uploaded images.

---

## Purchase Orders

**Who can access:** Admin and Superadmin only

This module tracks purchase orders for supplies and materials. Each purchase order contains line items — the individual things being ordered.

### Line items

A purchase order has one or more line items. Each line item has:
- Item name (what is being ordered)
- Quantity (how many)
- Unit price (cost per item)

The grand total is automatically calculated as `quantity × unit price` for all items combined.

### Statuses

| Status | Meaning |
|---|---|
| **Pending** | Order created, not yet started. |
| **Active** | Order is in progress. |
| **Finished** | Order is completed. |
| **Released** | Order is fully paid and released. |

### Creating a transaction

- A purchase order can be linked to a Sale/Transaction for payment tracking.
- Each purchase order can only have one linked transaction.
- The transaction is created from the purchase order page.

### Smart display

- By default, purchase orders that are both "Released" AND fully paid are hidden.
- Check the "Include Released" box to see all orders.
- Orders can be filtered by date (received date or due date).

---

## Expenses

**Who can access:** Admin and Superadmin only

This module tracks business expenses — money going out for materials, supplies, bills, and other costs.

### Recording an expense

1. Select which branch the expense belongs to.
2. Enter the amount, payment type, category, and description.
3. Choose the date the expense occurred.
4. Optionally attach a receipt image for record-keeping.
5. If the payment type is **Cash**, the branch's cash balance is automatically reduced.

### Payment types for expenses

Cash, Card, Check, Bank Transfer, GCash, Credit

### Voiding (cancelling) an expense

If an expense was recorded by mistake:

1. Click **Void** on the expense.
2. Provide a reason explaining why (minimum 2 characters).
3. The expense is NOT deleted — it stays in the system marked as voided (for audit).
4. If the expense was Cash, the money is returned to the branch's cash balance.
5. You cannot void an expense that is already voided.

### Deleting an expense

- Deleting an expense removes it from the system.
- If the expense was Cash, the money is returned to the branch's cash balance.

---

## Endorsements

**Who can access:** Admin and Superadmin only

Endorsements track cash taken out of a branch's cash drawer for operational purposes (e.g. buying supplies with cash).

### Recording an endorsement

1. Select the branch.
2. Enter the amount.
3. The system automatically deducts this amount from the branch's cash balance.

---

## Customers

**Who can access:** Admin and Superadmin only

This module manages the customer database. Customers are linked to sales, sublimation orders, and purchase orders.

### Customer information

Each customer has:
- First name and last name
- Company name (optional)

### Deleting a customer

- A customer cannot be deleted if they have ongoing work — specifically, any non-pending sales or any sublimation orders.
- This prevents deleting customers who still have active jobs in the system.

### Customer search

- When creating a sale or sublimation order, you can search for an existing customer by name or company.
- The search returns the top 5 matching results.

---

## Users

**Who can access:** Admin and Superadmin only

This module manages the people who use the system (staff and admin accounts). Superadmins are not listed here.

### Creating a user

1. Enter username, first name, last name, and password.
2. Select a role (staff or admin).
3. Assign them to a branch.

### Editing a user

You can change a user's name, role, branch, or password.

### Deleting a user

A user cannot be deleted if they have:
- Active (non-paid) sales transactions
- Active (non-completed) sublimation orders

This prevents removing users who still have incomplete work.

### Branch admin visibility

- Admins can only see users in their own branch.
- Superadmins can see all users across all branches.

---

## Branches

**Who can access:** Superadmin only

This module manages branch locations. Each user belongs to a branch, and most data (sales, expenses, etc.) is organized by branch.

### Special branch group

The branches "Peñaplata", "Babak", and "Tibungco" share sublimation data. A user in any of these three branches can see sublimation orders from all three.

### Deleting a branch

A branch cannot be deleted if any users are assigned to it. You must reassign or remove those users first.

---

## Tags

**Who can access:** Admin and Superadmin only

Tags are color-coded labels used to organize sublimation orders. You can create tags with a name and color, then attach them to sublimation orders to categorize and filter them.

---

## Settings

**Who can access:** All authenticated users

### Profile

- Edit your name and email address.
- Delete your account (logs you out permanently).

### Security

- Change your password.
- Set up two-factor authentication for extra security.

### Appearance

- Switch between light and dark theme.

---

## Cash Tracking

The system automatically tracks how much cash each branch has on hand. This happens in the background whenever:

| Action | Effect on cash balance |
|---|---|
| Sale payment in Cash | Increases cash |
| Cash payment is refunded | Decreases cash |
| Expense paid in Cash | Decreases cash |
| Cash expense voided | Increases cash (money returned) |
| Cash expense deleted | Increases cash (money returned) |
| Endorsement recorded | Decreases cash |

This ensures the cash balance in the system matches the physical money in the drawer.
