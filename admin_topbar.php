<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<div class="topBar">
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    $titles = [
    'admin_dashboard.php'   => 'Admin Dashboard',
    'admin_employees.php'   => 'Employee Dashboard',
    'admin_schedule.php'    => 'Employee Schedules Dashboard',
    'admin_requests.php'    => 'Employees Requests Dashboard',
];
    $pageTitle = $titles[$currentPage] ?? 'Admin Dashboard';
?>
<h4 class="dashboardTitle"><?= $pageTitle ?></h4>

    <div class="topBarRight">
        <div class="navUserProfile">
            <i class="bi bi-person-fill userProfileIcon"></i>
            <span class="userEmail"><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?></span>
            <a href="index.php">
                <i class="bi bi-box-arrow-right logoutIcon"></i>
            </a>
        </div>
    </div>
</div>