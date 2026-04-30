# 📘 Coding Conventions — Attendance Management System

This document defines the coding standards and conventions used across the Attendance Management System to ensure consistency, readability, and maintainability.

---

# 🧱 1. General Principles

* Write **clean, readable, and maintainable code**
* Keep **business logic separate from presentation**
* Avoid duplication (DRY principle)
* Prefer clarity over cleverness
* Use consistent naming across all files

---

# 📁 2. File Structure Convention

## Recommended Layering

```
/attendance-system
│
├── controllers/     # Request handlers (entry points)
├── services/        # Business logic layer
├── libraries/       # Reusable helper functions
├── config/          # DB, timezone, system config
└── public/          # UI / frontend entry points
```

## Current System Mapping

| File               | Layer         |
| ------------------ | ------------- |
| attendance_tap.php | Controller    |
| system_service.php | Service Layer |
| system_library.php | Utility Layer |
| db.php             | Config        |

---

# 🧾 3. Naming Conventions

## Variables

* Use `camelCase`
* Must be descriptive

```php id="v9k2ab"
$employeeId;
$scheduleDate;
$timeInLog;
```

---

## Functions

* Use `camelCase`
* Must start with verb

```php id="f2k9ld"
processAttendanceTap()
finalizeEmployeeAttendance()
getAttendanceRecords()
```

---

## Files

* Use `snake_case.php` or `descriptive_name.php`
* Must reflect responsibility

Examples:

* `attendance_tap.php`
* `system_service.php`
* `system_library.php`

---

## Database Fields

* Use `snake_case`

Examples:

* `employee_id`
* `schedule_date`
* `scheduled_start`

---

# 🧠 4. Business Logic Rules

## Service Layer Rule

All core logic must reside in:

* `system_service.php`

❌ Do NOT place business logic inside controllers

---

## Controller Rule

Controllers should only:

* Validate session
* Receive input
* Call service functions
* Return response

Example:

```php id="c8x1op"
processAttendanceTap($pdo, $employeeId, $timestamp);
```

---

## Utility Rule

`system_library.php` must only contain:

* Pure functions
* Reusable calculations
* No database writes

Examples:

* GPS distance calculation
* Gantt timeline computations

---

# ⏱️ 5. Time & Date Standards

* Always use PHP timezone:

```php id="t3m9qx"
date_default_timezone_set('Asia/Manila');
```

* All timestamps stored in **24-hour format**
* Always use `Y-m-d H:i:s` for database consistency

---

# 📊 6. Attendance Logic Standards

## Status Definitions

| Status     | Rule                           |
| ---------- | ------------------------------ |
| PRESENT    | Valid IN + OUT logs exist      |
| INCOMPLETE | Missing IN or OUT              |
| ABSENT     | No logs within schedule window |

---

## Cross-Day Rule

* Shifts spanning midnight must be treated as a single logical shift
* Logs must be merged across date boundaries

---

# 🔐 7. Security Standards

* Always enforce session checks:

```php id="s1n9aa"
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
```

* Never trust raw input
* Sanitize GET/POST data
* Use prepared statements for SQL

---

# 🗄️ 8. Database Standards

* Use **prepared statements only**
* Never concatenate SQL strings with user input
* Always index:

  * `employee_id`
  * `timestamp`
  * `schedule_date`

---

# 🧩 9. Function Design Rules

## Function Requirements

Each function must:

* Do ONE job only
* Have clear input/output
* Be reusable where possible

### Example:

```php id="z1x9pp"
function finalizeEmployeeAttendance(PDO $pdo, int $employeeId, array $schedule)
```

---

## Avoid

* Mixed responsibilities
* Direct HTML inside logic functions
* Hidden side effects

---

# 📌 10. Code Readability Rules

* Use spacing between logical blocks
* Add comments for complex logic only
* Avoid unnecessary inline comments

Example:

```php id="r8q1dd"
// Fetch logs within shift window
$stmt = $pdo->prepare("...");
```

---

# 📈 11. Logging & Debugging

* Use temporary logs during development only
* Remove debug prints in production
* Prefer structured debugging instead of echo dumps

---

# 🚀 12. Performance Standards

* Avoid N+1 queries
* Batch fetch data where possible
* Minimize repeated database calls
* Cache computed schedule results when possible

---

# 🧭 13. Maintainability Rules

* Refactor large functions into smaller ones
* Keep service layer clean and centralized
* Avoid duplication of attendance logic
* Ensure backward compatibility when modifying core logic

---

# 📌 Summary

This coding standard ensures that the Attendance Management System remains:

* Scalable
* Maintainable
* Readable
* Debug-friendly

All contributors must follow these conventions when modifying or extending the system.

---
