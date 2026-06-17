<?php
session_start();

require_once '../db.php';

// ── Mode detection ────────────────────────────────────────────────────────────
$token      = trim($_GET['token'] ?? '');
$tokenValid = false;
$employeeId = null;
$mode       = null; // 'token' | 'session'

if ($token) {
    $mode = 'token';
    $stmt = $pdo->prepare("
        SELECT employee_id FROM password_resets
        WHERE token = ? AND used = 0 AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $tokenValid = true;
        $employeeId = (int) $row['employee_id'];
    }
} elseif (isset($_SESSION['user_id']) && !empty($_SESSION['must_change_password'])) {
    $mode       = 'session';
    $employeeId = (int) $_SESSION['user_id'];
} else {
    header("Location: login.php");
    exit();
}

// Session mode: prevent re-display on back-button refresh
if ($mode === 'session' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_SESSION['change_password_shown'])) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
    $_SESSION['change_password_shown'] = true;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $isAjax = !empty($_SERVER['HTTP_X_CHANGE_PWD_AJAX']);

    $sendJSON = function (string $status, string $msg, string $redirect = '') use ($isAjax): void {
        if ($isAjax) {
            header('Content-Type: application/json');
            $payload = ['status' => $status, 'message' => $msg];
            if ($redirect) $payload['redirect'] = $redirect;
            echo json_encode($payload);
            exit();
        }
    };

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error = "Invalid request token. Please refresh the page and try again.";
        $sendJSON('error', $error);
    } elseif ($mode === 'token' && !$tokenValid) {
        $error = "This reset link is invalid or has already expired.";
        $sendJSON('error', $error);
    } else {
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!$newPassword) {
            $error = "New password is required.";
        } elseif ($newPassword === 'HSN.123') {
            $error = "You cannot use the default password. Please choose a different one.";
        } elseif (strlen($newPassword) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Passwords do not match.";
        } else {
            $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?")
                ->execute([$newPassword, $employeeId]);

            if ($mode === 'token') {
                $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")
                    ->execute([$token]);
                $redirect = 'login.php';
            } else {
                unset($_SESSION['must_change_password'], $_SESSION['change_password_shown']);
                $redirect = '../regular_pages/dashboard_page.php';
            }

            $sendJSON('success', 'Password updated successfully.', $redirect);

            header("Location: $redirect");
            exit();
        }

        if ($error) $sendJSON('error', $error);
    }
}

// ── View helpers ──────────────────────────────────────────────────────────────
$isTokenMode  = ($mode === 'token');
$pageTitle    = $isTokenMode ? 'Reset Password'  : 'Change Password';
$pageSubtitle = $isTokenMode
    ? 'Enter your new password below.'
    : 'Your account is using the default password.<br>Please set a new one before continuing.';
$btnLabel     = $isTokenMode ? 'Reset Password'  : 'Save Password';
$btnSaving    = $isTokenMode ? 'Resetting…' : 'Saving…';
$formAction   = $isTokenMode
    ? 'change_password.php?token=' . htmlspecialchars($token)
    : 'change_password.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — HSN DTR System</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="login.css">

    <!-- Theme bootstrap — applies saved theme before paint (defaults to light) -->
    <script>
        function toggleLoginTheme(isDark) {
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        }
        (function () {
            const isDark = localStorage.getItem('theme') === 'dark';
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            document.addEventListener('DOMContentLoaded', function () {
                const cb = document.getElementById('themeCheckbox');
                if (cb) cb.checked = isDark;
            });
        })();
    </script>
</head>
<body>

    <div class="login-wrapper">

        <!-- Left: image + branding -->
        <div class="login-left">
            <img src="../assets/images/hsn_logo_white.png" alt="HSN Logo" class="logo">
            <div class="login-left-footer">
                <p class="section-title text-light">HSN DTR<br>System</p>
                <p class="tagline-sub">Attendance tracking, simplified.</p>
            </div>
        </div>

        <!-- Right: password card -->
        <div class="card login-card">

            <!-- ── Theme toggle (top-right of card) ── -->
            <div class="login-theme-toggle">
                <input type="checkbox" id="themeCheckbox" hidden onchange="toggleLoginTheme(this.checked)">
                <label class="theme-pill-track" for="themeCheckbox" title="Toggle dark mode">
                    <span class="theme-pill-knob"></span>
                    <i class="bi bi-sun-fill theme-icon-sun"></i>
                    <i class="bi bi-moon-fill theme-icon-moon"></i>
                </label>
            </div>

            <div class="card-body login-right d-flex flex-column justify-content-center">

                <?php if ($isTokenMode && !$tokenValid): ?>

                    <h2 class="text-dark mb-1">Link Expired</h2>
                    <p class="text-secondary mb-4" style="font-size:0.875rem;">
                        This password reset link is invalid or has already expired.
                    </p>
                    <a href="forgot_password.php" class="btn-signin w-100 d-block text-center text-decoration-none mb-3">
                        Request a new link
                    </a>
                    <div class="text-center">
                        <a href="login.php" class="forgot-password text-meta w-100 d-flex align-items-center justify-content-center gap-1 text-decoration-none mt-2">
                            <i class="bi bi-arrow-left-short"></i>
                            <span>Back to Login</span>
                        </a>
                    </div>

                <?php else: ?>

                    <!-- Success state (token mode only) -->
                    <?php if ($isTokenMode): ?>
                    <div id="success-state" class="d-none">
                        <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3" role="alert">
                            <i class="bi bi-check-circle-fill flex-shrink-0"></i>
                            <span class="text-secondary">Your password has been reset successfully!</span>
                        </div>
                        <p class="text-secondary mb-3" style="font-size:0.875rem;">
                            Redirecting to login in <strong><span id="redirect-countdown">10</span>s</strong>…
                        </p>
                        <a href="login.php" class="btn-signin w-100 d-flex align-items-center justify-content-center gap-1 text-decoration-none">
                            <i class="bi bi-arrow-left-short"></i>
                            <span>Back to Login</span>
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Form -->
                    <div id="form-wrapper">

                        <h2 class="text-dark mb-1"><?= $pageTitle ?></h2>
                        <p class="text-secondary mb-4" style="font-size:0.875rem;">
                            <?= $pageSubtitle ?>
                        </p>

                        <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3" role="alert">
                                <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
                                <span class="text-secondary"><?= htmlspecialchars($error) ?></span>
                            </div>
                        <?php endif; ?>

                        <form id="pwd-form" action="<?= $formAction ?>" method="POST" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                            <div class="mb-3">
                                <label for="new-password-input" class="form-label text-secondary">New Password</label>
                                <div class="input-group">
                                    <input
                                        type="password"
                                        name="new_password"
                                        id="new-password-input"
                                        placeholder="Enter new password"
                                        class="form-control <?= $error ? 'is-invalid' : '' ?>"
                                        autocomplete="new-password"
                                    >
                                    <button type="button" class="btn btn-outline-secondary toggle-password" id="toggle-new" aria-label="Toggle visibility">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm-password-input" class="form-label text-secondary">Confirm Password</label>
                                <div class="input-group">
                                    <input
                                        type="password"
                                        name="confirm_password"
                                        id="confirm-password-input"
                                        placeholder="Confirm new password"
                                        class="form-control"
                                        autocomplete="new-password"
                                    >
                                    <button type="button" class="btn btn-outline-secondary toggle-password" id="toggle-confirm" aria-label="Toggle visibility">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" id="submit-btn" class="btn-signin w-100 mb-3"><?= $btnLabel ?></button>

                        </form>

                        <p class="text-center text-meta mb-0">
                            <?php if ($isTokenMode): ?>
                                <a href="login.php" class="no-id">Back to Login</a>
                            <?php else: ?>
                                <a href="logout.php" class="no-id">Log out instead</a>
                            <?php endif; ?>
                        </p>

                    </div><!-- #form-wrapper -->

                <?php endif; ?>

            </div><!-- .card-body -->

            <div class="card-footer login-footer">
                &copy; 2026 &nbsp;|&nbsp; Designed by Edrian R. Evangelista &amp; Earl David A. Jordan
            </div>

        </div><!-- .login-card -->

    </div><!-- .login-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (!$isTokenMode || $tokenValid): ?>
    <script>
        function initToggle(btnId, inputId) {
            document.getElementById(btnId).addEventListener('click', function () {
                const input  = document.getElementById(inputId);
                const hidden = input.type === 'password';
                input.type   = hidden ? 'text' : 'password';
                this.innerHTML = hidden
                    ? '<i class="bi bi-eye-slash-fill"></i>'
                    : '<i class="bi bi-eye-fill"></i>';
            });
        }

        initToggle('toggle-new',     'new-password-input');
        initToggle('toggle-confirm', 'confirm-password-input');

        (function () {
            const form        = document.getElementById('pwd-form');
            const submitBtn   = document.getElementById('submit-btn');
            const formWrapper = document.getElementById('form-wrapper');
            const successState = document.getElementById('success-state');
            const loginLeft   = document.querySelector('.login-left');
            const loginCard   = document.querySelector('.login-card');
            const wrapper     = document.querySelector('.login-wrapper');
            const btnLabel    = <?= json_encode($btnLabel) ?>;
            const btnSaving   = <?= json_encode($btnSaving) ?>;
            const isTokenMode = <?= $isTokenMode ? 'true' : 'false' ?>;

            function showBanner(msg) {
                let banner = document.getElementById('js-error-banner');
                if (!banner) {
                    banner = document.createElement('div');
                    banner.id        = 'js-error-banner';
                    banner.className = 'alert alert-danger d-flex align-items-center gap-2 py-2 mb-3';
                    banner.setAttribute('role', 'alert');
                    form.insertBefore(banner, form.firstChild);
                }
                banner.innerHTML = `<i class="bi bi-exclamation-circle-fill flex-shrink-0"></i><span class="text-secondary">${msg}</span>`;
            }

            function clearBanner() {
                const b = document.getElementById('js-error-banner');
                if (b) b.remove();
            }

            async function playExitAnimation(url) {
                const rect   = loginLeft.getBoundingClientRect();
                const radius = parseFloat(getComputedStyle(wrapper).borderRadius) || 16;

                loginCard.style.transition = 'opacity 0.35s ease';
                loginCard.style.opacity    = '0';

                const overlay = document.createElement('div');
                overlay.style.cssText = `
                    position: fixed;
                    top: ${rect.top}px; left: ${rect.left}px;
                    width: ${rect.width}px; height: ${rect.height}px;
                    background: url('../assets/images/drt_bg.jpg') center / cover no-repeat;
                    z-index: 9998;
                    border-radius: ${radius}px 0 0 ${radius}px;
                    transition: top .55s cubic-bezier(.4,0,.2,1),
                                left .55s cubic-bezier(.4,0,.2,1),
                                width .55s cubic-bezier(.4,0,.2,1),
                                height .55s cubic-bezier(.4,0,.2,1),
                                border-radius .55s ease;
                `;
                document.body.appendChild(overlay);

                await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));

                overlay.style.top          = '0';
                overlay.style.left         = '0';
                overlay.style.width        = '100vw';
                overlay.style.height       = '100vh';
                overlay.style.borderRadius = '0';

                await new Promise(r => setTimeout(r, 650));
                window.location.href = url;
            }

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                clearBanner();

                submitBtn.disabled    = true;
                submitBtn.textContent = btnSaving;

                let data;
                try {
                    const res = await fetch(form.action, {
                        method:  'POST',
                        body:    new FormData(this),
                        headers: { 'X-Change-Pwd-Ajax': '1' },
                    });
                    if (res.status >= 500) {
                        showBanner('Server error. Please try again later.');
                        submitBtn.disabled    = false;
                        submitBtn.textContent = btnLabel;
                        return;
                    }
                    data = await res.json();
                } catch (_) {
                    showBanner('Network error. Please check your connection and try again.');
                    submitBtn.disabled    = false;
                    submitBtn.textContent = btnLabel;
                    return;
                }

                if (data.status === 'success') {
                    if (isTokenMode) {
                        formWrapper.style.transition = 'opacity 0.25s ease';
                        formWrapper.style.opacity    = '0';
                        setTimeout(() => {
                            formWrapper.classList.add('d-none');
                            successState.classList.remove('d-none');
                            successState.classList.add('fade-in-up');

                            let secs = 10;
                            const cd = document.getElementById('redirect-countdown');
                            const timer = setInterval(() => {
                                secs--;
                                cd.textContent = secs;
                                if (secs <= 0) {
                                    clearInterval(timer);
                                    window.location.href = data.redirect;
                                }
                            }, 1000);
                        }, 270);
                    } else {
                        await playExitAnimation(data.redirect);
                    }
                } else {
                    submitBtn.disabled    = false;
                    submitBtn.textContent = btnLabel;
                    showBanner(data.message);
                }
            });
        })();
    </script>
    <?php endif; ?>

</body>
</html>
