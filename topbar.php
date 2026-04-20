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
$role = $_SESSION['user_role'];

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
        'records'    => 'Employee Records',
        'schedule'   => 'Employee Schedule',
        'logs'       => 'Employee Activity Logs',
    ],

    'admin' => [
        'dashboard'  => 'Admin Dashboard',
        'employees'   => 'Employees',
        'schedule'    => 'Schedules',
        'requests'    => 'Requests',
        'logs'        => 'Logs',
    ]
];

$title = $titles[$role][$current_page] ?? 'Dashboard';

// ---- DB ----
require_once '../db.php';

$today = date('Y-m-d');
$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');

// Scope to today only
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

// Cross-check attendance table
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
<!-- Top Bar -->
<div class="topBar">
    <!-- Dashboard Title -->
    <h4 class="dashboardTitle"><?= $title ?></h4>
    
    <!-- Top Bar Right -->
    <div class="topBarRight">

        <!-- Time in Button-->
        <button class="timeInButton <?= $timedIn ? 'btn-out' : 'btn-in' ?>" id="timeInBtn" onclick="handleTimeIn()">
            <i class="bi bi-stopwatch-fill timeInIcon"></i>
            <span id="timeInLabel"><?= $timedIn ? 'Time Out' : 'Time In' ?></span>
        </button>
        
        <div class="verticalDivider"></div>

        <!-- User Profile -->
        <div class="navUserProfile">
            
            <!-- User Dropdown -->
            <div class="userDropdownWrapper">

                <!-- User dropdown toggle -->
                 
                <span class="userEmail dropdown-toggle" id="userDropdownToggle">
                    <!-- User Icon -->
                    <i class="bi bi-person-fill userProfileIcon"></i>

                    <!-- User name or email -->
                    <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?>
                </span>

                <div class="userDropdownMenu" id="userDropdownMenu">

                    <!-- Request -->
                    <div class="dropdownSection">
                        <a href="#" class="userDropdownItem">
                            <i class="bi bi-clock-history"></i> Request OT
                        </a>

                        <a href="#" class="userDropdownItem">
                            <i class="bi bi-calendar-x"></i> Request Leave
                        </a>

                        <a href="#" class="userDropdownItem">
                            <i class="bi bi-briefcase"></i> Request OB
                        </a>

                        <a href="#" class="userDropdownItem">
                            <i class="bi bi-pencil-square"></i> Request Log Edit
                        </a>

                        <div class="horizontalDivider"></div>

                        <a href="../index.php" class="logoutText">
                            <i class="bi bi-box-arrow-right logoutIcon"></i> Logout
                        </a>
                    </div>

                </div> <!-- End of user dropdown menu -->
                
            </div> <!-- End of user dropdown wrapper -->
        </div> <!-- End of user profile -->
    </div> <!-- End of top bar right-->
</div> <!-- End of top bar -->

<!-- JavaScript -->
<script defer>
    // Get total worked hours for the week and month
    function getTotalWorkedHours() {
        fetch('../get_dashboard_data.php')
            .then(res => res.json())
            .then(data => {
                dashBoardWeekHours = document.getElementById('dashboard_week_hours');
                dashBoardMonthHours = document.getElementById('dashboard_month_hours');

                if (dashBoardWeekHours && dashBoardMonthHours) {
                    dashBoardWeekHours.textContent = data.weeklyHours + ' hours';
                    dashBoardMonthHours.textContent = data.monthlyHours + ' hours';
                }
         });
    }

    function initGanttCursors() {
        document.querySelectorAll('.gantt-bar-container').forEach(container => {
            const line  = container.querySelector('.gantt-cursor-line');
            const label = container.querySelector('.gantt-cursor-label');

            const rangeStart = parseInt(container.dataset.rangeStart);
            const rangeEnd   = parseInt(container.dataset.rangeEnd);
            const range      = rangeEnd - rangeStart;

            container.addEventListener('mousemove', (e) => {
                const rect = container.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const percent = Math.max(0, Math.min(1, x / rect.width));
                const time = Math.floor(rangeStart + (percent * range));

                line.style.left  = (percent * 100) + '%';
                label.style.left = (percent * 100) + '%';

                label.textContent = new Date(time * 1000).toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true,
                    timeZone: 'Asia/Manila'
                });
            });
        });
    }

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
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true,
                        timeZone: 'Asia/Manila'
                    });
                }

                if (hasData && tooltip) {
                    gtSched.textContent     = container.dataset.schedIn + ' – ' + container.dataset.schedOut;
                    gtActualIn.textContent  = container.dataset.actualIn;
                    gtActualOut.textContent = container.dataset.actualOut;

                    if (container.dataset.late) {
                        gtLate.textContent = container.dataset.late;
                        gtLateRow.style.display = 'flex';
                    } else {
                        gtLateRow.style.display = 'none';
                    }

                    if (container.dataset.overtime) {
                        gtOt.textContent = container.dataset.overtime;
                        const status = container.dataset.overtimeStatus;
                        gtOtRow.className = 'gt-row gt-ot ' + (status === 'approved' ? 'approved' : status === 'rejected' ? 'rejected' : '');
                        gtOtRow.style.display = 'flex';
                    } else {
                        gtOtRow.style.display = 'none';
                    }

                    tooltip.style.left = e.clientX + 'px';
                    tooltip.style.top  = e.clientY + 'px';
                    tooltip.classList.add('visible');
                }
            });

            container.addEventListener('mouseleave', () => {
                if (tooltip) tooltip.classList.remove('visible');
            });
        });
    }

    // Handle time in/out button click
    function handleTimeIn() {
        fetch('../timeinout.php')
            .then(async res => {
                const text = await res.text();
                return JSON.parse(text);
            })
            .then(response => {
                const timeInButton = document.getElementById('timeInBtn');
                const label = timeInButton.querySelector('#timeInLabel');
                const dashboardStatus = document.getElementById('dashboard_status');

                if (response.status === 'timed_in') {
                    timeInButton.classList.remove('btn-in');
                    timeInButton.classList.add('btn-out');
                    label.textContent = 'Time Out';
                    if (dashboardStatus) dashboardStatus.textContent = 'Timed In';
                } else {
                    timeInButton.classList.remove('btn-out');
                    timeInButton.classList.add('btn-in');
                    label.textContent = 'Time In';
                    if (dashboardStatus) dashboardStatus.textContent = 'Timed Out';
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

                            // Briefly suppress transitions on new content
                            newBox.querySelectorAll('.gantt-bar').forEach(bar => {
                                bar.style.transition = 'none';
                            });

                            document.querySelector('.recordBox').replaceWith(newBox);

                            // Re-enable transitions after paint
                            requestAnimationFrame(() => {
                                requestAnimationFrame(() => {
                                    newBox.querySelectorAll('.gantt-bar').forEach(bar => {
                                        bar.style.transition = '';
                                    });
                                });
                            });

                            initGanttCursors();
                        });
                }

            })
            .catch(err => console.log('Error:', err));
    }
    
    const toggle = document.getElementById('userDropdownToggle');
    const menu = document.getElementById('userDropdownMenu');

    let isOpen = false; // track click state

    // ===== HOVER BEHAVIOR =====
    toggle.addEventListener('mouseenter', () => {
        menu.classList.add('show');
    });

    toggle.addEventListener('mouseleave', () => {
        if (!isOpen) {
            menu.classList.remove('show');
        }
    });

    menu.addEventListener('mouseenter', () => {
        menu.classList.add('show');
    });

    menu.addEventListener('mouseleave', () => {
        if (!isOpen) {
            menu.classList.remove('show');
        }
    });

    // ===== CLICK BEHAVIOR (locks dropdown) =====
    toggle.addEventListener('click', (e) => {
        e.stopPropagation();

        isOpen = !isOpen;

        if (isOpen) {
            menu.classList.add('show');
        } else {
            menu.classList.remove('show');
        }
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!toggle.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove('show');
            isOpen = false;
        }
    });
    
</script>