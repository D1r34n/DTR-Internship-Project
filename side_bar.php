<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

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
        <a href="main_page.php" class="sideBarMenuItem">
            <img src="images/dashboard_icon.svg" class="dashboardIcon">
            <span class="menuText">Dashboard</span>
        </a>

        <a href="records.php" class="sideBarMenuItem">
            <img src="images/records_icon.svg" class="recordsIcon">
            <span class="menuText">Records</span>
        </a>
    </div>
</div>

