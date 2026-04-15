<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$timedIn = isset($_SESSION['timedIn']) && $_SESSION['timedIn'] === true;
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<div class="topBar">
    <h4 class="dashboardTitle">Employee Dashboard</h4>

    <div class="topBarRight">
        <button class="timeInButton" id="timeInBtn" onclick="handleTimeIn()">
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
        .then(res => res.text())
        .then(response => {
            const label = document.getElementById('timeInLabel');
            if (response.trim() === 'timed_in') {
                label.textContent = 'Time Out';
            } else {
                label.textContent = 'Time In';
                if (window.location.href.includes('record_page')) {
                    location.reload();
                }
            }
        });
}
</script>