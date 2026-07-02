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

### Smarter customer search
- The Search box now finds a customer by **first name, last name, or full name**. Typing a full name like "Juan Dela Cruz" matches even when the words span the first and last name.

### Open a sublimation from the Payments table
- On the **Payments** view, the row cells from **Customer Name** through **Date** are now clickable for sublimation-backed rows and open the related sublimation (in a new tab). The **Collection** and **Actions** buttons stay unaffected.

---

## Purchase Orders

### Smoother PO number search
- The **PO Number** search now waits until you pause typing before it searches (debounced), instead of firing a request on every keystroke. Stray leading/trailing spaces are ignored.
