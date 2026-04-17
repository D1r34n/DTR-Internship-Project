<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<div class="sideBar">
    <div class="topSideBar">
        <a href="main_page.php" class="logoWrapper">
            <img src="images/drt_sidebar_logo.png" class="sideBarLogo">
        </a>

        <div class="toggleWrapper">
            <i class="bi bi-arrow-bar-left icon-open toggleSideBarIcon"></i>
            <i class="bi bi-arrow-bar-right icon-close toggleSideBarIcon"></i>
        </div>
    </div>
    
    <div class="horizontalDivider"></div>

    <div class="sideBarMenu">
        <!-- Dashboard -->
        <a href="main_page.php" class="sideBarMenuItem">
            <img src="images/dashboard_icon.svg" class="dashboardIcon">
            <span class="menuText">Dashboard</span>
        </a>

        <!-- Records -->
        <a href="record_page.php" class="sideBarMenuItem">
            <img src="images/records_icon.svg" class="recordsIcon">
            <span class="menuText">Records</span>
        </a>

        <a href="schedule_page.php" class="sideBarMenuItem">
            <img src="images/schedule_icon.svg" class="scheduleIcon">
            <span class="menuText">Schedule</span>
</a>

        <!-- Logs -->
        <a href="logs_page.php" class="sideBarMenuItem">
            <img src="images/logs_icon.svg" class="logsIcon">
            <span class="menuText">Logs</span>
        </a>

    </div>
</div>

