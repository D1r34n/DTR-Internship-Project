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

    <div class="userDropdownWrapper">
        <span class="userEmail dropdown-toggle" id="userDropdownToggle">
            <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?>
        </span>
        <div class="userDropdownMenu" id="userDropdownMenu">
            <a href="#" class="userDropdownItem" onclick="return false;">
                <i class="bi bi-clock-history"></i> Request Overtime
            </a>
            <a href="#" class="userDropdownItem" onclick="return false;">
                <i class="bi bi-calendar-x"></i> Request Leave
            </a>
        </div>
    </div>

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
            console.log('Response:', response);
            const label = document.getElementById('timeInLabel');
            if (response.trim() === 'timed_in') {
                label.textContent = 'Time Out';
            } else {
                label.textContent = 'Time In';
                if (window.location.href.includes('record_page')) {
                    location.reload();
                }
            }
        })
        .catch(err => console.log('Error:', err));
}

const toggle = document.getElementById('userDropdownToggle');
const menu = document.getElementById('userDropdownMenu');

toggle.addEventListener('click', () => {
    menu.classList.toggle('show');
});

// Close when clicking outside
document.addEventListener('click', (e) => {
    if (!toggle.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('show');
    }
});
</script>