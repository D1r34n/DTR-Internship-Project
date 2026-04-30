<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

$employeeId = $_SESSION['user_id'];
$role       = $_SESSION['user_role'];

// Validate role
if (!in_array($role, ['admin', 'employee', 'workforce'])) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// Page titles
$titles = [
    'employee' => [
        'dashboard' => 'Employee Dashboard',
        'records'   => 'Employee Records',
        'schedule'  => 'Employee Schedule',
        'logs'      => 'Employee Activity Logs',
    ],
    'workforce' => [
        'dashboard'          => 'Employee Dashboard',
        'records'            => 'Employee Records',
        'schedule'           => 'Employee Schedule',
        'logs'               => 'Employee Activity Logs',
        'workforce_schedule' => 'Manage Schedules',
    ],
    'admin' => [
        'dashboard'         => 'Admin Dashboard',
        'employees'         => 'Employees',
        'schedule'          => 'Schedules',
        'employee_requests' => 'Employee Requests',
        'schedule_requests' => 'Schedule Requests',
        'logs'              => 'Logs',
        'employee_logs'     => 'Employee Logs',
        'departments'       => 'Departments',
    ]
];

// Set current page title
$currentPage = $currentPage ?? 'dashboard';
$title       = $titles[$role][$currentPage] ?? 'Dashboard';

// Set date timezone (Philippines)
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// Get last log entry — also check for active break
$stmt = $pdo->prepare("
    SELECT log_type
    FROM logs
    WHERE employee_id = ?
    ORDER BY log_time DESC
    LIMIT 1
");
$stmt->execute([$employeeId]);
$lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

$hasTimeIn  = $lastLog && $lastLog['log_type'] === 'IN';
$isOnBreak  = $lastLog && $lastLog['log_type'] === 'BREAK_IN';
$isBreakOut = $lastLog && $lastLog['log_type'] === 'BREAK_OUT';
$timedIn    = $hasTimeIn || $isOnBreak || $isBreakOut;

$currentStatus = $timedIn ? 'Timed In' : 'Timed Out';
?>

<!-- Shared CSS — loaded once via topbar for all pages -->
<link rel="stylesheet" href="../assets/css/root.css">
<link rel="stylesheet" href="../assets/css/typography.css">
<link rel="stylesheet" href="../assets/css/components.css">
<link rel="stylesheet" href="../navbars.css">

<!-- Topbar external libs -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Modal CSS -->
<link rel="stylesheet" href="../dropdown_requests/ot_modal.css">
<link rel="stylesheet" href="../dropdown_requests/leave_modal.css">
<link rel="stylesheet" href="../dropdown_requests/ob_modal.css">
<link rel="stylesheet" href="../dropdown_requests/log_edit_modal.css">

<!-- TOP BAR -->
<div class="top-bar">
    <h4 class="dashboard-title section-title"><?= htmlspecialchars($title) ?></h4>

    <div class="top-bar-right">

        <!-- TIME IN / OUT BUTTON GROUP -->
        <div class="time-in-wrapper" id="time-in-wrapper">

            <button
                class="time-in-button <?= $timedIn ? 'btn-out' : 'btn-in' ?>"
                id="time-in-btn"
                onclick="handleTimeIn()"
            >
                <i class="bi bi-stopwatch-fill time-in-icon"></i>
                <span id="time-in-label">
                    <?= $timedIn ? 'Time Out' : 'Time In' ?>
                </span>
            </button>

            <?php if ($timedIn): ?>
            <div class="break-menu" id="break-menu">
                <div class="break-menu-section">
                    <button
                        class="break-menu-item <?= $isOnBreak ? 'break-out-btn' : 'break-in-btn' ?>"
                        id="break-action-btn"
                        onclick="handleBreak()"
                    >
                        <?php if ($isOnBreak): ?>
                            <i class="bi bi-play-fill"></i>
                            <span id="break-label">Break Out</span>
                        <?php else: ?>
                            <i class="bi bi-pause-fill"></i>
                            <span id="break-label">Break In</span>
                        <?php endif; ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <div class="vertical-divider"></div>

        <!-- USER DROPDOWN (BOOTSTRAP VERSION) -->
        <div class="dropdown">
            <a
                class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle user-dropdown-toggle"
                href="#"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <i class="bi bi-person-fill"></i>

                <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'User') ?>

                <i class="bi bi-chevron-down"></i>
            </a>

            <ul class="dropdown-menu dropdown-menu-end glass-dropdown">

                <li>
                    <a class="dropdown-item" href="#" onclick="openOTModal(); return false;">
                        <i class="bi bi-clock-history me-2"></i> Request OT
                    </a>
                </li>

                <li>
                    <a class="dropdown-item" href="#" onclick="openLeaveModal(); return false;">
                        <i class="bi bi-calendar-x me-2"></i> Request Leave
                    </a>
                </li>

                <li>
                    <a class="dropdown-item" href="#" onclick="openOBModal(); return false;">
                        <i class="bi bi-briefcase me-2"></i> Request OB
                    </a>
                </li>

                <li>
                    <a class="dropdown-item" href="#" onclick="openLogEditModal(); return false;">
                        <i class="bi bi-pencil-square me-2"></i> Request Log Edit
                    </a>
                </li>

                <li><hr class="dropdown-divider"></li>

                <li>
                    <a class="dropdown-item text-danger" href="../authentication_pages/logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                    </a>
                </li>

            </ul>
        </div>

    </div>
</div>

<!-- MODALS -->
<?php include '../dropdown_requests/ot_modal.php'; ?>
<?php include '../dropdown_requests/leave_modal.php'; ?>
<?php include '../dropdown_requests/ob_modal.php'; ?>
<?php include '../dropdown_requests/log_edit_modal.php'; ?>

<!-- JAVASCRIPT -->
<script defer>
    document.addEventListener('DOMContentLoaded', () => {

        // Sync time-in state across tabs
        window.addEventListener('storage', (event) => {
            if (event.key !== 'attendance_update') return;

            const tap   = localStorage.getItem('attendance_tap_result');
            const btn   = document.getElementById('time-in-btn');
            const label = document.getElementById('time-in-label');

            if (tap === 'timed_in') {
                btn.classList.remove('btn-in');
                btn.classList.add('btn-out');
                label.textContent = 'Time Out';
            } else if (tap === 'timed_out') {
                btn.classList.remove('btn-out');
                btn.classList.add('btn-in');
                label.textContent = 'Time In';
            }
        });

        // ===== BREAK MENU HOVER =====
        const timeInWrapper = document.getElementById('time-in-wrapper');
        const breakMenu     = document.getElementById('break-menu');

        if (timeInWrapper && breakMenu) {
            let breakMenuTimeout = null;

            const showBreakMenu = () => {
                const btn       = document.getElementById('time-in-btn');
                const breakMenu = document.getElementById('break-menu');
                if (!btn || !breakMenu) return;
                if (!btn.classList.contains('btn-out')) return;
                clearTimeout(breakMenuTimeout);
                breakMenu.classList.add('show');
            };

            const hideBreakMenu = () => {
                breakMenuTimeout = setTimeout(() => {
                    breakMenu.classList.remove('show');
                }, 150);
            };

            timeInWrapper.addEventListener('mouseenter', showBreakMenu);
            timeInWrapper.addEventListener('mouseleave', hideBreakMenu);
            breakMenu.addEventListener('mouseenter', () => clearTimeout(breakMenuTimeout));
            breakMenu.addEventListener('mouseleave', hideBreakMenu);
        }

    });

    // ===== COOLDOWN =====
    const startCooldown = (btn, seconds, originalText) => {
        const label = btn.querySelector('#time-in-label');

        btn.disabled = true;
        btn.classList.add('btn-cooldown');

        const fill = document.createElement('div');
        fill.classList.add('btn-cooldown-fill');
        fill.style.transition = `width ${seconds}s linear`;
        btn.appendChild(fill);

        label.style.position = 'relative';
        label.style.zIndex   = '1';

        let remaining = seconds;

        label.innerHTML = `
            <span class="btn-cooldown-label">
                <span class="btn-spinner"></span>
                <span class="cooldown-text">(${remaining}s)</span>
            </span>
        `;

        const interval = setInterval(() => {
            remaining--;
            const countEl = btn.querySelector('.cooldown-text');
            if (remaining > 0 && countEl) {
                countEl.textContent = `(${remaining}s)`;
            }
        }, 1000);

        requestAnimationFrame(() => {
            requestAnimationFrame(() => { fill.style.width = '100%'; });
        });

        setTimeout(() => {
            clearInterval(interval);
            if (btn.contains(fill)) btn.removeChild(fill);

            label.innerHTML      = originalText;
            label.style.position = '';
            label.style.zIndex   = '';

            btn.classList.remove('btn-cooldown');
            btn.disabled  = false;
            isProcessing  = false;
        }, seconds * 1000);
    };

    // ===== SPINNER =====
    const showSpinner = (btn) => {
        const label = btn.querySelector('#time-in-label');
        btn.classList.add('btn-loading');
        label.innerHTML = `<span class="btn-spinner btn-spinner--lg"></span>`;
    };

    // ===== PRE-CACHE GPS ON PAGE LOAD =====
    let cachedPosition = null;

    navigator.geolocation.watchPosition(
        (pos) => { cachedPosition = pos; },
        (err) => { console.warn('GPS watch error:', err); },
        { enableHighAccuracy: false, maximumAge: 60000, timeout: 10000 }
    );

    // ===== TIME IN / OUT =====
    let isProcessing = false;

    const handleTimeIn = async () => {
        if (isProcessing) return;
        if (document.getElementById('time-in-btn').disabled) return;

        const btn          = document.getElementById('time-in-btn');
        const label        = btn.querySelector('#time-in-label');
        const status       = document.getElementById('dashboard-status');
        const originalText = label.textContent;

        isProcessing = true;
        btn.disabled = true;

        showSpinner(btn);

        const submitTap = async (pos) => {
            try {
                const res = await fetch('../system_functions/attendance_tap.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({
                        lat:      pos.coords.latitude,
                        lng:      pos.coords.longitude,
                        accuracy: pos.coords.accuracy
                    })
                });

                const response = await res.json();
                console.log(response);

                // Clicked too fast
                if (response.error === 'too_fast') {
                    const wait = response.seconds_remaining || 5;
                    startCooldown(btn, wait, originalText);
                    isProcessing = false;
                    return;
                }

                // Log corrupted
                if (response.error === 'log_corrupted') {
                    btn.disabled    = false;
                    btn.classList.remove('btn-loading');
                    label.innerHTML = originalText;
                    isProcessing    = false;

                    if (!document.getElementById('log-corruption-warning')) {
                        const warning = document.createElement('div');
                        warning.id    = 'log-corruption-warning';
                        warning.classList.add('corruption-warning');
                        warning.innerHTML = `
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>Attendance log corrupted. Please contact your administrator to fix your records.</span>
                        `;
                        document.body.appendChild(warning);
                        setTimeout(() => warning.remove(), 6000);
                    }

                    console.warn('Log corruption detected:', response);
                    return;
                }

                // Shift has already ended
                if (response.error === 'shift_ended') {
                    btn.disabled    = false;
                    btn.classList.remove('btn-loading');
                    label.innerHTML = originalText;
                    isProcessing    = false;
                    alert('Your shift has already ended. You have been marked absent.');
                    return;
                }

                btn.disabled = false;
                btn.classList.remove('btn-loading');

                let tapSuccess = false;

                if (response.tap === 'timed_in') {
                    btn.classList.remove('btn-in');
                    btn.classList.add('btn-out');
                    label.innerHTML = 'Time Out';
                    if (status) status.textContent = 'Timed In';
                    tapSuccess = true;

                    // Inject break menu if it doesn't exist yet
                    if (!document.getElementById('break-menu')) {
                        const breakMenuEl     = document.createElement('div');
                        breakMenuEl.className = 'break-menu';
                        breakMenuEl.id        = 'break-menu';
                        breakMenuEl.innerHTML = `
                            <div class="break-menu-section">
                                <button class="break-menu-item break-in-btn" id="break-action-btn" onclick="handleBreak()">
                                    <i class="bi bi-pause-fill"></i>
                                    <span id="break-label">Break In</span>
                                </button>
                            </div>
                        `;
                        document.getElementById('time-in-wrapper').appendChild(breakMenuEl);

                        const breakMenu     = breakMenuEl;
                        const timeInWrapper = document.getElementById('time-in-wrapper');
                        let breakMenuTimeout = null;

                        const showBreakMenu = () => {
                            const btn = document.getElementById('time-in-btn');
                            if (!btn || !btn.classList.contains('btn-out')) return;
                            clearTimeout(breakMenuTimeout);
                            breakMenu.classList.add('show');
                        };

                        const hideBreakMenu = () => {
                            breakMenuTimeout = setTimeout(() => breakMenu.classList.remove('show'), 150);
                        };

                        timeInWrapper.addEventListener('mouseenter', showBreakMenu);
                        timeInWrapper.addEventListener('mouseleave', hideBreakMenu);
                        breakMenu.addEventListener('mouseenter', () => clearTimeout(breakMenuTimeout));
                        breakMenu.addEventListener('mouseleave', hideBreakMenu);
                        breakMenu.classList.add('show');
                    }
                }

                if (response.tap === 'timed_out') {
                    btn.classList.remove('btn-out');
                    btn.classList.add('btn-in');
                    label.innerHTML = 'Time In';
                    if (status) status.textContent = 'Timed Out';
                    tapSuccess = true;

                    const breakMenu = document.getElementById('break-menu');
                    if (breakMenu) breakMenu.remove();
                }

                if (tapSuccess) {
                    localStorage.setItem('attendance_tap_result', response.tap);
                    localStorage.setItem('attendance_update', Date.now());
                    if (document.getElementById('attendance-timeline')) refreshChart();
                }

                if (typeof getTotalWorkedHours === 'function') getTotalWorkedHours();
                if (document.getElementById('logs-table-body')) fetchLogs();
                if (document.getElementById('attendance-table-body')) loadAttendance();

            } catch (err) {
                console.error(err);
            } finally {
                isProcessing = false;
                if (document.querySelector('.gantt-container')) refreshGantt();
            }
        };

        const onError = (err) => {
            console.error(err);
            alert('Location permission is required.');
            isProcessing    = false;
            btn.disabled    = false;
            btn.classList.remove('btn-loading');
            label.innerHTML = originalText;
        };

        if (cachedPosition) {
            await submitTap(cachedPosition);
        } else {
            navigator.geolocation.getCurrentPosition(
                async (pos) => await submitTap(pos),
                onError,
                { enableHighAccuracy: false, maximumAge: 60000, timeout: 10000 }
            );
        }
    };

    // ===== BREAK IN / OUT =====
    let isBreakProcessing = false;

    const handleBreak = async () => {
        if (isBreakProcessing) return;

        const btn           = document.getElementById('break-action-btn');
        const originalClass = btn.className;
        const isBreakOut    = originalClass.includes('break-out-btn');

        isBreakProcessing = true;

        const breakLabel     = btn.querySelector('#break-label');
        breakLabel.innerHTML = `<span class="btn-spinner btn-spinner--sm"></span>`;

        const setBreakBtn = (isOut) => {
            btn.querySelector('i').className              = isOut ? 'bi bi-play-fill' : 'bi bi-pause-fill';
            btn.querySelector('#break-label').textContent = isOut ? 'Break Out' : 'Break In';
            btn.className = `break-menu-item ${isOut ? 'break-out-btn' : 'break-in-btn'}`;
        };

        const restoreBreakBtn = () => setBreakBtn(isBreakOut);

        const submitBreak = async (pos) => {
            try {
                const res = await fetch('../system_functions/attendance_tap.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({
                        lat:       pos.coords.latitude,
                        lng:       pos.coords.longitude,
                        accuracy:  pos.coords.accuracy,
                        break_tap: true,
                    })
                });

                const response = await res.json();
                console.log(response);

                // Clicked too fast
                if (response.error === 'too_fast') {
                    const wait = response.seconds_remaining || 5;
                    let remaining = wait;

                    btn.className = originalClass;
                    btn.querySelector('i').className = isBreakOut ? 'bi bi-play-fill' : 'bi bi-pause-fill';
                    btn.querySelector('#break-label').innerHTML = `
                        <span class="break-cooldown-label">
                            <span class="btn-spinner btn-spinner--xs"></span>
                            <span id="break-cooldown-text">(${remaining}s)</span>
                        </span>
                    `;
                    btn.disabled = true;
                    btn.classList.add('btn-disabled');

                    const interval = setInterval(() => {
                        remaining--;
                        const el = document.getElementById('break-cooldown-text');
                        if (remaining > 0 && el) {
                            el.textContent = `(${remaining}s)`;
                        } else {
                            clearInterval(interval);
                            btn.querySelector('#break-label').textContent = isBreakOut ? 'Break Out' : 'Break In';
                            btn.disabled = false;
                            btn.classList.remove('btn-disabled');
                        }
                    }, 1000);

                    isBreakProcessing = false;
                    return;
                }

                btn.disabled = false;

                if (response.tap === 'break_in') {
                    setBreakBtn(true);
                } else if (response.tap === 'break_out') {
                    setBreakBtn(false);
                    btn.disabled = true;
                    btn.classList.add('btn-disabled');
                } else {
                    restoreBreakBtn();
                }

                if (document.querySelector('.gantt-container')) refreshGantt();
                if (document.getElementById('logs-table-body')) fetchLogs();

            } catch (err) {
                console.error(err);
                restoreBreakBtn();
            } finally {
                isBreakProcessing = false;
                const breakMenu = document.getElementById('break-menu');
                if (breakMenu) breakMenu.classList.remove('show');
            }
        };

        const onBreakError = (err) => {
            console.error(err);
            alert('Location permission is required.');
            restoreBreakBtn();
            isBreakProcessing = false;
        };

        if (cachedPosition) {
            await submitBreak(cachedPosition);
        } else {
            navigator.geolocation.getCurrentPosition(
                async (pos) => await submitBreak(pos),
                onBreakError,
                { enableHighAccuracy: false, maximumAge: 60000, timeout: 10000 }
            );
        }
    };

    // ===== NAVIGATION UNLOAD GUARD =====
    let _allowUnload = false;

    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[href]');
        if (!link) return;
        const href = link.getAttribute('href');
        if (!href || href === '#' || href.startsWith('javascript:') || href.startsWith('#')) return;
        try {
            const url = new URL(link.href, window.location.href);
            if (url.origin === window.location.origin) _allowUnload = true;
        } catch (_) {}
    });

    document.addEventListener('submit', () => { _allowUnload = true; });

    window.addEventListener('beforeunload', (e) => {
        if (_allowUnload) return;
        e.preventDefault();
        e.returnValue = '';
    });
</script>