<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once 'db.php';

$success = "";
$editData = null;

// HANDLE DELETE
if (isset($_GET['delete'])) {
    $employeeId = $_GET['delete'];
    
    // Delete logs first
    $stmt = $pdo->prepare("DELETE FROM logs WHERE employee_id = ?");
    $stmt->execute([$employeeId]);
    
    // Delete schedules too
    $stmt = $pdo->prepare("DELETE FROM schedules WHERE employee_id = ?");
    $stmt->execute([$employeeId]);
    
    // Then delete employee
    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    
    $success = "Employee deleted successfully!";
}

// HANDLE ADD / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    if (isset($_POST['employee_id']) && !empty($_POST['employee_id'])) {
        // EDIT
        if (!empty($password)) {
            $stmt = $pdo->prepare("UPDATE employees SET name=?, email=?, password=?, role=? WHERE id=?");
            $stmt->execute([$name, $email, $password, $role, $_POST['employee_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE employees SET name=?, email=?, role=? WHERE id=?");
            $stmt->execute([$name, $email, $role, $_POST['employee_id']]);
        }
        $success = "Employee updated successfully!";
    } else {
        // ADD
        $stmt = $pdo->prepare("INSERT INTO employees (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $role]);
        $success = "Employee added successfully!";
    }
}

// HANDLE EDIT LOAD
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// GET ALL EMPLOYEES
$employees = $pdo->query("SELECT * FROM employees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Management</title>
    <link rel="stylesheet" href="admin_employees.css">
    <link rel="stylesheet" href="side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body::before { background-image: url('images/drt_bg.jpg'); }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <?php include 'admin_topbar.php'; ?>

    <div class="adminWrapper">
        <div class="adminBox">

            <div class="adminTitleRow">
            <h5 class="adminTitle">Employee Management</h5>
            <input type="text" id="searchInput" class="searchInput" placeholder="Search employee..." onkeyup="searchTable()">
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

           <!-- EMPLOYEE LIST -->
           <div class="tableScrollWrapper">
           <table class="table table-bordered table-hover mt-3">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
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
                                <td>
        <div class="actionDropdownWrapper">
        <button class="btn btn-sm editBtn actionToggle" onclick="toggleActionMenu(this)"> Actions <i class="bi bi-chevron-down"></i>
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
                        <tr><td colspan="4" class="text-center">No employees found.</td></tr>
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
                        <div class="formGroup">
                            <label>Name</label>
                            <input type="text" name="name" class="formControl" required
                                value="<?= $editData ? htmlspecialchars($editData['name']) : '' ?>">
                        </div>

                        <div class="formGroup">
                            <label>Email</label>
                            <input type="email" name="email" class="formControl" required
                                value="<?= $editData ? htmlspecialchars($editData['email']) : '' ?>">
                        </div>

                        <div class="formGroup">
                            <label><?= $editData ? 'New Password (leave blank to keep)' : 'Password' ?></label>
                            <input type="password" name="password" class="formControl"
                                <?= $editData ? '' : 'required' ?>>
                        </div>

                        <div class="formGroup">
                            <label>Role</label>
                            <select name="role" class="formControl" required>
                                <option value="employee" <?= ($editData && $editData['role'] === 'employee') ? 'selected' : '' ?>>Employee</option>
                                <option value="admin" <?= ($editData && $editData['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                    </div>

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
function toggleActionMenu(btn) {
    const menu = btn.nextElementSibling;
    document.querySelectorAll('.actionMenu').forEach(m => {
        if (m !== menu) m.classList.remove('show');
    });
    menu.classList.toggle('show');
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.actionDropdownWrapper')) {
        document.querySelectorAll('.actionMenu').forEach(m => m.classList.remove('show'));
    }
});

function searchTable() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.tableScrollWrapper tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
    });
}
</script>

</body>
</html>