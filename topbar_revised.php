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
    'DTR Internship Project',
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
                    <button class="btn btn-success btn-sm" onclick="handleTimeIn()">
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
                            data-bs-auto-close="outside"
                            aria-expanded="false">
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>

                    <ul class="dropdown-menu break-menu">
                        <li>
                            <button
                                class="dropdown-item btn btn-break"
                                id="break-action-btn"
                                data-state="<?= $isOnBreak ? 'out' : 'in' ?>"
                                onclick="handleBreak()"
                                <?= $isBreakOut ? 'disabled' : '' ?>
                            >
                                <?php if ($isOnBreak): ?>
                                    <i class="bi bi-arrow-return-right"></i> Resume Work
                                <?php else: ?>
                                    <i class="bi bi-cup-hot-fill"></i> Take Break
                                <?php endif; ?>
                            </button>
                        </li>
                    </ul>

                <?php endif; ?>

            </div>

            <!-- Divider -->
            <div class="vr mx-1 opacity-25"></div>

            <!-- Actions Dropdown -->
            <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                    <i class="bi bi-person-fill"></i>
                    <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'User') ?>
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="openOTModal(); return false;">
                        <i class="bi bi-clock-history"></i> Request OT
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="openLeaveModal(); return false;">
                        <i class="bi bi-calendar-x"></i> Request Leave
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="openOBModal(); return false;">
                        <i class="bi bi-briefcase"></i> Request OB
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="openLogEditModal(); return false;">
                        <i class="bi bi-pencil-square"></i> Request Log Edit
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a></li>
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
// ===============================
// GLOBAL STATES
// ===============================
let isProcessing = false;
let isBreakProcessing = false;
let cachedPosition = null;

document.addEventListener('DOMContentLoaded', () => {

    // CROSS TAB SYNC
    window.addEventListener('storage', (event) => {
        if (event.key !== 'attendance_update') return;

        const status = localStorage.getItem('attendance_tap_result');
        const statusEl = document.getElementById('dashboard-status');

        if (!statusEl) return;

        if (status === 'timed_in') statusEl.textContent = 'Timed In';
        else if (status === 'timed_out') statusEl.textContent = 'Timed Out';
    });

    // GPS CACHE
    navigator.geolocation.watchPosition(
        (pos) => cachedPosition = pos,
        (err) => console.warn('GPS watch error:', err),
        { enableHighAccuracy: false, maximumAge: 60000, timeout: 10000 }
    );

});


// ===============================
// UI HELPERS
// ===============================
const setLoading = (btn) => {
    if (!btn) return;

    btn.disabled = true;
    btn.dataset.originalHtml = btn.innerHTML;

    btn.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Loading...
    `;
};

const restoreButton = (btn) => {
    if (!btn) return;

    btn.disabled = false;

    if (btn.dataset.originalHtml) {
        btn.innerHTML = btn.dataset.originalHtml;
    }
};

// Button group animation — retriggers on every call
const setBtnGroup = (btnGroup, newHTML) => {
    btnGroup.innerHTML = newHTML;
    btnGroup.classList.remove('btn-group-animate');
    void btnGroup.offsetWidth; // force reflow
    btnGroup.classList.add('btn-group-animate');
};


// ===============================
// TIME IN / OUT
// ===============================
const handleTimeIn = async () => {
    if (isProcessing) return;

    const btnGroup = document.querySelector('.btn-group');
    const statusEl = document.getElementById('dashboard-status');

    if (!btnGroup) return;

    const originalGroupHTML = btnGroup.innerHTML;
    isProcessing = true;

    // Spinner on the whole group
    setBtnGroup(btnGroup, `
        <button class="btn btn-sm" disabled>
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        </button>
    `);

    const reset = () => {
        setBtnGroup(btnGroup, originalGroupHTML);
        isProcessing = false;
    };

    const onError = () => {
        alert('Location permission required.');
        reset();
    };

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

            if (response.error === 'too_fast') {
                let remaining = response.seconds_remaining;

                const tick = () => {
                    setBtnGroup(btnGroup, `
                        <button class="btn btn-sm" disabled>
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            Wait ${remaining}s...
                        </button>
                    `);

                    if (remaining <= 0) {
                        setBtnGroup(btnGroup, originalGroupHTML);
                        isProcessing = false;
                        return;
                    }

                    remaining--;
                    setTimeout(tick, 1000);
                };

                tick();
                return;
            }

            if (response.error === 'shift_ended') {
                alert('Shift ended. You are marked absent.');
                reset();
                return;
            }

            if (response.tap === 'timed_in') {
                setBtnGroup(btnGroup, `
                    <button class="btn btn-danger btn-sm" onclick="handleTimeIn()">
                        <i class="bi bi-stopwatch-fill"></i> Time Out
                    </button>
                    <button type="button"
                            class="btn btn-danger btn-sm dropdown-toggle dropdown-toggle-split"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu break-menu">
                            <button
                                class="dropdown-item btn btn-break"
                                id="break-action-btn"
                                data-state="in"
                                onclick="handleBreak()"
                            >
                                <i class="bi bi-cup-hot-fill"></i> Take Break
                            </button>
                        </li>
                    </ul>
                `);
                if (statusEl) statusEl.textContent = 'Timed In';

            } else if (response.tap === 'timed_out') {
                setBtnGroup(btnGroup, `
                    <button class="btn btn-success btn-sm" onclick="handleTimeIn()">
                        <i class="bi bi-stopwatch-fill"></i> Time In
                    </button>
                `);
                if (statusEl) statusEl.textContent = 'Timed Out';
            }

            localStorage.setItem('attendance_tap_result', response.tap);
            localStorage.setItem('attendance_update', Date.now());

        } catch (err) {
            console.error(err);
            reset();
        } finally {
            isProcessing = false;
        }
    };

    if (cachedPosition) {
        submit(cachedPosition);
    } else {
        navigator.geolocation.getCurrentPosition(submit, onError);
    }
};


// ===============================
// BREAK HANDLER
// ===============================
const handleBreak = async () => {
    if (isBreakProcessing) return;

    const btn = document.getElementById('break-action-btn');
    if (!btn) return;

    isBreakProcessing = true;
    setLoading(btn);

    const reset = () => {
        restoreButton(btn);
        isBreakProcessing = false;
    };

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
                let remaining = response.seconds_remaining;

                // Animate once into cooldown state
                setBtnGroup(btnGroup, `
                    <button class="btn btn-sm" disabled>
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <span id="cooldown-text">Wait ${remaining}s...</span>
                    </button>
                `);

                const tick = () => {
                    if (remaining <= 0) {
                        setBtnGroup(btnGroup, originalGroupHTML);
                        isProcessing = false;
                        return;
                    }

                    remaining--;

                    // Just update the text, no animation
                    const countEl = btnGroup.querySelector('#cooldown-text');
                    if (countEl) countEl.textContent = `Wait ${remaining}s...`;

                    setTimeout(tick, 1000);
                };

                setTimeout(tick, 1000);
                return;
            }

            if (response.tap === 'break_in') {
                btn.disabled = false;
                btn.dataset.state = 'out'; // ← add this
                btn.innerHTML = `<i class="bi bi-arrow-return-right"></i> Resume Work`;
            }

            if (response.tap === 'break_out') {
                btn.disabled = true;
                btn.dataset.state = 'in'; // ← add this
                btn.innerHTML = `<i class="bi bi-cup-hot-fill"></i> Take Break`;
            }

        } catch (err) {
            console.error(err);
            reset();
        } finally {
            isBreakProcessing = false;
        }
    };

    const onError = () => {
        alert('Location permission required.');
        reset();
    };

    if (cachedPosition) {
        submit(cachedPosition);
    } else {
        navigator.geolocation.getCurrentPosition(submit, onError);
    }
};


// expose globally
window.handleTimeIn = handleTimeIn;
window.handleBreak = handleBreak;
</script>