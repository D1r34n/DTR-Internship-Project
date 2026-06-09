<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['must_change_password'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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

require_once '../db.php';

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $isAjax = !empty($_SERVER['HTTP_X_CHANGE_PWD_AJAX']);

    $sendError = function (string $msg) use ($isAjax): void {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $msg]);
            exit();
        }
    };

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error = "Invalid request token. Please refresh the page and try again.";
        $sendError($error);
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
                ->execute([$newPassword, $_SESSION['user_id']]);

            unset($_SESSION['must_change_password'], $_SESSION['change_password_shown']);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'redirect' => '../regular_pages/dashboard_page.php']);
                exit();
            }

            header("Location: ../regular_pages/dashboard_page.php");
            exit();
        }

        if ($error) $sendError($error);
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password — HSN DTR System</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="login.css">
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

        <!-- Right: change password card -->
        <div class="card login-card">

            <div class="card-body login-right d-flex flex-column justify-content-center">

                <h2 class="text-dark mb-1">Change Password</h2>
                <p class="text-secondary mb-4" style="font-size:0.875rem;">
                    Your account is using the default password.<br>
                    Please set a new one before continuing.
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3" role="alert">
                        <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
                        <span class="text-secondary"><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="change_password.php" method="POST" novalidate>
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

                    <button type="submit" class="btn-signin w-100 mb-3">Save Password</button>

                </form>

                <p class="text-center text-meta mb-0">
                    <a href="logout.php" class="no-id">Log out instead</a>
                </p>

            </div><!-- .card-body -->

            <div class="card-footer login-footer">
                &copy; 2026 &nbsp;|&nbsp; Designed by Edrian R. Evangelista &amp; Earl David A. Jordan
            </div>

        </div><!-- .login-card -->

    </div><!-- .login-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        /* ------------------------------------------------
           Password Visibility Toggles
           ------------------------------------------------ */
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

        /* ------------------------------------------------
           Form Submission + Exit Animation
           ------------------------------------------------ */
        (function () {
            const form      = document.querySelector('form');
            const saveBtn   = form.querySelector('.btn-signin');
            const loginLeft = document.querySelector('.login-left');
            const loginCard = document.querySelector('.login-card');
            const wrapper   = document.querySelector('.login-wrapper');

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

                saveBtn.disabled    = true;
                saveBtn.textContent = 'Saving…';

                let data;
                try {
                    const res = await fetch('change_password.php', {
                        method:  'POST',
                        body:    new FormData(this),
                        headers: { 'X-Change-Pwd-Ajax': '1' },
                    });
                    if (res.status >= 500) {
                        showBanner('Server error. Please try again later.');
                        saveBtn.disabled    = false;
                        saveBtn.textContent = 'Save Password';
                        return;
                    }
                    data = await res.json();
                } catch (_) {
                    showBanner('Network error. Please check your connection and try again.');
                    saveBtn.disabled    = false;
                    saveBtn.textContent = 'Save Password';
                    return;
                }

                if (data.status === 'success') {
                    await playExitAnimation(data.redirect);
                } else {
                    saveBtn.disabled    = false;
                    saveBtn.textContent = 'Save Password';
                    showBanner(data.message);
                }
            });
        })();
    </script>
</body>
</html>
