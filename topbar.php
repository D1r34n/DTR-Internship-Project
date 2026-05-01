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
if (!in_array($role, ['admin', 'employee'])) {
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
    'admin' => [
        'dashboard' => 'Admin Dashboard',
        'employees' => 'Employees',
        'schedule'  => 'Schedules',
        'requests'  => 'Requests',
        'logs'      => 'Logs',
    ]
];

// Set current page title
$current_page = $current_page ?? 'dashboard';
$title = $titles[$role][$current_page] ?? 'Dashboard';

// Set date timezone (Philippines)
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// Get last log entry
$stmt = $pdo->prepare("
    SELECT log_type
    FROM logs
    WHERE employee_id = ?
    ORDER BY log_time DESC
    LIMIT 1
");

$stmt->execute([$employeeId]);

$lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

// Determine the attendance state based on logs

// TRUE if last action is IN and not yet followed by OUT
$hasTimeIn  = $lastLog && $lastLog['log_type'] === 'IN';

// TRUE if last action is OUT
$hasTimeOut = $lastLog && $lastLog['log_type'] === 'OUT';

// FINAL UI STATE:
// Employee is considered "Timed In" only if last action is IN
$timedIn = $hasTimeIn && !$hasTimeOut;

// Display text for UI
$currentStatus = $timedIn ? 'Timed In' : 'Timed Out';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<link rel="stylesheet" href="../dropdown_requests/navbars.css">
<link rel="stylesheet" href="../dropdown_requests/ot_modal.css">
<link rel="stylesheet" href="../dropdown_requests/leave_modal.css">
<link rel="stylesheet" href="../dropdown_requests/ob_modal.css">
<link rel="stylesheet" href="../dropdown_requests/log_edit_modal.css">

<!-- TOP BAR -->
<div class="topBar">
    <h4 class="dashboardTitle"><?= htmlspecialchars($title) ?></h4>

    <div class="topBarRight">

        <!-- TIME IN / OUT BUTTON (with break dropdown) -->
        <div class="timeInWrapper" id="timeInWrapper"
            onmouseenter="if(!document.getElementById('timeInBtn').disabled && document.getElementById('timeInBtn').classList.contains('btn-out')) document.getElementById('breakDropdown').style.display='block'"
            onmouseleave="document.getElementById('breakDropdown').style.display='none'">

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

            <div class="breakDropdown" id="breakDropdown" style="display:none;">
                <div class="breakDropdownItem" onclick="handleTakeBreak()">
                    <i class="bi bi-cup-hot"></i> Take a Break
                </div>
            </div>
        </div>

        <div class="verticalDivider"></div>

        <!-- USER DROPDOWN -->
        <div class="dropdown">
            <span class="userEmail dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false">
                <i class="bi bi-person-fill"></i>
                <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'User') ?>
            </span>

            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item" href="#" onclick="openOTModal(); return false;">
                        <i class="bi bi-clock-history"></i> Request OT
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" onclick="openLeaveModal(); return false;">
                        Request Leave
                    </a>
                </li>

                <li><hr class="dropdown-divider"></li>

                <li>
                    <a class="dropdown-item logout-item" href="../logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>

    </div>
</div>

<!-- MODALS -->
<?php include '../dropdown_requests/modal_request.php'; ?>


<!-- JAVASCRIPT -->
<script defer>
    document.addEventListener('DOMContentLoaded', () => {
        window.addEventListener('storage', (event) => {
            if (event.key !== 'attendance_update') return;

            const tap        = localStorage.getItem('attendance_tap_result');
            const breakState = localStorage.getItem('attendance_break_state'); // NEW
            const btn        = document.getElementById('timeInBtn');
            const label      = document.getElementById('timeInLabel');

            // NEW: handle break state sync across tabs
            if (breakState === 'on_break') {
                btn.classList.remove('btn-in', 'btn-out');
                btn.classList.add('btn-break');
                label.textContent = 'End Break';
                btn.onclick = handleEndBreak;
            } else if (tap === 'timed_in') {
                btn.classList.remove('btn-in', 'btn-break');
                btn.classList.add('btn-out');
                label.textContent = 'Time Out';
                btn.onclick = handleTimeIn;
            } else if (tap === 'timed_out') {
                btn.classList.remove('btn-out', 'btn-break');
                btn.classList.add('btn-in');
                label.textContent = 'Time In';
                btn.onclick = handleTimeIn;
            }
        });
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

        const btn    = document.getElementById('timeInBtn');
        const label  = btn.querySelector('#timeInLabel');
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
                        lat:      pos.coords.latitude,
                        lng:      pos.coords.longitude,
                        accuracy: pos.coords.accuracy
                    })
                });

                const response = await res.json();
                console.log(response);

                if (response.error === 'too_fast') {
                    const wait = response.seconds_remaining || 5;
                    startCooldown(btn, wait, originalText);
                    isProcessing = false;
                    return;
                }

                if (response.error === 'log_corrupted') {
                    btn.disabled = false;
                    btn.style.pointerEvents   = '';
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

                if (response.error === 'shift_ended') {
                    btn.disabled = false;
                    btn.style.pointerEvents   = '';
                    btn.style.backgroundColor = '';
                    label.innerHTML = originalText;
                    isProcessing = false;
                    alert('Your shift has already ended. You have been marked absent.');
                    return;
                }

                btn.disabled = false;
                btn.style.pointerEvents   = '';
                btn.style.backgroundColor = '';

                let tapSuccess = false;

                if (response.tap === 'timed_in') {
                    btn.classList.remove('btn-in');
                    btn.classList.add('btn-out');
                    label.innerHTML = 'Time Out';
                    if (status) status.textContent = 'Timed In';
                    tapSuccess = true;
                }

                if (response.tap === 'timed_out') {
                    btn.classList.remove('btn-out');
                    btn.classList.add('btn-in');
                    label.innerHTML = 'Time In';
                    if (status) status.textContent = 'Timed Out';
                    // NEW: clear break state on time out
                    localStorage.removeItem('attendance_break_state');
                    tapSuccess = true;
                }

                if (tapSuccess) {
                    localStorage.setItem('attendance_tap_result', response.tap);
                    localStorage.setItem('attendance_update', Date.now());
                    if (document.getElementById('attendanceTimeline')) refreshChart();
                    if (document.querySelector('.ganttContainer')) refreshGantt();
                }

                if (typeof getTotalWorkedHours === 'function') getTotalWorkedHours();
                if (document.getElementById('logs_table_body')) fetchLogs();
                if (document.getElementById('attendance_table_body')) loadAttendance();

            } catch (err) {
                console.error(err);
            } finally {
                isProcessing = false;
            }
        };

        const onError = (err) => {
            console.error(err);
            alert('Location permission is required.');
            isProcessing = false;
            btn.disabled = false;
            btn.style.pointerEvents   = '';
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

    // ===== BREAK HANDLERS (NEW) =====
    const handleTakeBreak = () => {
        document.getElementById('breakDropdown').style.display = 'none';

        const btn    = document.getElementById('timeInBtn');
        const label  = btn.querySelector('#timeInLabel');
        const status = document.getElementById('dashboard_status');

        // Swap to break state
        btn.classList.remove('btn-out');
        btn.classList.add('btn-break');
        btn.disabled          = false;
        btn.style.pointerEvents = '';
        btn.onclick           = handleEndBreak;
        label.innerHTML       = 'End Break';

        if (status) status.textContent = 'On Break';

        localStorage.setItem('attendance_break_state', 'on_break');
        localStorage.setItem('attendance_update', Date.now());
    };

    const handleEndBreak = () => {
        const btn    = document.getElementById('timeInBtn');
        const label  = btn.querySelector('#timeInLabel');
        const status = document.getElementById('dashboard_status');

        // Swap back to timed-in state
        btn.classList.remove('btn-break');
        btn.classList.add('btn-out');
        btn.onclick     = handleTimeIn;
        label.innerHTML = 'Time Out';

        if (status) status.textContent = 'Timed In';

        localStorage.removeItem('attendance_break_state');
        localStorage.setItem('attendance_update', Date.now());
    };

    // ===== BREAK DROPDOWN HOVER =====
    const wrapper  = document.getElementById('timeInWrapper');
    const dropdown = document.getElementById('breakDropdown');
    const btn      = document.getElementById('timeInBtn');

    let hoverTimer = null;

    const showDropdown = () => {
        if (btn.classList.contains('btn-out')) {
            clearTimeout(hoverTimer);
            dropdown.style.display = 'block';
        }
    };

    const hideDropdown = () => {
        hoverTimer = setTimeout(() => {
            dropdown.style.display = 'none';
        }, 1500);
    };

    wrapper.addEventListener('mouseenter', showDropdown);
    wrapper.addEventListener('mouseleave', hideDropdown);
    dropdown.addEventListener('mouseenter', () => clearTimeout(hoverTimer));
    dropdown.addEventListener('mouseleave', hideDropdown);

    // ===== USER DROPDOWN =====
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

</script>