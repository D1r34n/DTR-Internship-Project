<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<div class="topBar">
    <h4 class="dashboardTitle"> <?php echo ($current_page == 'dashboard') ? 'Employee Dashboard' : ''; ?></h4>

    <div class="topBarRight">
        <!-- Time In Button -->
        <form method="POST">
            <button type="submit" id="time_in_button" class="timeInButton">
                <i class="bi bi-stopwatch-fill timeInIcon"></i> Time In
            </button>
        </form>
        
        <div class="verticalDivider"></div>

        <div class="navUserProfile">
            <i class="bi bi-person-fill userProfileIcon"></i>
            <span class="userEmail"><?php echo isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'user.name@gmail.com'; ?></span>
            <a href="index.php">
                <i class="bi bi-box-arrow-right logoutIcon"></i>
            </a>
        </div>
    </div>
</div>


<script>
    timedIn = false;
    timeInButton = document.getElementById('time_in_button');
    timeInButton.addEventListener('click', function() {
        if (timedIn) {
            timeInButton.style.backgroundColor = '#97be41'; // Change back to original color
            timeInButton.innerHTML = '<i class="bi bi-stopwatch-fill timeInIcon"></i> Time In'; // Change text back to "Time In"
            timedIn = false;
        } else {
            timeInButton.style.backgroundColor = '#c62a2a'; // Change to red color
            timeInButton.innerHTML = '<i class="bi bi-stopwatch-fill timeInIcon"></i> Time Out'; // Change text to "Time Out"
            timedIn = true;
        }
    });
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