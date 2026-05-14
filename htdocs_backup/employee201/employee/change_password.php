<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// ✅ Redirect if not logged in as employee
if (!isset($_SESSION['employee_code'])) {
    header("Location: ../auth/login.php");
    exit();
}

$employee_code = $_SESSION['employee_code'];
$message       = "";
$messageType   = ""; // "success" or "error"

// ✅ Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // ── Basic field check ─────────────────────────────────────────────────────
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message     = "All fields are required.";
        $messageType = "error";

    } elseif ($newPassword !== $confirmPassword) {
        $message     = "New passwords do not match.";
        $messageType = "error";

    } elseif ($newPassword === $currentPassword) {
        $message     = "New password must be different from your current password.";
        $messageType = "error";

    } elseif (strlen($newPassword) < 6) {
        $message     = "New password must be at least 6 characters.";
        $messageType = "error";

    } else {
        // ── Fetch hashed password from employee_users ─────────────────────────
        $stmt = $conn->prepare("SELECT password FROM employee_users WHERE employee_code = ? LIMIT 1");
        $stmt->bind_param("s", $employee_code);
        $stmt->execute();
        $stmt->bind_result($hashedPassword);
        $stmt->fetch();
        $stmt->close();

        if (!$hashedPassword || !password_verify($currentPassword, $hashedPassword)) {
            $message     = "Current password is incorrect.";
            $messageType = "error";

        } else {
            // ── Update hashed + plain password in employee_users ──────────────
            $newHashed = password_hash($newPassword, PASSWORD_BCRYPT);
            $update    = $conn->prepare("UPDATE employee_users SET password = ?, plain_password = ? WHERE employee_code = ?");
            $update->bind_param("sss", $newHashed, $newPassword, $employee_code);

            if ($update->execute()) {
                $update->close();
                $message     = "Password updated successfully!";
                $messageType = "success";
            } else {
                $message     = "Error updating password. Please try again.";
                $messageType = "error";
                $update->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Change Password</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
* { box-sizing: border-box; }
body {
    font-family: 'Segoe UI', sans-serif;
    background: #1e1e2d;
    color: #fff;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
    padding: 20px;
}
.container {
    background: #2b2b3b;
    padding: 32px 28px;
    border-radius: 14px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
}
h2 {
    margin: 0 0 6px;
    color: #3b82f6;
    text-align: center;
    font-size: 22px;
}
.subtitle {
    text-align: center;
    color: #9ca3af;
    font-size: 13px;
    margin-bottom: 24px;
}
form { display: flex; flex-direction: column; gap: 16px; }

label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #9ca3af;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.input-group { position: relative; }
.input-group input {
    width: 100%;
    padding: 11px 42px 11px 14px;
    background: #1e1e2d;
    border: 1px solid #3b3b4a;
    border-radius: 8px;
    color: #f9fafb;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
}
.input-group input:focus  { border-color: #3b82f6; }
.input-group input.invalid { border-color: #ef4444 !important; }
.input-group input.valid   { border-color: #22c55e !important; }

.toggle-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    font-size: 16px;
    padding: 0;
    line-height: 1;
}
.toggle-btn:hover { color: #f9fafb; }

.hint {
    font-size: 11px;
    color: #6b7280;
    margin-top: 5px;
    line-height: 1.4;
}
.hint.ok  { color: #22c55e; }
.hint.bad { color: #ef4444; }

.message {
    padding: 11px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-align: center;
    margin-bottom: 8px;
}
.message.success { background: #14532d; color: #86efac; border: 1px solid #16a34a; }
.message.error   { background: #4c0519; color: #fca5a5; border: 1px solid #dc2626; }

button[type=submit] {
    padding: 12px;
    background: linear-gradient(90deg, #2563eb, #3b82f6);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    margin-top: 4px;
    transition: opacity 0.2s, transform 0.1s;
}
button[type=submit]:hover   { opacity: 0.9; transform: translateY(-1px); }
button[type=submit]:active  { transform: translateY(0); }
button[type=submit]:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

.back-link {
    display: block;
    margin-top: 18px;
    text-align: center;
    color: #6b7280;
    text-decoration: none;
    font-size: 13px;
    transition: color 0.2s;
}
.back-link:hover { color: #f9fafb; }

.redirect-note {
    text-align: center;
    font-size: 12px;
    color: #6b7280;
    margin-top: 6px;
}
</style>
</head>
<body>
<div class="container">
    <h2>🔒 Change Password</h2>
    <p class="subtitle">Update your login password below</p>

    <?php if (!empty($message)): ?>
        <div class="message <?= $messageType ?>">
            <?= $messageType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($message) ?>
        </div>
        <?php if ($messageType === 'success'): ?>
            <p class="redirect-note">Redirecting to profile in <span id="countdown">3</span>s...</p>
        <?php endif; ?>
    <?php endif; ?>

    <form method="POST" id="changePassForm" <?= $messageType === 'success' ? 'style="display:none"' : '' ?>>

        <div>
            <label for="current_password">Current Password</label>
            <div class="input-group">
                <input type="password" name="current_password" id="current_password"
                       placeholder="Enter your current password" required autocomplete="current-password">
                <button type="button" class="toggle-btn" onclick="toggleField('current_password', this)">👁</button>
            </div>
        </div>

        <div>
            <label for="new_password">New Password</label>
            <div class="input-group">
                <input type="password" name="new_password" id="new_password"
                       placeholder="Enter new password" required autocomplete="new-password"
                       oninput="validateNewPassword()">
                <button type="button" class="toggle-btn" onclick="toggleField('new_password', this)">👁</button>
            </div>
            <p class="hint" id="newPassHint">At least 6 characters</p>
        </div>

        <div>
            <label for="confirm_password">Confirm New Password</label>
            <div class="input-group">
                <input type="password" name="confirm_password" id="confirm_password"
                       placeholder="Re-enter new password" required autocomplete="new-password"
                       oninput="validateConfirm()">
                <button type="button" class="toggle-btn" onclick="toggleField('confirm_password', this)">👁</button>
            </div>
            <p class="hint" id="confirmHint"></p>
        </div>

        <button type="submit" id="submitBtn" disabled>Update Password</button>
    </form>

    <a href="profile.php" class="back-link">⬅ Back to Profile</a>
</div>

<script>
// ── Toggle show / hide for any password field ─────────────────────────────────
function toggleField(fieldId, btn) {
    const input  = document.getElementById(fieldId);
    const hidden = input.type === "password";
    input.type      = hidden ? "text" : "password";
    btn.textContent = hidden ? "🙈" : "👁";
}

// ── Validate new password: minimum 6 characters ───────────────────────────────
function validateNewPassword() {
    const input   = document.getElementById("new_password");
    const hint    = document.getElementById("newPassHint");
    const val     = input.value;
    const isValid = val.length >= 6;

    input.classList.remove("valid", "invalid");
    hint.className = "hint";

    if (val.length === 0) {
        hint.textContent = "At least 6 characters";
    } else if (isValid) {
        input.classList.add("valid");
        hint.classList.add("ok");
        hint.textContent = "✓ Password is acceptable";
    } else {
        input.classList.add("invalid");
        hint.classList.add("bad");
        hint.textContent = `✗ Too short — at least 6 characters required (currently ${val.length})`;
    }

    validateConfirm();
    updateSubmitBtn();
}

// ── Validate confirm matches new password ─────────────────────────────────────
function validateConfirm() {
    const newVal     = document.getElementById("new_password").value;
    const confirmVal = document.getElementById("confirm_password").value;
    const hint       = document.getElementById("confirmHint");
    const input      = document.getElementById("confirm_password");

    input.classList.remove("valid", "invalid");
    hint.className = "hint";
    hint.textContent = "";

    if (confirmVal.length === 0) return;

    if (newVal === confirmVal) {
        input.classList.add("valid");
        hint.classList.add("ok");
        hint.textContent = "✓ Passwords match";
    } else {
        input.classList.add("invalid");
        hint.classList.add("bad");
        hint.textContent = "✗ Passwords do not match";
    }

    updateSubmitBtn();
}

// ── Enable submit only when both validations pass ─────────────────────────────
function updateSubmitBtn() {
    const newVal     = document.getElementById("new_password").value;
    const confirmVal = document.getElementById("confirm_password").value;
    const ok         = newVal.length >= 6 && newVal === confirmVal;
    document.getElementById("submitBtn").disabled = !ok;
}

// ── Auto-redirect countdown after success ─────────────────────────────────────
<?php if ($messageType === 'success'): ?>
let secs = 3;
const el = document.getElementById("countdown");
const t  = setInterval(() => {
    secs--;
    if (el) el.textContent = secs;
    if (secs <= 0) { clearInterval(t); window.location.href = "profile.php"; }
}, 1000);
<?php endif; ?>
</script>
</body>
</html>