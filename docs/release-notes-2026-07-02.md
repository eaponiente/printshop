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

### Tabs split by settlement status
The list tabs now let you jump straight to a settlement status. Each tab lists the projects in that state:

- **Paid** — projects that are fully paid.
- **Partial** — projects that have been partially paid.
- **Unpaid** — projects with no payment yet.

### Status filter removed
- The **Status** dropdown in the filter bar has been removed. The three tabs above now cover that, so there's no longer a redundant status filter to set.
- All other filters (Search, Frequency, Date, Branch, Staff, Payment type) are unchanged.

### Smarter customer search
- The Search box now finds a customer by **first name, last name, or full name**. Typing a full name like "Juan Dela Cruz" matches even when the words span the first and last name.

### Jump to a sublimation from its project
- For sublimation-backed projects, the **Particular** column is now a link that opens the related sublimation in a new tab.

---

## Purchase Orders

### Smoother PO number search
- The **PO Number** search now waits until you pause typing before it searches (debounced), instead of firing a request on every keystroke. Stray leading/trailing spaces are ignored.
