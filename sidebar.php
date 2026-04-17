<!-- PHP -->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['user_role'] ?? null;

if (!$role) {
    header("Location: index.php");
    exit();
}

if (!in_array($role, ['admin', 'employee'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$dashboardLink = ($role === 'admin')
    ? 'admin_dashboard.php'
    : 'employee_dashboard.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<div class="sideBar">
    <div class="topSideBar">
        <a href="<?= $dashboardLink ?>" class="logoWrapper">
            <img src="../images/drt_sidebar_logo.png" class="sideBarLogo">
        </a>

        <div class="toggleWrapper">
            <i class="bi bi-arrow-bar-left icon-open toggleSideBarIcon"></i>
            <i class="bi bi-arrow-bar-right icon-close toggleSideBarIcon"></i>
        </div>
    </div>
    
    <div class="horizontalDivider"></div>

    <?php if ($role === 'employee'): ?>
        <div id="employeeMenu" class="sideBarMenu">
            <a href="employee_dashboard.php" class="sideBarMenuItem">
                <img src="../images/dashboard_icon.svg" class="dashboardIcon">
                <span class="menuText">Dashboard</span>
            </a>

            <a href="employee_records.php" class="sideBarMenuItem">
                <img src="../images/records_icon.svg" class="recordsIcon">
                <span class="menuText">Records</span>
            </a>

            <a href="employee_schedule.php" class="sideBarMenuItem">
                <img src="../images/schedule_icon.svg" class="scheduleIcon">
                <span class="menuText">Schedules</span>
            </a>

            <a href="employee_logs.php" class="sideBarMenuItem">
                <img src="../images/logs_icon.svg" class="logsIcon">
                <span class="menuText">Logs</span>
            </a>
        </div>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
        <div id="adminMenu" class="sideBarMenu">
            <a href="admin_dashboard.php" class="sideBarMenuItem">
                <img src="../images/dashboard_icon.svg" class="dashboardIcon">
                <span class="menuText">Dashboard</span>
            </a>

            <a href="admin_employees.php" class="sideBarMenuItem">
                <i class="bi bi-people-fill dashboardIcon"></i>
                <span class="menuText">Employees</span>
            </a>

            <a href="admin_schedule.php" class="sideBarMenuItem">
                <i class="bi bi-calendar-week dashboardIcon"></i>
                <span class="menuText">Schedules</span>
            </a>

            <a href="admin_requests.php" class="sideBarMenuItem">
                <i class="bi bi-envelope-paper dashboardIcon"></i>
                <span class="menuText">Requests</span>
            </a>
        </div>
    <?php endif; ?>
</div>

