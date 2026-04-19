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

<div class="sideBar">
    <div class="topSideBar">
        <a href="employee_dashboard.php" class="topSideBarItem">
            <img src="../images/hsn_logo_white.png" class="sideBarLogo">
            <span class="topSideBarText">DTR System</span>
        </a>
    </div>
    
    <a class="horizontalDivider"></a>

    <?php if ($role === 'employee'): ?>
        <div id="employeeMenu" class="sideBarMenu">
            <a href="employee_dashboard.php" class="sideBarMenuItem active" >
                <i class="bi bi-columns-gap sidebarIcon"></i>
                <span class="menuText">Dashboard</span>
            </a>

            <a href="employee_records.php" class="sideBarMenuItem">
                <i class="bi bi-bar-chart-steps sidebarIcon"></i>
                <span class="menuText">Records</span>
            </a>

            <a href="employee_schedule.php" class="sideBarMenuItem">
                <i class="bi bi-calendar-week sidebarIcon"></i>
                <span class="menuText">Schedules</span>
            </a>

            <a href="employee_logs.php" class="sideBarMenuItem">
                <i class="bi bi-clipboard-minus sidebarIcon"></i>
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

<!-- Java Script -->
 <script>
    document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.querySelector(".sideBar");
        const links = document.querySelectorAll(".sideBarMenuItem");

        links.forEach(link => {
            link.addEventListener("click", (e) => {
                e.preventDefault(); // stop immediate navigation

                // trigger collapse animation
                sidebar.classList.add("force-collapse");

                // wait for animation to finish
                setTimeout(() => {
                    window.location.href = link.href;
                }, 250); // match CSS transition duration
            });
        });
    });
</script>