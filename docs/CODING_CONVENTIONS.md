# Coding Conventions — Attendance Management System

This document defines the coding standards and conventions used across the Attendance Management System to ensure consistency, readability, and maintainability.

---

## Scope

These coding conventions apply to:

* Backend PHP logic
* Attendance processing system
* Database interaction layer
* Frontend CSS and HTML

They do **not** apply to:

* External frontend libraries
* Vendor packages

---

## 1. General Principles

* Write **clean, readable, and maintainable code**
* Keep **business logic separate from presentation**
* Avoid duplication (DRY principle)
* Prefer clarity over cleverness
* Use consistent naming across all files

---

## 2. File Structure

```
/attendance-system
│
├── assets/                      # Contains CSS template and visual content
    ├── attendance_captures/     # Image captures from webcam
    ├── css/
        │   ├── root.css               # CSS variables / design tokens
        │   ├── typography.css         # Font sizes, weights, body styles
        │   ├── base.css               # Resets, global defaults
        │   ├── layout.css             # Grid, sidebar, containers
        │   ├── components.css         # Buttons, cards, modals, forms
        │   ├── calendar.css           # Calendar-specific styles
        │   ├── gantt.css              # Gantt chart styles
        │   └── utilities.css          # Helper classes
        ├── images/                    # All image assets
        ├── user_profiles/             # Contains user avatars
├── authentication_pages/        # Contains login, logout, reset password logic
├── docs/                        # README file and system documentation
├── dropdown_requests/           # Overtime, Leave, OB, Log Edit, Schedule Edit request functions 

```

### Current System Mapping

| File               | Responsibility                       |
| ------------------ | ------------------------------------ |
| attendance_tap.php | Handles incoming tap requests        |
| system_service.php | Core attendance business logic       |
| system_library.php | Pure helper utilities (no DB writes) |
| db.php             | Database connection                  |

---

## 🧾 3. Naming Conventions

### PHP Variables

* Use `camelCase`
* Must be descriptive

```php
$employeeId;
$scheduleDate;
$timeInLog;
```

---

### PHP Functions

* Use `camelCase`
* Must start with a verb

```php
function processAttendanceTap($pdo, $employeeId, $timestamp);
function finalizeEmployeeAttendance($pdo, $employeeId, $schedule);
function getAttendanceRecords($employeeId);
```

---

### PHP Files

* Use `snake_case.php` or descriptive names
* Must reflect responsibility

Examples:

* attendance_tap.php
* system_service.php
* system_library.php

---

### Database Fields

* Use `snake_case`

Examples:

* employee_id
* schedule_date
* scheduled_start

---

### CSS Classes

* Use `kebab-case`
* Must be descriptive and reflect purpose

Examples:

```html
class="side-bar"
class="page-header"
class="field-group"
class="btn-signin"
class="text-meta"
```

---

### HTML IDs

* Use `kebab-case`
* Must be unique per page
* Must clearly identify the element

Examples:

```html
id="toggle-password"
id="email-input"
id="password-input"
id="employee-menu"
id="admin-menu"
```

---

## 🎨 4. CSS & Frontend Conventions

### File Load Order

CSS files must be loaded in this exact order to ensure variables are available before they are used:

```html
<link rel="stylesheet" href="../assets/css/root.css">
<link rel="stylesheet" href="../assets/css/typography.css">
<link rel="stylesheet" href="../assets/css/components.css">
<!-- Page-specific CSS last -->
<link rel="stylesheet" href="page.css">
```

---

### CSS Variables

* All design tokens (colors, shadows, borders) must be defined in `root.css` inside `:root {}`
* Always use `var(--token-name)` — never hardcode hex values in component styles
* Variables declared outside `:root {}` will not work

```css
/* ✅ Correct */
color: var(--text-light);
background: var(--primary-color);

/* ❌ Incorrect */
color: rgba(255, 255, 255, 0.85);
background: #97be41;
```

---

### Font Loading

* Poppins is loaded once via `@import` inside `typography.css`
* Never add a separate `<link>` for Poppins in page `<head>` — it is redundant
* `@import` must be the very first line of `typography.css` — no comments before it

```css
/* ✅ Correct — first line of typography.css */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500&display=swap');

/* ❌ Incorrect — in page <head> */
<link href="https://fonts.googleapis.com/css2?family=Poppins..." rel="stylesheet">
```

---

### Typography Rules

* Font: **Poppins** (weights: 300, 400, 500 only)
* Base size: `16px` set on `html`
* Body default: `font-weight: 300`
* Never use font-weight values outside 300, 400, or 500
* Always include `line-height` on text utility classes
* Always apply typography classes to HTML elements

| Class               | Size     | Weight | Use Case                    |
| ------------------- | -------- | ------ | --------------------------- |
| `.page-header`      | 1.5rem   | 500    | Main page title             |
| `.section-title`    | 1.25rem  | 500    | Card and section headings   |
| `.subsection-title` | 1.125rem | 400    | Smaller headings            |
| `.text-primary`     | 1rem     | 300    | Main content, paragraphs    |
| `.text-secondary`   | 0.875rem | 300    | Labels, descriptions        |
| `.text-meta`        | 0.75rem  | 300    | Timestamps, helper text     |
| `.stats-number`     | 1.75rem  | 500    | Dashboard stat values       |
| `.navbar-text`      | 0.95rem  | 400    | Topbar / navbar text        |
| `.sidebar-item`     | 0.95rem  | 300    | Sidebar menu items          |

---

### General CSS Rules

* One responsibility per file — do not mix layout rules into `typography.css`
* Form elements (`button`, `input`, `select`, `textarea`) must explicitly declare `font-family: 'Poppins', sans-serif` — they do not inherit it by default
* Always enable font smoothing on `body`:

```css
body {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}
```

---

### Comment Header Style

Use a two-tier comment system for all CSS files:

```css
/* ================================================
   FILE TITLE         ← one per file, at the top
   ================================================ */

/* ------------------------------------------------
   Section Name       ← one per section block
   ------------------------------------------------ */

/* Inline note */     ← for single rule clarifications
```

---

## 🧠 5. Business Logic Rules

### Service Layer Rule

All core logic must reside in:

* `system_service.php`

❌ Do NOT place business logic inside controllers

---

### Controller Rule

Controllers should only:

* Validate session
* Receive input
* Call service functions
* Return response

Example:

```php
processAttendanceTap($pdo, $employeeId, $timestamp);
```

---

### Utility Rule

`system_library.php` must only contain:

* Pure functions
* Reusable calculations
* No database writes

Examples:

* GPS distance calculation
* Gantt timeline computations

---

## ⏱️ 6. Time & Date Standards

* Always use PHP timezone:

```php
date_default_timezone_set('Asia/Manila');
```

* All timestamps stored in **24-hour format**
* Always use `Y-m-d H:i:s` for database consistency

---

## 📊 7. Attendance Logic Standards

### Status Definitions

| Status     | Rule                           |
| ---------- | ------------------------------ |
| PRESENT    | Valid IN + OUT logs exist      |
| INCOMPLETE | Missing IN or OUT              |
| ABSENT     | No logs within schedule window |

---

### Cross-Day Rule

* Shifts spanning midnight are treated as a single logical shift
* Logs must be merged across date boundaries

---

## ⚖️ 8. Rule Priority

If rules conflict:

1. Security rules (highest priority)
2. Data integrity rules
3. Performance rules
4. Readability rules

---

## 🔐 9. Security Standards

* Always enforce session checks:

```php
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
```

* Never trust raw input
* Sanitize GET/POST data with `htmlspecialchars()` before output
* Use prepared statements for all SQL queries

---

## 🗄️ 10. Database Standards

* Use **prepared statements only**
* Never concatenate SQL strings with user input
* Always index:

  * employee_id
  * timestamp
  * schedule_date

---

## 🧩 11. Function Design Rules

### Function Requirements

Each function must:

* Do ONE job only
* Have clear input/output
* Be reusable where possible

### Example:

```php
function finalizeEmployeeAttendance($pdo, $employeeId, $schedule)
```

---

### Avoid

* Mixed responsibilities
* Direct HTML inside logic functions
* Hidden side effects

---

## 📌 12. Code Readability Rules

* Use spacing between logical blocks
* Add comments for complex logic only
* Avoid unnecessary inline comments

Example:

```php
// Fetch logs within shift window
$stmt = $pdo->prepare("...");
```

---

## 📈 13. Logging & Debugging

* Use logs only during development
* Remove debug output in production
* Prefer structured debugging over echo dumps

---

## 🚀 14. Performance Standards

* Avoid N+1 queries
* Batch fetch data where possible
* Minimize repeated database calls
* Cache computed schedule results when possible

---

## 🧭 15. Maintainability Rules

* Refactor large functions into smaller ones
* Keep service layer clean and centralized
* Avoid duplication of attendance logic
* Ensure backward compatibility when modifying core logic

---

## 🧾 16. Commenting & Documentation Style

This system follows a structured commenting hierarchy to ensure readability and consistency across all files.

### 📌 File Header (Top-Level)

Used once per file to describe the file purpose.

```
/* ================================================
   FILE TITLE            ← top-level, one per file
   ================================================ */
```

### 📌 Section Header (Mid-Level)

Used to separate major sections inside a file.

```
/* ------------------------------------------------
   Section Name          ← mid-level, per section
   ------------------------------------------------ */
```

### 📌 Inline Comment (Single Rule)

Used for short explanations per line or rule.

```
/* Single note */        ← inline, per rule
```

---

## 📌 Summary

These coding standards ensure that the Attendance Management System remains:

* Scalable
* Maintainable
* Readable
* Secure
* Debug-friendly

All contributors must follow these conventions when modifying or extending the system.