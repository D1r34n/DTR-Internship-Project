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
        'workforce_logs'     => 'Manage Logs',
    ],
    'admin' => [
        'dashboard'         => 'Admin Dashboard',
        'manage_employees'  => 'Manage Employees',
        'employee_requests' => 'Employee Requests',
        'schedule_requests' => 'Schedule Requests',
        'employee_logs'     => 'Employee Logs',
        'departments'       => 'Departments',
    ],
];

$title = $titles[$role][$currentPage] ?? 'Dashboard';

/* =========================================================
   AUTO BREADCRUMB FROM URL
========================================================= */
$parts = array_values(array_filter(explode('/', $_SERVER['PHP_SELF'])));

$excludeSegments = [
    'localhost', 'DTR-Internship-Project', 'DTR Internship Project',
    'admin_pages', 'employee_pages', 'workforce_pages',
    'system_functions', 'dropdown_requests', 'assets', 'includes', 'db',
];

$segments = [];
foreach ($parts as $part) {
    $clean = str_replace('.php', '', $part);
    if (in_array($clean, $excludeSegments) || $clean === '' || $clean === 'index') continue;
    $segments[] = $clean;
}

$breadcrumbPath = [['label' => 'HSN DTR System']];
foreach ($segments as $index => $seg) {
    $label = ucwords(str_replace('_', ' ', $seg));
    $breadcrumbPath[] = $index === array_key_last($segments)
        ? ['label' => $label]
        : ['label' => $label, 'url' => '/' . $seg . '.php'];
}

/* =========================================================
   ATTENDANCE STATE
========================================================= */
date_default_timezone_set('Asia/Manila');

$stmt = $pdo->prepare("
    SELECT log_type FROM logs
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
// Once someone does BREAK_OUT they can take another break next time they're on break,
// so we only disable the button if they've never timed in at all
$breakDisabled = !$timedIn;
?>

<!-- Modal CSS — topbar-specific, not duplicated in layout_start -->
<link rel="stylesheet" href="../dropdown_requests/ot_modal.css">
<link rel="stylesheet" href="../dropdown_requests/leave_modal.css">
<link rel="stylesheet" href="../dropdown_requests/ob_modal.css">
<link rel="stylesheet" href="../dropdown_requests/log_edit_modal.css">

<nav class="navbar topbar">
    <div class="container-fluid d-flex align-items-center justify-content-between">

        <!-- LEFT: Breadcrumb + Title -->
        <div class="d-flex flex-column">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb text-breadcrumb">
                    <?php foreach ($breadcrumbPath as $index => $crumb): ?>
                        <?php $isLast = $index === array_key_last($breadcrumbPath); ?>
                        <li class="breadcrumb-item <?= $isLast ? 'active' : '' ?>"
                            <?= $isLast ? 'aria-current="page"' : '' ?>>
                            <?php if (!$isLast && isset($crumb['url'])): ?>
                                <a href="<?= htmlspecialchars($crumb['url']) ?>">
                                    <?= htmlspecialchars($crumb['label']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($crumb['label']) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="navbar-brand mb-0 h2 text-light"><?= htmlspecialchars($title) ?></div>
        </div>

        <!-- RIGHT: Actions -->
        <div class="d-flex align-items-center gap-3">

            <!-- Time In/Out -->
            <div class="btn-group" id="attendance-btn-group">
                <?php if (!$timedIn): ?>
                    <button class="btn btn-success" onclick="handleTimeIn()">
                        <i class="bi bi-stopwatch-fill"></i> Time In
                    </button>
                <?php else: ?>
                    <button class="btn btn-danger" onclick="handleTimeIn()">
                        <i class="bi bi-stopwatch-fill"></i> Time Out
                    </button>
                    <button type="button"
                            class="btn btn-danger dropdown-toggle dropdown-toggle-split"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="outside"
                            aria-expanded="false">

                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu break-menu">
                        <li>
                            <button class="btn btn-break"
                                    id="break-action-btn"
                                    data-state="<?= $isOnBreak ? 'out' : 'in' ?>"
                                    onclick="handleBreak()">
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

            <div class="vr"></div>

            <!-- User Dropdown -->
            <div class="dropdown">
                <button class="btn dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                    <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'User') ?>
                    
                    <div class="vr"></div>
            
                    <span class="empRole empRole-<?= $role ?>">
                        <?= ucfirst($role) ?>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="#"
                           data-bs-toggle="modal" data-bs-target="#otModal"
                           onclick="openOTModal()">
                            <i class="bi bi-clock-history"></i> Request OT
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#"
                           data-bs-toggle="modal" data-bs-target="#leaveModal"
                           onclick="openLeaveModal()">
                            <i class="bi bi-calendar-x"></i> Request Leave
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#"
                           data-bs-toggle="modal" data-bs-target="#obModal"
                           onclick="openOBModal()">
                            <i class="bi bi-briefcase"></i> Request OB
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#" onclick="openLogEditModal(); return false;">
                            <i class="bi bi-pencil-square"></i> Request Log Edit
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item logout-item" href="../authentication_pages/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</nav>

<?php include '../dropdown_requests/modal_request.php'; ?>

<script defer>
    let isProcessing      = false;
    let isBreakProcessing = false;
    let cachedPosition    = null;

    document.addEventListener('DOMContentLoaded', () => {

        // Cross-tab attendance sync
        window.addEventListener('storage', e => {
            if (e.key !== 'attendance_update') return;
            const status   = localStorage.getItem('attendance_tap_result');
            const statusEl = document.getElementById('dashboard-status');
            if (!statusEl) return;
            statusEl.textContent = status === 'timed_in' ? 'Timed In' : 'Timed Out';
        });

        // GPS cache
        navigator.geolocation.watchPosition(
            pos => cachedPosition = pos,
            err => console.warn('GPS watch error:', err),
            { enableHighAccuracy: false, maximumAge: 60000, timeout: 10000 }
        );
    });

    /* -------------------------------------------------------
       UI HELPERS
    ------------------------------------------------------- */
    const setLoading = btn => {
        if (!btn) return;
        btn.disabled = true;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading...`;
    };

    const restoreButton = btn => {
        if (!btn) return;
        btn.disabled = false;
        if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
    };

    const setBtnGroup = (group, html) => {
        group.innerHTML = html;
        group.classList.remove('btn-group-animate');
        void group.offsetWidth; // force reflow
        group.classList.add('btn-group-animate');
    };

    const timeInHTML = () => `
        <button class="btn btn-success" onclick="handleTimeIn()">
            <i class="bi bi-stopwatch-fill"></i> Time In
        </button>`;

    const timeOutHTML = () => `
        <button class="btn btn-danger" onclick="handleTimeIn()">
            <i class="bi bi-stopwatch-fill"></i> Time Out
        </button>
        <button type="button"
                class="btn btn-danger dropdown-toggle dropdown-toggle-split"
                data-bs-toggle="dropdown"
                aria-expanded="false">
            <span class="visually-hidden">Toggle Dropdown</span>
        </button>
        <ul class="dropdown-menu break-menu">
            <li>
                <button class="btn btn-break"
                        id="break-action-btn"
                        data-state="in"
                        onclick="handleBreak()">
                    <i class="bi bi-cup-hot-fill"></i> Take Break
                </button>
            </li>
        </ul>`;

    const spinnerHTML = () => `
        <button class="btn" disabled>
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        </button>`;

    const cooldownHTML = secs => `
        <button class="btn" disabled>
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span id="cooldown-text">Wait ${secs}s...</span>
        </button>`;

    /* -------------------------------------------------------
       TIME IN / OUT
    ------------------------------------------------------- */
    const handleTimeIn = async () => {
        if (isProcessing) return;

        const btnGroup = document.getElementById('attendance-btn-group');
        const statusEl = document.getElementById('dashboard-status');
        if (!btnGroup) return;

        const originalHTML = btnGroup.innerHTML;
        isProcessing = true;
        setBtnGroup(btnGroup, spinnerHTML());

        const reset   = () => { setBtnGroup(btnGroup, originalHTML); isProcessing = false; };
        const onError = () => { alert('Location permission required.'); reset(); };

        const submit = async pos => {
            try {
                const res = await fetch('../system_functions/attendance_tap.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        lat:      pos.coords.latitude,
                        lng:      pos.coords.longitude,
                        accuracy: pos.coords.accuracy
                    })
                });

                const data = await res.json();

                if (data.error === 'too_fast') {
                    let remaining = data.seconds_remaining;
                    const tick = () => {
                        if (remaining <= 0) { setBtnGroup(btnGroup, originalHTML); isProcessing = false; return; }
                        setBtnGroup(btnGroup, cooldownHTML(remaining--));
                        setTimeout(tick, 1000);
                    };
                    tick();
                    return;
                }

                if (data.error === 'shift_ended') {
                    alert('Shift ended. You are marked absent.');
                    reset();
                    return;
                }

                if (data.tap === 'timed_in') {
                    setBtnGroup(btnGroup, timeOutHTML());
                    if (statusEl) statusEl.textContent = 'Timed In';
                } else if (data.tap === 'timed_out') {
                    setBtnGroup(btnGroup, timeInHTML());
                    if (statusEl) statusEl.textContent = 'Timed Out';
                }

                localStorage.setItem('attendance_tap_result', data.tap);
                localStorage.setItem('attendance_update', Date.now());
                document.dispatchEvent(new CustomEvent('attendance_tapped'));

            } catch (err) {
                console.error(err);
                reset();
            } finally {
                isProcessing = false;
            }
        };

        cachedPosition
            ? submit(cachedPosition)
            : navigator.geolocation.getCurrentPosition(submit, onError);
    };

    /* -------------------------------------------------------
       BREAK HANDLER
    ------------------------------------------------------- */
    const handleBreak = async () => {
        if (isBreakProcessing) return;

        const btn = document.getElementById('break-action-btn');
        if (!btn) return;

        isBreakProcessing = true;
        setLoading(btn);

        const reset   = () => { restoreButton(btn); isBreakProcessing = false; };
        const onError = () => { alert('Location permission required.'); reset(); };

        const submit = async pos => {
            try {
                const res = await fetch('../system_functions/attendance_tap.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        lat:       pos.coords.latitude,
                        lng:       pos.coords.longitude,
                        accuracy:  pos.coords.accuracy,
                        break_tap: true
                    })
                });

                const data = await res.json();

                if (data.error === 'too_fast') {
                    // Break cooldown: just restore and show a brief message
                    btn.disabled = true;
                    btn.innerHTML = `<span id="cooldown-text">Wait ${data.seconds_remaining}s...</span>`;
                    let remaining = data.seconds_remaining;
                    const tick = () => {
                        if (remaining <= 0) { restoreButton(btn); isBreakProcessing = false; return; }
                        const el = document.getElementById('cooldown-text');
                        if (el) el.textContent = `Wait ${--remaining}s...`;
                        setTimeout(tick, 1000);
                    };
                    setTimeout(tick, 1000);
                    return;
                }

                if (data.tap === 'break_in') {
                    btn.disabled  = false;
                    btn.dataset.state = 'out';
                    btn.innerHTML = `<i class="bi bi-arrow-return-right"></i> Resume Work`;
                    document.dispatchEvent(new CustomEvent('attendance_tapped'));
                }

                if (data.tap === 'break_out') {
                    // Re-enable — they can take another break later if they need to
                    btn.disabled  = false;
                    btn.dataset.state = 'in';
                    btn.innerHTML = `<i class="bi bi-cup-hot-fill"></i> Take Break`;
                    document.dispatchEvent(new CustomEvent('attendance_tapped'));
                }

            } catch (err) {
                console.error(err);
                reset();
            } finally {
                isBreakProcessing = false;
            }
        };

        cachedPosition
            ? submit(cachedPosition)
            : navigator.geolocation.getCurrentPosition(submit, onError);
    };

    window.handleTimeIn = handleTimeIn;
    window.handleBreak  = handleBreak;
</script>