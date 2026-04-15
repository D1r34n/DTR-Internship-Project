<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<div class="sideBar">
    <div class="topSideBar">
        <a href="index.php">
            <img src="images/drt_sidebar_logo.png" class="sideBarLogo">
        </a>
        <i class="bi bi-arrow-bar-left toggleSideBarIcon"></i>    
    </div>
    
    <div class="horizontalDivider"></div>

    <div class="sideBarMenu">
        <a href="main_page.php" class="sideBarMenuItem"> 
            <img src="images/dashboard_icon.svg" class="dashboardIcon">Dashboard
        </a>

        <a href="records.php" class="sideBarMenuItem">
            <img src="images/records_icon.svg" class="recordsIcon">Records
        </a>
    </div>
</div>