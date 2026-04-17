<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<div class="sideBar">
    <div class="topSideBar">
        <a href="admin_dashboard.php" class="logoWrapper">
            <img src="images/drt_sidebar_logo.png" class="sideBarLogo">
        </a>
        <div class="toggleWrapper">
            <i class="bi bi-arrow-bar-left icon-open toggleSideBarIcon"></i>
            <i class="bi bi-arrow-bar-right icon-close toggleSideBarIcon"></i>
        </div>
    </div>

    <div class="horizontalDivider"></div>

    <div class="sideBarMenu">
        <a href="admin_employees.php" class="sideBarMenuItem">
            <i class="bi bi-people-fill dashboardIcon"></i>
            <span class="menuText">Employees</span>
        </a>

        <a href="admin_schedule.php" class="sideBarMenuItem">
            <i class="bi bi-calendar-week dashboardIcon"></i>
            <span class="menuText">Schedules</span>
        </a>
    </div>
</div>