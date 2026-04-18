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
            <span class="userEmail"><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?></span>
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
        })
        .catch(err => console.log('Error:', err));
}

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