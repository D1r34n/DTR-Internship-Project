<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- AUTH CHECK ----
if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    header("Location: ../index.php");
    exit();
}

$employeeId = $_SESSION['user_id'];
$role       = $_SESSION['user_role'];

// ---- ROLE VALIDATION ----
if (!in_array($role, ['admin', 'employee'])) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// ---- PAGE TITLES ----
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

$title = $titles[$role][$current_page] ?? 'Dashboard';

// ---- DB ----
require_once '../db.php';

$today      = date('Y-m-d');
$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');

$stmt = $pdo->prepare("
    SELECT log_type 
    FROM logs 
    WHERE employee_id = ?
    AND log_time BETWEEN ? AND ?
    ORDER BY log_time DESC 
    LIMIT 1
");
$stmt->execute([$employeeId, $todayStart, $todayEnd]);
$lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT actual_time_in, actual_time_out
    FROM attendance
    WHERE employee_id = ? AND date = ?
");
$stmt->execute([$employeeId, $today]);
$todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

$timedIn = ($lastLog && $lastLog['log_type'] === 'login')
        || (
              $todayAttendance
              && !empty($todayAttendance['actual_time_in'])
              && empty($todayAttendance['actual_time_out'])
           );
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">

<!-- TOP BAR -->
<div class="topBar">
    <h4 class="dashboardTitle"><?= $title ?></h4>

    <div class="topBarRight">
        <button class="timeInButton <?= $timedIn ? 'btn-out' : 'btn-in' ?>" id="timeInBtn" onclick="handleTimeIn()">
            <i class="bi bi-stopwatch-fill timeInIcon"></i>
            <span id="timeInLabel"><?= $timedIn ? 'Time Out' : 'Time In' ?></span>
        </button>

        <div class="verticalDivider"></div>

        <div class="navUserProfile">
            <div class="userDropdownWrapper">
                <span class="userEmail dropdown-toggle" id="userDropdownToggle">
                    <i class="bi bi-person-fill userProfileIcon"></i>
                    <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?>
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
                        <a href="../index.php" class="logoutText">
                            <i class="bi bi-box-arrow-right logoutIcon"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODALS -->
<?php include '../ot_modal.php'; ?>
<?php include '../leave_modal.php'; ?>
<?php include '../ob_modal.php'; ?>
<?php include '../log_edit_modal.php'; ?>

<!-- JAVASCRIPT -->
<script defer>

    // ===== GANTT CURSORS =====
    function initGanttCursors() {
        const tooltip     = document.getElementById('gantt-tooltip');
        const gtSched     = document.getElementById('gt-sched');
        const gtActualIn  = document.getElementById('gt-actual-in');
        const gtActualOut = document.getElementById('gt-actual-out');
        const gtLateRow   = document.getElementById('gt-late-row');
        const gtLate      = document.getElementById('gt-late');
        const gtOtRow     = document.getElementById('gt-ot-row');
        const gtOt        = document.getElementById('gt-ot');

        document.querySelectorAll('.gantt-bar-container').forEach(container => {
            const line  = container.querySelector('.gantt-cursor-line');
            const label = container.querySelector('.gantt-cursor-label');

            const rangeStart = parseInt(container.dataset.rangeStart);
            const rangeEnd   = parseInt(container.dataset.rangeEnd);
            const range      = rangeEnd - rangeStart;

            const hasData = container.dataset.actualIn;

            container.addEventListener('mousemove', (e) => {
                const rect    = container.getBoundingClientRect();
                const x       = e.clientX - rect.left;
                const percent = Math.max(0, Math.min(1, x / rect.width));
                const time    = Math.floor(rangeStart + (percent * range));

                if (line)  line.style.left  = (percent * 100) + '%';
                if (label) label.style.left = (percent * 100) + '%';

                if (label) {
                    label.textContent = new Date(time * 1000).toLocaleTimeString('en-US', {
                        hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila'
                    });
                }

                if (hasData && tooltip) {
                    gtSched.textContent     = container.dataset.schedIn + ' – ' + container.dataset.schedOut;
                    gtActualIn.textContent  = container.dataset.actualIn;
                    gtActualOut.textContent = container.dataset.actualOut;

                    if (container.dataset.late) {
                        gtLate.textContent      = container.dataset.late;
                        gtLateRow.style.display = 'flex';
                    } else {
                        gtLateRow.style.display = 'none';
                    }

                    if (container.dataset.overtime) {
                        gtOt.textContent  = container.dataset.overtime;
                        const status      = container.dataset.overtimeStatus;
                        gtOtRow.className = 'gt-row gt-ot ' + (status === 'approved' ? 'approved' : status === 'rejected' ? 'rejected' : '');
                        gtOtRow.style.display = 'flex';
                    } else {
                        gtOtRow.style.display = 'none';
                    }

                    tooltip.style.left = e.clientX + 'px';
                    tooltip.style.top  = e.clientY  + 'px';
                    tooltip.classList.add('visible');
                }
            });

            container.addEventListener('mouseleave', () => {
                if (tooltip) tooltip.classList.remove('visible');
            });
        });
    }

    initGanttCursors();

    // ===== TIME IN/OUT =====
    function getTotalWorkedHours() {
        fetch('../get_dashboard_data.php')
            .then(res => res.json())
            .then(data => {
                const week  = document.getElementById('dashboard_week_hours');
                const month = document.getElementById('dashboard_month_hours');
                if (week && month) {
                    week.textContent  = data.weeklyHours  + ' hours';
                    month.textContent = data.monthlyHours + ' hours';
                }
            });
    }

    function handleTimeIn() {
        fetch('../timeinout.php')
            .then(async res => JSON.parse(await res.text()))
            .then(response => {
                const btn    = document.getElementById('timeInBtn');
                const label  = btn.querySelector('#timeInLabel');
                const status = document.getElementById('dashboard_status');

                if (response.status === 'timed_in') {
                    btn.classList.replace('btn-in', 'btn-out');
                    label.textContent = 'Time Out';
                    if (status) status.textContent = 'Timed In';
                } else {
                    btn.classList.replace('btn-out', 'btn-in');
                    label.textContent = 'Time In';
                    if (status) status.textContent = 'Timed Out';
                }

                getTotalWorkedHours();
                if (document.getElementById('logs_table_body')) loadLogs();
                if (document.getElementById('attendance_table_body')) loadAttendance();

                if (document.querySelector('.recordBox')) {
                    fetch(window.location.href)
                        .then(r => r.text())
                        .then(html => {
                            const doc    = new DOMParser().parseFromString(html, 'text/html');
                            const newBox = doc.querySelector('.recordBox');
                            newBox.querySelectorAll('.gantt-bar').forEach(b => b.style.transition = 'none');
                            document.querySelector('.recordBox').replaceWith(newBox);
                            requestAnimationFrame(() => requestAnimationFrame(() => {
                                newBox.querySelectorAll('.gantt-bar').forEach(b => b.style.transition = '');
                            }));
                            initGanttCursors();
                        });
                }
            })
            .catch(err => console.log('Error:', err));
    }

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

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js" defer></script>