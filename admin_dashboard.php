<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin_dashboard.css">
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

    <div class="dashboardContent">
        <p id="currentDate"></p>
        <h1 id="currentTime"></h1>

        <div class="dashboardSummary">
            <div class="summaryCard">
                <p>Total Employees</p>
                <h5><?php
                    require_once 'db.php';
                    $count = $pdo->query("SELECT COUNT(*) FROM employees WHERE role = 'employee'")->fetchColumn();
                    echo $count;
                ?></h5>
            </div>

            <div class="summaryCard">
                <p>Present Today</p>
                <h5><?php
                    $today = date('Y-m-d');
                    $present = $pdo->query("SELECT COUNT(DISTINCT employee_id) FROM logs WHERE DATE(log_time) = '$today' AND log_type = 'login'")->fetchColumn();
                    echo $present;
                ?></h5>
            </div>

            <div class="summaryCard">
                <p>Absent Today</p>
                <h5><?php echo $count - $present; ?></h5>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>