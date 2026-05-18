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

if (!in_array($role, ['admin', 'employee'])) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

if (!empty($_SESSION['must_change_password'])) {
    header("Location: ../authentication_pages/change_password.php");
    exit();
}

$requestsOpen = in_array($currentPage, ['employee_requests', 'schedule_requests']);
?>

<div id="sidebar" class="d-flex flex-column flex-shrink-0">

    <!-- Brand / Logo -->
    <div class="sidebar-brand" id="sidebar-brand">
        <a id="brand-link" href="../dashboard_page.php" class="brand-item">
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

        <!-- ALL EMPLOYEE MENU -->
        <?php navLink('../admin_pages/dashboard_page.php', 'bi-columns-gap', 'Dashboard', $currentPage === 'dashboard'); ?>
        <?php navLink('../employee_pages/records_page.php', 'bi-bar-chart-steps',  'Records', $currentPage === 'records');    ?>
        <?php navLink('../employee_pages/schedules_page.php', 'bi-calendar-week',  'Schedule', $currentPage === 'schedule');    ?>
        <?php navLink('../employee_pages/logs_page.php',        'bi-journal-text',  'Activity Logs',        $currentPage === 'logs'); ?>


        <!-- ADMIN MENU -->
        <?php if ($role === 'admin'): ?>

            <!-- MANAGE EMPLOYEES -->
            <?php navLink('../admin_pages/admin_manage_employees.php',    'bi-people-fill', 'Manage Employees',   $currentPage === 'manage_employees');   ?>

            <!-- REQUESTS DROPDOWN -->
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
                    <a href="../admin_pages/admin_requests.php"
                       class="sidebar-sub-link <?= ($currentPage === 'employee_requests') ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Employee Requests</span>
                    </a>
                    <a href="../admin_pages/admin_schedule_requests.php"
                       class="sidebar-sub-link <?= ($currentPage === 'schedule_requests') ? 'active' : '' ?>">
                        <i class="bi bi-calendar-check"></i>
                        <span>Schedule Requests</span>
                    </a>
                </div>
            </div>

            <!-- DEPARTMENTS LIST -->
            <?php navLink('../admin_pages/admin_departments.php',    'bi-building-gear', 'Departments',       $currentPage === 'departments'); ?>
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