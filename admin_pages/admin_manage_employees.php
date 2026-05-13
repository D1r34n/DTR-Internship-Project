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
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $birthdate  = !empty($_POST['birthdate']) ? $_POST['birthdate'] : null;
    $role       = $_POST['role'] ?? 'employee';
    $department = !empty($_POST['department_id']) ? $_POST['department_id'] : null;

    // ---- GET ROLE ID ----
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_key = ?");
    $roleStmt->execute([$role]);
    $roleId = $roleStmt->fetchColumn();

    if (!$roleId) {
        $_SESSION['error'] = "Invalid role selected.";
        header("Location: admin_manage_employees.php");
        exit();
    }

    // =========================================================
    // EDIT EMPLOYEE
    // =========================================================
    if (!empty($_POST['employee_id'])) {

        $employeeId = $_POST['employee_id'];

        $oldStmt = $pdo->prepare("SELECT email, first_name, last_name FROM employees WHERE id = ?");
        $oldStmt->execute([$employeeId]);
        $oldEmployee = $oldStmt->fetch(PDO::FETCH_ASSOC);
        $oldEmail    = $oldEmployee['email'] ?? null;

        if ($oldEmail && strtolower($oldEmail) !== strtolower($email)) {
            $fullName = htmlspecialchars($first_name . ' ' . $last_name);
            sendMail(
                $oldEmail,
                $fullName,
                'Your HSN DTR Account Email Has Been Updated',
                "
                <p>Hi {$fullName},</p>
                <p>This is a notification that your account email has been changed.</p>
                <p><strong>Old Email:</strong> {$oldEmail}<br>
                   <strong>New Email:</strong> {$email}</p>
                <p>— HSN DTR System</p>
                "
            );
        }

        // update query here if needed

    }

    // =========================================================
    // ADD EMPLOYEE
    // =========================================================
    else {
        $stmt = $pdo->prepare("
            INSERT INTO employees (first_name, last_name, email, role_id, department_id, birthdate, hired_date)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([$first_name, $last_name, $email, $roleId, $department, $birthdate]);

        $employeeId   = $pdo->lastInsertId();
        $fullName     = $first_name . ' ' . $last_name;
        $profileImage = 'default_profile.png';

        $pdo->prepare("UPDATE employees SET profile_image = ? WHERE id = ?")
            ->execute([$profileImage, $employeeId]);

        $safeName = htmlspecialchars($fullName);
        sendMail($email, $safeName, 'Your HSN DTR Account', "
            <p>Hi {$safeName},</p>
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
    SELECT e.*, CONCAT(e.first_name, ' ', e.last_name) AS name,
           r.role_key AS role, r.role_name,
           d.department_name, d.department_code
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

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

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
                            <button class="btn btn-sm dropdown-toggle" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <span id="roleBtnLabel">All Roles</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <button class="dropdown-item" type="button"
                                            onclick="selectFilter('role','','All Roles')">
                                        All Roles
                                    </button>
                                </li>
                                <?php foreach ($roles as $r): ?>
                                <li>
                                    <button class="dropdown-item" type="button"
                                            onclick="selectFilter('role','<?= $r['role_key'] ?>','<?= $r['role_name'] ?>')">
                                        <?= $r['role_name'] ?>
                                    </button>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <input type="hidden" id="role-filter" value="">

                        <!-- Department filter — Bootstrap dropdown with search inside -->
                        <div class="dropdown">
                            <button class="btn btn-sm dropdown-toggle"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    aria-expanded="false"
                                    id="deptFilterBtn">
                                <span id="deptFilterLabel">All Departments</span>
                            </button>
                            <div class="dropdown-menu p-2" style="min-width:220px;">
                                <input type="text"
                                       class="form-control form-control-sm mb-2"
                                       id="deptFilterSearch"
                                       placeholder="Search...">
                                <ul class="list-unstyled mb-0" id="deptFilterList"
                                    style="max-height:200px; overflow-y:auto;"></ul>
                            </div>
                        </div>
                        <input type="hidden" id="dept-filter" value="">

                        <!-- Search -->
                        <div class="input-group input-group-sm" style="max-width:200px;">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="empSearch" class="form-control" placeholder="Search...">
                        </div>

                        <!-- Manage button -->
                        <div class="dropdown">
                            <button class="btn btn-sm btn-success dropdown-toggle" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-plus-lg"></i> Manage
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#"
                                       data-bs-toggle="modal" data-bs-target="#empModal">
                                        <i class="bi bi-person-plus"></i> Add Employee
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#"
                                       data-bs-toggle="modal" data-bs-target="#importScheduleModal">
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
                                    data-first-name="<?= htmlspecialchars($emp['first_name']) ?>"
                                    data-last-name="<?= htmlspecialchars($emp['last_name']) ?>"
                                    data-email="<?= htmlspecialchars($emp['email']) ?>"
                                    data-role="<?= $emp['role'] ?>"
                                    data-role-name="<?= htmlspecialchars($emp['role_name'] ?? ucfirst($emp['role'])) ?>"
                                    data-dept="<?= htmlspecialchars($emp['department_id'] ?? '') ?>"
                                    data-dept-name="<?= htmlspecialchars($emp['department_name'] ?? '') ?>">

                                    <td><?= $emp['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php
                                            $avatar = !empty($emp['profile_image'])
                                                ? $emp['profile_image']
                                                : 'default_profile.png';
                                            ?>
                                            <img src="../assets/user_profiles/<?= htmlspecialchars($avatar) ?>"
                                                 class="employee-avatar" alt="avatar">
                                            <span><?= htmlspecialchars($emp['name']) ?></span>
                                        </div>
                                    </td>
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

        <!-- Add Employee Modal -->
        <div class="modal fade" id="empModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title" id="empModalTitle">Add Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form method="POST" action="admin_manage_employees.php">
                        <input type="hidden" name="employee_id" id="modalEmpId">

                        <div class="modal-body">
                            <div class="row g-4 align-items-start">

                                <!-- LEFT: Avatar preview -->
                                <div class="col-md-4 d-flex flex-column align-items-center">
                                    <div id="profilePreview">
                                        <img src="../assets/user_profiles/default_profile.png"
                                             class="employee-avatar" alt="default avatar"
                                             style="width:140px;height:140px;border-radius:50%;object-fit:cover;">
                                    </div>
                                    <small class="text-meta d-block mt-2 text-center">
                                        Default profile preview
                                    </small>
                                </div>

                                <!-- RIGHT: Form inputs -->
                                <div class="col-md-8">
                                    <div class="row g-3">

                                        <div class="col-md-6">
                                            <label class="form-label">First Name</label>
                                            <input type="text" name="first_name" id="modalFirstName"
                                                   class="form-control" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Last Name</label>
                                            <input type="text" name="last_name" id="modalLastName"
                                                   class="form-control" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" id="modalEmail"
                                                   class="form-control" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Birthdate</label>
                                            <input type="date" name="birthdate" id="modalBirthdate"
                                                   class="form-control" required>
                                        </div>

                                        <!-- Role -->
                                        <div class="col-md-6">
                                            <label class="form-label">Role</label>
                                            <div class="dropdown w-100">
                                                <button class="btn w-100 text-start dropdown-toggle"
                                                        type="button"
                                                        data-bs-toggle="dropdown"
                                                        aria-expanded="false">
                                                    <span id="roleLabel">Select Role</span>
                                                </button>
                                                <ul class="dropdown-menu w-100" id="roleDropdown">
                                                    <?php foreach ($roles as $r): ?>
                                                    <li>
                                                        <button class="dropdown-item" type="button"
                                                                onclick="selectRole('<?= $r['role_key'] ?>','<?= $r['role_name'] ?>')">
                                                            <?= $r['role_name'] ?>
                                                        </button>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <input type="hidden" name="role" id="roleInput">
                                        </div>

                                        <!-- Department — Bootstrap dropdown with search inside -->
                                        <div class="col-md-12">
                                            <label class="form-label">Department</label>
                                            <div class="dropdown w-100">
                                                <button class="btn w-100 text-start dropdown-toggle"
                                                        type="button"
                                                        data-bs-toggle="dropdown"
                                                        data-bs-auto-close="outside"
                                                        aria-expanded="false"
                                                        id="deptDropdownBtn">
                                                    <span id="deptSelectedText">Select Department</span>
                                                </button>
                                                <div class="dropdown-menu w-100 p-2"
                                                     aria-labelledby="deptDropdownBtn">
                                                    <input type="text"
                                                           class="form-control form-control-sm mb-2"
                                                           id="deptSearchInMenu"
                                                           placeholder="Search...">
                                                    <ul class="list-unstyled mb-0" id="deptList"
                                                        style="max-height:180px; overflow-y:auto;"></ul>
                                                </div>
                                            </div>
                                            <input type="hidden" name="department_id" id="deptInput">
                                        </div>

                                    </div>
                                </div>

                            </div>
                        </div>

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

                    <div class="modal-header">
                        <h5 class="modal-title">Import Employee Schedule</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <form id="importScheduleForm"
                          action="bulk_schedule_api.php?action=import"
                          method="POST"
                          enctype="multipart/form-data">

                        <div class="modal-body">

                            <div class="rounded p-3 mb-3"
                                 style="background:var(--primary-glass);border:1px solid var(--primary-border);color:var(--text-light);">
                                Upload an <strong>xlsx</strong> file with the following columns:<br>
                                <small><b>employee_id</b>, employee_name (optional), start_date, end_date, time</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Select Excel File</label>
                                <input type="file" name="schedule_file" id="scheduleFileInput"
                                       class="form-control" accept=".xlsx" required>
                                <small class="text-secondary d-block mt-1">
                                    Preview will appear below after selecting file.
                                </small>
                            </div>

                            <div id="filePreview" class="mt-3" style="display:none;">
                                <div class="rounded p-2"
                                     style="background:var(--frosted-bg);border:1px solid var(--frosted-border);">
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
                                            <tbody id="previewBody"></tbody>
                                        </table>
                                    </div>
                                    <small class="d-block mt-2" style="color:var(--text-muted);">
                                        Showing first 5 rows only
                                    </small>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center rounded p-3 mt-3"
                                 style="background:var(--frosted-bg);border:1px solid var(--frosted-border);">
                                <div>
                                    <small class="d-block" style="color:var(--text-muted);">
                                        Download the official Excel template to ensure correct format.
                                    </small>
                                    <small style="color:var(--text-muted);">
                                        Columns: employee_id, employee_name (optional), start_date, end_date, time
                                    </small>
                                </div>
                                <a href="bulk_schedule_api.php?action=download_template" class="btn btn-sm ms-3">
                                    <i class="bi bi-download"></i> Template
                                </a>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-download"></i> Import Schedule
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

    </div><!-- #main-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
    <script src="../system_functions/gantt.js"></script>
    <script>

        let currentEmployeeId = null;
        let currentStart      = '<?= date('Y-m-01') ?>';
        let currentEnd        = '<?= date('Y-m-t') ?>';

        // ---- TABLE STATE ----
        let ROWS_PER_PAGE = 10;
        let allRows       = [];
        let sortCol       = null;
        let sortDir       = 1;
        let currentPage   = 1;
        let lastTotal     = 0;
        let deptItems     = [];

        document.addEventListener('DOMContentLoaded', () => {

            allRows = Array.from(document.querySelectorAll('#empList .empRow'));
            applyFilters();

            // Search
            document.getElementById('empSearch')
                .addEventListener('input', () => { currentPage = 1; applyFilters(); });

            // Dept filter search — inside Bootstrap dropdown menu
            document.getElementById('deptFilterSearch')
                ?.addEventListener('input', function () {
                    const q = this.value.toLowerCase();
                    renderDeptList(deptItems.filter(d => d.label.toLowerCase().includes(q)), 'filter');
                });

            // Modal dept search — inside Bootstrap dropdown menu
            document.getElementById('deptSearchInMenu')
                ?.addEventListener('input', function () {
                    const q = this.value.toLowerCase();
                    renderDeptList(
                        deptItems.filter(d => d.value !== '' && d.label.toLowerCase().includes(q)),
                        'modal'
                    );
                });

            // Reset modal fields on open
            document.getElementById('empModal')
                ?.addEventListener('show.bs.modal', () => {
                    document.getElementById('deptSelectedText').textContent = 'Select Department';
                    document.getElementById('deptInput').value              = '';
                    document.getElementById('deptSearchInMenu').value       = '';
                    document.getElementById('roleLabel').textContent        = 'Select Role';
                    document.getElementById('roleInput').value              = '';
                    document.getElementById('modalFirstName').value         = '';
                    document.getElementById('modalLastName').value          = '';
                    document.getElementById('modalEmail').value             = '';
                    document.getElementById('modalBirthdate').value         = '';
                    renderDeptList(deptItems.filter(d => d.value !== ''), 'modal');
                    updateProfilePreview();
                });

            // Avatar
            document.getElementById('modalFirstName')
                ?.addEventListener('input', updateProfilePreview);
            document.getElementById('modalLastName')
                ?.addEventListener('input', updateProfilePreview);

            loadDepartments();
        });

        /* -------------------------------------------------------
           AVATAR PREVIEW
        ------------------------------------------------------- */
        function updateProfilePreview() {
            const el = document.getElementById('profilePreview');
            if (!el) return;
            el.innerHTML = `
                <img src="../assets/user_profiles/default_profile.png"
                     class="employee-avatar" alt="default avatar"
                     style="width:140px;height:140px;border-radius:50%;object-fit:cover;">
            `;
        }

        /* -------------------------------------------------------
           DEPARTMENT DROPDOWNS
        ------------------------------------------------------- */
        function loadDepartments() {
            fetch('/DTR-Internship-Project/admin_pages/department_api.php?action=list')
                .then(r => r.json())
                .then(depts => {
                    deptItems = [
                        { value: '', label: 'All Departments' },
                        ...depts.map(d => ({ value: String(d.id), label: d.department_name }))
                    ];
                    renderDeptList(deptItems, 'filter');
                    renderDeptList(deptItems.filter(d => d.value !== ''), 'modal');
                })
                .catch(() => {});
        }

        function renderDeptList(items, target) {
            const listId = target === 'filter' ? 'deptFilterList' : 'deptList';
            const list   = document.getElementById(listId);
            if (!list) return;

            list.innerHTML = '';
            items.forEach(item => {
                const li  = document.createElement('li');
                const btn = document.createElement('button');
                btn.type        = 'button';
                btn.className   = 'dropdown-item rounded';
                btn.textContent = item.label;
                btn.onclick = () => {
                    if (target === 'filter') {
                        document.getElementById('dept-filter').value         = item.value;
                        document.getElementById('deptFilterLabel').textContent = item.label;
                        bootstrap.Dropdown.getInstance(
                            document.getElementById('deptFilterBtn')
                        )?.hide();
                        currentPage = 1;
                        applyFilters();
                    } else {
                        document.getElementById('deptInput').value              = item.value;
                        document.getElementById('deptSelectedText').textContent = item.label;
                        bootstrap.Dropdown.getInstance(
                            document.getElementById('deptDropdownBtn')
                        )?.hide();
                    }
                };
                li.appendChild(btn);
                list.appendChild(li);
            });
        }

        /* -------------------------------------------------------
           ROLE SELECT
        ------------------------------------------------------- */
        function selectRole(value, label) {
            document.getElementById('roleInput').value       = value;
            document.getElementById('roleLabel').textContent = label;
            bootstrap.Dropdown.getInstance(
                document.querySelector('#roleDropdown').closest('.dropdown').querySelector('[data-bs-toggle="dropdown"]')
            )?.hide();
        }

        /* -------------------------------------------------------
           ROLE FILTER
        ------------------------------------------------------- */
        function selectFilter(type, value, label) {
            if (type === 'role') {
                document.getElementById('role-filter').value        = value;
                document.getElementById('roleBtnLabel').textContent = label;
                currentPage = 1;
                applyFilters();
            }
        }

        /* -------------------------------------------------------
           FILTER + SORT + PAGINATE
        ------------------------------------------------------- */
        function applyFilters() {
            const q    = document.getElementById('empSearch').value.toLowerCase().trim();
            const role = document.getElementById('role-filter').value;
            const dept = document.getElementById('dept-filter').value;

            let filtered = allRows.filter(row => {
                const firstName  = (row.dataset.firstName || '').toLowerCase();
                const lastName   = (row.dataset.lastName  || '').toLowerCase();
                const fullName   = `${firstName} ${lastName}`.trim();
                const email      = (row.dataset.email     || '').toLowerCase();

                const matchSearch = !q || fullName.includes(q) || email.includes(q);
                const matchRole   = !role || row.dataset.role === role;
                const matchDept   = !dept || row.dataset.dept === dept;

                return matchSearch && matchRole && matchDept;
            });

            if (sortCol) {
                filtered.sort((a, b) => {
                    if (sortCol === 'id') {
                        return sortDir * (parseInt(a.dataset.id) - parseInt(b.dataset.id));
                    }
                    const av = (a.dataset[sortCol] || '').toLowerCase();
                    const bv = (b.dataset[sortCol] || '').toLowerCase();
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
            allRows.forEach(r => r.style.display = 'none');
            pagRows.forEach(r => { tbody.appendChild(r); r.style.display = ''; });

            const emptyRow = document.querySelector('#empList .emptyRow');
            if (emptyRow) emptyRow.style.display = total === 0 ? '' : 'none';

            document.getElementById('empCount').textContent = total;
            renderPagination(total, totalPages, start);
        }

        /* -------------------------------------------------------
           SORT
        ------------------------------------------------------- */
        function sortBy(col) {
            if (sortCol === col) {
                sortDir = sortDir === 1 ? -1 : 1;
                if (sortDir === 1) sortCol = null;
            } else {
                sortCol = col;
                sortDir = 1;
            }
            updateSortIcons();
            currentPage = 1;
            applyFilters();
        }

        function updateSortIcons() {
            document.querySelectorAll('.sortable').forEach(el => el.classList.remove('sorted'));
            document.querySelectorAll('.sortIcon').forEach(el => {
                el.className = 'sortIcon bi bi-arrow-down-up';
            });
            if (!sortCol) return;
            const header = document.querySelector(`[onclick="sortBy('${sortCol}')"]`);
            if (header) header.classList.add('sorted');
            const icon = document.getElementById('sort-' + sortCol);
            if (icon) icon.className = 'sortIcon bi ' + (sortDir === 1 ? 'bi-arrow-up' : 'bi-arrow-down');
        }

        /* -------------------------------------------------------
           PAGINATION
        ------------------------------------------------------- */
        function renderPagination(total, totalPages, start) {
            const pag = document.getElementById('empPagination');
            if (!pag) return;
            if (total === 0) { pag.innerHTML = ''; return; }

            const end     = Math.min(start + ROWS_PER_PAGE, total);
            const showing = `${start + 1}–${end} of ${total}`;

            let html = `
                <div class="row align-items-center g-2 w-100">
                    <div class="col-md d-flex align-items-center gap-2">
                        <span class="text-meta">Showing ${showing}</span>
                    </div>
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
                    html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
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
                    <div class="col-md d-flex justify-content-md-end align-items-center gap-2">
                        <span class="text-meta text-nowrap">Rows per page</span>
                        <div class="dropdown">
                            <button class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                <span id="rowsPerPageLabel">${ROWS_PER_PAGE} Rows</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button class="dropdown-item" onclick="changeRowsPerPage(10)">10</button></li>
                                <li><button class="dropdown-item" onclick="changeRowsPerPage(25)">25</button></li>
                                <li><button class="dropdown-item" onclick="changeRowsPerPage(50)">50</button></li>
                                <li><button class="dropdown-item" onclick="changeRowsPerPage(100)">100</button></li>
                            </ul>
                        </div>
                    </div>
                </div>
            `;

            pag.innerHTML = html;
        }

        function getPageNums(cur, tot) {
            if (tot <= 7) return Array.from({ length: tot }, (_, i) => i + 1);
            if (cur <= 4) return [1, 2, 3, 4, 5, '...', tot];
            if (cur >= tot - 3) return [1, '...', tot-4, tot-3, tot-2, tot-1, tot];
            return [1, '...', cur-1, cur, cur+1, '...', tot];
        }

        function changePage(n) {
            const totalPages = Math.max(1, Math.ceil(lastTotal / ROWS_PER_PAGE));
            if (n < 1 || n > totalPages) return;
            currentPage = n;
            applyFilters();
        }

        function changeRowsPerPage(value) {
            ROWS_PER_PAGE = parseInt(value);
            currentPage   = 1;
            applyFilters();
        }

    </script>
</body>
</html>