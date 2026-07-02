# What's New — July 2, 2026

A summary of recent improvements to the Projects (Sales), Sublimations, and Purchase Orders modules.

---

## Sublimations

### Search box
- A new **Search** box on the sublimations list lets you search by **project description** or **customer name** (first or last). Results update as you type.

### View Sewed Item
- The **Actions** menu on a sublimation now has a **View Sewed Item** option (shown once a sewed item exists for that sublimation). It opens the Sewed Items page filtered to that exact item.

---

## Projects (Sales)

### Status tabs: Partial, Paid, Unpaid
- The Projects list now has three tabs by settlement status — **Partial**, **Paid**, **Unpaid** (in that order) — and opens on **Partial** so unsettled projects are surfaced first. Each tab lists the projects in that state.
- The old **Status** dropdown in the filter bar has been removed since the tabs now cover it.
- The **payment breakdown** (Projects Summary Overview) is fixed to **Partial + Paid** collections — it stays the same when you switch between the Partial and Paid tabs and only changes with the Date/Frequency, Branch, and Staff filters. It is hidden on the **Unpaid** tab.

### Smarter customer search
- The Search box now finds a customer by **first name, last name, or full name**. Typing a full name like "Juan Dela Cruz" matches even when the words span the first and last name.

### Open a sublimation from the table
- On the Projects table, the row cells from **Customer Name** through **Date** are clickable for sublimation-backed rows and open the related sublimation (in a new tab). Those rows show a link cue on the customer name. The **Collection** and **Actions** buttons stay unaffected.

---

## Purchase Orders

### Smoother PO number search
- The **PO Number** search now waits until you pause typing before it searches (debounced), instead of firing a request on every keystroke. Stray leading/trailing spaces are ignored.
