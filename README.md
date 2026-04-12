# Printing Shop Management - Implementation workflows

This document outlines the internal wiring, data flows, and state transitions for the core financial modules: **Sales/Transactions**, **Sublimations**, and **Expenses**. It is based entirely on the project's programmatic rules currently deployed.

---

## 1. Sales & Transactions Flow

The Sales module acts as the central accounting ledger for the business. A `Transaction` represents a billable service to a customer.

### Workflow & Record Creation
- **Initialization**: Transactions are processed via `SaleController`. Upon creation, the system triggers `Transaction::generateNumber()` to construct an incrementing Invoice prefix (e.g., `INV-2026-00001`). 
- **Updatability Constraints**: Staff are permitted to edit the `amount_total` of a transaction **only** if the current status remains `PENDING`. Once partial or full payments are introduced, the total amount is locked to enforce accounting traceability.

### Payment Handling & Auto-Transitions
Transactions support a "Split Payment" architecture. All incoming payments invoke the `$transaction->recordPayment()` encapsulation layer.
- **Overpayment Prevention**: The system evaluates `amount_total` versus `amount_paid` before confirming the payload; payments attempting to push the balance below zero throw a fatal exception.
- **State Changes**: Progress is automatically calculated upon processing the payment:
  - Defaults to `PENDING` if unpaid.
  - Automatically transitions to `PARTIAL` once payment amounts are attached.
  - Shifts directly to `PAID` once the balance hits precisely zero, instantaneously stamping the `fulfilled_at` timestamp.
- **Cash Impact**: If the recorded `.payment_type` is tracked as `CASH`, the system dispatches `CashOnHandService::adjustBalance` categorized as `revenue`, which correctly increments the branch's local physical cash drawer.

---

## 2. Sublimations Flow

The Sublimations module manages custom orders navigating through complex pre-production, active production, and completion lifecycle phases.

### Phase Gates & Restrictions (`canMoveTo()` logic)
A sublimation relies strictly on its internal `$sublimation->canMoveTo()` validation logic to shift its Phase.
- **Overrides**: `superadmin` users, profiles marked as Purchase Orders, or instances toggled as `production_authorized` bypass all stage gate requirements completely.
- **Phase 1 (Pre-Payment)**: Statuses such as *Approval*, *Layout*, and *Waiting for DP* can move between one another flexibly. However, once an item leaves Phase 1, it cannot be reverted back to it.
- **Phase 2 (Production)**: The record **must** reach the `DOWNPAYMENT_COMPLETE` state to authorize entry into active technical states (e.g. *Sizing*, *Printing*, *Sewing*).
- **Phase 3 (Post-Production)**: An item tagged as `CLAIMED` has only one valid forward transition path—which is directly to the terminal state `COMPLETED`. 

### Interaction with Sales / Transactions
Sublimations serve as a specialized funnel into the main ledger. 
- When a sublimation successfully crosses the `DOWNPAYMENT_COMPLETE` state gate via `SublimationController@updateStatus`, the system checks for existing linkages. 
- If none exist, the Sublimation module utilizes the `SalesService` to **automatically generate** a fresh `Transaction` on the central ledger (flagging the 'particular' as "Sublimation" and inheriting the total amounts). 
- Once linked to production phases, the base sublimation `amount_total` behaves as read-only.

---

## 3. Expenses Flow

Expenses orchestrate systemic cash outflows, including purchasing materials, internal expenditures, and business liabilities using the `ExpenseController`.

### Workflow
- **Creation**: Requires branch routing, standard amounts, categorizable `payment_type` declarations, and allows uploading a receipt proxying through the `FileUploadService`.
- **Cash Impact**: Similar to the Sales architecture, tracking payments specifically as `CASH` immediately notifies the `CashOnHandService` to adjust the specified branch drawer downward categorized as an `expense`.

### Voiding Strategy
The system adopts an immutable-audit approach rather than a hard disconnect for mistakes via the `ExpenseController@void` framework.
- **Conditions**: To execute a `$expense->void()` interaction, the user must supply an explicit `reason` string spanning length bounds. 
- **Restoration Hook**: If the initial voided expense had successfully withdrawn money explicitly as `CASH`, the backend actively reconstructs the balance by utilizing `CashOnHandService::adjustBalance` backward as `revenue`—re-depositing the cash back into the branch drawer safely.

---

## Module System Interaction Overview
All three modules revolve heavily around the `CashOnHandService` singleton. The sub-systems remain decoupled yet effectively pool their logic regarding tangible cash metrics securely preventing localized drawer balance drifting. Sublimations explicitly feed directly into Transactions creating the financial paper trail seamlessly through code-driven state constraints rather than manual user duplications.
