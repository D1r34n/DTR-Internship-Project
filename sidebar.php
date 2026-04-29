<!-- PHP -->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['user_role'] ?? null;
$current_page = $current_page ?? '';
if (!$role) {
    header("Location: ../index.php");
    exit();
}

if (!in_array($role, ['admin', 'employee', 'workforce'])) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

$dashboardLink = ($role === 'admin')
    ? 'admin_dashboard.php'
    : 'employee_dashboard.php';
?>

<div class="sideBar">
    <div class="topSideBar">
        <a href="<?= $dashboardLink ?>" class="topSideBarItem">
            <img src="../images/hsn_logo_white.png" class="sideBarLogo">
            <span class="topSideBarText">DTR System</span>
        </a>
    </div>

    <a class="horizontalDivider"></a>

    <?php if ($role === 'employee'): ?>
        <div id="employeeMenu" class="sideBarMenu">
            <a href="employee_dashboard.php"
            class="sideBarMenuItem <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-columns-gap sidebarIcon"></i>
                <span class="menuText">Dashboard</span>
            </a>

            <a href="employee_records.php"
            class="sideBarMenuItem <?= ($current_page === 'records') ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-steps sidebarIcon"></i>
                <span class="menuText">Records</span>
            </a>

            <a href="employee_schedule.php"
            class="sideBarMenuItem <?= ($current_page === 'schedule') ? 'active' : '' ?>">
                <i class="bi bi-calendar-week sidebarIcon"></i>
                <span class="menuText">Schedules</span>
            </a>

            <a href="employee_logs.php"
            class="sideBarMenuItem <?= ($current_page === 'logs') ? 'active' : '' ?>">
                <i class="bi bi-clipboard-minus sidebarIcon"></i>
                <span class="menuText">Logs</span>
            </a>
        </div>
    <?php endif; ?>

    <?php if ($role === 'workforce'): ?>
        <div id="workforceMenu" class="sideBarMenu">
            <a href="employee_dashboard.php"
            class="sideBarMenuItem <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-columns-gap sidebarIcon"></i>
                <span class="menuText">Dashboard</span>
            </a>

            <a href="employee_records.php"
            class="sideBarMenuItem <?= ($current_page === 'records') ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-steps sidebarIcon"></i>
                <span class="menuText">Records</span>
            </a>

            <a href="employee_schedule.php"
            class="sideBarMenuItem <?= ($current_page === 'schedule') ? 'active' : '' ?>">
                <i class="bi bi-calendar-week sidebarIcon"></i>
                <span class="menuText">Schedules</span>
            </a>

            <a href="employee_logs.php"
            class="sideBarMenuItem <?= ($current_page === 'logs') ? 'active' : '' ?>">
                <i class="bi bi-clipboard-minus sidebarIcon"></i>
                <span class="menuText">Logs</span>
            </a>

            <a href="workforce_schedule.php"
            class="sideBarMenuItem <?= ($current_page === 'workforce_schedule') ? 'active' : '' ?>">
                <i class="bi bi-calendar-plus sidebarIcon"></i>
                <span class="menuText">Manage Schedules</span>
            </a>
        </div>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
        <div id="adminMenu" class="sideBarMenu">
            <a href="admin_dashboard.php"
            class="sideBarMenuItem <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-columns-gap sidebarIcon"></i>
                <span class="menuText">Dashboard</span>
            </a>

            <a href="admin_employees.php"
            class="sideBarMenuItem <?= ($current_page === 'employees') ? 'active' : '' ?>">
                <i class="bi bi-people-fill sidebarIcon"></i>
                <span class="menuText">Employees</span>
            </a>

            <a href="admin_schedule.php"
            class="sideBarMenuItem <?= ($current_page === 'schedule') ? 'active' : '' ?>">
                <i class="bi bi-calendar-week sidebarIcon"></i>
                <span class="menuText">Schedules</span>
            </a>

            <?php $requestsOpen = in_array($current_page ?? '', ['requests', 'schedule_requests']); ?>
            <div class="sideBarDropdown">
                <button class="sideBarMenuItemDropdown <?= $requestsOpen ? 'active open locked' : '' ?>"
                        onclick="toggleSidebarDropdown(this)">
                    <i class="bi bi-envelope-paper sidebarIcon"></i>
                    <span class="menuText">Requests</span>
                    <i class="bi bi-chevron-down sideBarDropdownChevron"></i>
                </button>
                <div class="sideBarSubMenu <?= $requestsOpen ? 'open' : '' ?>">
                    <a href="admin_requests.php"
                       class="sideBarSubItem <?= ($current_page === 'requests') ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-text sidebarSubIcon"></i>
                        <span class="menuText">Employee Requests</span>
                    </a>
                    <a href="admin_schedule_requests.php"
                       class="sideBarSubItem <?= ($current_page === 'schedule_requests') ? 'active' : '' ?>">
                        <i class="bi bi-calendar-check sidebarSubIcon"></i>
                        <span class="menuText">Schedule Requests</span>
                    </a>
                </div>
            </div>

            <a href="admin_logs.php"
            class="sideBarMenuItem <?= ($current_page === 'employee_logs') ? 'active' : '' ?>">
                <i class="bi bi-journal-text sidebarIcon"></i>
                <span class="menuText">Logs</span>
            </a>

            <a href="admin_departments.php"
            class="sideBarMenuItem <?= ($current_page === 'departments') ? 'active' : '' ?>">
                <i class="bi bi-building-gear sidebarIcon"></i>
                <span class="menuText">Departments</span>
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Java Script -->
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const sidebar         = document.querySelector(".sideBar");
        const links           = document.querySelectorAll(".sideBarMenuItem, .sideBarSubItem");
        const dropdownWrapper = document.querySelector(".sideBarDropdown");

        // Page-change click animation
        links.forEach(link => {
            link.addEventListener("click", (e) => {
                e.preventDefault();
                sidebar.classList.add("force-collapse");
                setTimeout(() => { window.location.href = link.href; }, 250);
            });
        });

        if (dropdownWrapper) {
            const btn     = dropdownWrapper.querySelector(".sideBarMenuItemDropdown");
            const subMenu = btn.nextElementSibling;

            // Hover open
            dropdownWrapper.addEventListener("mouseenter", () => {
                btn.classList.add("open");
                subMenu.classList.add("open");
            });

            // Hover close (only if not locked)
            dropdownWrapper.addEventListener("mouseleave", () => {
                if (!btn.classList.contains("locked")) {
                    btn.classList.remove("open");
                    subMenu.classList.remove("open");
                }
            });
        }

        // Sidebar collapse also closes unlocked dropdown
        sidebar.addEventListener("mouseleave", () => {
            document.querySelectorAll(".sideBarMenuItemDropdown:not(.locked)").forEach(btn => {
                btn.classList.remove("open");
                btn.nextElementSibling.classList.remove("open");
            });
        });
    });

    // Click = toggle lock
    function toggleSidebarDropdown(btn) {
        const subMenu = btn.nextElementSibling;
        if (btn.classList.contains("locked")) {
            btn.classList.remove("locked", "open");
            subMenu.classList.remove("open");
        } else {
            btn.classList.add("locked", "open");
            subMenu.classList.add("open");
        }
    }
</script>
