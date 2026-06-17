<?php
/* ================================================
   LOGIN PAGE
   Entry point for employee authentication.
   Handles CSRF validation, rate limiting,
   credential verification, and session bootstrap.
   ================================================ */
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../regular_pages/dashboard_page.php");
    exit();
}

require_once '../db.php';

date_default_timezone_set('Asia/Manila');

$pageTitle = "HSN DTR System";

/* ------------------------------------------------
   Constants
   ------------------------------------------------ */
define('LOGIN_MAX_ATTEMPTS',    5);
define('LOGIN_LOCKOUT_SECONDS', 900);

/* ------------------------------------------------
   CSRF Token Bootstrap
   ------------------------------------------------ */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ------------------------------------------------
   Client IP Resolution
   Supports Cloudflare and reverse-proxy setups.
   ------------------------------------------------ */
$clientIp = $_SERVER['HTTP_CF_CONNECTING_IP']
         ?? (isset($_SERVER['HTTP_X_FORWARDED_FOR'])
                ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
                : null)
         ?? $_SERVER['REMOTE_ADDR']
         ?? 'unknown';

$ipKey      = 'login_attempts_' . md5($clientIp);
$lockoutKey = 'login_lockout_'  . md5($clientIp);

/* ------------------------------------------------
   Flash Error Read
   Read session errors before clearing them so the
   HTML template can render them on GET requests.
   ------------------------------------------------ */
$errorEmail    = $_SESSION['error_email']    ?? "";
$errorPassword = $_SESSION['error_password'] ?? "";
$errorGeneral  = $_SESSION['error_general']  ?? "";
$oldEmail      = $_SESSION['old_email']      ?? "";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    unset(
        $_SESSION['error_email'],
        $_SESSION['error_password'],
        $_SESSION['old_email'],
        $_SESSION['error_general']
    );
}

/* ------------------------------------------------
   Lockout Remaining Seconds
   Sent to client as a relative value to avoid
   clock-skew issues between server and browser.
   ------------------------------------------------ */
$lockoutRemaining = 0;
if (!empty($_SESSION[$lockoutKey])) {
    if (time() < $_SESSION[$lockoutKey]) {
        $lockoutRemaining = (int)$_SESSION[$lockoutKey] - time();
    } else {
        unset($_SESSION[$lockoutKey]);
    }
}

/* ================================================
   POST — Authentication Handler
   ================================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $isAjax = !empty($_SERVER['HTTP_X_LOGIN_AJAX']);

    /* ------------------------------------------------
       Unified Error Responder
       JSON for AJAX requests, session flash + redirect
       for standard form submissions.
       ------------------------------------------------ */
    $sendLoginError = function (string $field, string $msg, array $extra = []) use ($isAjax): void {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(array_merge(['status' => 'error', 'field' => $field, 'message' => $msg], $extra));
            exit();
        }
        $key = $field === 'general' ? 'error_general' : 'error_' . $field;
        $_SESSION[$key] = $msg;
        header('Location: login.php');
        exit();
    };

    /* ------------------------------------------------
       Rate Limit Gate
       Must run before CSRF to block brute-force
       requests regardless of token validity.
       ------------------------------------------------ */
    if (!empty($_SESSION[$lockoutKey]) && time() < $_SESSION[$lockoutKey]) {
        $remaining = (int)$_SESSION[$lockoutKey] - time();
        $sendLoginError('general', "Too many failed attempts.", ['lockout_remaining' => $remaining]);
    }

    /* ------------------------------------------------
       CSRF Validation
       ------------------------------------------------ */
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $sendLoginError('general', "Invalid request token. Please refresh the page and try again.");
    }

    /* ------------------------------------------------
       Input Sanitisation
       ------------------------------------------------ */
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (strlen($email) > 255 || strlen($password) > 255) {
        $sendLoginError('general', "Invalid input. Please try again.");
    }

    $_SESSION['old_email'] = $email;

    if (!$email)                                    $sendLoginError('email',    "Email is required.");
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $sendLoginError('email',    "Please enter a valid email address.");
    if (!$password)                                 $sendLoginError('password', "Password is required.");

    /* ------------------------------------------------
       Employee Lookup
       ------------------------------------------------ */
    $stmt = $pdo->prepare("
        SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS name, e.profile_image, e.email, e.password,
               r.role_key AS role, e.department_id, e.is_archived
        FROM employees e
        LEFT JOIN roles r ON r.id = e.role_id
        WHERE e.email = ?
    ");
    $stmt->execute([$email]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee || !empty($employee['is_archived'])) {
        $sendLoginError('email', "No account found with that email address.");
    }

    /* ------------------------------------------------
       Credential Verification
       ------------------------------------------------ */
    if ($password === $employee['password']) {

        session_regenerate_id(true);

        $_SESSION['user_id']       = $employee['id'];
        $_SESSION['profile_image'] = $employee['profile_image'];
        $_SESSION['user_email']    = $employee['email'];
        $_SESSION['user_name']     = $employee['name'];
        $_SESSION['user_role']     = $employee['role'];
        $_SESSION['department_id'] = $employee['department_id'];

        unset(
            $_SESSION['error_email'],
            $_SESSION['error_password'],
            $_SESSION['old_email'],
            $_SESSION['error_general'],
            $_SESSION[$ipKey],
            $_SESSION[$lockoutKey]
        );

        if ($employee['password'] === 'HSN.123') {
            $_SESSION['must_change_password'] = true;
        }

        $redirect = ($employee['password'] === 'HSN.123')
            ? 'change_password.php'
            : '../regular_pages/dashboard_page.php';

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'redirect' => $redirect]);
            exit();
        }

        header("Location: {$redirect}");
        exit();

    } else {

        /* ------------------------------------------------
           Failed Attempt Tracking
           Uses a timestamped window so the counter
           resets after LOGIN_LOCKOUT_SECONDS without
           requiring a manual flush.
           ------------------------------------------------ */
        $now      = time();
        $attempts = $_SESSION[$ipKey] ?? ['count' => 0, 'since' => $now];

        if ($now - $attempts['since'] > LOGIN_LOCKOUT_SECONDS) {
            $attempts = ['count' => 0, 'since' => $now];
        }

        $attempts['count']++;
        $_SESSION[$ipKey] = $attempts;

        if ($attempts['count'] >= LOGIN_MAX_ATTEMPTS) {
            $lockoutTimestamp      = $now + LOGIN_LOCKOUT_SECONDS;
            $_SESSION[$lockoutKey] = $lockoutTimestamp;
            unset($_SESSION[$ipKey]);
            $sendLoginError('general', "Too many failed attempts.", ['lockout_remaining' => LOGIN_LOCKOUT_SECONDS]);
        }

        $remaining = LOGIN_MAX_ATTEMPTS - $attempts['count'];
        $sendLoginError('password', "Incorrect password. {$remaining} attempt(s) remaining.");
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="login.css">

    <link rel="preload" as="video" href="../assets/images/logo_intro.mp4" type="video/mp4">

    <!-- ------------------------------------------------
         Theme bootstrap
         Applies the saved theme before first paint to
         avoid a flash. Defaults to light mode.
         ------------------------------------------------ -->
    <script>
        function toggleLoginTheme(isDark) {
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        }
        (function () {
            const isDark = localStorage.getItem('theme') === 'dark'; // default: light
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            document.addEventListener('DOMContentLoaded', function () {
                const cb = document.getElementById('themeCheckbox');
                if (cb) cb.checked = isDark;
            });
        })();
    </script>
</head>
<body>

    <!-- ── Video intro overlay ── -->
    <div id="login-intro">
        <video id="intro-video" muted playsinline preload="auto">
            <source src="../assets/images/logo_intro.mp4" type="video/mp4">
        </video>
        <button id="intro-skip" type="button">
            Skip <i class="bi bi-skip-forward-fill"></i>
        </button>
    </div>

    <div class="login-wrapper">

        <!-- Left: image + branding -->
        <div class="login-left">
            <img src="../assets/images/hsn_logo_white.png" alt="HSN Logo" class="logo">
            <div class="login-left-footer">
                <!-- Convention §4: use typography classes, not fw-bold / small utilities -->
                <p class="section-title text-light">HSN DTR<br>System</p>
                <p class="tagline-sub">Attendance tracking, simplified.</p>
            </div>
        </div>

        <!-- Right: login card -->
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

                <h2 class="text-dark mb-1">Sign In</h2>
                <p class="text-secondary mb-4">Welcome back! Enter your credentials to continue.</p>

                <?php if (isset($_GET['timeout'])): ?>
                    <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3" role="alert">
                        <i class="bi bi-clock-history flex-shrink-0"></i>
                        <span>Your session expired due to inactivity. Please sign in again.</span>
                    </div>
                <?php endif; ?>

                <?php if ($errorGeneral): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3" role="alert">
                        <i class="bi bi-shield-exclamation-fill flex-shrink-0"></i>
                        <span class="text-secondary"><?= htmlspecialchars($errorGeneral) ?></span>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" id="login-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="mb-3">
                        <label for="email-input" class="form-label text-secondary">Email</label>
                        <div class="input-group">
                            <input
                                type="email"
                                name="email"
                                id="email-input"
                                placeholder="Enter your email"
                                autocomplete="username"
                                class="form-control <?= $errorEmail ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($oldEmail) ?>"
                            >
                        </div>
                        <?php if ($errorEmail): ?>
                            <div class="invalid-feedback d-block">
                                <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errorEmail) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="password-input" class="form-label text-secondary">Password</label>
                        <div class="input-group">
                            <input
                                type="password"
                                name="password"
                                id="password-input"
                                placeholder="Enter your password"
                                class="form-control <?= $errorPassword ? 'is-invalid' : '' ?>"
                            >
                            <button type="button" class="btn btn-outline-secondary toggle-password" id="toggle-password" aria-label="Toggle password visibility">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </div>
                        <?php if ($errorPassword): ?>
                            <div class="invalid-feedback d-block">
                                <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errorPassword) ?>
                            </div>
                        <?php endif; ?>

                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <!-- Convention §3: HTML IDs must be kebab-case -->
                            <input class="form-check-input" type="checkbox" id="remember-me">
                            <label class="form-check-label text-meta" for="remember-me">Remember me</label>
                        </div>
                        <a href="https://www.hsnservice.com/" target="_blank" class="forgot-password text-meta">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="btn-signin w-100 mt-4 mb-3">Sign In</button>

                </form>

                <p class="text-center text-meta mb-0">
                    Don't have an HSN ID?
                    <a href="mailto:Service.Hsnc@hsnservice.com" target="_blank" class="no-id">Contact your HR admin</a>
                </p>

            </div><!-- .card-body -->

            <div class="card-footer login-footer">
                &copy; 2026 &nbsp;|&nbsp; Designed by Edrian R. Evangelista &amp; Earl David A. Jordan
            </div>

        </div><!-- .login-card -->

    </div><!-- .login-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Pass lockout remaining seconds to JS as a relative value (avoids client clock skew) -->
    <script>const LOCKOUT_REMAINING = <?= (int)$lockoutRemaining ?>;</script>

    <script>
        /* ------------------------------------------------
           Video Intro
           Plays once per browser session, skippable.
           ------------------------------------------------ */
        (function () {
            const intro = document.getElementById('login-intro');
            if (sessionStorage.getItem('intro_seen')) { intro.remove(); return; }

            document.body.classList.add('intro-active');

            const video = document.getElementById('intro-video');
            const skip  = document.getElementById('intro-skip');

            function endIntro() {
                sessionStorage.setItem('intro_seen', '1');
                if (skip) skip.style.display = 'none';
                document.body.classList.remove('intro-active');
                if (!video.classList.contains('ready')) { intro.remove(); return; }
                video.classList.remove('ready');
                video.addEventListener('transitionend', function () { intro.remove(); }, { once: true });
            }

            let started = false;
            function startVideo() {
                if (started) return;
                started = true;
                video.classList.add('ready');
                video.play().catch(endIntro);
            }

            if (video) {
                video.addEventListener('canplaythrough', startVideo, { once: true });
                if (video.readyState >= 4) startVideo();
                video.addEventListener('ended', endIntro);
                video.addEventListener('error', function () { setTimeout(endIntro, 300); });
            }
            if (skip) skip.addEventListener('click', endIntro);
        })();

        /* ------------------------------------------------
           Sign In Form
           Client-side validation, AJAX submission,
           lockout countdown, and exit animation.
           ------------------------------------------------ */
        (function () {
            const form       = document.getElementById('login-form');
            const signinBtn  = form.querySelector('.btn-signin');
            const emailInput = document.getElementById('email-input');
            const passInput  = document.getElementById('password-input');
            const loginLeft  = document.querySelector('.login-left');
            const loginCard  = document.querySelector('.login-card');
            const wrapper    = document.querySelector('.login-wrapper');

            function showFieldError(input, msg) {
                input.classList.add('is-invalid');
                const mb3 = input.closest('.mb-3');
                let fb = mb3.querySelector('.invalid-feedback');
                if (!fb) {
                    fb = document.createElement('div');
                    fb.className = 'invalid-feedback d-block';
                    mb3.appendChild(fb);
                }
                fb.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>${msg}`;
            }

            function clearFieldError(input) {
                input.classList.remove('is-invalid');
                const mb3 = input.closest('.mb-3');
                const fb = mb3.querySelector('.invalid-feedback');
                if (fb) fb.remove();
            }

            function showBanner(msg) {
                let banner = document.getElementById('js-error-banner');
                if (!banner) {
                    banner = document.createElement('div');
                    banner.id        = 'js-error-banner';
                    banner.className = 'alert alert-danger d-flex align-items-center gap-1 py-2 mb-3';
                    banner.setAttribute('role', 'alert');
                    form.insertBefore(banner, form.firstChild);
                }
                banner.innerHTML = `<i class="bi bi-shield-exclamation-fill flex-shrink-0"></i>${msg}`;
            }

            function clearBanner() {
                const banner = document.getElementById('js-error-banner');
                if (banner) banner.remove();
            }

            /* ------------------------------------------------
               Lockout Countdown
               Counts down from a relative seconds value so
               client and server clock differences don't cause
               premature or delayed expiry.
               ------------------------------------------------ */
            let countdownTimer = null;

            function startLockoutCountdown(remainingSeconds) {
                signinBtn.disabled = true;
                if (countdownTimer) clearInterval(countdownTimer);

                let secondsLeft = remainingSeconds;

                function tick() {
                    if (secondsLeft <= 0) {
                        clearInterval(countdownTimer);
                        signinBtn.disabled    = false;
                        signinBtn.textContent = 'Sign In';
                        clearBanner();
                        return;
                    }
                    const minutes = Math.floor(secondsLeft / 60);
                    const seconds = secondsLeft % 60;
                    const label   = minutes > 0
                        ? `${minutes}m ${String(seconds).padStart(2, '0')}s`
                        : `${seconds}s`;
                    showBanner(`Too many failed attempts. Try again in <strong>${label}</strong>.`);
                    secondsLeft--;
                }

                tick();
                countdownTimer = setInterval(tick, 1000);
            }

            if (LOCKOUT_REMAINING > 0) startLockoutCountdown(LOCKOUT_REMAINING);

            emailInput.addEventListener('input', function () { clearFieldError(emailInput); });
            passInput.addEventListener('input',  function () { clearFieldError(passInput); });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                clearBanner();
                clearFieldError(emailInput);
                clearFieldError(passInput);

                /* Client-side validation */
                let valid      = true;
                const emailVal = emailInput.value.trim();
                const passVal  = passInput.value;

                if (!emailVal) {
                    showFieldError(emailInput, 'Email is required.');
                    valid = false;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
                    showFieldError(emailInput, 'Please enter a valid email address.');
                    valid = false;
                }

                if (!passVal) {
                    showFieldError(passInput, 'Password is required.');
                    valid = false;
                }

                if (!valid) return;

                signinBtn.disabled    = true;
                signinBtn.textContent = 'Signing in…';

                let data;
                try {
                    const res = await fetch('login.php', {
                        method:  'POST',
                        body:    new FormData(this),
                        headers: { 'X-Login-Ajax': '1' },
                    });
                    if (res.status >= 500) {
                        showBanner('Server error. Please try again later.');
                        signinBtn.disabled    = false;
                        signinBtn.textContent = 'Sign In';
                        return;
                    }
                    data = await res.json();
                } catch (_) {
                    showBanner('Network error. Please check your connection and try again.');
                    signinBtn.disabled    = false;
                    signinBtn.textContent = 'Sign In';
                    return;
                }

                if (data.status === 'success') {
                    await playExitAnimation(data.redirect);
                } else {
                    signinBtn.disabled    = false;
                    signinBtn.textContent = 'Sign In';
                    if      (data.field === 'email')    showFieldError(emailInput, data.message);
                    else if (data.field === 'password') showFieldError(passInput,  data.message);
                    else if (data.lockout_remaining)    startLockoutCountdown(data.lockout_remaining);
                    else                                showBanner(data.message);
                }
            });

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
        })();

        /* ------------------------------------------------
           Password Visibility Toggle
           ------------------------------------------------ */
        (function () {
            const toggleBtn     = document.getElementById('toggle-password');
            const passwordInput = document.getElementById('password-input');

            toggleBtn.addEventListener('click', function () {
                const isHidden      = passwordInput.type === 'password';
                passwordInput.type  = isHidden ? 'text' : 'password';
                toggleBtn.innerHTML = isHidden
                    ? '<i class="bi bi-eye-slash-fill"></i>'
                    : '<i class="bi bi-eye-fill"></i>';
            });
        })();
    </script>
</body>
</html>