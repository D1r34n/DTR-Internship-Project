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
        <button class="timeInButton <?= $timedIn ? 'timed-in-active' : '' ?>" 
                id="timeInBtn" 
                onclick="handleTimeIn()"
                <?= $timedIn ? 'disabled' : '' ?>>
            <i class="bi bi-stopwatch-fill timeInIcon"></i>
            <span id="timeInLabel"><?= $timedIn ? 'Timed In' : 'Time In' ?></span>
        </button>
        <div class="verticalDivider"></div>

        <div class="navUserProfile">
            <i class="bi bi-person-fill userProfileIcon"></i>
            <span class="userEmail"><?= htmlspecialchars($_SESSION['user_email']) ?></span>
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
            if (response.trim() === 'timed_in') {
                const btn = document.getElementById('timeInBtn');
                const label = document.getElementById('timeInLabel');
                btn.classList.add('timed-in-active');
                btn.disabled = true;
                label.textContent = 'Timed In';
                location.reload(); // refresh to show record in table
            }
        });
}
</script>

<?php

date_default_timezone_set('Asia/Manila');
$time = date('Y-m-d H:i:s');

// If not set yet, default = false
if (!isset($_SESSION['timedIn'])) {
    $_SESSION['timedIn'] = false;
}

if ($_SESSION['timedIn'] === false) {
    // TIME IN
    $_SESSION['timedIn'] = true;
    $_SESSION['time_in'] = $time;

    echo "Time In: " . $time;

} else {
    // TIME OUT
    $_SESSION['timedIn'] = false;
    $timeIn = $_SESSION['time_in'];

    echo "Time In: " . $timeIn . "<br>";
    echo "Time Out: " . $time;

    // Optional: save both to DB here

    unset($_SESSION['time_in']);
}
?>