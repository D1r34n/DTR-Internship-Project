<!-- PHP -->
<?php
    // Start session and get employee ID 
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $employeeId = $_SESSION['user_id'];

    // Set title and dashboard status based on current page
    $titles = [
        'dashboard' => 'Employee Dashboard',
        'records' => 'Records',
        'schedule' => 'Schedule'

    ];
    $title = $titles[$current_page] ?? '';

    // Check if the user is currently timed in or out
    require_once 'db.php';
    $stmt = $pdo->prepare("
        SELECT log_type 
        FROM logs 
        WHERE employee_id = ?
        ORDER BY log_time DESC 
        LIMIT 1
    ");
    $stmt->execute([$employeeId]);
    $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

    $timedIn = ($lastLog && $lastLog['log_type'] === 'login');
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
                            <i class="bi bi-clock-history"></i> Overtime
                        </a>

                        <a href="#" class="userDropdownItem">
                            <i class="bi bi-calendar-x"></i> Leave
                        </a>

                        <a href="#" class="userDropdownItem">
                            <i class="bi bi-briefcase"></i> Official Business
                        </a>

                        <a href="#" class="userDropdownItem">
                            <i class="bi bi-pencil-square"></i> Log Edit
                        </a>

                        <div class="horizontalDivider"></div>

                        <a href="index.php" class="logoutText">
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
        fetch('get_dashboard_data.php')
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

    // Handle time in/out button click
    function handleTimeIn() {
        fetch('timeinout.php')
            .then(res => res.json())
            .then(response => {

                // Variables 
                const timeInButton = document.getElementById('timeInBtn');
                const label = timeInButton.querySelector('#timeInLabel');
                const dashboardStatus = document.getElementById('dashboard_status');

                // Change button appearance and label based on new status
                if (response.status === 'timed_in') {
                    timeInButton.classList.remove('btn-in');
                    timeInButton.classList.add('btn-out');

                    label.textContent = 'Time Out';
                    if (dashboardStatus) {
                        dashboardStatus.textContent = 'Timed In';
                    }
                }else {
                    timeInButton.classList.remove('btn-out');
                    timeInButton.classList.add('btn-in');    

                    label.textContent = 'Time In';
                    if (dashboardStatus) {
                        dashboardStatus.textContent = 'Timed Out';
                    }
                }

                // Refresh dashboard data and logs if on the appropriate pages
                getTotalWorkedHours();

                if (document.getElementById('logs_table_body')) {
                    loadLogs();
                    console.log('Logs table refreshed');
                }

                if (document.getElementById('attendance_table_body')) {
                    loadAttendance();
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