<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../regular_pages/dashboard_page.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../db.php';
require_once '../send_mail.php';

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $isAjax = !empty($_SERVER['HTTP_X_FORGOT_PWD_AJAX']);

    $sendJSON = function (string $status, string $msg, string $redirect = '') use ($isAjax): void {
        if ($isAjax) {
            header('Content-Type: application/json');
            $payload = ['status' => $status, 'message' => $msg];
            if ($redirect) $payload['redirect'] = $redirect;
            echo json_encode($payload);
            exit();
        }
    };

    // CSRF check
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error = "Invalid request token. Please refresh the page and try again.";
        $sendJSON('error', $error);
    } else {
        $email = trim($_POST['email'] ?? '');

        if (!$email) {
            $error = "Email address is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Look up employee by email
            $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Generate a secure token
                $token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Invalidate any existing tokens for this user
                $pdo->prepare("DELETE FROM password_resets WHERE employee_id = ?")
                    ->execute([$user['id']]);

                // Insert new token
                $pdo->prepare("INSERT INTO password_resets (employee_id, token, expires_at) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $token, $expiresAt]);

                // Build reset link
                $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                    . '://' . $_SERVER['HTTP_HOST']
                    . dirname($_SERVER['SCRIPT_NAME'])
                    . '/change_password.php?token=' . $token;

                $fullName = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                $subject  = "HSN DTR System Password Reset Request";
                $body     = "
                    <p>Hi {$fullName},</p>
                    <p>We received a request to reset your password.</p>
                    <p><a href='{$resetLink}'>Click here to reset your password</a> (valid for 1 hour).</p>
                    <p>If you did not request this, you can safely ignore this email.</p>
                    <p>— HSN DTR System</p>
                ";

                sendMail($email, $fullName, $subject, $body);
            }

            // Always return success — don't reveal whether email exists
            $success = true;
            $sendJSON('success', 'If that email is registered, a reset link has been sent.');
        }

        if ($error) $sendJSON('error', $error);
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — HSN DTR System</title>

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

        <!-- Right: forgot password card -->
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

                <h2 class="text-dark mb-1">Forgot Password</h2>
                <p class="text-secondary mb-4" style="font-size:0.875rem;">
                    Enter your email address and we'll send you a reset link.
                </p>

                <!-- Success state (shown after submit) -->
                <div id="success-message" class="d-none">
                    <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3" role="alert">
                        <i class="bi bi-check-circle-fill flex-shrink-0"></i>
                        <span class="text-secondary">If that email is registered, a reset link has been sent. Check your inbox.</span>
                    </div>
                    <a href="login.php" class="btn-signin w-100 d-flex align-items-center justify-content-center gap-1 text-decoration-none">
                        <i class="bi bi-arrow-left-short"></i>
                        <span>Back to Login</span>
                    </a>

                    <p class="text-center text-meta mb-0 mt-3" style="font-size:0.875rem;">
                        Didn't receive it?
                        <button id="resend-btn" type="button" class="btn btn-link p-0 text-meta" style="font-size:0.875rem;vertical-align:baseline;" disabled>
                            Resend in <span id="resend-countdown">60</span>s
                        </button>
                    </p>
                </div>

                <!-- Form (hidden after submit) -->
                <div id="form-wrapper">

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3" role="alert">
                            <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
                            <span class="text-secondary"><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form id="forgot-form" action="forgot_password.php" method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <div class="mb-3">
                            <label for="email-input" class="form-label text-secondary">Email Address</label>
                            <div class="input-group">
                                <input
                                    type="email"
                                    name="email"
                                    id="email-input"
                                    placeholder="Enter your email address"
                                    class="form-control <?= $error ? 'is-invalid' : '' ?>"
                                    autocomplete="email"
                                    required
                                >
                            </div>
                        </div>

                        <button type="submit" id="submit-btn" class="btn-signin w-100 mb-3">Send Reset Link</button>
                        <a href="login.php" class="forgot-password text-meta w-100 d-flex align-items-center justify-content-center gap-1 text-decoration-none mt-2">
                            <i class="bi bi-arrow-left-short"></i>
                            <span>Back to Login</span>
                        </a>

                    </form>
                </div>

            </div><!-- .card-body -->

            <div class="card-footer login-footer">
                &copy; 2026 &nbsp;|&nbsp; Designed by Edrian R. Evangelista &amp; Earl David A. Jordan
            </div>

        </div><!-- .login-card -->

    </div><!-- .login-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        (function () {
            const form        = document.getElementById('forgot-form');
            const submitBtn   = document.getElementById('submit-btn');
            const formWrapper = document.getElementById('form-wrapper');
            const successMsg  = document.getElementById('success-message');
            const resendBtn   = document.getElementById('resend-btn');

            let lastEmail      = '';
            let countdownTimer = null;

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
                const banner = document.getElementById('js-error-banner');
                if (banner) banner.remove();
            }

            function startCountdown() {
                clearInterval(countdownTimer);
                resendBtn.disabled = true;
                let secs = 60;
                resendBtn.innerHTML = `Resend in <span id="resend-countdown">${secs}</span>s`;

                countdownTimer = setInterval(() => {
                    secs--;
                    const cd = document.getElementById('resend-countdown');
                    if (secs > 0) {
                        cd.textContent = secs;
                    } else {
                        clearInterval(countdownTimer);
                        resendBtn.disabled   = false;
                        resendBtn.textContent = 'Resend';
                    }
                }, 1000);
            }

            async function sendRequest(email) {
                const fd = new FormData();
                fd.append('email', email);
                fd.append('csrf_token', document.querySelector('[name="csrf_token"]').value);

                const res = await fetch('forgot_password.php', {
                    method:  'POST',
                    body:    fd,
                    headers: { 'X-Forgot-Pwd-Ajax': '1' },
                });
                return res.json();
            }

            resendBtn.addEventListener('click', async function () {
                resendBtn.disabled    = true;
                resendBtn.textContent = 'Sending…';
                try { await sendRequest(lastEmail); } catch (_) {}
                startCountdown();
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                clearBanner();

                submitBtn.disabled    = true;
                submitBtn.textContent = 'Sending...';

                let data;
                try {
                    const res = await fetch('forgot_password.php', {
                        method:  'POST',
                        body:    new FormData(this),
                        headers: { 'X-Forgot-Pwd-Ajax': '1' },
                    });
                    if (res.status >= 500) {
                        showBanner('Server error. Please try again later.');
                        submitBtn.disabled    = false;
                        submitBtn.textContent = 'Send Reset Link';
                        return;
                    }
                    data = await res.json();
                } catch (_) {
                    showBanner('Network error. Please check your connection and try again.');
                    submitBtn.disabled    = false;
                    submitBtn.textContent = 'Send Reset Link';
                    return;
                }

                if (data.status === 'success') {
                    lastEmail = document.getElementById('email-input').value;
                    formWrapper.style.transition = 'opacity 0.25s ease';
                    formWrapper.style.opacity    = '0';
                    setTimeout(() => {
                        formWrapper.classList.add('d-none');
                        successMsg.classList.remove('d-none');
                        successMsg.classList.add('fade-in-up');
                        startCountdown();
                    }, 270);
                } else {
                    submitBtn.disabled    = false;
                    submitBtn.textContent = 'Send Reset Link';
                    showBanner(data.message);
                }
            });
        })();
    </script>
</body>
</html>