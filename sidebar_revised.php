<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
$role        = $_SESSION['user_role'] ?? null;
$currentPage = $currentPage ?? '';

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

$requestsOpen = in_array($currentPage, ['employee_requests', 'schedule_requests']);
?>

<div id="sidebar" class="d-flex flex-column flex-shrink-0 position-sticky top-0">

    <!-- Brand / Logo -->
    <div class="sidebar-brand">

        <a id="brandLink" href="<?= $dashboardLink ?>" class="brand-item">
            <img src="../assets/images/hsn_logo_white.png" height="20">
            <span>DTR System</span>
        </a>

        <button id="sidebarToggle" class="sidebar-toggle" title="Collapse sidebar">
            <i class="bi bi-list"></i>
        </button>

    </div>

    <!-- Navigation -->
    <nav class="d-flex flex-column flex-grow-1">

        <?php
        function navLink(string $href, string $icon, string $label, bool $active): void {
            $cls = $active ? 'active' : '';
            echo <<<HTML
            <a href="{$href}" class="sidebar-link {$cls}">
                <i class="bi {$icon}"></i>
                <span>{$label}</span>
            </a>
            HTML;
        }
        ?>

        <!-- ── EMPLOYEE MENU ───────────────────────────────────────────────── -->
        <?php if ($role === 'employee'): ?>
            <?php navLink('employee_dashboard.php', 'bi-columns-gap',      'Dashboard', $currentPage === 'dashboard'); ?>
            <?php navLink('employee_records.php',   'bi-bar-chart-steps',  'Records',   $currentPage === 'records');   ?>
            <?php navLink('employee_schedule.php',  'bi-calendar-week',    'Schedules', $currentPage === 'schedule');  ?>
            <?php navLink('employee_logs.php',      'bi-clipboard-minus',  'Logs',      $currentPage === 'logs');      ?>
        <?php endif; ?>

        <!-- ── WORKFORCE MENU ─────────────────────────────────────────────── -->
        <?php if ($role === 'workforce'): ?>
            <?php navLink('employee_dashboard.php', 'bi-columns-gap',       'Dashboard',        $currentPage === 'dashboard');        ?>
            <?php navLink('employee_records.php',   'bi-bar-chart-steps',   'Records',          $currentPage === 'records');          ?>
            <?php navLink('employee_schedule.php',  'bi-calendar-week',     'Schedules',        $currentPage === 'schedule');         ?>
            <?php navLink('employee_logs.php',      'bi-clipboard-minus',   'Logs',             $currentPage === 'logs');             ?>
            <?php navLink('workforce_schedule.php', 'bi-calendar-plus',     'Manage Schedules', $currentPage === 'workforce_schedule'); ?>
        <?php endif; ?>

        <!-- ── ADMIN MENU ─────────────────────────────────────────────────── -->
        <?php if ($role === 'admin'): ?>
            <?php navLink('admin_dashboard.php',   'bi-columns-gap',      'Dashboard',   $currentPage === 'dashboard');  ?>
            <?php navLink('admin_employees.php',   'bi-people-fill',      'Employees',   $currentPage === 'employees');  ?>
            <?php navLink('admin_schedule.php',    'bi-calendar-week',    'Schedules',   $currentPage === 'schedule');   ?>

            <!-- Requests dropdown -->
            <div class="sidebar-dropdown">
                <button
                    class="sidebar-link sidebar-dropdown-toggle <?= $requestsOpen ? 'active' : '' ?>"
                    data-bs-toggle="collapse"
                    data-bs-target="#requestsSubmenu"
                    aria-expanded="<?= $requestsOpen ? 'true' : 'false' ?>"
                >
                    <i class="bi bi-envelope-paper"></i>
                    <span>Requests</span>
                    <i class="bi bi-chevron-down transition-chevron"></i>
                </button>

                <div id="requestsSubmenu" class="collapse <?= $requestsOpen ? 'show' : '' ?>">
                    <a href="admin_requests.php"
                       class="sidebar-sub-link <?= ($currentPage === 'employee_requests') ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Employee Requests</span>
                    </a>
                    <a href="admin_schedule_requests.php"
                       class="sidebar-sub-link <?= ($currentPage === 'schedule_requests') ? 'active' : '' ?>">
                        <i class="bi bi-calendar-check"></i>
                        <span>Schedule Requests</span>
                    </a>
                </div>
            </div>

            <?php navLink('admin_logs.php',        'bi-journal-text',     'Logs',        $currentPage === 'employee_logs'); ?>
            <?php navLink('admin_departments.php', 'bi-building-gear',    'Departments', $currentPage === 'departments');   ?>
        <?php endif; ?>

    </nav>
</div>

<script>
const sidebar = document.getElementById('sidebar');
const toggle  = document.getElementById('sidebarToggle');

function setCollapsed(collapsed) {
    sidebar.classList.toggle('collapsed', collapsed);
    localStorage.setItem('sidebar-collapsed', collapsed);
}

// restore state
setCollapsed(localStorage.getItem('sidebar-collapsed') === 'true');

// ONLY ONE CONTROLLER NOW
toggle.addEventListener('click', () => {
    const isCollapsed = sidebar.classList.contains('collapsed');
    setCollapsed(!isCollapsed);
});
</script>