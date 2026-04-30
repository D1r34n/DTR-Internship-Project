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

/* =========================================================
   AUTO BREADCRUMB FROM URL (NO MANUAL ARRAYS)
========================================================= */

$currentPath = $_SERVER['PHP_SELF'];
$parts = array_values(array_filter(explode('/', $currentPath)));

$excludeSegments = [
    'localhost',
    'DTR-Internship-Project',
    'system_functions',
    'dropdown_requests',
    'assets',
    'includes',
    'db',
];

$segments = [];

foreach ($parts as $part) {
    $clean = str_replace('.php', '', $part);

    if (in_array($clean, $excludeSegments)) continue;
    if ($clean === '' || $clean === 'index') continue;

    $segments[] = $clean;
}

$breadcrumbPath = [];

// Always start with Dashboard
$breadcrumbPath[] = [
    'label' => 'HSN DTR System',
    'url'   => '/dashboard.php'
];

$accumulated = '';

foreach ($segments as $index => $seg) {

    $accumulated .= '/' . $seg;

    $label = ucwords(str_replace('_', ' ', $seg));

    if ($index === array_key_last($segments)) {
        $breadcrumbPath[] = [
            'label' => $label
        ];
    } else {
        $breadcrumbPath[] = [
            'label' => $label,
            'url'   => $accumulated . '.php'
        ];
    }
}

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

<!-- Topbar external libs -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Shared CSS — loaded once via topbar for all pages -->
<link rel="stylesheet" href="../assets/css/root.css">
<link rel="stylesheet" href="../assets/css/typography.css">
<link rel="stylesheet" href="../assets/css/components.css">
<link rel="stylesheet" href="../navbars_revised.css">

<!-- Modal CSS -->
<link rel="stylesheet" href="../dropdown_requests/ot_modal.css">
<link rel="stylesheet" href="../dropdown_requests/leave_modal.css">
<link rel="stylesheet" href="../dropdown_requests/ob_modal.css">
<link rel="stylesheet" href="../dropdown_requests/log_edit_modal.css">

<!-- TOP BAR -->
<nav class="navbar fixed-top shadow-sm">
    <div class="container-fluid d-flex align-items-center justify-content-between">

        <!-- LEFT: Breadcrumb + Title -->
        <div class="d-flex flex-column">

            <!-- Breadcrumbs -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb text-breadcrumb">

                <?php foreach ($breadcrumbPath as $index => $crumb): ?>
                    <?php $isLast = $index === array_key_last($breadcrumbPath); ?>

                    <?php if ($isLast): ?>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?= htmlspecialchars($crumb['label']) ?>
                        </li>
                    <?php else: ?>
                        <li class="breadcrumb-item">
                            <?= htmlspecialchars($crumb['label']) ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>

                </ol>
            </nav>

            <!-- Page Title -->
            <div class="navbar-brand mb-0 h2 text-light">
                <?= $title ?>
            </div>

        </div>

        <!-- RIGHT: Actions -->
        <div class="d-flex align-items-center gap-2">

            <!-- Time In/Out Button -->
            <div class="btn-group">

                <!-- TIME IN STATE -->
                <?php if (!$timedIn): ?>
                    <button class="btn btn-primary btn-sm" onclick="handleTimeIn()">
                        <i class="bi bi-stopwatch-fill"></i>
                        Time In
                    </button>

                <?php else: ?>

                    <!-- TIME OUT + DROPDOWN -->
                    <button class="btn btn-danger btn-sm" onclick="handleTimeIn()">
                        <i class="bi bi-stopwatch-fill"></i>
                        Time Out
                    </button>

                    <button type="button"
                            class="btn btn-danger btn-sm dropdown-toggle dropdown-toggle-split"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end">

                        <li>
                            <a class="dropdown-item" href="#" onclick="handleBreak(); return false;">
                                <?php if ($isOnBreak): ?>
                                    <i class="bi bi-play-fill"></i> Break Out
                                <?php else: ?>
                                    <i class="bi bi-pause-fill"></i> Break In
                                <?php endif; ?>
                            </a>
                        </li>

                    </ul>

                <?php endif; ?>

            </div>

            <!-- Divider (optional visual separation) -->
            <div class="vr mx-1 opacity-25"></div>

            <!-- Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                    Actions
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#">Profile</a></li>
                    <li><a class="dropdown-item" href="#">Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#">Logout</a></li>
                </ul>
            </div>

        </div>
    </div>
</nav>

<!-- MODALS -->
<?php include '../dropdown_requests/ot_modal.php'; ?>
<?php include '../dropdown_requests/leave_modal.php'; ?>
<?php include '../dropdown_requests/ob_modal.php'; ?>
<?php include '../dropdown_requests/log_edit_modal.php'; ?>

<script defer>
document.addEventListener('DOMContentLoaded', () => {

    // ===============================
    // CROSS TAB SYNC
    // ===============================
    window.addEventListener('storage', (event) => {
        if (event.key !== 'attendance_update') return;

        const status = localStorage.getItem('attendance_tap_result');
        const statusEl = document.getElementById('dashboard-status');

        if (!statusEl) return;

        if (status === 'timed_in') {
            statusEl.textContent = 'Timed In';
        } else if (status === 'timed_out') {
            statusEl.textContent = 'Timed Out';
        }
    });

    // ===============================
    // GPS CACHE
    // ===============================
    let cachedPosition = null;

    navigator.geolocation.watchPosition(
        (pos) => cachedPosition = pos,
        (err) => console.warn('GPS watch error:', err),
        { enableHighAccuracy: false, maximumAge: 60000, timeout: 10000 }
    );

});

// ===============================
// GLOBAL STATES
// ===============================
let isProcessing = false;
let isBreakProcessing = false;

// ===============================
// UI HELPERS
// ===============================
const showSpinner = (btn, text = '') => {
    if (!btn) return;

    btn.disabled = true;
    btn.dataset.originalText = text;

    btn.innerHTML = `
        <span class="btn-spinner btn-spinner--sm"></span>
        Processing...
    `;
};

const restoreButton = (btn, text) => {
    if (!btn) return;

    btn.disabled = false;
    btn.innerHTML = text;
};

// ===============================
// TIME IN / OUT (MATCHES YOUR PHP)
// ===============================
const handleTimeIn = async () => {
    if (isProcessing) return;

    const btn = document.querySelector('.btn-group .btn-primary, .btn-group .btn-danger');
    const statusEl = document.getElementById('dashboard-status');

    if (!btn) return;

    const originalHTML = btn.innerHTML;
    isProcessing = true;

    showSpinner(btn, originalHTML);

    const submit = async (pos) => {
        try {
            const res = await fetch('../system_functions/attendance_tap.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                    accuracy: pos.coords.accuracy
                })
            });

            const response = await res.json();

            // too fast
            if (response.error === 'too_fast') {
                alert(`Please wait ${response.seconds_remaining}s`);
                restoreButton(btn, originalHTML);
                isProcessing = false;
                return;
            }

            // shift ended
            if (response.error === 'shift_ended') {
                alert('Shift ended. You are marked absent.');
                restoreButton(btn, originalHTML);
                isProcessing = false;
                return;
            }

            // SUCCESS
            if (response.tap === 'timed_in') {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-danger');
                btn.innerHTML = 'Time Out';

                if (statusEl) statusEl.textContent = 'Timed In';

            } else if (response.tap === 'timed_out') {
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-primary');
                btn.innerHTML = 'Time In';

                if (statusEl) statusEl.textContent = 'Timed Out';
            }

            localStorage.setItem('attendance_tap_result', response.tap);
            localStorage.setItem('attendance_update', Date.now());

        } catch (err) {
            console.error(err);
            restoreButton(btn, originalHTML);
        } finally {
            isProcessing = false;
        }
    };

    const onError = () => {
        alert('Location permission required.');
        restoreButton(btn, originalHTML);
        isProcessing = false;
    };

    if (cachedPosition) {
        submit(cachedPosition);
    } else {
        navigator.geolocation.getCurrentPosition(submit, onError);
    }
};

// ===============================
// BREAK HANDLER (BOOTSTRAP DROPDOWN VERSION)
// ===============================
const handleBreak = async () => {
    if (isBreakProcessing) return;

    const btn = document.getElementById('break-action-btn');
    if (!btn) return;

    const originalHTML = btn.innerHTML;
    isBreakProcessing = true;

    btn.disabled = true;
    btn.innerHTML = `<span class="btn-spinner btn-spinner--sm"></span>`;

    const submit = async (pos) => {
        try {
            const res = await fetch('../system_functions/attendance_tap.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                    break_tap: true
                })
            });

            const response = await res.json();

            if (response.error === 'too_fast') {
                alert(`Wait ${response.seconds_remaining}s`);
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                isBreakProcessing = false;
                return;
            }

            if (response.tap === 'break_in') {
                btn.innerHTML = `<i class="bi bi-play-fill"></i> Break Out`;
            }

            if (response.tap === 'break_out') {
                btn.innerHTML = `<i class="bi bi-pause-fill"></i> Break In`;
                btn.disabled = true;
            }

        } catch (err) {
            console.error(err);
            btn.innerHTML = originalHTML;
        } finally {
            isBreakProcessing = false;
        }
    };

    const onError = () => {
        alert('Location permission required.');
        btn.innerHTML = originalHTML;
        btn.disabled = false;
        isBreakProcessing = false;
    };

    if (cachedPosition) {
        submit(cachedPosition);
    } else {
        navigator.geolocation.getCurrentPosition(submit, onError);
    }
};

// expose globally for PHP onclick
window.handleTimeIn = handleTimeIn;
window.handleBreak = handleBreak;
</script>