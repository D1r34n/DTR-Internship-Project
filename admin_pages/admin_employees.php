<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

$success  = "";
$editData = null;

// ---- HANDLE DELETE ----
if (isset($_GET['delete'])) {
    $employeeId = $_GET['delete'];

    $pdo->prepare("DELETE FROM logs WHERE employee_id = ?")->execute([$employeeId]);
    $pdo->prepare("DELETE FROM schedules WHERE employee_id = ?")->execute([$employeeId]);
    $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$employeeId]);

    $success = "Employee deleted successfully!";
}

// ---- HANDLE ADD / EDIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']);
    $email      = trim($_POST['email']);
    $password   = trim($_POST['password']);
    $role       = $_POST['role'];
    $department = !empty($_POST['department']) ? $_POST['department'] : null;

    if (!empty($_POST['employee_id'])) {
        // EDIT
        if (!empty($password)) {
            $pdo->prepare("UPDATE employees SET name=?, email=?, password=?, role=?, department=? WHERE id=?")
                ->execute([$name, $email, $password, $role, $department, $_POST['employee_id']]);
        } else {
            $pdo->prepare("UPDATE employees SET name=?, email=?, role=?, department=? WHERE id=?")
                ->execute([$name, $email, $role, $department, $_POST['employee_id']]);
        }
        $success = "Employee updated successfully!";
    } else {
        // ADD
        $pdo->prepare("INSERT INTO employees (name, email, password, role, department) VALUES (?, ?, ?, ?, ?)")
            ->execute([$name, $email, $password, $role, $department]);
        $success = "Employee added successfully!";
    }
}

// ---- HANDLE EDIT LOAD ----
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ---- GET ALL EMPLOYEES ----
$employees = $pdo->query("SELECT * FROM employees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Management</title>

    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="admin_employees.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body::before { background-image: url('../images/drt_bg.jpg'); }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <?php include '../sidebar.php'; ?>

    <!-- TOPBAR -->
    <?php
    $current_page = 'employees';
    include '../topbar.php';
    ?>

    <!-- PAGE WRAPPER -->
    <div class="employeeWrapper">
        <div class="employeeBox">

            <!-- TITLE ROW -->
            <div class="adminTitleRow">
                <h5 class="adminTitle">Employee Management</h5>
                <input type="text" id="searchInput" class="searchInput" placeholder="Search employee..." onkeyup="searchTable()">
            </div>

            <!-- SUCCESS ALERT -->
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <!-- EMPLOYEE TABLE -->
            <div class="tableScrollWrapper">
                <table class="table table-bordered table-hover mt-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($employees) > 0): ?>
                            <?php foreach ($employees as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= ucfirst($row['role']) ?></td>
                                    <td><?= $row['department'] ? htmlspecialchars($row['department']) : '—' ?></td>
                                    <td>
                                        <div class="actionDropdownWrapper">
                                            <button class="btn btn-sm actionToggle" onclick="toggleActionMenu(this)">
                                                Actions <i class="bi bi-chevron-down"></i>
                                            </button>
                                            <div class="actionMenu">
                                                <a href="admin_employees.php?edit=<?= $row['id'] ?>" class="actionItem">
                                                    <i class="bi bi-pencil-fill"></i> Edit
                                                </a>
                                                <a href="admin_employees.php?delete=<?= $row['id'] ?>" class="actionItem deleteItem"
                                                    onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars($row['name']) ?>?')">
                                                    <i class="bi bi-trash-fill"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center">No employees found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ADD / EDIT FORM -->
            <div class="adminFormWrapper">
                <h6 class="formTitle"><?= $editData ? 'Edit Employee' : 'Add New Employee' ?></h6>
                <form method="POST" action="admin_employees.php">

                    <?php if ($editData): ?>
                        <input type="hidden" name="employee_id" value="<?= $editData['id'] ?>">
                    <?php endif; ?>

                    <div class="formGrid">

                        <!-- Name -->
                        <div class="formGroup">
                            <label>Name</label>
                            <input type="text" name="name" class="formControl" required
                                value="<?= $editData ? htmlspecialchars($editData['name']) : '' ?>">
                        </div>

                        <!-- Email -->
                        <div class="formGroup">
                            <label>Email</label>
                            <input type="email" name="email" class="formControl" required
                                value="<?= $editData ? htmlspecialchars($editData['email']) : '' ?>">
                        </div>

                        <!-- Password -->
                        <div class="formGroup">
                            <label><?= $editData ? 'New Password (leave blank to keep)' : 'Password' ?></label>
                            <input type="password" name="password" class="formControl"
                                <?= $editData ? '' : 'required' ?>>
                        </div>

                        <!-- Role -->
                        <div class="formGroup">
                            <label>Role</label>
                            <div class="customSelectWrapper">
                                <div class="customSelectToggle" onclick="toggleRoleDropdown()">
                                    <span id="roleLabel"><?= $editData ? ucfirst($editData['role']) : 'Employee' ?></span>
                                    <i class="bi bi-chevron-down"></i>
                                </div>
                                <div class="customSelectMenu" id="roleDropdown">
                                    <div class="customSelectItem" onclick="selectRole('employee', 'Employee')">Employee</div>
                                    <div class="customSelectItem" onclick="selectRole('workforce', 'Workforce')">Workforce</div>
                                    <div class="customSelectItem" onclick="selectRole('admin', 'Admin')">Admin</div>
                                </div>
                            </div>
                            <input type="hidden" name="role" id="roleInput" value="<?= $editData ? $editData['role'] : 'employee' ?>">
                        </div>

                        <!-- Department -->
                        <div class="formGroup">
                            <label>Department</label>
                            <div class="customSelectWrapper">
                                <div class="customSelectToggle" onclick="toggleDeptDropdown()">
                                    <span id="deptLabel"><?= $editData && $editData['department'] ? htmlspecialchars($editData['department']) : 'None' ?></span>
                                    <i class="bi bi-chevron-down"></i>
                                </div>
                                <div class="customSelectMenu" id="deptDropdown">
                                    <div class="customSelectItem" onclick="selectDept('', 'None')">None</div>
                                    <div class="customSelectItem" onclick="selectDept('CSS', 'CSS')">CSS</div>
                                    <div class="customSelectItem" onclick="selectDept('HR', 'HR')">HR</div>
                                </div>
                            </div>
                            <input type="hidden" name="department" id="deptInput" value="<?= $editData ? htmlspecialchars($editData['department'] ?? '') : '' ?>">
                        </div>

                    </div>

                    <!-- Form Actions -->
                    <div class="formActions">
                        <button type="submit" class="btnSave">
                            <i class="bi bi-check-circle-fill"></i> <?= $editData ? 'Update Employee' : 'Save Employee' ?>
                        </button>
                        <?php if ($editData): ?>
                            <a href="admin_employees.php" class="btnCancel">Cancel</a>
                        <?php endif; ?>
                    </div>

                </form>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        // ---- ACTION DROPDOWN ----
        function toggleActionMenu(btn) {
            const menu = btn.nextElementSibling;
            document.querySelectorAll('.actionMenu').forEach(m => {
                if (m !== menu) m.classList.remove('show');
            });
            menu.classList.toggle('show');
        }

        // ---- SEARCH TABLE ----
        function searchTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.tableScrollWrapper tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        // ---- ROLE CUSTOM DROPDOWN ----
        function toggleRoleDropdown() {
            document.getElementById('roleDropdown').classList.toggle('show');
            document.getElementById('deptDropdown').classList.remove('show');
        }

        function selectRole(value, label) {
            document.getElementById('roleInput').value       = value;
            document.getElementById('roleLabel').textContent = label;
            document.getElementById('roleDropdown').classList.remove('show');
        }

        // ---- DEPARTMENT CUSTOM DROPDOWN ----
        function toggleDeptDropdown() {
            document.getElementById('deptDropdown').classList.toggle('show');
            document.getElementById('roleDropdown').classList.remove('show');
        }

        function selectDept(value, label) {
            document.getElementById('deptInput').value       = value;
            document.getElementById('deptLabel').textContent = label;
            document.getElementById('deptDropdown').classList.remove('show');
        }

        // ---- CLOSE DROPDOWNS ON OUTSIDE CLICK ----
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.actionDropdownWrapper')) {
                document.querySelectorAll('.actionMenu').forEach(m => m.classList.remove('show'));
            }
            if (!e.target.closest('.customSelectWrapper')) {
                document.getElementById('roleDropdown').classList.remove('show');
                document.getElementById('deptDropdown').classList.remove('show');
            }
        });

        // ---- AUTO DISMISS ALERTS ----
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity    = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 1000);

    </script>
</body>
</html>