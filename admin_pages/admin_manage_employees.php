<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
require_once '../send_mail.php';
date_default_timezone_set('Asia/Manila');

// ---- HANDLE ADD EMPLOYEE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $email      = trim($_POST['email'] ?? '');
    $role       = $_POST['role'] ?? 'employee';
    $department = !empty($_POST['department_id']) ? $_POST['department_id'] : null;

    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_key = ?");
    $roleStmt->execute([$role]);
    $roleId = $roleStmt->fetchColumn() ?: null;

    if (!empty($_POST['employee_id'])) {
        // Fetch old email before updating so we can notify it if it changes
        $oldStmt = $pdo->prepare("SELECT email, first_name, last_name FROM employees WHERE id = ?");
        $oldStmt->execute([$_POST['employee_id']]);
        $oldEmployee = $oldStmt->fetch(PDO::FETCH_ASSOC);
        $oldEmail = $oldEmployee['email'] ?? null;

        // Notify old email if the email address was changed
        if ($oldEmail && strtolower($oldEmail) !== strtolower($email)) {
            $fullName = htmlspecialchars($first_name . ' ' . $last_name);
            sendMail($oldEmail, $fullName, 'Your HSN DTR Account Email Has Been Updated', "
                <p>Hi {$fullName},</p>
                <p>This is a notification that the email address for your HSN DTR System account has been changed.</p>
                <p><strong>Old Email:</strong> {$oldEmail}<br>
                   <strong>New Email:</strong> {$email}</p>
                <p>If you did not request this change, please contact your administrator immediately.</p>
                <p>— HSN DTR System</p>
            ");
        }
    } else {
        $pdo->prepare("INSERT INTO employees (first_name, last_name, email, role_id, department_id, hired_date) VALUES (?, ?, ?, ?, ?, CURDATE())")
            ->execute([$first_name, $last_name, $email, $roleId, $department]);

        $fullName = htmlspecialchars($first_name . ' ' . $last_name);
        sendMail($email, $fullName, 'Your HSN DTR Account', "
            <p>Hi {$fullName},</p>
            <p>Your account has been created in the HSN DTR System.</p>
            <p><strong>Email:</strong> {$email}<br>
               <strong>Password:</strong> HSN.123</p>
            <p>Please log in and change your password.</p>
            <p>— HSN DTR System</p>
        ");
    }
    header("Location: admin_manage_employees.php");
    exit();
}

// ---- GET ALL ROLES ----
$roles = $pdo->query("SELECT role_key, role_name FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// ---- GET ALL EMPLOYEES ----
$employees = $pdo->query("
    SELECT e.*, CONCAT(e.first_name, ' ', e.last_name) AS name, r.role_key AS role, r.role_name, d.department_name, d.department_code
    FROM employees e
    LEFT JOIN roles r ON r.id = e.role_id
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY e.first_name, e.last_name
")->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'manage_employees'; 
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Management</title>

    <!-- 1. Bootstrap FIRST -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- 2. Your global CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <!-- 3. Page-specific CSS -->
    <link rel="stylesheet" href="admin_manage_employees.css">
</head>
<body>

    <?php include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">

        <?php include '../topbar_revised.php'; ?>
        
        <div class="card card-glass employee-list-card">
            <div class="card-body d-flex flex-column employee-list-card-body">

                <!-- Filter Section -->
                <div class="filter-wrapper">
                    <span class="employee-title text-primary">
                        <i class="bi bi-people-fill"></i>
                        Total Employees: <span id="empCount"><?= count($employees) ?></span>
                    </span>
                    <div class="d-flex gap-2 align-items-center ms-auto flex-wrap">

                        <!-- Role filter -->
                        <div class="dropdown">
                            <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span id="roleBtnLabel">All Roles</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button class="dropdown-item" type="button" onclick="selectFilter('role','','All Roles')">All Roles</button></li>
                                <?php foreach ($roles as $r): ?>
                                <li><button class="dropdown-item" type="button" onclick="selectFilter('role','<?= $r['role_key'] ?>','<?= $r['role_name'] ?>')"><?= $r['role_name'] ?></button></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <input type="hidden" id="role-filter" value="">

                        <!-- DEPARTMENT FILTER DROPDOWN -->
                        <div class="dropdown w-30">

                            <div class="input-group input-group-sm" style="max-width: 220px;">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>

                                <input
                                    type="text"
                                    id="dept-search-input"
                                    class="form-control"
                                    placeholder="All Departments"
                                    autocomplete="off"
                                >
                            </div>

                            <ul class="dropdown-menu p-2 w-100" id="filter-dept-menu"></ul>

                        </div>

                        <input type="hidden" id="dept-filter" value="">

                        <!-- Search -->
                        <div class="input-group input-group-sm" style="max-width: 200px;">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>
                            <input 
                                type="text" 
                                id="empSearch" 
                                class="form-control" 
                                placeholder="Search..."
                                oninput="applyFilters()">
                        </div>

                        <div class="dropdown">
                            <button class="btn btn-sm btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Add">
                                <i class="bi bi-plus-lg"></i> Manage
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#empModal">
                                        <i class="bi bi-person-plus"></i> Add Employee
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#importScheduleModal">
                                        <i class="bi bi-download"></i> Import Schedule
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Table Header -->
                <div class="tableHeaderGlass">
                    <table class="table table-borderless mb-0">
                        <colgroup>
                            <col style="width:8%">
                            <col style="width:22%">
                            <col style="width:26%">
                            <col style="width:12%">
                            <col style="width:20%">
                            <col style="width:12%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="sortable" onclick="sortBy('id')">ID <i class="bi bi-arrow-down-up sortIcon" id="sort-id"></i></th>
                                <th class="sortable" onclick="sortBy('name')">Name <i class="bi bi-arrow-down-up sortIcon" id="sort-name"></i></th>
                                <th class="sortable" onclick="sortBy('email')">Email <i class="bi bi-arrow-down-up sortIcon" id="sort-email"></i></th>
                                <th class="sortable" onclick="sortBy('role')">Role <i class="bi bi-arrow-down-up sortIcon" id="sort-role"></i></th>
                                <th class="sortable" onclick="sortBy('deptName')">Department <i class="bi bi-arrow-down-up sortIcon" id="sort-deptName"></i></th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>

                <!-- Scrollable Body -->
                <div class="tableScroll">
                    <table class="table table-hover mb-0">
                        <colgroup>
                            <col style="width:8%">
                            <col style="width:22%">
                            <col style="width:26%">
                            <col style="width:12%">
                            <col style="width:20%">
                            <col style="width:12%">
                        </colgroup>
                        <tbody id="empList">
                            <?php foreach ($employees as $emp): ?>
                                <tr class="empRow"
                                    data-id="<?= $emp['id'] ?>"
                                    data-first-name="<?= htmlspecialchars($emp['first_name']) ?>" data-last-name="<?= htmlspecialchars($emp['last_name']) ?>"
                                    data-email="<?= htmlspecialchars($emp['email']) ?>"
                                    data-role="<?= $emp['role'] ?>"
                                    data-role-name="<?= htmlspecialchars($emp['role_name'] ?? ucfirst($emp['role'])) ?>"
                                    data-dept="<?= htmlspecialchars($emp['department_id'] ?? '') ?>"
                                    data-dept-name="<?= htmlspecialchars($emp['department_name'] ?? '') ?>">

                                    <td><?= $emp['id'] ?></td>
                                    <td><?= htmlspecialchars($emp['name']) ?></td>
                                    <td><?= htmlspecialchars($emp['email']) ?></td>
                                    <td>
                                        <span class="empRoleBadge empRole-<?= $emp['role'] ?>">
                                            <?= htmlspecialchars($emp['role_name'] ?? ucfirst($emp['role'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($emp['department_name'] ?? 'No Department') ?></td>
                                    <td class="text-end">

                                        <a class="btn btn-sm btn-success d-flex align-items-center gap-2"
                                        href="admin_employee_view.php?id=<?= $emp['id'] ?>">
                                            <i class="bi bi-eye-fill"></i> View
                                        </a>

                                    </td>

                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($employees)): ?>
                                <tr class="emptyRow">
                                    <td colspan="6">
                                        <div class="logsEmpty">
                                            <i class="bi bi-people logsEmptyIcon"></i>
                                            No employees found.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="empPagination" class="empPagination"></div>

            </div>
        </div>

        <!-- Gantt Tooltip -->
        <div id="gantt_tooltip">
            <div class="ganttToolTipRow">
                <span class="ganttToolTipLabel">Scheduled</span>
                <span class="ganttToolTipValue" id="gt-sched"></span>
            </div>
            <div class="ganttToolTipRow">
                <span class="ganttToolTipLabel">Time In</span>
                <span class="ganttToolTipValue" id="gt-actual-in"></span>
            </div>
            <div class="ganttToolTipRow">
                <span class="ganttToolTipLabel">Time Out</span>
                <span class="ganttToolTipValue" id="gt-actual-out"></span>
            </div>
            <div class="ganttToolTipRow ganttToolTipEarly" id="gt-early-row">
                <span class="ganttToolTipLabel">Early</span>
                <span class="ganttToolTipValue" id="gt-early"></span>
            </div>
            <div class="ganttToolTipRow ganttToolTipLate" id="gt-late-row">
                <span class="ganttToolTipLabel">Late</span>
                <span class="ganttToolTipValue" id="gt-late"></span>
            </div>
            <div class="ganttToolTipRow ganttToolTipOverBreak" id="gt-ob-row">
                <span class="ganttToolTipLabel">Overbreak</span>
                <span class="ganttToolTipValue" id="gt-ob"></span>
            </div>
            <div class="ganttToolTipRow ganttToolTipOverTime" id="gt-ot-row">
                <span class="ganttToolTipLabel">Overtime</span>
                <span class="ganttToolTipValue" id="gt-ot"></span>
            </div>
            <div class="ganttToolTipRow ganttToolTipUnderTime" id="gt-ut-row">
                <span class="ganttToolTipLabel">Undertime</span>
                <span class="ganttToolTipValue" id="gt-ut"></span>
            </div>
        </div>

        <!-- Add Employee Modal (Bootstrap) -->
        <div class="modal fade" id="empModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">

                    <!-- Header -->
                    <div class="modal-header">
                        <h5 class="modal-title" id="empModalTitle">Add Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- Body -->
                    <form method="POST" action="admin_manage_employees.php">
                                
                        <input type="hidden" name="employee_id" id="modalEmpId">

                        <div class="modal-body">

                            <div class="row g-3">

                                <!-- Name -->
                                <div class="col-md-6">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" id="modalFirstName" class="form-control" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" id="modalLastName" class="form-control" required>
                                </div>

                                <!-- Email -->
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" id="modalEmail" class="form-control" required>
                                </div>

                                <!-- Role -->
                                <div class="col-md-3">
                                    <label class="form-label">Role</label>

                                    <div class="dropdown w-100">
                                        <button class="btn btn-outline-light dropdown-toggle w-100 text-start" type="button" id="roleDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span id="roleLabel">Select Role</span>
                                        </button>

                                        <ul class="dropdown-menu w-100" id="roleDropdown">
                                            <?php foreach ($roles as $r): ?>
                                            <li>
                                                <button class="dropdown-item" type="button" onclick="selectRole('<?= $r['role_key'] ?>', '<?= $r['role_name'] ?>')">
                                                    <?= $r['role_name'] ?>
                                                </button>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>

                                    <input type="hidden" name="role" id="roleInput" value="">
                                </div>

                                <!-- Department (Searchable Dropdown) -->
                                <div class="col-md-6">

                                    <label class="form-label">Department</label>

                                    <div class="dropdown w-100">

                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="bi bi-search"></i>
                                            </span>

                                            <input
                                                type="text"
                                                id="dept-search-input-modal"
                                                class="form-control"
                                                placeholder="Select Department"
                                                autocomplete="off"
                                            >
                                        </div>

                                        <ul class="dropdown-menu p-2 w-100" id="dept-modal-menu"></ul>

                                    </div>

                                    <input type="hidden" name="department_id" id="deptInput">

                                </div>

                            </div>

                        </div>

                        <!-- Footer -->
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                Cancel
                            </button>

                            <button type="submit" class="btn btn-success" id="modalSubmitBtn">
                                <i class="bi bi-check-circle-fill"></i> Add Employee
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>

        <!-- Bulk Schedule Modal -->
        <div class="modal fade" id="importScheduleModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">

                <!-- Header -->
                <div class="modal-header">
                    <h5 class="modal-title">Import Employee Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <!-- Body -->
                <form id="importScheduleForm"
                    action="bulk_schedule_api.php?action=import"
                    method="POST"
                    enctype="multipart/form-data">

                    <div class="modal-body">

                        <!-- Instructions -->
                        <div class="rounded p-3 mb-3" style="background:var(--primary-glass);border:1px solid var(--primary-border);color:var(--text-light);">
                            Upload an <strong>xlsx</strong> file with the following columns:
                            <br>
                            <small>
                            <b>employee_id</b>, employee_name (optional), start_date, end_date, time
                            </small>
                        </div>

                        <!-- File Input -->
                        <div class="mb-3">
                            <label class="form-label">Select Excel File</label>
                            <input 
                                type="file" 
                                name="schedule_file" 
                                id="scheduleFileInput"
                                class="form-control"
                                accept=".xlsx"
                                required
                            >

                            <small class="text-secondary d-block mt-1">
                                Preview will appear below after selecting file.
                            </small>
                        </div>

                        <!-- FILE PREVIEW -->
                        <div id="filePreview" class="mt-3" style="display:none;">
                            <div class="rounded p-2" style="background:var(--frosted-bg);border:1px solid var(--frosted-border);">

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong style="color:var(--text-lightest);">File Preview</strong>
                                    <span id="fileName" class="small" style="color:var(--text-muted);"></span>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>employee_id</th>
                                                <th>employee_name</th>
                                                <th>start_date</th>
                                                <th>end_date</th>
                                                <th>time</th>
                                            </tr>
                                        </thead>
                                        <tbody id="previewBody">
                                        </tbody>
                                    </table>
                                </div>

                                <small class="d-block mt-2" style="color:var(--text-muted);">
                                    Showing first 5 rows only
                                </small>
                            </div>
                        </div>

                        <!-- Template Download -->
                        <div class="d-flex justify-content-between align-items-center rounded p-3 mt-3" style="background:var(--frosted-bg);border:1px solid var(--frosted-border);">

                            <div>
                                <small class="d-block" style="color:var(--text-muted);">
                                    Download the official Excel template to ensure correct format.
                                </small>
                                <small style="color:var(--text-muted);">
                                    Columns: employee_id, employee_name (optional), start_date, end_date, time,
                                </small>
                            </div>

                            <a href="bulk_schedule_api.php?action=download_template"
                            class="btn btn-sm ms-3">
                                <i class="bi bi-download"></i> Template
                            </a>

                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-download"></i> Import Schedule
                        </button>
                    </div>
                </form>

                </div>
            </div>
        </div>

    </div><!-- #main-wrapper -->

    <!-- 4. Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
    <script src="../system_functions/gantt.js"></script>
    <script>

        let currentEmployeeId = null;
        let datePicker        = null;
        let currentStart      = '<?= date('Y-m-01') ?>';
        let currentEnd        = '<?= date('Y-m-t') ?>';

        // ---- TABLE STATE ----
        let ROWS_PER_PAGE = 10;
        let allRows     = [];
        let sortCol     = null;
        let sortDir     = 1;
        let currentPage = 1;
        let lastTotal   = 0;

        document.addEventListener('DOMContentLoaded', () => {
            allRows = Array.from(document.querySelectorAll('#empList .empRow'));
            applyFilters();
        });

        // ---- FILTER DROPDOWN SELECTION ----
        function selectFilter(type, value, label) {
            if (type === 'role') {
                document.getElementById('role-filter').value         = value;
                document.getElementById('roleBtnLabel').textContent = label;
            } else {
                document.getElementById('dept-filter').value     = value;
                document.getElementById('dept-search-input').value = label === 'All Departments' ? '' : label;
                document.getElementById('filter-dept-menu').classList.remove('show');
            }
            currentPage = 1;
            applyFilters();
        }

        // ---- FILTER + SORT + PAGINATE ----
        function applyFilters() {
            const q    = document.getElementById('empSearch').value.toLowerCase().trim();
            const role = document.getElementById('role-filter').value;
            const dept = document.getElementById('dept-filter').value;

            let filtered = allRows.filter(row => {

                const firstName = (row.dataset.firstName || '').toLowerCase();
                const lastName  = (row.dataset.lastName || '').toLowerCase();
                const fullName  = `${firstName} ${lastName}`.trim();
                const email     = (row.dataset.email || '').toLowerCase();

                const matchSearch = !q
                    || fullName.includes(q)
                    || email.includes(q);

                const matchRole = !role || row.dataset.role === role;
                const matchDept = !dept || row.dataset.dept === dept;

                return matchSearch && matchRole && matchDept;
            });

            if (sortCol) {
                filtered.sort((a, b) => {
                    if (sortCol === 'id') {
                        return sortDir * (parseInt(a.dataset.id) - parseInt(b.dataset.id));
                    }

                    const key = sortCol === 'deptName' ? 'deptName' : sortCol;

                    const av = (a.dataset[key] || '').toLowerCase();
                    const bv = (b.dataset[key] || '').toLowerCase();

                    return sortDir * av.localeCompare(bv);
                });
            }

            const total      = filtered.length;
            lastTotal        = total;
            const totalPages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));

            if (currentPage > totalPages) currentPage = 1;

            const start   = (currentPage - 1) * ROWS_PER_PAGE;
            const pagRows = filtered.slice(start, start + ROWS_PER_PAGE);

            const tbody = document.getElementById('empList');

            pagRows.forEach(r => tbody.appendChild(r));

            allRows.forEach(r => r.style.display = 'none');
            pagRows.forEach(r => r.style.display = '');

            const emptyRow = document.querySelector('#empList .emptyRow');

            if (emptyRow) {
                emptyRow.style.display = total === 0 ? '' : 'none';
            }

            document.getElementById('empCount').textContent = total;

            renderPagination(total, totalPages, start);
        }
        // ---- SORT ----
        function sortBy(col) {
            if (sortCol === col) {
                if (sortDir === 1) {
                    // 2nd click → DESC
                    sortDir = -1;
                } else {
                    // 3rd click → reset
                    sortCol = null;
                    sortDir = 1;
                }
            } else {
                // New column → ASC
                sortCol = col;
                sortDir = 1;
            }
            updateSortIcons();
            currentPage = 1;
            applyFilters();
        }

        function updateSortIcons() {
            // Reset all headers
            document.querySelectorAll('.sortable').forEach(el => {
                el.classList.remove('sorted');
            });

            // Reset all icons
            document.querySelectorAll('.sortIcon').forEach(el => {
                el.className = 'sortIcon bi bi-arrow-down-up';
            });

            // If no sort, stop here
            if (!sortCol) return;

            // Activate current header
            const header = document.querySelector(`[onclick="sortBy('${sortCol}')"]`);
            if (header) header.classList.add('sorted');

            // Update icon direction
            const icon = document.getElementById('sort-' + sortCol);
            if (icon) {
                icon.className =
                    'sortIcon bi ' +
                    (sortDir === 1 ? 'bi-arrow-up' : 'bi-arrow-down');
            }
        }

        // ---- PAGINATION ----
        function renderPagination(total, totalPages, start) {
            const pag = document.getElementById('empPagination');
            if (!pag) return;
            if (total === 0) { pag.innerHTML = ''; return; }

            const end     = Math.min(start + ROWS_PER_PAGE, total);
            const showing = `${start + 1}–${end} of ${total}`;

            let html = `
                <div class="row align-items-center g-2 w-100">

                    <!-- LEFT -->
                    <div class="col-md d-flex align-items-center gap-2 flex-nowrap">
                        <span class="text-meta">
                            Showing ${showing}
                        </span>
                    </div>

                    <!-- CENTER (PAGINATION BUTTONS) -->
                    <div class="col-md d-flex justify-content-center">
                        <ul class="pagination pagination-sm mb-0">
            `;

            html += `
                <li class="page-item${currentPage === 1 ? ' disabled' : ''}">
                    <button class="page-link" onclick="changePage(${currentPage - 1})">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                </li>
            `;

            getPageNums(currentPage, totalPages).forEach(p => {
                if (p === '...') {
                    html += `<li class="page-item disabled"><span class="page-link pag-ellipsis">…</span></li>`;
                } else {
                    html += `
                        <li class="page-item${p === currentPage ? ' active' : ''}">
                            <button class="page-link" onclick="changePage(${p})">${p}</button>
                        </li>
                    `;
                }
            });

            html += `
                <li class="page-item${currentPage === totalPages ? ' disabled' : ''}">
                    <button class="page-link" onclick="changePage(${currentPage + 1})">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </li>
                        </ul>
                    </div>

                    <!-- RIGHT -->
                    <div class="col-md d-flex justify-content-md-end justify-content-start align-items-center gap-2 flex-nowrap">

                        <span class="text-meta text-nowrap">
                            Rows per page
                        </span>

                        <div class="dropdown">

                            <button
                                class="btn btn-sm dropdown-toggle"
                                type="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">

                                <span id="rowsPerPageLabel">
                                    ${ROWS_PER_PAGE} Rows
                                </span>

                            </button>

                            <ul class="dropdown-menu">

                                <li>
                                    <button class="dropdown-item" type="button" onclick="changeRowsPerPage(10)">
                                        10 Rows
                                    </button>
                                </li>

                                <li>
                                    <button class="dropdown-item" type="button" onclick="changeRowsPerPage(25)">
                                        25 Rows
                                    </button>
                                </li>

                                <li>
                                    <button class="dropdown-item" type="button" onclick="changeRowsPerPage(50)">
                                        50 Rows
                                    </button>
                                </li>

                                <li>
                                    <button class="dropdown-item" type="button" onclick="changeRowsPerPage(100)">
                                        100 Rows
                                    </button>
                                </li>

                            </ul>

                        </div>

                    </div>

                </div>
            `;

            pag.innerHTML = html;
        }

        function getPageNums(cur, tot) {
            if (tot <= 7) return Array.from({length: tot}, (_, i) => i + 1);
            if (cur <= 4)      return [1,2,3,4,5,'...',tot];
            if (cur >= tot-3)  return [1,'...',tot-4,tot-3,tot-2,tot-1,tot];
            return [1,'...',cur-1,cur,cur+1,'...',tot];
        }

        function changePage(n) {
            const totalPages = Math.max(1, Math.ceil(lastTotal / ROWS_PER_PAGE));
            if (n < 1 || n > totalPages) return;
            currentPage = n;
            applyFilters();
        }
        
        function changeRowsPerPage(value) {

            ROWS_PER_PAGE = parseInt(value);

            const label = document.getElementById('rowsPerPageLabel');

            if (label) {
                label.textContent = `${ROWS_PER_PAGE} Rows`;
            }

            currentPage = 1;

            applyFilters();
        }

        // ---- SELECT EMPLOYEE ----
        function selectEmployee(id, name) {
            currentEmployeeId = id;
            document.querySelectorAll('#empList .empRow').forEach(r => r.classList.remove('active'));
            document.querySelector(`#empList .empRow[data-id="${id}"]`).classList.add('active');
            document.getElementById('empPlaceholder').style.display    = 'none';
            document.getElementById('empRecordsContent').style.display = 'flex';
            document.getElementById('selectedEmpName').textContent     = name;
            initDatePicker();
            loadRecords();
        }

        // ---- DATE PICKER ----
        function initDatePicker() {
            if (datePicker) { datePicker.destroy(); datePicker = null; }

            datePicker = flatpickr('#dateRangePicker', {
                mode:          'range',
                dateFormat:    'Y-m-d',
                altInput:      true,
                altInputClass: 'empDateInput',
                altFormat:     'M j, Y',
                defaultDate:   [currentStart, currentEnd],
                onChange(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        currentStart = instance.formatDate(selectedDates[0], 'Y-m-d');
                        currentEnd   = instance.formatDate(selectedDates[1], 'Y-m-d');
                        loadRecords();
                    }
                }
            });
        }

        // ---- LOAD RECORDS ----
        function loadRecords() {
            if (!currentEmployeeId) return;

            const container = document.getElementById('ganttContainer');
            container.innerHTML = '<div class="ganttEmpty"><div class="empSpinner"></div> Loading...</div>';

            const params = new URLSearchParams({
                employee_id: currentEmployeeId,
                start:       currentStart,
                end:         currentEnd
            });

            fetch('get_admin_employee_records.php?' + params.toString())
                .then(r => r.text())
                .then(html => {
                    container.innerHTML = html;
                    initGanttCursors();
                })
                .catch(() => {
                    container.innerHTML = '<div class="ganttEmpty" style="color:#ff8a8a;">Failed to load records.</div>';
                });
        }

        // ---- MODAL ----

        function closeModal(e) {
            if (e.target === document.getElementById('empModalOverlay')) closeModalBtn();
        }

        function closeModalBtn() {
            document.getElementById('empModalOverlay').style.display = 'none';
        }

        function createDropdown({
            inputId,
            menuId,
            hiddenInputId = null,
            items = [],
            placeholder = 'Select',
            onSelect = null,
            allowSearch = true
        }) {
            const input = document.getElementById(inputId);
            const menu = document.getElementById(menuId);
            const hidden = hiddenInputId ? document.getElementById(hiddenInputId) : null;

            if (!input || !menu) return;

            function render(list) {
                menu.innerHTML = '';

                list.forEach(item => {
                    const li = document.createElement('li');

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'dropdown-item';
                    btn.textContent = item.label;

                    btn.onclick = () => select(item);

                    li.appendChild(btn);
                    menu.appendChild(li);
                });
            }

            function select(item) {
                input.value = item.label;

                if (hidden) hidden.value = item.value;

                if (onSelect) onSelect(item);

                menu.classList.remove('show');
            }

            function filter() {
                const q = input.value.toLowerCase().trim();
                const filtered = items.filter(i =>
                    i.label.toLowerCase().includes(q)
                );
                render(filtered);
            }

            function open() {
                menu.classList.add('show');
            }

            // events
            input.addEventListener('click', open);

            if (allowSearch) {
                input.addEventListener('input', filter);
            }

            document.addEventListener('click', e => {
                if (!e.target.closest(`#${menuId}`) &&
                    !e.target.closest(`#${inputId}`)) {
                    menu.classList.remove('show');
                }
            });

            // initial render
            render(items);
        }

        // ---- ROLE / DEPT SELECTS ----
        function toggleModalDropdown(id) {
            const all = ['roleDropdown', 'deptDropdown'];
            all.forEach(d => {
                if (d !== id) document.getElementById(d).classList.remove('show');
            });
            document.getElementById(id).classList.toggle('show');
        }

        function selectRole(value, label) {
            document.getElementById('roleInput').value       = value;
            document.getElementById('roleLabel').textContent = label;
            document.getElementById('roleDropdown').classList.remove('show');
        }

        function selectDept(value, label) {
            document.getElementById('deptInput').value = value;
            document.getElementById('dept-search-input-modal').value = label;

            // close modal dropdown only
            const modalMenu = document.getElementById('dept-modal-menu');
            if (modalMenu) modalMenu.classList.remove('show');
        }

        document.addEventListener('click', e => {
            if (!e.target.closest('.customSelectWrapper')) {
                document.querySelectorAll('.customSelectMenu').forEach(m => m.classList.remove('show'));
            }
        });

        function loadDepartments() {
            fetch('/DTR-Internship-Project/admin_pages/department_api.php?action=list')
                .then(r => r.json())
                .then(depts => {

                    const formatted = depts.map(d => ({
                        value: String(d.id),
                        label: d.department_name
                    }));

                    // =========================
                    // FILTER DROPDOWN
                    // =========================
                    createDropdown({
                        inputId: 'dept-search-input',
                        menuId: 'filter-dept-menu',
                        hiddenInputId: 'dept-filter',
                        items: [
                            { value: '', label: 'All Departments' },
                            ...formatted
                        ],
                        onSelect: () => applyFilters()
                    });

                    const urlDept = new URLSearchParams(location.search).get('dept');
                    if (urlDept) {
                        const match = formatted.find(d => d.value === urlDept);
                        if (match) {
                            document.getElementById('dept-filter').value       = match.value;
                            document.getElementById('dept-search-input').value = match.label;
                            applyFilters();
                        }
                    }

                    // =========================
                    // MODAL DROPDOWN
                    // =========================
                    createDropdown({
                        inputId: 'dept-search-input-modal',
                        menuId: 'dept-modal-menu',
                        hiddenInputId: 'deptInput',
                        items: [
                            { value: '', label: 'None' },
                            ...formatted
                        ],
                        allowSearch: true
                    });

                })
                .catch(() => {});
        }
        loadDepartments();

        // Auto-dismiss success alert
        setTimeout(() => {
            document.querySelectorAll('.empAlert').forEach(a => {
                a.style.transition = 'opacity 0.5s';
                a.style.opacity    = '0';
                setTimeout(() => a.remove(), 500);
            });
        }, 3000);

        // ---- BULK SCHEDULE: FILE PREVIEW ----
        document.getElementById('scheduleFileInput').addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            document.getElementById('fileName').textContent = file.name;

            const reader = new FileReader();
            reader.onload = function (e) {
                const data     = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const sheet    = workbook.Sheets[workbook.SheetNames[0]];
                const rows     = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });

                const tbody    = document.getElementById('previewBody');
                tbody.innerHTML = '';

                const dataRows = rows.slice(1, 6);
                if (dataRows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="color:var(--text-muted);">No data rows found</td></tr>';
                } else {
                    dataRows.forEach(row => {
                        const tr = document.createElement('tr');
                        for (let i = 0; i < 5; i++) {
                            const td = document.createElement('td');
                            td.textContent = row[i] ?? '';
                            tr.appendChild(td);
                        }
                        tbody.appendChild(tr);
                    });
                }

                document.getElementById('filePreview').style.display = '';
            };
            reader.readAsArrayBuffer(file);
        });

        // Reset preview when modal is closed
        document.getElementById('importScheduleModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('scheduleFileInput').value = '';
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('previewBody').innerHTML = '';
            document.getElementById('fileName').textContent = '';
            const submitBtn = document.querySelector('#importScheduleForm [type="submit"]');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Import Schedule';
        });

        // ---- BULK SCHEDULE: AJAX SUBMIT ----
        document.getElementById('importScheduleForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const btn = this.querySelector('[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="empSpinner"></span> Importing...';

            fetch('bulk_schedule_api.php?action=import', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(r => r.json())
            .then(result => {
                bootstrap.Modal.getInstance(document.getElementById('importScheduleModal')).hide();

                const isError = result.status !== 'success';
                let msg = isError
                    ? (result.message || 'Import failed.')
                    : `Imported ${result.inserted} schedule entries successfully.`;
                if (!isError && result.errors && result.errors.length > 0) {
                    msg += ` (${result.errors.length} row(s) skipped)`;
                }
                showImportAlert(msg, isError);
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = 'Import Schedule';
                showImportAlert('Import failed. Please try again.', true);
            });
        });

        function showImportAlert(message, isError = false) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'empAlert';
            if (isError) {
                alertDiv.style.background   = 'rgba(220,53,69,0.15)';
                alertDiv.style.borderColor  = 'rgba(220,53,69,0.3)';
                alertDiv.style.color        = '#ff8a8a';
            }
            alertDiv.textContent = message;

            const filterWrapper = document.querySelector('.filter-wrapper');
            filterWrapper.insertAdjacentElement('afterend', alertDiv);

            setTimeout(() => {
                alertDiv.style.transition = 'opacity 0.5s';
                alertDiv.style.opacity    = '0';
                setTimeout(() => alertDiv.remove(), 500);
            }, 4000);
        }

    </script>
</body>
</html>
