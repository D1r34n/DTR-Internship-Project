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
$current_page = $current_page ?? 'dashboard';
$title = $titles[$role][$current_page] ?? 'Dashboard';

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

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<link rel="stylesheet" href="../dropdown_requests/ot_modal.css">
<link rel="stylesheet" href="../dropdown_requests/leave_modal.css">
<link rel="stylesheet" href="../dropdown_requests/ob_modal.css">
<link rel="stylesheet" href="../dropdown_requests/log_edit_modal.css">

<!-- TOP BAR -->
<div class="topBar">
    <h4 class="dashboardTitle"><?= htmlspecialchars($title) ?></h4>

    <div class="topBarRight">

        <!-- TIME IN / OUT BUTTON GROUP -->
        <div class="timeInWrapper" id="timeInWrapper" style="position: relative; display: flex; align-items: center; gap: 0.5rem;">

            <button
                class="timeInButton <?= $timedIn ? 'btn-out' : 'btn-in' ?>"
                id="timeInBtn"
                onclick="handleTimeIn()"
            >
                <i class="bi bi-stopwatch-fill timeInIcon"></i>
                <span id="timeInLabel">
                    <?= $timedIn ? 'Time Out' : 'Time In' ?>
                </span>
            </button>

            <?php if ($timedIn): ?>
            <div class="breakMenu" id="breakMenu">
                <div class="breakMenuSection">
                    <button class="breakMenuItem <?= $isOnBreak ? 'breakOutBtn' : 'breakInBtn' ?>" id="breakActionBtn" onclick="handleBreak()">
                        <?php if ($isOnBreak): ?>
                            <i class="bi bi-play-fill"></i>
                            <span id="breakLabel">Break Out</span>
                        <?php else: ?>
                            <i class="bi bi-pause-fill"></i>
                            <span id="breakLabel">Break In</span>
                        <?php endif; ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <div class="verticalDivider"></div>

        <!-- USER DROPDOWN -->
        <div class="navUserProfile">
            <div class="userDropdownWrapper">

                <span class="userEmail dropdown-toggle" id="userDropdownToggle">
                    <i class="bi bi-person-fill userProfileIcon"></i>
                    <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'User') ?>
                    <i class="bi bi-chevron-down userArrow"></i>
                </span>

                <div class="userDropdownMenu" id="userDropdownMenu">
                    <div class="dropdownSection">

                        <a href="#" class="userDropdownItem" onclick="openOTModal(); return false;">
                            <i class="bi bi-clock-history"></i> Request OT
                        </a>
                        <a href="#" class="userDropdownItem" onclick="openLeaveModal(); return false;">
                            <i class="bi bi-calendar-x"></i> Request Leave
                        </a>
                        <a href="#" class="userDropdownItem" onclick="openOBModal(); return false;">
                            <i class="bi bi-briefcase"></i> Request OB
                        </a>
                        <a href="#" class="userDropdownItem" onclick="openLogEditModal(); return false;">
                            <i class="bi bi-pencil-square"></i> Request Log Edit
                        </a>

                        <div class="horizontalDivider"></div>

                        <a href="../logout.php" class="logoutText">
                            <i class="bi bi-box-arrow-right logoutIcon"></i> Logout
                        </a>

                    </div>
                </div>
            </div>
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
            window.addEventListener('storage', (event) => {
                if (event.key !== 'attendance_update') return;

                const tap   = localStorage.getItem('attendance_tap_result');
                const btn   = document.getElementById('timeInBtn');
                const label = document.getElementById('timeInLabel');
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
            const timeInWrapper  = document.getElementById('timeInWrapper');
            const breakMenu      = document.getElementById('breakMenu');

            if (timeInWrapper && breakMenu) {
                let breakMenuTimeout = null;

                const showBreakMenu = () => {
                    const btn        = document.getElementById('timeInBtn');
                    const breakMenu  = document.getElementById('breakMenu');
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

        // Cooldown function for debounce (UI feedback)
        const startCooldown = (btn, seconds, originalText) => {
            const label = btn.querySelector('#timeInLabel');

            btn.disabled = true;
            btn.style.position = 'relative';
            btn.style.overflow = 'hidden';
            btn.style.pointerEvents = 'none';
            btn.style.backgroundColor = '#9ca3af';

            const fill = document.createElement('div');
            fill.style.cssText = `
                position: absolute;
                top: 0; left: 0;
                height: 100%;
                width: 0%;
                background: rgba(255,255,255,0.2);
                transition: width ${seconds}s linear;
                pointer-events: none;
                z-index: 0;
            `;
            btn.appendChild(fill);

            label.style.position = 'relative';
            label.style.zIndex = '1';

            let remaining = seconds;

            label.innerHTML = `
                <span style="display:flex;align-items:center;justify-content:center;gap:6px;">
                    <span style="
                        display: inline-block;
                        width: 14px;
                        height: 14px;
                        border: 2px solid rgba(255,255,255,0.3);
                        border-top-color: white;
                        border-radius: 50%;
                        animation: btn-spin 0.7s linear infinite;
                        flex-shrink: 0;
                    "></span>
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
                requestAnimationFrame(() => {
                    fill.style.width = '100%';
                });
            });

            setTimeout(() => {
                clearInterval(interval);
                if (btn.contains(fill)) btn.removeChild(fill);

                label.innerHTML = originalText;
                label.style.position = '';
                label.style.zIndex = '';

                btn.style.overflow = '';
                btn.style.pointerEvents = '';
                btn.style.backgroundColor = '';
                btn.disabled = false;
                isProcessing = false;

            }, seconds * 1000);
        };

        // Spinner helpers
        const showSpinner = (btn) => {
            const label = btn.querySelector('#timeInLabel');

            btn.style.pointerEvents = 'none';
            btn.style.backgroundColor = '#9ca3af';

            label.innerHTML = `
                <span style="
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 100%;
                ">
                    <span style="
                        display: inline-block;
                        width: 18px;
                        height: 18px;
                        border: 3px solid rgba(255,255,255,0.3);
                        border-top-color: white;
                        border-radius: 50%;
                        animation: btn-spin 0.7s linear infinite;
                    "></span>
                </span>
            `;
        };

        // Inject keyframes once
        if (!document.getElementById('btn-spin-style')) {
            const style = document.createElement('style');
            style.id = 'btn-spin-style';
            style.textContent = `@keyframes btn-spin { to { transform: rotate(360deg); } }`;
            document.head.appendChild(style);
        }

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
            if (document.getElementById('timeInBtn').disabled) return;

            const btn = document.getElementById('timeInBtn');
            const label = btn.querySelector('#timeInLabel');
            const status = document.getElementById('dashboard_status');

            isProcessing = true;
            btn.disabled = true;

            const originalText = label.textContent;
            showSpinner(btn);

            const submitTap = async (pos) => {
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
                        btn.disabled = false;
                        btn.style.pointerEvents  = '';
                        btn.style.backgroundColor = '';
                        label.innerHTML = originalText;
                        isProcessing = false;

                        const existing = document.getElementById('log-corruption-warning');
                        if (!existing) {
                            const warning = document.createElement('div');
                            warning.id = 'log-corruption-warning';
                            warning.style.cssText = `
                                position: fixed;
                                top: 1rem;
                                left: 50%;
                                transform: translateX(-50%);
                                background: #dc3545;
                                color: white;
                                padding: 0.75rem 1.25rem;
                                border-radius: 10px;
                                font-size: 0.875rem;
                                font-family: 'Poppins', sans-serif;
                                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                                z-index: 9999;
                                display: flex;
                                align-items: center;
                                gap: 0.5rem;
                                max-width: 420px;
                                text-align: center;
                            `;
                            warning.innerHTML = `
                                <i class="bi bi-exclamation-triangle-fill" style="font-size:1.1rem; flex-shrink:0;"></i>
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
                        btn.disabled = false;
                        btn.style.pointerEvents = '';
                        btn.style.backgroundColor = '';
                        label.innerHTML = originalText;
                        isProcessing = false;
                        alert('Your shift has already ended. You have been marked absent.');
                        return;
                    }

                    btn.disabled = false;
                    btn.style.pointerEvents = '';
                    btn.style.backgroundColor = '';

                    let tapSuccess = false;

                    if (response.tap === 'timed_in') {
                        btn.classList.remove('btn-in');
                        btn.classList.add('btn-out');
                        label.innerHTML = 'Time Out';
                        if (status) status.textContent = 'Timed In';
                        tapSuccess = true;

                        // Inject break menu if it doesn't exist yet
                        if (!document.getElementById('breakMenu')) {
                            const breakMenuEl = document.createElement('div');
                            breakMenuEl.className = 'breakMenu';
                            breakMenuEl.id        = 'breakMenu';
                            breakMenuEl.innerHTML = `
                                <div class="breakMenuSection">
                                    <button class="breakMenuItem breakInBtn" id="breakActionBtn" onclick="handleBreak()">
                                        <i class="bi bi-pause-fill"></i>
                                        <span id="breakLabel">Break In</span>
                                    </button>
                                </div>
                            `;
                            document.getElementById('timeInWrapper').appendChild(breakMenuEl);

                            // Attach hover listeners
                            const breakMenu     = breakMenuEl;
                            const timeInWrapper = document.getElementById('timeInWrapper');
                            let breakMenuTimeout = null;

                            const showBreakMenu = () => {
                                const btn = document.getElementById('timeInBtn');
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

                        const breakMenu = document.getElementById('breakMenu');
                        if (breakMenu) breakMenu.remove();
                    }

                    if (tapSuccess) {
                        localStorage.setItem('attendance_tap_result', response.tap);
                        localStorage.setItem('attendance_update', Date.now());
                        if (document.getElementById('attendanceTimeline')) refreshChart();
                    }

                    if (typeof getTotalWorkedHours === 'function') getTotalWorkedHours();
                    if (document.getElementById('logs_table_body')) fetchLogs();
                    if (document.getElementById('attendance_table_body')) loadAttendance();

                } catch (err) {
                    console.error(err);
                } finally {
                    isProcessing = false;
                    
                    // Refresh Gantt no matter the response
                    if (document.querySelector('.ganttContainer')) refreshGantt();
                }
            };

            const onError = (err) => {
                console.error(err);
                alert('Location permission is required.');
                isProcessing = false;
                btn.disabled = false;
                btn.style.pointerEvents = '';
                btn.style.backgroundColor = '';
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

            const btn           = document.getElementById('breakActionBtn');
            const originalClass = btn.className;
            const isBreakOut    = originalClass.includes('breakOutBtn');

            isBreakProcessing = true;

            // Only replace the label span — icon <i> stays untouched
            const breakLabel = btn.querySelector('#breakLabel');
            breakLabel.innerHTML = `<span style="
                display: inline-block;
                width: 14px; height: 14px;
                border: 2px solid rgba(255,255,255,0.3);
                border-top-color: white;
                border-radius: 50%;
                animation: btn-spin 0.7s linear infinite;
                vertical-align: middle;
            "></span>`;

            const setBreakBtn = (isOut) => {
                btn.querySelector('i').className   = isOut ? 'bi bi-play-fill' : 'bi bi-pause-fill';
                btn.querySelector('#breakLabel').textContent = isOut ? 'Break Out' : 'Break In';
                btn.className = `breakMenuItem ${isOut ? 'breakOutBtn' : 'breakInBtn'}`;
            };

            const restoreBreakBtn = () => setBreakBtn(isBreakOut);

            const submitBreak = async (pos) => {
                try {
                    const res = await fetch('../system_functions/attendance_tap.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
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
                        btn.querySelector('#breakLabel').innerHTML = `
                            <span style="display:inline-flex;align-items:center;gap:4px;">
                                <span style="
                                    display:inline-block;width:12px;height:12px;
                                    border:2px solid rgba(255,255,255,0.3);
                                    border-top-color:white;border-radius:50%;
                                    animation:btn-spin 0.7s linear infinite;
                                "></span>
                                <span id="breakCooldownText">(${remaining}s)</span>
                            </span>
                        `;
                        btn.disabled      = true;
                        btn.style.opacity = '0.5';
                        btn.style.cursor  = 'not-allowed';

                        const interval = setInterval(() => {
                            remaining--;
                            const el = document.getElementById('breakCooldownText');
                            if (remaining > 0 && el) {
                                el.textContent = `(${remaining}s)`;
                            } else {
                                clearInterval(interval);
                                btn.querySelector('#breakLabel').textContent = isBreakOut ? 'Break Out' : 'Break In';
                                btn.disabled      = false;
                                btn.style.opacity = '';
                                btn.style.cursor  = '';
                            }
                        }, 1000);

                        isBreakProcessing = false;
                        return;
                    }

                    btn.disabled = false;

                    if (response.tap === 'break_in') {
                        setBreakBtn(true); // now showing "Break Out"
                    } else if (response.tap === 'break_out') {
                        setBreakBtn(false); // now showing "Break In"
                        btn.disabled      = true;
                        btn.style.opacity = '0.5';
                        btn.style.cursor  = 'not-allowed';
                    } else {
                        restoreBreakBtn();
                    }

                    if (document.querySelector('.ganttContainer')) refreshGantt();
                    if (document.getElementById('logs_table_body')) fetchLogs();

                } catch (err) {
                    console.error(err);
                    restoreBreakBtn();
                } finally {
                    isBreakProcessing = false;
                    const breakMenu = document.getElementById('breakMenu');
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

        // Dropdown menu
        const toggle = document.getElementById('userDropdownToggle');
        const menu   = document.getElementById('userDropdownMenu');
        let isOpen   = false;

        toggle.addEventListener('mouseenter', () => menu.classList.add('show'));
        toggle.addEventListener('mouseleave', () => { if (!isOpen) menu.classList.remove('show'); });
        menu.addEventListener('mouseenter',   () => menu.classList.add('show'));
        menu.addEventListener('mouseleave',   () => { if (!isOpen) menu.classList.remove('show'); });

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            isOpen = !isOpen;
            menu.classList.toggle('show', isOpen);
        });

    document.addEventListener('click', (e) => {
        if (!toggle.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove('show');
            isOpen = false;
        }
    });

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