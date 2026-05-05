<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_GET['id'])) {
    header("Location: admin_employees.php");
    exit();
}

$employeeId = $_GET['id'];

// ---- GET EMPLOYEE ----
$stmt = $pdo->prepare("
    SELECT e.*, d.department_name, d.department_code
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.id = ?
");
$stmt->execute([$employeeId]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    echo "Employee not found.";
    exit();
}

// ---- GET LOGS (optional preview) ----
$logs = $pdo->prepare("
    SELECT * FROM logs 
    WHERE employee_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$logs->execute([$employeeId]);
$logs = $logs->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html>
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
    <link rel="stylesheet" href="admin_employees_revised.css">

    <!-- 4. Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
</head>

<body>

<?php include '../sidebar_revised.php'; ?>

<div id="main-wrapper" class="p-4">

    <a href="admin_employees_revised.php" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Back
    </a>

    <!-- EMPLOYEE CARD -->
    <div class="card card-glass p-4 mb-4">

        <h3 class="mb-3">
            <?= htmlspecialchars($emp['name']) ?>
        </h3>

        <p><strong>Email:</strong> <?= htmlspecialchars($emp['email']) ?></p>

        <p><strong>Role:</strong> <?= ucfirst($emp['role']) ?></p>

        <p><strong>Department:</strong>
            <?= htmlspecialchars($emp['department_name'] ?? 'None') ?>
        </p>

    </div>

    <!-- LOGS PREVIEW -->
    <div class="card card-glass p-4">

        <h5 class="mb-3">Recent Logs</h5>

        <?php if (empty($logs)): ?>
            <p class="text-muted">No logs found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= $log['created_at'] ?? '-' ?></td>
                                <td><?= $log['action'] ?? '-' ?></td>
                                <td><?= $log['time'] ?? '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>