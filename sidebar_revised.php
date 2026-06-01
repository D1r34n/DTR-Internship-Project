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

if (!in_array($role, ['superadmin', 'manager', 'admin', 'workforce', 'employee'])) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

if (!empty($_SESSION['must_change_password'])) {
    header("Location: ../authentication_pages/change_password.php");
    exit();
}

$requestsOpen  = in_array($currentPage, ['employee_requests', 'schedule_requests']);
$schedulesOpen = in_array($currentPage, ['schedule', 'cutoffs']);
$reportsOpen = in_array($currentPage, ['attendance_report', 'filing_report', 'leave_report', 'leave_summary']);
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
        <?php navLink('../regular_pages/dashboard_page.php', 'bi-columns-gap', 'Dashboard', $currentPage === 'dashboard'); ?>
        <?php navLink('../regular_pages/records_page.php', 'bi-bar-chart-steps',  'Records', $currentPage === 'records');    ?>
        
        <?php if (in_array($role, ['superadmin'])): ?>

            <!-- SCHEDULE DROPDOWN -->
            <div class="sidebar-dropdown">
                <button
                    class="sidebar-link sidebar-dropdown-toggle <?= $schedulesOpen ? 'active' : '' ?>"
                    data-bs-toggle="collapse"
                    data-bs-target="#schedules-submenu"
                    aria-expanded="<?= $schedulesOpen ? 'true' : 'false' ?>"
                    data-tooltip-title="Schedules"
                >
                    <i class="bi bi-calendar-week"></i>
                    <span>Schedules</span>
                    <i class="bi bi-chevron-down transition-chevron"></i>
                </button>

                <div id="schedules-submenu" class="collapse <?= $schedulesOpen ? 'show' : '' ?>">

                    <!-- CALENDAR -->
                    <a href="../regular_pages/schedules_page.php"
                       class="sidebar-sub-link <?= ($currentPage === 'schedule') ? 'active' : '' ?>">
                        <i class="bi bi-calendar"></i>
                        <span>Calendar</span>
                    </a>

                    <!-- CUT-OFF PERIODS -->
                    <a href="../management_pages/admin_cutoff.php"
                       class="sidebar-sub-link <?= ($currentPage === 'cutoffs') ? 'active' : '' ?>">
                        <i class="bi bi-scissors"></i>
                        <span>Cut-Offs</span>
                    </a>
                </div>
            </div>

        <?php else: ?>
            <?php navLink('../regular_pages/schedules_page.php', 'bi-calendar-week', 'Schedules', $currentPage === 'schedule'); ?>
        <?php endif; ?>

        <?php navLink('../regular_pages/logs_page.php', 'bi-journal-text', 'Activity Logs', $currentPage === 'logs'); ?>

        <!-- MANAGEMENT MENU -->
        <?php if (in_array($role, ['superadmin', 'admin', 'manager', 'workforce'])): ?>

            <!-- MANAGE EMPLOYEES -->
            <?php navLink('../management_pages/admin_manage_employees.php', 'bi-people', 'Manage Employees', $currentPage === 'manage_employees'); ?>

            <!-- REQUESTS DROPDOWN — manager, workforce, and superadmin only -->
            <?php if (in_array($role, ['superadmin', 'manager', 'workforce'])): ?>
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
                    <!-- Employee Requests -->
                    <a href="../management_pages/admin_requests.php"
                       class="sidebar-sub-link <?= ($currentPage === 'employee_requests') ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Employee Requests</span>
                    </a>

                    <!-- Schedule Requests -->
                    <a href="../management_pages/admin_schedule_requests.php"
                       class="sidebar-sub-link <?= ($currentPage === 'schedule_requests') ? 'active' : '' ?>">
                        <i class="bi bi-calendar-check"></i>
                        <span>Schedule Requests</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- DEPARTMENTS — superadmin only -->
            <?php if ($role === 'superadmin'): ?>
                <?php navLink('../management_pages/admin_departments.php', 'bi-building-gear', 'Departments', $currentPage === 'departments'); ?>
                
                <?php 
                // Determine if the inner leave submenu group should be auto-expanded
                $leaveMenuOpen = in_array($currentPage, ['leave_report', 'leave_summary']); 
                ?>

                <div class="sidebar-dropdown">
                    <button
                        class="sidebar-link sidebar-dropdown-toggle <?= $reportsOpen ? 'active' : '' ?>"
                        data-bs-toggle="collapse"
                        data-bs-target="#reports-submenu"
                        aria-expanded="<?= $reportsOpen ? 'true' : 'false' ?>"
                        data-tooltip-title="Reports"
                    >
                        <i class="bi bi-file-earmark-bar-graph"></i>
                        <span>Reports</span>
                        <i class="bi bi-chevron-down transition-chevron"></i>
                    </button>

                    <div id="reports-submenu" class="collapse <?= $reportsOpen ? 'show' : '' ?>">

                        <a href="../reports_pages/attendance_report_page.php"
                        class="sidebar-sub-link <?= ($currentPage === 'attendance_report') ? 'active' : '' ?>">
                            <i class="bi bi-file-earmark-text"></i>
                            <span>Attendance Report</span>
                        </a>

                        <a href="../reports_pages/filing_report_page.php"
                        class="sidebar-sub-link <?= ($currentPage === 'filing_report') ? 'active' : '' ?>">
                            <i class="bi bi-file-earmark-check"></i>
                            <span>Filing Report</span>
                        </a>

                        <div class="sidebar-nested-group">
                            <button
                                class="sidebar-sub-link sidebar-dropdown-toggle border-0 bg-transparent text-start w-100 d-flex align-items-center <?= $leaveMenuOpen ? 'active' : '' ?>"
                                data-bs-toggle="collapse"
                                data-bs-target="#leave-reports-submenu"
                                aria-expanded="<?= $leaveMenuOpen ? 'true' : 'false' ?>"
                                style="box-shadow: none;"
                            >
                                <i class="bi bi-file-earmark-easel"></i>
                                <span>Leave Reports</span>
                                <i class="bi bi-chevron-down transition-chevron ms-4" style="font-size: 0.8rem;"></i>
                            </button>

                            <div id="leave-reports-submenu" class="collapse <?= $leaveMenuOpen ? 'show' : '' ?>" style="padding-left: 15px;">
                                
                                <a href="../reports_pages/leave_report_page.php"
                                class="sidebar-sub-link <?= ($currentPage === 'leave_report') ? 'active' : '' ?>" style="font-size: 0.85rem;">
                                    <i class="bi bi-calendar-range"></i>
                                    <span>Leaves Taken</span>
                                </a>

                                <a href="../reports_pages/leave_summary_page.php" 
                                class="sidebar-sub-link <?= ($currentPage === 'leave_summary') ? 'active' : '' ?>" style="font-size: 0.85rem;">
                                    <i class="bi bi-calculator"></i>
                                    <span>Leaves Summary</span>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </nav>
</div>

<script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');

        const initTooltips = () => {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                if (!bootstrap.Tooltip.getInstance(el)) {
                    new bootstrap.Tooltip(el, {
                        placement: 'right',
                        trigger: 'hover',
                        container: 'body',
                        delay: { show: 100, hide: 100 }
                    });
                }
            });
            document.querySelectorAll('[data-tooltip-title]').forEach(el => {
                if (!bootstrap.Tooltip.getInstance(el)) {
                    new bootstrap.Tooltip(el, {
                        title: el.dataset.tooltipTitle,
                        placement: 'right',
                        trigger: 'hover',
                        container: 'body',
                        delay: { show: 100, hide: 100 }
                    });
                }
            });
        };

        const destroyTooltips = () => {
            document.querySelectorAll('[data-bs-toggle="tooltip"], [data-tooltip-title]').forEach(el => {
                const instance = bootstrap.Tooltip.getInstance(el);
                if (instance) {
                    instance.hide();
                    instance.dispose();
                }
            });
        };

        const toggleTooltips = () => {
            const collapsed = sidebar.classList.contains('collapsed');

            if (collapsed) {
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