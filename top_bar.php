<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
$employeeId = $_SESSION['user_id'];

// Set title and dashboard status based on current page
$titles = [
    'dashboard' => 'Employee Dashboard',
    'records' => 'Records',
    'schedule' => 'Schedule'

];

$title = $titles[$current_page] ?? '';

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

<div class="topBar">
    <h4 class="dashboardTitle"><?= $title ?></h4>

    <div class="topBarRight">
        <!-- TIME IN BUTTON -->
        <button class="timeInButton <?= $timedIn ? 'btn-out' : 'btn-in' ?>" id="timeInBtn" onclick="handleTimeIn()">
            <i class="bi bi-stopwatch-fill timeInIcon"></i>
            <span id="timeInLabel"><?= $timedIn ? 'Time Out' : 'Time In' ?></span>
        </button>
        
        <div class="verticalDivider"></div>

        <div class="navUserProfile">
    <i class="bi bi-person-fill userProfileIcon"></i>

    <div class="userDropdownWrapper">
        <span class="userEmail dropdown-toggle" id="userDropdownToggle">
            <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?>
        </span>
        <div class="userDropdownMenu" id="userDropdownMenu">
            <a href="#" class="userDropdownItem" onclick="return false;">
                <i class="bi bi-clock-history"></i> Request Overtime
            </a>
            <a href="#" class="userDropdownItem" onclick="return false;">
                <i class="bi bi-calendar-x"></i> Request Leave
            </a>
        </div>
    </div>

    <a href="index.php">
        <i class="bi bi-box-arrow-right logoutIcon"></i>
    </a>
</div>
    </div>
</div>

<script>
function handleTimeIn() {
    fetch('timeinout.php')
        .then(res => res.json())
        .then(response => {

            const timeInButton = document.getElementById('timeInBtn');
            const label = timeInButton.querySelector('#timeInLabel');

            const dashboardStatus = document.getElementById('dashboard_status');

            if (response.status === 'timed_in') {
                timeInButton.style.backgroundColor = '#dc3545';
                label.textContent = 'Time Out';

                if (dashboardStatus) {
                    dashboardStatus.textContent = 'Timed In';
                }

            } else {
                timeInButton.style.backgroundColor = '#97be41';
                label.textContent = 'Time In';

                if (dashboardStatus) {
                    dashboardStatus.textContent = 'Timed Out';
                }
            }

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

toggle.addEventListener('click', () => {
    menu.classList.toggle('show');
});

// Close dropdown menu when clicking outside
document.addEventListener('click', (e) => {
    if (!toggle.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('show');
    }
});

function getTotalWorkedHours() {
    fetch('get_dashboard_data.php')
        .then(res => res.json())
        .then(data => {
            $dashBoardWeekHours = document.getElementById('dashboard_week_hours');
            $dashBoardMonthHours = document.getElementById('dashboard_month_hours');

            if ($dashBoardWeekHours && $dashBoardMonthHours) {
                $dashBoardWeekHours.textContent = data.weeklyHours + ' hours';
                $dashBoardMonthHours.textContent = data.monthlyHours + ' hours';
            }
        });
}
</script>