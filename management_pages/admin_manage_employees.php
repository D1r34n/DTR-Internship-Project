<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['superadmin', 'admin', 'manager', 'workforce'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
require_once '../send_mail.php';

date_default_timezone_set('Asia/Manila');


// ---- HANDLE ADD EMPLOYEE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['form_token']) || $_POST['form_token'] !== ($_SESSION['form_token'] ?? '')) {
        $_SESSION['error'] = "Duplicate submission detected. Please try again.";
        header("Location: admin_manage_employees.php");
        exit();
    }
    unset($_SESSION['form_token']);

    $first_name      = trim($_POST['first_name'] ?? '');
    $last_name       = trim($_POST['last_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $birthdate       = !empty($_POST['birthdate']) ? $_POST['birthdate'] : null;
    $role            = $_POST['role'] ?? 'employee';
    $department      = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $employee_ref_id = trim($_POST['employee_ref_id'] ?? '');

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
    // ADD EMPLOYEE
    // =========================================================
    {
        if (!preg_match('/^\d{6}$/', $employee_ref_id)) {
            $_SESSION['error'] = "Employee ID must be exactly 6 digits.";
            header("Location: admin_manage_employees.php");
            exit();
        }

        $dupIdStmt = $pdo->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $dupIdStmt->execute([$employee_ref_id]);
        if ($dupIdStmt->fetchColumn()) {
            $_SESSION['error'] = "An employee with that ID already exists.";
            header("Location: admin_manage_employees.php");
            exit();
        }

        $dupStmt = $pdo->prepare("SELECT id FROM employees WHERE LOWER(email) = LOWER(?)");
        $dupStmt->execute([$email]);
        if ($dupStmt->fetchColumn()) {
            $_SESSION['error'] = "An employee with that email already exists.";
            header("Location: admin_manage_employees.php");
            exit();
        }

        $stmt = $pdo->prepare("
            INSERT INTO employees (employee_id, first_name, last_name, email, role_id, department_id, birthdate, hired_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([$employee_ref_id, $first_name, $last_name, $email, $roleId, $department, $birthdate]);

        $employeeId   = $pdo->lastInsertId();
        $fullName     = $first_name . ' ' . $last_name;
        $profileImage = 'default_profile.png';

        $pdo->prepare("UPDATE employees SET profile_image = ? WHERE id = ?")
            ->execute([$profileImage, $employeeId]);

        $pdo->prepare("INSERT INTO employee_leave_balances (employee_id) VALUES (?)")
            ->execute([$employeeId]);
        
       $pdo->prepare("
            INSERT INTO logs (employee_id, log_type, log_time, longitude, latitude, is_within_office, edit_requested_by)
            VALUES (?, 'ADD_EMPLOYEE', NOW(), 0, 0, 0, ?)")
            ->execute([$employeeId, $_SESSION['user_id']]);     

        $safeName = htmlspecialchars($fullName);    

        $safeName = htmlspecialchars($fullName);
        sendMail($email, $safeName, 'Your HSN DTR Account', "
            <p>Hi {$safeName},</p>
            <p>Your account has been created in the HSN DTR System.</p>
            <p><strong>Email:</strong> {$email}<br>
            <strong>Password:</strong> HSN.123</p>
            <p>Please log in and change your password.</p>
            <p>— HSN DTR System</p>
        ");

        $_SESSION['success'] = "Employee \"{$fullName}\" added successfully.";
    }

    header("Location: admin_manage_employees.php");
    exit();
}

$_SESSION['form_token'] = bin2hex(random_bytes(16));

// ---- GET ALL ROLES ----
$roles = $pdo->query("SELECT role_key, role_name FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// ---- WORKFORCE / MANAGER: restrict to own department ----
$workforceDeptId = null;
if (in_array($_SESSION['user_role'], ['workforce', 'manager'])) {
    $deptStmt = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
    $deptStmt->execute([$_SESSION['user_id']]);
    $workforceDeptId = $deptStmt->fetchColumn();
}

// ---- GET ALL EMPLOYEES ----
if ($workforceDeptId) {
    $hideManager = ($_SESSION['user_role'] === 'workforce') ? "AND r.role_key != 'manager'" : '';
    $empStmt = $pdo->prepare("
        SELECT e.*, CONCAT(e.first_name, ' ', e.last_name) AS name,
               r.role_key AS role, r.role_name,
               d.department_name, d.department_code
        FROM employees e
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.department_id = ? {$hideManager} AND e.is_archived = 0
        ORDER BY e.first_name, e.last_name
    ");
    $empStmt->execute([$workforceDeptId]);
    $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $employees = $pdo->query("
        SELECT e.*, CONCAT(e.first_name, ' ', e.last_name) AS name,
               r.role_key AS role, r.role_name,
               d.department_name, d.department_code
        FROM employees e
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.is_archived = 0
        ORDER BY e.first_name, e.last_name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// ---- TODAY'S ATTENDANCE SUMMARY ----
$today = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM attendances a
    JOIN employees e ON e.id = a.employee_id
    WHERE a.work_date = ? AND a.actual_time_in IS NOT NULL
");
$stmt->execute([$today]);
$presentCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM attendances a
    JOIN employees e ON e.id = a.employee_id
    WHERE a.work_date = ? AND a.actual_time_in IS NOT NULL AND a.late_minutes > 0
");
$stmt->execute([$today]);
$lateCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM attendances a
    JOIN employees e ON e.id = a.employee_id
    WHERE a.work_date = ? AND a.status = 'absent'
");
$stmt->execute([$today]);
$absentCount = (int) $stmt->fetchColumn();

$currentPage      = 'manage_employees';
$initialDeptFilter = isset($_GET['dept']) ? (int)$_GET['dept'] : 0;
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
        <!-- Summary Card -->
        <div class="container-fluid flex-shrink-0 px-3">
            <div class="row">

                <!-- Total Employees -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-info p-3">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-info">
                                <i class="bi bi-people-fill fs-2"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number">
                                    <?= count($employees) ?>
                                </div>
                                <div class="text-meta">
                                    Total Employees
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Present Employees -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-success p-3">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-success">
                                <i class="bi bi-check-circle-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number">
                                    <?= $presentCount ?>
                                </div>
                                <div class="text-meta">
                                    Employee Present
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Late Employees -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-warning p-3">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-warning">
                                <i class="bi bi-clock-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number">
                                    <?= $lateCount ?>
                                </div>
                                <div class="text-meta">
                                    Employee Late
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Absent Employees -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-danger p-3">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-danger">
                                <i class="bi bi-x-circle-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number">
                                    <?= $absentCount ?>
                                </div>
                                <div class="text-meta">
                                    Employee Absent
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Employee List -->
        <div class="card card-neutral employee-list-card">
            <div class="card-body d-flex flex-column employee-list-card-body">

            <!-- Filter Section -->
            <div class="filter-wrapper">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100">

                    <!-- LEFT SIDE -->
                    <div class="d-flex gap-3 align-items-center flex-wrap">

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

                        <!-- Department filter -->
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

                                <ul class="list-unstyled mb-0"
                                    id="deptFilterList"
                                    style="max-height:200px; overflow-y:auto;">
                                </ul>
                            </div>
                        </div>

                        <input type="hidden" id="dept-filter" value="">

                        <!-- Search -->
                        <div class="input-group input-group-sm" style="max-width:200px;">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>

                            <input type="text"
                                id="empSearch"
                                class="form-control"
                                placeholder="Search...">
                        </div>

                    </div>

                    <!-- RIGHT SIDE -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-success dropdown-toggle"
                                type="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">
                            <i class="bi bi-plus-lg"></i> Manage
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end">

                            <?php if (!in_array($_SESSION['user_role'], ['manager', 'workforce'])): ?>
                            <li>
                                <a class="dropdown-item"
                                href="#"
                                data-bs-toggle="modal"
                                data-bs-target="#empModal">
                                    <i class="bi bi-person-plus"></i> Add Employee
                                </a>
                            </li>

                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>

                            <li>
                                <a class="dropdown-item"
                                href="#"
                                data-bs-toggle="modal"
                                data-bs-target="#import-schedule-modal">
                                    <i class="bi bi-download"></i> Import Schedule
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                href="#"
                                data-bs-toggle="modal"
                                data-bs-target="#import-leaves-modal">
                                    <i class="bi bi-download"></i> Import Leaves
                                </a>
                            </li>

                        </ul>
                    </div>

                </div>

            </div>

                <!-- Table -->
                <div class="table-scroll-wrapper">
                    <table class="table table-hover mb-0">
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
                                <th class="sortable" onclick="sortBy('employeeId')">Emp. ID <i class="bi bi-arrow-down-up sortIcon" id="sort-employeeId"></i></th>
                                <th class="sortable" onclick="sortBy('name')">Name <i class="bi bi-arrow-down-up sortIcon" id="sort-name"></i></th>
                                <th class="sortable" onclick="sortBy('email')">Email <i class="bi bi-arrow-down-up sortIcon" id="sort-email"></i></th>
                                <th class="sortable" onclick="sortBy('role')">Role <i class="bi bi-arrow-down-up sortIcon" id="sort-role"></i></th>
                                <th class="sortable" onclick="sortBy('deptName')">Department <i class="bi bi-arrow-down-up sortIcon" id="sort-deptName"></i></th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="empList">
                            <?php foreach ($employees as $emp): ?>
                                <tr class="empRow"
                                    data-id="<?= $emp['id'] ?>"
                                    data-employee-id="<?= htmlspecialchars($emp['employee_id'] ?? '') ?>"
                                    data-first-name="<?= htmlspecialchars($emp['first_name']) ?>"
                                    data-last-name="<?= htmlspecialchars($emp['last_name']) ?>"
                                    data-email="<?= htmlspecialchars($emp['email']) ?>"
                                    data-role="<?= $emp['role'] ?>"
                                    data-role-name="<?= htmlspecialchars($emp['role_name'] ?? ucfirst($emp['role'])) ?>"
                                    data-dept="<?= htmlspecialchars($emp['department_id'] ?? '') ?>"
                                    data-dept-name="<?= htmlspecialchars($emp['department_name'] ?? '') ?>">

                                    <td>
                                        <div class="copy-cell">
                                            <button class="copy-btn" data-copy="<?= htmlspecialchars($emp['employee_id'] ?? '') ?>" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Copy ID"><i class="bi bi-copy"></i></button>
                                            <?= htmlspecialchars($emp['employee_id'] ?? '—') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="copy-cell gap-2">
                                            <button class="copy-btn" data-copy="<?= htmlspecialchars($emp['name']) ?>" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Copy name"><i class="bi bi-copy"></i></button>
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
                                           href="admin_employee_view.php?employee_id=<?= $emp['employee_id'] ?>">
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
                </div><!-- .table-scroll-wrapper -->

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

                    <form method="POST" action="admin_manage_employees.php" onsubmit="validateAddEmployeeForm(event)">
                        <input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">

                        <div class="modal-body">
                            <div class="row g-4 align-items-center">

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

                                        <div class="col-md-12">
                                            <label class="form-label">Employee ID</label>
                                            <input type="text" name="employee_ref_id" id="modalEmpRefId"
                                                   class="form-control" maxlength="6" placeholder="6-digit number">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">First Name</label>
                                            <input type="text" name="first_name" id="modalFirstName"
                                                   class="form-control">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Last Name</label>
                                            <input type="text" name="last_name" id="modalLastName"
                                                   class="form-control">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" id="modalEmail"
                                                   class="form-control">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Birthdate</label>
                                            <input type="date" name="birthdate" id="modalBirthdate"
                                                   class="form-control">
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

                                        <!-- Department -->
                                        <div class="col-md-12">
                                            <label class="form-label">Department</label>
                                            <div class="dropdown w-100">
                                                <button class="btn w-100 text-start dropdown-toggle"
                                                        type="button"
                                                        data-bs-toggle="dropdown"
                                                        data-bs-auto-close="outside"
                                                        aria-expanded="false"
                                                        id="deptModalBtn">
                                                    <span id="deptModalLabel">Select Department</span>
                                                </button>
                                                <div class="dropdown-menu w-100 p-2" id="dept-modal-menu">
                                                    <input type="text"
                                                           class="form-control form-control-sm mb-2"
                                                           id="deptModalSearch"
                                                           placeholder="Search department...">
                                                    <ul class="list-unstyled mb-0"
                                                        id="deptModalList"
                                                        style="max-height:200px; overflow-y:auto;">
                                                    </ul>
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
        <div class="modal fade" id="import-schedule-modal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Import Employee Schedules</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <form id="import-schedule-form"
                          action="bulk_importing_api.php?action=import_schedule"
                          method="POST"
                          enctype="multipart/form-data">

                        <div class="modal-body">

                            <div class="mb-3">

                                <label class="form-label">Upload Excel File</label>

                                <label for="schedule-file-input" class="schedule-dropzone w-100">

                                    <div class="schedule-dropzone-icon">
                                        <i class="bi bi-cloud-arrow-up-fill"></i>
                                    </div>

                                    <div class="schedule-dropzone-title">
                                        Drag & Drop your .xlsx file here
                                    </div>

                                    <div class="schedule-dropzone-subtitle">
                                        or click to browse files
                                    </div>

                                    <div class="schedule-dropzone-meta mt-3">
                                        Accepted format: <strong>.xlsx</strong>
                                    </div>

                                    <input type="file"
                                        name="schedule_file"
                                        id="schedule-file-input"
                                        accept=".xlsx"
                                        hidden>

                                </label>

                                <small class="text-secondary d-block mt-2">
                                    Preview will appear below after selecting file.
                                </small>

                            </div>

                            <div id="schedule-file-preview" class="mt-3" style="display:none;">
                                <div class="rounded p-2"
                                     style="background:var(--frosted-bg);border:1px solid var(--frosted-border);">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong style="color:var(--text-lightest);">File Preview</strong>
                                        <span id="schedule-file-name" class="small" style="color:var(--text-muted);"></span>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Employee ID</th>
                                                    <th>Employee Name</th>
                                                    <th>Start Date</th>
                                                    <th>End Date</th>
                                                    <th>time</th>
                                                </tr>
                                            </thead>
                                            <tbody id="schedule-preview-body"></tbody>
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
                                </div>
                                <a href="bulk_importing_api.php?action=download_schedule_template"
                                    onclick="showToast('Downloading template...', 'info')"
                                    class="btn btn-sm ms-3">
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

        <!-- Bulk Leaves Modal -->
        <div class="modal fade" id="import-leaves-modal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Import Employee Leaves</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <form id="import-leave-form"
                        action="bulk_importing_api.php?action=import_leaves"
                        method="POST"
                        enctype="multipart/form-data">

                        <div class="modal-body">

                            <div class="mb-3">

                                <label class="form-label">Upload Excel File</label>

                                <label for="leaves-file-input" class="schedule-dropzone w-100">

                                    <div class="schedule-dropzone-icon">
                                        <i class="bi bi-cloud-arrow-up-fill"></i>
                                    </div>

                                    <div class="schedule-dropzone-title">
                                        Drag & Drop your .xlsx file here
                                    </div>

                                    <div class="schedule-dropzone-subtitle">
                                        or click to browse files
                                    </div>

                                    <div class="schedule-dropzone-meta mt-3">
                                        Accepted format: <strong>.xlsx</strong>
                                    </div>

                                    <input type="file"
                                        name="schedule_file"
                                        id="leaves-file-input"
                                        accept=".xlsx"
                                        hidden>

                                </label>

                                <small class="text-secondary d-block mt-2">
                                    Preview will appear below after selecting file.
                                </small>

                            </div>

                            <div id="leaves-file-preview" class="mt-3" style="display:none;">
                                <div class="rounded p-2"
                                    style="background:var(--frosted-bg);border:1px solid var(--frosted-border);">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong style="color:var(--text-lightest);">File Preview</strong>
                                        <span id="leaves-file-name" class="small" style="color:var(--text-muted);"></span>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Employee ID</th>
                                                    <th>Employee Name</th>
                                                    <th>Buffer</th>
                                                    <th>Vacation</th>
                                                    <th>Sick</th>
                                                    <th>Paternity</th>
                                                    <th>Maternity</th>
                                                    <th>Solo Parent</th>
                                                    <th>Birthday</th>
                                                </tr>
                                            </thead>
                                            <tbody id="leaves-preview-body"></tbody>
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
                                </div>
                                <a href="bulk_importing_api.php?action=download_leave_template"
                                    onclick="showToast('Downloading template...', 'info')"
                                    class="btn btn-sm ms-3">
                                    <i class="bi bi-download"></i> Template
                                </a>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-download"></i> Import Leaves
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
        <?php if (!empty($_SESSION['error'])): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast('<?= addslashes(htmlspecialchars($_SESSION['error'])) ?>', 'danger'));</script>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['success'])): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast('<?= addslashes(htmlspecialchars($_SESSION['success'])) ?>', 'success'));</script>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
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
        let ROWS_PER_PAGE = parseInt(localStorage.getItem('empRowsPerPage') || '10');
        let allRows       = [];
        let sortCol       = null;
        let sortDir       = 1;
        let currentPage   = 1;
        let lastTotal     = 0;
        let deptItems     = [];

        document.addEventListener('DOMContentLoaded', () => {

            allRows = Array.from(document.querySelectorAll('#empList .empRow'));
            applyFilters();

            document.querySelectorAll('.copy-btn').forEach(el =>
                bootstrap.Tooltip.getOrCreateInstance(el, { trigger: 'hover focus' })
            );

            document.getElementById('empList').addEventListener('click', e => {
                const copyBtn = e.target.closest('.copy-btn');
                if (copyBtn) {
                    e.stopPropagation();
                    const tip = bootstrap.Tooltip.getInstance(copyBtn);
                    navigator.clipboard.writeText(copyBtn.dataset.copy).then(() => {
                        const icon = copyBtn.querySelector('i');
                        icon.className = 'bi bi-check-lg';
                        if (tip) {
                            tip.setContent({ '.tooltip-inner': 'Copied!' });
                            tip.show();
                        }
                        setTimeout(() => {
                            icon.className = 'bi bi-copy';
                            if (tip) {
                                tip.setContent({ '.tooltip-inner': copyBtn.dataset.bsTitle });
                                tip.hide();
                            }
                        }, 1200);
                    });
                    return;
                }
                if (e.target.closest('a, button')) return;
                const row = e.target.closest('.empRow');
                if (row) window.location.href = `admin_employee_view.php?employee_id=${row.dataset.employeeId}`;
            });

            document.getElementById('modalEmpRefId')
                ?.addEventListener('blur', function () {
                    const v = this.value.trim();
                    if (v !== '') this.value = v.padStart(6, '0');
                });

            document.getElementById('empSearch')
                .addEventListener('input', () => { currentPage = 1; applyFilters(); });

            document.getElementById('deptFilterSearch')
                ?.addEventListener('input', function () {
                    const q = this.value.toLowerCase();
                    renderDeptList(deptItems.filter(d => d.label.toLowerCase().includes(q)), 'filter');
                });

            document.getElementById('deptModalSearch')
                ?.addEventListener('input', function () {
                    const q = this.value.toLowerCase();
                    renderDeptList(
                        deptItems.filter(d => d.value !== '' && d.label.toLowerCase().includes(q)),
                        'modal'
                    );
                });

document.getElementById('empModal')
                ?.addEventListener('show.bs.modal', () => {
                    document.getElementById('deptModalLabel').textContent = 'Select Department';
                    document.getElementById('deptModalSearch').value      = '';
                    document.getElementById('deptInput').value            = '';
                    renderDeptList(deptItems.filter(d => d.value !== ''), 'modal');
                    document.getElementById('roleLabel').textContent = 'Select Role';
                    document.getElementById('roleInput').value      = '';
                    document.getElementById('modalEmpRefId').value  = '';
                    document.getElementById('modalFirstName').value = '';
                    document.getElementById('modalLastName').value  = '';
                    document.getElementById('modalEmail').value     = '';
                    document.getElementById('modalBirthdate').value = '';
                    updateProfilePreview();
                });

            document.getElementById('modalFirstName')
                ?.addEventListener('input', updateProfilePreview);
            document.getElementById('modalLastName')
                ?.addEventListener('input', updateProfilePreview);

            loadDepartments();

            initImportModal({
                modalId:      'import-schedule-modal',
                formId:       'import-schedule-form',
                fileInputId:  'schedule-file-input',
                previewId:    'schedule-file-preview',
                fileNameId:   'schedule-file-name',
                previewBodyId:'schedule-preview-body',
                cols: 5,
            });

            initImportModal({
                modalId:       'import-leaves-modal',
                formId:        'import-leave-form',
                fileInputId:   'leaves-file-input',
                previewId:     'leaves-file-preview',
                fileNameId:    'leaves-file-name',
                previewBodyId: 'leaves-preview-body',
                cols: 9,
            });                                 
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
        const initialDeptFilter = <?= $initialDeptFilter ?>;

        function loadDepartments() {
            fetch('/DTR-Internship-Project/management_pages/department_api.php?action=list')
                .then(r => r.json())
                .then(depts => {
                    deptItems = [
                        { value: '', label: 'All Departments' },
                        ...depts.map(d => ({ value: String(d.id), label: d.department_name }))
                    ];
                    renderDeptList(deptItems, 'filter');
                    renderDeptList(deptItems.filter(d => d.value !== ''), 'modal');

                    if (initialDeptFilter) {
                        const match = deptItems.find(d => d.value === String(initialDeptFilter));
                        if (match) {
                            document.getElementById('dept-filter').value           = match.value;
                            document.getElementById('deptFilterLabel').textContent = match.label;
                            currentPage = 1;
                            applyFilters();
                        }
                    }
                })
                .catch(() => {});
        }

        function renderDeptList(items, target) {
            let listId, onSelect;

            if (target === 'filter') {
                listId   = 'deptFilterList';
                onSelect = item => {
                    document.getElementById('dept-filter').value           = item.value;
                    document.getElementById('deptFilterLabel').textContent = item.label;
                    bootstrap.Dropdown.getInstance(
                        document.getElementById('deptFilterBtn')
                    )?.hide();
                    currentPage = 1;
                    applyFilters();
                };
            } else if (target === 'modal') {
                listId   = 'deptModalList';
                onSelect = item => {
                    document.getElementById('deptInput').value            = item.value;
                    document.getElementById('deptModalLabel').textContent = item.label;
                    bootstrap.Dropdown.getInstance(
                        document.getElementById('deptModalBtn')
                    )?.hide();
                };
            } else {
                return;
            }

            const list = document.getElementById(listId);
            if (!list) return;

            list.innerHTML = '';
            items.forEach(item => {
                const li  = document.createElement('li');
                const btn = document.createElement('button');
                btn.type        = 'button';
                btn.className   = 'dropdown-item rounded';
                btn.textContent = item.label;
                btn.onclick     = () => onSelect(item);
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
            localStorage.setItem('empRowsPerPage', value);
            currentPage   = 1;
            applyFilters();
        }
        
        /* -------------------------------------------------------
        BULK SCHEDULE FUNCTIONS
        ------------------------------------------------------- */

        function initImportModal({ modalId, formId, fileInputId, previewId, fileNameId, previewBodyId, cols }) {

            const form      = document.getElementById(formId);
            const fileInput = document.getElementById(fileInputId);
            const dropzone  = fileInput?.closest('label.schedule-dropzone');

            if (!form || !fileInput) return;

            // Drag & drop
            if (dropzone) {
                ['dragenter', 'dragover'].forEach(ev => {
                    dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.add('dragover'); });
                });
                ['dragleave', 'drop'].forEach(ev => {
                    dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.remove('dragover'); });
                });
                dropzone.addEventListener('drop', e => {
                    if (e.dataTransfer.files.length) {
                        // Can't directly assign FileList — use DataTransfer
                        const dt = new DataTransfer();
                        Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
                        fileInput.files = dt.files;
                        fileInput.dispatchEvent(new Event('change'));
                    }
                });
            }

            // File preview
            fileInput.addEventListener('change', function () {
                const file = this.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = function (e) {
                    try {
                        const data     = new Uint8Array(e.target.result);
                        const workbook = XLSX.read(data, { type: 'array' });
                        const sheet    = workbook.Sheets[workbook.SheetNames[0]];
                        const rows     = XLSX.utils.sheet_to_json(sheet, { header: 1 });
                        const dataRows = rows.slice(1, 6);

                        const tbody      = document.getElementById(previewBodyId);
                        const fileNameEl = document.getElementById(fileNameId);
                        const preview    = document.getElementById(previewId);

                        fileNameEl.textContent = file.name;
                        tbody.innerHTML = '';

                        if (dataRows.length === 0) {
                            tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center" style="color:var(--text-muted);">No data rows found.</td></tr>`;
                        } else {
                            dataRows.forEach(row => {
                                const tr = document.createElement('tr');
                                for (let i = 0; i < cols; i++) {
                                    const td = document.createElement('td');
                                    td.textContent = row[i] ?? '—';
                                    tr.appendChild(td);
                                }
                                tbody.appendChild(tr);
                            });
                        }

                        preview.style.display = 'block';
                    } catch (err) {
                        showToast('Could not read file. Make sure it is a valid .xlsx file.', 'danger');
                    }
                };
                reader.readAsArrayBuffer(file);
            });

            // Form submit
            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                if (!fileInput.files[0]) {
                    showToast('Please select a file first.', 'danger');
                    return;
                }

                const submitBtn = this.querySelector('[type="submit"]');
                const origHTML  = submitBtn.innerHTML;
                submitBtn.disabled  = true;
                submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Importing...`;

                try {
                    const res  = await fetch(this.action, { method: 'POST', body: new FormData(this) });
                    const data = await res.json();

                    if (data.status === 'success') {
                        let msg = `Imported ${data.inserted} record${data.inserted !== 1 ? 's' : ''}.`;

                        if (data.errors?.length) {
                            const MAX_SHOWN = 3;
                            const shown     = data.errors.slice(0, MAX_SHOWN);
                            const details   = shown.map(err => `Row ${err.row} (${err.message})`).join(', ');
                            const extra     = data.errors.length > MAX_SHOWN ? ` +${data.errors.length - MAX_SHOWN} more` : '';
                            msg += ` ${data.errors.length} row${data.errors.length !== 1 ? 's' : ''} skipped — ${details}${extra}.`;
                        }

                        showToast(msg, data.errors?.length ? 'warning' : 'success');
                        bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
                    } else {
                        showToast(data.message || 'Import failed.', 'danger');
                    }
                } catch (err) {
                    showToast('Something went wrong. Please try again.', 'danger');
                } finally {
                    submitBtn.disabled  = false;
                    submitBtn.innerHTML = origHTML;
                }
            });

            // Reset on close
            document.getElementById(modalId)?.addEventListener('hidden.bs.modal', () => {
                fileInput.value = '';
                document.getElementById(previewId).style.display = 'none';
                document.getElementById(previewBodyId).innerHTML = '';
            });
        }

        /* -------------------------------------------------------
        FORM VALIDATION
        ------------------------------------------------------- */
        function validateAddEmployeeForm(e) {
            e.preventDefault();

            const empRefId  = document.getElementById('modalEmpRefId').value.trim();
            const firstName = document.getElementById('modalFirstName').value.trim();
            const lastName  = document.getElementById('modalLastName').value.trim();
            const email     = document.getElementById('modalEmail').value.trim();
            const birthdate = document.getElementById('modalBirthdate').value.trim();
            const role      = document.getElementById('roleInput').value.trim();
            const dept      = document.getElementById('deptInput').value.trim();

            if (!empRefId) {
                showToast('Employee ID is required.', 'danger'); return;
            }
            if (!/^\d{6}$/.test(empRefId)) {
                showToast('Employee ID must be exactly 6 digits.', 'danger'); return;
            }
            if (!firstName) {
                showToast('First name is required.', 'danger'); return;
            }
            if (!lastName) {
                showToast('Last name is required.', 'danger'); return;
            }
            if (!email) {
                showToast('Email is required.', 'danger'); return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showToast('Please enter a valid email address.', 'danger'); return;
            }
            if (!birthdate) {
                showToast('Birthdate is required.', 'danger'); return;
            }
            if (!role) {
                showToast('Please select a role.', 'danger'); return;
            }
            if (!dept) {
                showToast('Please select a department.', 'warning'); return;
            }

            const btn = document.getElementById('modalSubmitBtn');
            btn.disabled  = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...`;

            showToast('Adding employee...', 'success');
            e.target.submit();
        }
    </script>
</body>
</html>