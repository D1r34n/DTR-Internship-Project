<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<div class="topBar">
    <h4 class="dashboardTitle">Employee Dashboard</h4>

    <div class="topBarRight">
        <button class="timeInButton"><i class="bi bi-stopwatch-fill timeInIcon"></i> Time In</button>
        <div class="verticalDivider"></div>

        <div class="navUserProfile">
            <i class="bi bi-person-fill userProfileIcon"></i>
            <span class="userEmail">user.name@gmail.com</span>
            <a href="index.php">
                <i class="bi bi-box-arrow-right logoutIcon"></i>
            </a>
        </div>
    </div>
</div>