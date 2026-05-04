<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
$role         = $_SESSION['user_role'] ?? null;
$currentPage  = $currentPage ?? '';

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

<div class="side-bar">

    <!-- TOP SECTION -->
    <div class="top-side-bar">
        <a href="<?= $dashboardLink ?>" class="top-side-bar-item">
            <img src="../assets/images/hsn_logo_white.png" class="side-bar-logo" alt="HSN Logo">
            <span class="top-side-bar-text">DTR System</span>
        </a>
    </div>

    <div class="horizontal-divider"></div>

    <!-- EMPLOYEE MENU -->
    <?php if ($role === 'employee'): ?>
        <div id="employee-menu" class="side-bar-menu">

            <a href="employee_dashboard.php"
               class="side-bar-menu-item <?= ($currentPage === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-columns-gap sidebar-icon"></i>
                <span class="menu-text">Dashboard</span>
            </a>

            <a href="employee_records.php"
               class="side-bar-menu-item <?= ($currentPage === 'records') ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-steps sidebar-icon"></i>
                <span class="menu-text">Records</span>
            </a>

            <a href="employee_schedule.php"
               class="side-bar-menu-item <?= ($currentPage === 'schedule') ? 'active' : '' ?>">
                <i class="bi bi-calendar-week sidebar-icon"></i>
                <span class="menu-text">Schedules</span>
            </a>

            <a href="employee_logs.php"
               class="side-bar-menu-item <?= ($currentPage === 'logs') ? 'active' : '' ?>">
                <i class="bi bi-clipboard-minus sidebar-icon"></i>
                <span class="menu-text">Logs</span>
            </a>

        </div>
    <?php endif; ?>

    <!-- WORKFORCE MENU -->
    <?php if ($role === 'workforce'): ?>
        <div id="workforce-menu" class="side-bar-menu">

            <a href="employee_dashboard.php"
               class="side-bar-menu-item <?= ($currentPage === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-columns-gap sidebar-icon"></i>
                <span class="menu-text">Dashboard</span>
            </a>

            <a href="employee_records.php"
               class="side-bar-menu-item <?= ($currentPage === 'records') ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-steps sidebar-icon"></i>
                <span class="menu-text">Records</span>
            </a>

            <a href="employee_schedule.php"
               class="side-bar-menu-item <?= ($currentPage === 'schedule') ? 'active' : '' ?>">
                <i class="bi bi-calendar-week sidebar-icon"></i>
                <span class="menu-text">Schedules</span>
            </a>

            <a href="employee_logs.php"
               class="side-bar-menu-item <?= ($currentPage === 'logs') ? 'active' : '' ?>">
                <i class="bi bi-clipboard-minus sidebar-icon"></i>
                <span class="menu-text">Logs</span>
            </a>

            <a href="workforce_schedule.php"
               class="side-bar-menu-item <?= ($currentPage === 'workforce_schedule') ? 'active' : '' ?>">
                <i class="bi bi-calendar-plus sidebar-icon"></i>
                <span class="menu-text">Manage Schedules</span>
            </a>

        </div>
    <?php endif; ?>

    <!-- ADMIN MENU -->
    <?php if ($role === 'admin'): ?>
        <div id="admin-menu" class="side-bar-menu">

            <a href="admin_dashboard.php"
               class="side-bar-menu-item <?= ($currentPage === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-columns-gap sidebar-icon"></i>
                <span class="menu-text">Dashboard</span>
            </a>

            <a href="admin_employees.php"
               class="side-bar-menu-item <?= ($currentPage === 'employees') ? 'active' : '' ?>">
                <i class="bi bi-people-fill sidebar-icon"></i>
                <span class="menu-text">Employees</span>
            </a>

            <a href="admin_schedule.php"
               class="side-bar-menu-item <?= ($currentPage === 'schedule') ? 'active' : '' ?>">
                <i class="bi bi-calendar-week sidebar-icon"></i>
                <span class="menu-text">Schedules</span>
            </a>

            <?php $requestsOpen = in_array($currentPage, ['employee_requests', 'schedule_requests']); ?>
            <div class="side-bar-dropdown">
                <button
                    class="side-bar-menu-item-dropdown <?= $requestsOpen ? 'active open locked' : '' ?>"
                    onclick="toggleSidebarDropdown(this)"
                >
                    <i class="bi bi-envelope-paper sidebar-icon"></i>
                    <span class="menu-text">Requests</span>
                    <i class="bi bi-chevron-down side-bar-dropdown-chevron"></i>
                </button>

                <div class="side-bar-sub-menu <?= $requestsOpen ? 'open' : '' ?>">
                    <a href="admin_requests.php"
                       class="side-bar-sub-item <?= ($currentPage === 'employee_requests') ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-text sidebar-sub-icon"></i>
                        <span class="menu-text">Employee Requests</span>
                    </a>
                    <a href="admin_schedule_requests.php"
                       class="side-bar-sub-item <?= ($currentPage === 'schedule_requests') ? 'active' : '' ?>">
                        <i class="bi bi-calendar-check sidebar-sub-icon"></i>
                        <span class="menu-text">Schedule Requests</span>
                    </a>
                </div>
            </div>

            <a href="admin_logs.php"
               class="side-bar-menu-item <?= ($currentPage === 'employee_logs') ? 'active' : '' ?>">
                <i class="bi bi-journal-text sidebar-icon"></i>
                <span class="menu-text">Logs</span>
            </a>

            <a href="admin_departments.php"
               class="side-bar-menu-item <?= ($currentPage === 'departments') ? 'active' : '' ?>">
                <i class="bi bi-building-gear sidebar-icon"></i>
                <span class="menu-text">Departments</span>
            </a>

        </div>
    <?php endif; ?>

</div>

<!-- JAVASCRIPT -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar         = document.querySelector('.side-bar');
        const links           = document.querySelectorAll('.side-bar-menu-item, .side-bar-sub-item');
        const dropdownWrapper = document.querySelector('.side-bar-dropdown');

        // Page-change click animation
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                sidebar.classList.add('force-collapse');
                setTimeout(() => { window.location.href = link.href; }, 250);
            });
        });

        document.querySelectorAll(".sideBarDropdown").forEach(dropdownWrapper => {
            const btn     = dropdownWrapper.querySelector('.side-bar-menu-item-dropdown');
            const subMenu = btn.nextElementSibling;

            // Hover open
            dropdownWrapper.addEventListener('mouseenter', () => {
                btn.classList.add('open');
                subMenu.classList.add('open');
            });

            // Hover close — only if not locked
            dropdownWrapper.addEventListener('mouseleave', () => {
                if (!btn.classList.contains('locked')) {
                    btn.classList.remove('open');
                    subMenu.classList.remove('open');
                }
            });
        });

        // Sidebar collapse also closes unlocked dropdowns
        sidebar.addEventListener('mouseleave', () => {
            document.querySelectorAll('.side-bar-menu-item-dropdown:not(.locked)').forEach(btn => {
                btn.classList.remove('open');
                btn.nextElementSibling.classList.remove('open');
            });
        });

        // Trunk gradient: green from top → active item midpoint
        document.querySelectorAll(".sideBarSubMenu").forEach(menu => {
            const activeItem = menu.querySelector(".sideBarSubItem.active");
            if (!activeItem) return;
            const midpoint = activeItem.offsetTop + activeItem.offsetHeight / 2;
            menu.style.setProperty("--active-px", `${midpoint.toFixed(1)}px`);
        });
    });

    // Click = toggle lock
    function toggleSidebarDropdown(btn) {
        const subMenu = btn.nextElementSibling;
        if (btn.classList.contains('locked')) {
            btn.classList.remove('locked', 'open');
            subMenu.classList.remove('open');
        } else {
            btn.classList.add('locked', 'open');
            subMenu.classList.add('open');
        }
    }
</script>