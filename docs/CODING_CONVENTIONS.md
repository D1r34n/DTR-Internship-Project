# Coding Conventions — HSN DTR System

This document defines the coding standards and conventions used across the HSN DTR System to ensure consistency, readability, and maintainability.

---

## Scope

These conventions apply to:

- Backend PHP logic
- Frontend CSS, HTML, and JavaScript
- Database schema and queries
- Email and third-party integrations

They do **not** apply to:

- External libraries and vendor packages
- Auto-generated files

---

## 1. General Principles

- Write clean, readable, and maintainable code
- Keep business logic separate from presentation
- Avoid duplication (DRY principle)
- Prefer clarity over cleverness
- Use consistent naming across all files

---

## 2. File Structure

```
DTR-Internship-Project/
│
├── assets/
│   ├── css/
│   │   ├── root.css              # Design tokens (colors, shadows, spacing)
│   │   ├── typography.css        # Font import, sizes, weights, text utilities
│   │   ├── components.css        # Buttons, cards, modals, form controls
│   │   └── navbars_revised.css   # Sidebar and topbar styles
│   ├── images/                   # Static image assets
│   ├── user_profiles/            # Employee profile photos
│   └── attendance_captures/      # Webcam captures from attendance taps
│
├── authentication_pages/         # Login, logout, password reset, change password
├── management_pages/             # Admin pages (employees, departments, schedules, etc.)
├── regular_pages/                # Employee-facing pages (dashboard, logs, records)
├── reports_pages/                # Attendance and leave reports
├── dropdown_requests/            # AJAX handlers for leave, OT, OB, log edit, schedule edit
├── system_functions/             # Core business logic and utility functions
├── docs/                         # Documentation
├── sql/                          # Database schema files
├── vendor/                       # Composer packages (not tracked in git)
│
├── db.php                        # PDO connection, session timeout, timezone
├── send_mail.php                 # PHPMailer wrapper (sendMail, sendMailBulk)
├── mail_config.php               # Mail constants loaded from .env
├── env.php                       # Dotenv loader
├── index.php                     # Entry point redirector
├── sidebar_revised.php           # Navigation sidebar (role-aware, server-rendered)
└── topbar_revised.php            # Top navigation bar
```

### Key System Files

| File | Responsibility |
| ---- | -------------- |
| `db.php` | Database connection, session timeout, Asia/Manila timezone |
| `send_mail.php` | `sendMail()` and `sendMailBulk()` PHPMailer wrappers |
| `mail_config.php` | SMTP constants from `.env` |
| `system_functions/system_service.php` | Core attendance business logic |
| `system_functions/system_library.php` | Pure utility functions (no DB writes) |
| `sidebar_revised.php` | Server-rendered navigation based on user role |

---

## 3. Naming Conventions

### PHP Variables

Use `camelCase`. Must be descriptive.

```php
$employeeId
$scheduleDate
$timeInLog
$isAjax
```

### PHP Functions

Use `camelCase`. Must start with a verb.

```php
function sendMail(string $toEmail, string $toName, string $subject, string $body): bool
function sendMailBulk(array $recipients, string $subject): int
function distanceMeters($lat1, $lon1, $lat2, $lon2): float
```

### PHP Files

Use `snake_case.php`. Name must reflect the file's responsibility.

- Page files: `dashboard_page.php`, `logs_page.php`
- API handlers: `admin_requests_api.php`, `bulk_importing_api.php`
- Request handlers: `request_leave.php`, `request_ot.php`
- Include components: `sidebar_revised.php`, `topbar_revised.php`

### Database Tables and Columns

Use `snake_case` for both tables and columns.

```sql
-- Tables
employees, leave_requests, schedule_edit_requests, password_resets

-- Columns
employee_id, scheduled_start, actual_time_in, created_at
```

### CSS Classes

Use `kebab-case`. Must be descriptive and reflect purpose.

```html
class="sidebar-link"
class="btn-signin"
class="text-meta"
class="card-neutral"
class="login-theme-toggle"
```

### HTML IDs

Use `kebab-case`. Must be unique per page.

```html
id="email-input"
id="toggle-password"
id="submit-btn"
id="success-message"
```

---

## 4. PHP Conventions

### Database Access

Use PDO with prepared statements exclusively. Never concatenate SQL with user input.

```php
// Correct
$stmt = $pdo->prepare("SELECT id FROM employees WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Incorrect — never do this
$result = $pdo->query("SELECT * FROM employees WHERE email = '$email'");
```

PDO is configured in `db.php` with:
- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`
- `SET time_zone = '+08:00'` on connection

### Session Handling

Start sessions at the top of every protected page. Check the session immediately after.

```php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication_pages/login.php");
    exit();
}
```

Session inactivity timeout is 30 minutes (`SESSION_TIMEOUT = 1800`), enforced in `db.php`.

### CSRF Protection

Two CSRF token strategies are used:

**Session token** — for AJAX and standard form submissions:

```php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validation
if (!hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    // reject
}
```

**Form token** — single-use, for forms that must not be resubmitted:

```php
$_SESSION['form_token'] = bin2hex(random_bytes(16));

// After validation, immediately invalidate
unset($_SESSION['form_token']);
```

Always use `hash_equals()` for token comparison — never `===` or `==`.

### AJAX Detection and JSON Responses

Detect AJAX via a custom request header sent from JavaScript:

```php
$isAjax = !empty($_SERVER['HTTP_X_MY_PAGE_AJAX']);
```

Return JSON responses using a closure:

```php
$sendJSON = function (string $status, string $msg, string $redirect = '') use ($isAjax): void {
    if ($isAjax) {
        header('Content-Type: application/json');
        $payload = ['status' => $status, 'message' => $msg];
        if ($redirect) $payload['redirect'] = $redirect;
        echo json_encode($payload);
        exit();
    }
};
```

Use `'status' => 'success'` or `'status' => 'error'` as the discriminator field.

### Timezone

Always set the timezone in `db.php`. Do not set it per-page.

```php
date_default_timezone_set('Asia/Manila');
// and on the PDO connection:
$pdo->exec("SET time_zone = '+08:00'");
```

This ensures PHP's `date()` and MySQL's `NOW()` / `CURDATE()` agree.

### Input Validation

- Use `trim()` on all string inputs
- Use `filter_var($email, FILTER_VALIDATE_EMAIL)` for emails
- Cast numeric inputs: `(int)$_POST['id']`, `intval()`
- Use `preg_match()` for pattern-constrained fields (e.g., 6-digit employee IDs)
- Use `htmlspecialchars()` before echoing any user-supplied value into HTML

### Output Encoding

```php
// Always escape before output
echo htmlspecialchars($user['name']);

// In AJAX, json_encode() handles escaping automatically
echo json_encode(['name' => $user['name']]);
```

---

## 5. Email Conventions

Use the `sendMail()` wrapper from `send_mail.php`. Never call `mail()` directly.

```php
require_once '../send_mail.php';

sendMail($toEmail, $toName, $subject, $htmlBody);
```

The function signature:
```php
function sendMail(string $toEmail, string $toName, string $subject, string $body): bool
```

- `$body` is HTML — use `<p>`, `<a>`, `<strong>` tags, not `\n` linebreaks
- Errors are logged via `error_log()`, not thrown — the function returns `false` on failure

For bulk emails (e.g., notifying multiple employees):
```php
sendMailBulk($recipients, $subject);
// $recipients = [['email' => '...', 'name' => '...', 'body' => '...'], ...]
```

SMTP configuration lives in `.env` and is loaded by `mail_config.php`. Never hardcode credentials.

---

## 6. CSS & Frontend Conventions

### File Load Order

Load CSS files in this exact order — variables must be defined before they are used:

```html
<link rel="stylesheet" href="../assets/css/root.css">
<link rel="stylesheet" href="../assets/css/typography.css">
<link rel="stylesheet" href="../assets/css/components.css">
<!-- Page-specific CSS last -->
<link rel="stylesheet" href="page.css">
```

### CSS Variables

All design tokens are defined in `root.css` inside `:root {}`. Always use `var(--token-name)` — never hardcode hex or rgba values in component styles.

```css
/* Correct */
color: var(--text-light);
background: var(--primary-color);

/* Incorrect */
color: rgba(255, 255, 255, 0.85);
background: #97be41;
```

Exception: login.css dark-mode overrides use hardcoded values for elements Bootstrap owns.

### Theming (Dark / Light Mode)

The default theme is **dark** — dark mode variables are declared on `:root`. Light mode is applied via the `[data-theme="light"]` attribute on `<html>`.

```css
/* Dark mode — default, in root.css */
:root {
    --bg-page: #101213;
    --text-primary: rgba(255, 255, 255, 0.85);
}

/* Light mode override */
[data-theme="light"] {
    --bg-page: #f5f5f5;
    --text-primary: #1a1a1a;
}
```

When adding theme-aware styles, always override in both directions if the default (dark) value would not read correctly in light mode.

### Font

Poppins is loaded once via `@import` at the top of `typography.css`. Never add a `<link>` for Poppins in a page `<head>`.

Allowed weights: **300, 400, 500, 600, 700** only.

Form elements do not inherit font-family — always declare explicitly:

```css
button, input, select, textarea {
    font-family: 'Poppins', sans-serif;
}
```

### Typography Classes

| Class | Size | Weight | Use |
| ----- | ---- | ------ | --- |
| `.page-header` | 1.5rem | 500 | Main page title |
| `.section-title` | 1.25rem | 500 | Card and section headings |
| `.subsection-title` | 1.125rem | 400 | Smaller headings |
| `.text-secondary` | 0.875rem | 300 | Labels, descriptions |
| `.text-meta` | 0.75rem | 300 | Timestamps, helper text |
| `.stats-number` | 1.75rem | 500 | Dashboard stat values |

### Comment Style

Use a two-tier comment system:

```css
/* ================================================
   FILE TITLE
   ================================================ */

/* ------------------------------------------------
   Section Name
   ------------------------------------------------ */

/* Inline note for a single rule */
```

---

## 7. JavaScript Conventions

### Approach

Vanilla JavaScript only — no framework (React, Vue, etc.). Bootstrap 5.3.3 is the only UI library.

### AJAX with Fetch

```javascript
const res = await fetch('api_endpoint.php', {
    method:  'POST',
    body:    new FormData(form),
    headers: { 'X-My-Page-Ajax': '1' },
});

if (res.status >= 500) {
    showBanner('Server error. Please try again later.');
    return;
}

const data = await res.json();

if (data.status === 'success') {
    // handle success
} else {
    showBanner(data.message);
}
```

- Always send a custom header (e.g., `X-Forgot-Pwd-Ajax: 1`) so PHP can detect AJAX
- Always check for HTTP 500 before calling `.json()`
- Wrap in `try/catch` to handle network failures

### DOM Interaction

Use `getElementById`, `querySelector`, `querySelectorAll`. Use `classList.add/remove/toggle` for state. Prefer direct DOM references stored in `const` over repeated querying.

```javascript
const submitBtn = document.getElementById('submit-btn');
submitBtn.disabled    = true;
submitBtn.textContent = 'Saving…';
```

### Animations

Use CSS class toggling to drive entrance animations. For JS-driven transitions, use `style.transition` with `setTimeout` to sequence steps:

```javascript
// Fade out, then show new state
element.style.transition = 'opacity 0.25s ease';
element.style.opacity    = '0';
setTimeout(() => {
    element.classList.add('d-none');
    nextElement.classList.remove('d-none');
    nextElement.classList.add('fade-in-up');
}, 270);
```

Do not put animation keyframes in `<style>` tags inside PHP files — add them to the appropriate CSS file.

### Code Organization

Wrap page-level JS in an IIFE to avoid polluting the global scope:

```javascript
(function () {
    const form = document.getElementById('my-form');
    // ...
})();
```

Functions shared across an IIFE and outside handlers (e.g., `initToggle`) are declared at the top level of the script block.

---

## 8. HTML Conventions

### Bootstrap

Bootstrap **5.3.3** is loaded via CDN:

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
```

Bootstrap Icons **1.11.3** is used for icons:

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<i class="bi bi-check-circle-fill"></i>
```

### Data Attributes

Bootstrap attributes follow the `data-bs-*` convention:

```html
<button data-bs-toggle="modal" data-bs-target="#myModal">Open</button>
<span data-bs-toggle="tooltip" data-bs-title="Helpful tip">?</span>
```

Custom data attributes use `data-*` for page-specific state:

```html
<tr data-employee-id="<?= $id ?>" data-status="active">
```

---

## 9. Security Standards

### Rule Priority

1. Security rules (highest)
2. Data integrity
3. Performance
4. Readability

### Session Protection

Every protected page must validate the session before any logic runs:

```php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication_pages/login.php");
    exit();
}
```

Role-based access: check `$_SESSION['user_role']` to restrict pages to specific roles (`superadmin`, `admin`, `manager`, `workforce`, `employee`).

### Login Rate Limiting

Login attempts are rate-limited per IP:

- **5 failed attempts** triggers a **15-minute lockout** (900 seconds)
- Lockout state is tracked in the database

### Password Resets

Token-based reset flow uses the `password_resets` table:

- Token: `bin2hex(random_bytes(32))` — 64 hex characters
- Expiry: 1 hour from generation (`expires_at`)
- Single-use: `used = 1` set on successful reset
- Old tokens are deleted before inserting a new one per employee

### Prepared Statements

All SQL queries use prepared statements. Never build queries with string concatenation.

### Output Escaping

- HTML output: `htmlspecialchars($value)` always
- JSON output: `json_encode()` handles escaping automatically
- JavaScript values injected from PHP: use `json_encode()`:

```php
const isTokenMode = <?= json_encode($isTokenMode) ?>;
```

---

## 10. Database Standards

### Schema Conventions

- Primary key: `id INT AUTO_INCREMENT PRIMARY KEY`
- Foreign keys: `<table_singular>_id` (e.g., `employee_id`, `department_id`)
- Timestamps: `created_at DATETIME DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`
- Boolean flags: `TINYINT(1) DEFAULT 0` (e.g., `is_archived`, `is_rest_day`, `used`)
- Enum for status: `ENUM('pending','approved','rejected')` for restricted states
- JSON columns: for arrays of values (e.g., `selected_dates` in leave requests)

### Active Tables

| Table | Purpose |
| ----- | ------- |
| `employees` | User accounts with roles, departments, profile info |
| `attendances` | Clock-in/out logs with computed durations and status |
| `schedules` | Work schedules per employee with rest day flags |
| `departments` | Organizational hierarchy with `parent_id` |
| `roles` | Role definitions with `role_key` |
| `leave_requests` | Leave filings with `selected_dates` (JSON) |
| `leave_types` | Leave type configuration |
| `employee_leave_balances` | Per-employee leave balance tracking |
| `overtime_requests` | Overtime filing |
| `log_edit_requests` | Requests to amend attendance logs |
| `schedule_edit_requests` | Requests to change schedules |
| `cutoffs` | Payroll period definitions |
| `password_resets` | Password reset tokens |
| `logs` | Audit trail of system actions |
| `events` | Calendar events |
| `quote_of_the_day` | Dashboard motivational quote |
| `system_state` | System configuration flags |

### Always Index

- `employee_id` on any table that joins to `employees`
- Date/time columns used in range queries (`work_date`, `schedule_date`, `expires_at`)

---

## 11. Time and Date Standards

- Timezone is always **Asia/Manila (UTC+8)**
- Set once in `db.php` — do not repeat `date_default_timezone_set()` per page
- MySQL session timezone also set to `+08:00` in `db.php`
- Store all timestamps as `Y-m-d H:i:s`
- Use `NOW()` in MySQL and `date('Y-m-d H:i:s')` in PHP — both will agree after `db.php` loads

---

## 12. Environment and Configuration

Sensitive values live in `.env` and are never committed to the repository.

```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=app_specific_password
MAIL_FROM_EMAIL=no-reply@hsndtr.com
MAIL_FROM_NAME="HSN DTR System"
```

Load `.env` via `env.php` at the top of files that need it:

```php
require_once '../env.php';
```

Database credentials (`db.php`) are hardcoded for local development (`localhost`, `root`, empty password). Update for production.

### Composer Dependencies

| Package | Version | Purpose |
| ------- | ------- | ------- |
| `phpmailer/phpmailer` | ^7.0 | SMTP email sending |
| `phpoffice/phpspreadsheet` | 1.29 | Excel import/export |
| `vlucas/phpdotenv` | ^5.6 | `.env` file loading |

---

## 13. Performance Standards

- Avoid N+1 queries — batch-fetch related data in a single query with JOINs where possible
- Use `LIMIT` on all list queries; API endpoints enforce `min(100, $limit)` caps
- Minimize repeated database calls within a single request
- Close SMTP connections after bulk mail: `$mail->smtpClose()`

---

## 14. Code Readability

- Use blank lines between logical blocks
- Add comments only when the **why** is non-obvious — not the what
- Align assignments vertically within a block for readability:

```php
$token     = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
$fullName  = $user['first_name'] . ' ' . $user['last_name'];
```

```javascript
submitBtn.disabled    = true;
submitBtn.textContent = 'Saving…';
```

---

## Summary

All contributors must follow these conventions when modifying or extending the system. When in doubt, match the style of the surrounding code.
