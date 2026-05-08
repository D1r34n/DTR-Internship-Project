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

$today   = date('Y-m-d');
$count   = $pdo->query("SELECT COUNT(*) FROM employees e JOIN roles r ON r.id = e.role_id WHERE r.role_key = 'employee'")->fetchColumn();
$stmt    = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM logs WHERE DATE(log_time) = ? AND log_type = 'login'");
$stmt->execute([$today]);
$present = $stmt->fetchColumn();
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>

    <!-- 1. Bootstrap FIRST -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <!-- 2. Your global CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <!-- 3. Page-specific CSS -->
    <link rel="stylesheet" href="admin_dashboard.css">
</head>
<body>

    <?php $currentPage = 'dashboard'; include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">

        <?php include '../topbar_revised.php'; ?>

        <div class="dashboardContent">
            <p id="currentDate"></p>
            <h1 id="currentTime"></h1>

            <div class="dashboardSummary">
                <div class="summaryCard">
                    <p>Total Employees</p>
                    <h5><?= $count ?></h5>
                </div>

                <div class="summaryCard">
                    <p>Present Today</p>
                    <h5><?= $present ?></h5>
                </div>

                <div class="summaryCard">
                    <p>Absent Today</p>
                    <h5><?= $count - $present ?></h5>
                </div>
            </div>
        </div>

    </div>

    <script>
        function updateDateTime() {
            const now = new Date();
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const timeOptions = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
            document.getElementById("currentDate").textContent = now.toLocaleDateString(undefined, dateOptions);
            document.getElementById("currentTime").textContent = now.toLocaleTimeString(undefined, timeOptions);
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
