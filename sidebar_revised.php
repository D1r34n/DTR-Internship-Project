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

<div id="sidebar" class="d-flex flex-column flex-shrink-0">

    <!-- Brand / Logo -->
    <div class="sidebar-brand" id="sidebar-brand">
        <a id="brand-link" href="<?= $dashboardLink ?>" class="brand-item">
            <img src="../assets/images/hsn_logo_white.png" height="20" alt="HSN Logo">
            <span>DTR System</span>
        </a>

        <!-- Maximize icon — shown on hover when collapsed -->
        <button id="sidebar-toggle"
                class="sidebar-toggle"
                data-bs-toggle="tooltip"
                data-bs-placement="right"
                data-bs-title="Toggle sidebar">
            <i class="bi bi-layout-sidebar-inset" id="toggle-icon"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="d-flex flex-column flex-grow-1">

        <?php
        function navLink(string $href, string $icon, string $label, bool $active): void {
            $cls = $active ? 'active' : '';

            echo <<<HTML
            <a href="{$href}"
            class="sidebar-link {$cls}"
            data-bs-toggle="tooltip"
            data-bs-placement="right"
            data-bs-title="{$label}">
                <i class="bi {$icon}"></i>
                <span>{$label}</span>
            </a>
            HTML;
        }
        ?>

        <!-- EMPLOYEE MENU -->
        <?php if ($role === 'employee'): ?>
            <?php navLink('employee_dashboard.php', 'bi-columns-gap',     'Dashboard', $currentPage === 'dashboard'); ?>
            <?php navLink('employee_records.php',   'bi-bar-chart-steps', 'Records',   $currentPage === 'records');   ?>
            <?php navLink('employee_schedule.php',  'bi-calendar-week',   'Schedules', $currentPage === 'schedule');  ?>
            <?php navLink('logs_page.php',      'bi-clipboard-minus', 'Logs',      $currentPage === 'logs');      ?>
        <?php endif; ?>

        <!-- WORKFORCE MENU -->
        <?php if ($role === 'workforce'): ?>
            <?php navLink('employee_dashboard.php', 'bi-columns-gap',     'Dashboard', $currentPage === 'dashboard'); ?>
            <?php navLink('employee_records.php',   'bi-bar-chart-steps', 'Records',   $currentPage === 'records'); ?>
            <?php navLink('employee_schedule.php',  'bi-calendar-week',   'Schedules', $currentPage === 'schedule'); ?>
            <?php navLink('employee_logs.php',      'bi-clipboard-minus', 'Logs',      $currentPage === 'logs'); ?>

    <?php 
        $manageEmpOpen = in_array($currentPage, ['workforce_schedule', 'workforce_logs']); 
    ?>

    <!-- Manage Employees dropdown (ADMIN STYLE) -->
    <div class="sidebar-dropdown">
        <button
            class="sidebar-link sidebar-dropdown-toggle <?= $manageEmpOpen ? 'active' : '' ?>"
            data-bs-toggle="collapse"
            data-bs-target="#workforce-submenu"
            aria-expanded="<?= $manageEmpOpen ? 'true' : 'false' ?>"
            data-tooltip-title="Manage Employees"
        >
            <i class="bi bi-people-fill"></i>
            <span>Manage Employees</span>
            <i class="bi bi-chevron-down transition-chevron"></i>
        </button>

        <div id="workforce-submenu" class="collapse <?= $manageEmpOpen ? 'show' : '' ?>">
            <a href="workforce_schedule.php"
               class="sidebar-sub-link <?= ($currentPage === 'workforce_schedule') ? 'active' : '' ?>">
                <i class="bi bi-calendar-plus"></i>
                <span>Employee Schedules</span>
            </a>

            <a href="workforce_logs.php"
               class="sidebar-sub-link <?= ($currentPage === 'workforce_logs') ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i>
                <span>Employee Logs</span>
            </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- ADMIN MENU -->
        <?php if ($role === 'admin'): ?>
            <!-- Dashboard -->
            <?php navLink('admin_dashboard.php',   'bi-columns-gap',   'Dashboard',   $currentPage === 'dashboard');  ?>
            
            <!-- Manage Employees -->
            <?php navLink('../admin_pages/admin_manage_employees.php',    'bi-people-fill', 'Manage Employees',   $currentPage === 'manage employees');   ?>

            <!-- Requests dropdown -->
            <div class="sidebar-dropdown">
                <button
                    class="sidebar-link sidebar-dropdown-toggle <?= $requestsOpen ? 'active' : '' ?>"
                    data-bs-toggle="collapse"
                    data-bs-target="#requests-submenu"
                    aria-expanded="<?= $requestsOpen ? 'true' : 'false' ?>"
                    data-tooltip-title="Requests"
                >
                    <i class="bi bi-envelope-paper"></i>
                    <span>Requests</span>
                    <i class="bi bi-chevron-down transition-chevron"></i>
                </button>

                <div id="requests-submenu" class="collapse <?= $requestsOpen ? 'show' : '' ?>">
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

            <?php navLink('../employee_pages/logs_page.php',        'bi-journal-text',  'Logs',        $currentPage === 'employee_logs'); ?>
            <?php navLink('admin_departments.php', 'bi-building-gear', 'Departments', $currentPage === 'departments');   ?>
        <?php endif; ?>

    </nav>
</div>

<script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');

        const initTooltips = () => {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                bootstrap.Tooltip.getOrCreateInstance(el, {
                    placement: 'right',
                    trigger: 'hover',
                    container: 'body',
                    delay: { show: 100, hide: 100 }
                });
            });
            document.querySelectorAll('[data-tooltip-title]').forEach(el => {
                bootstrap.Tooltip.getOrCreateInstance(el, {
                    title: el.getAttribute('data-tooltip-title'),
                    placement: 'right',
                    trigger: 'hover',
                    container: 'body',
                    delay: { show: 100, hide: 100 }
                });
            });
        };

        const destroyTooltips = () => {
            document.querySelectorAll('[data-bs-toggle="tooltip"], [data-tooltip-title]').forEach(el => {
                const instance = bootstrap.Tooltip.getInstance(el);
                if (instance) instance.dispose();
            });
        };

        const toggleTooltips = () => {
            if (sidebar.classList.contains('collapsed')) {
                initTooltips();
            } else {
                destroyTooltips();
            }
        };

        // initial state
        toggleTooltips();

        // observe changes
        const observer = new MutationObserver(toggleTooltips);
        observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    });
   
    const sidebar    = document.getElementById('sidebar');
    const toggle     = document.getElementById('sidebar-toggle');
    const toggleIcon = document.getElementById('toggle-icon');
    const brand      = document.getElementById('sidebar-brand'); 

    const brandLink  = document.getElementById('brand-link');
    const brandLogo  = brandLink ? brandLink.querySelector('img') : null;

    const sidebar_dropdowns = document.querySelectorAll('.sidebar-dropdown');

    if (sidebar && toggle && toggleIcon && brand) {

        function setCollapsed(collapsed) {
            sidebar.classList.toggle('collapsed', collapsed);

            toggleIcon.className = collapsed
                ? 'bi bi-layout-sidebar-inset'
                : 'bi bi-layout-sidebar-inset-reverse';

            localStorage.setItem('sidebar-collapsed', collapsed);

            if (collapsed) {
                // 🔥 RESET ALL DROPDOWNS
                document.querySelectorAll('#sidebar .collapse.show').forEach(el => {
                    const instance = bootstrap.Collapse.getOrCreateInstance(el);
                    instance.hide();
                });
            } else {
                if (brandLogo) brandLogo.style.opacity = '1';
                toggle.style.opacity = '1';
                toggle.style.pointerEvents = 'auto';
            }
        }

        // Restore saved state
        setCollapsed(localStorage.getItem('sidebar-collapsed') === 'true');

        // Ensure correct initial hover state
        if (sidebar.classList.contains('collapsed')) {
            toggle.style.opacity = '0';
            toggle.style.pointerEvents = 'none';
        }

        // Toggle click
        toggle.addEventListener('click', () => {
            setCollapsed(!sidebar.classList.contains('collapsed'));
        });

        // Hover behavior
        brand.addEventListener('mouseenter', () => {
            if (sidebar.classList.contains('collapsed')) {
                toggle.style.opacity = '1';
                toggle.style.pointerEvents = 'auto';
                if (brandLogo) brandLogo.style.opacity = '0';
            }
        });

        brand.addEventListener('mouseleave', () => {
            if (sidebar.classList.contains('collapsed')) {
                toggle.style.opacity = '0';
                toggle.style.pointerEvents = 'none';
                if (brandLogo) brandLogo.style.opacity = '1';
            }
        });

        // Dropdown auto-expand
        document.querySelectorAll('.sidebar-dropdown-toggle').forEach(toggleBtn => {

            toggleBtn.addEventListener("click", function (e) {

                const targetSelector = this.getAttribute('data-bs-target');
                const targetEl = document.querySelector(targetSelector);

                if (sidebar.classList.contains('collapsed')) {
                    e.preventDefault();
                    e.stopPropagation(); // 🚨 VERY IMPORTANT

                    setCollapsed(false);

                    if (targetEl) {
                        setTimeout(() => {
                            bootstrap.Collapse
                                .getOrCreateInstance(targetEl)
                                .show();
                        }, 200);
                    }

                    return;
                }

            });

        });
    }
</script>