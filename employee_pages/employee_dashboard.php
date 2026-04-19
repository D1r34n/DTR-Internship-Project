<!-- PHP -->
<!-- Redirects the user to the login page if not logged in -->
<?php
session_start();

// Check if user is logged in else redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

$employeeId = $_SESSION['user_id'];

$today = date("Y-m-d");
$stmt = $pdo->prepare("
    SELECT log_time, log_type
    FROM logs
    WHERE employee_id = ?
    AND DATE(log_time) = ?
    ORDER BY log_time DESC
");

$stmt->execute([$employeeId, $today]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTR Project Acer</title>

    <!-- 1. Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <!-- 2. CSS -->
    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link rel="stylesheet" href="employee_dashboard.css">

</head>
<body>
    <!-- SIDEBAR -->
    <?php include '../sidebar.php'; ?>

    <!-- TOPBAR -->
    <?php 
    $current_page = 'dashboard';
    include '../topbar.php'; 
    ?>

    <!-- Dashboard -->
    <div class=dashboardWrapper>
        <div class="dashboardContent">
            <p id="currentDate"></p>
            <h1 id="currentTime"></h1>

            <div class="dashboardSummary">
                <div class="summaryCard">
                    <p>Number of Leaves</p>
                    <h5 id="dashboard_status"><?php echo $timedIn ? 'Timed In' : 'Timed Out'; ?></h5>
                </div>
                
                <div class="summaryCard">
                    <p>Total Time Worked</p>
                    <h5 id="dashboard_week_hours">0 hours</h5>
                </div>

                <div class="summaryCard">
                    <p>Total Hours This Month</p>
                    <h5 id="dashboard_month_hours">0 hours</h5>
                </div>
            </div>
        </div>

        <!-- Attendace Log for today only -->
        <div class="logsForTheDay">
            <div class="recordBox">
                <h5 class="tableTitle">Time Log</h5>

                <div class="tableScroll">
                    <table class="table table-bordered table-hover mt-3">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Log Type</th>
                            </tr>
                        </thead>

                        <tbody id="logs_table_body">
                            <?php if (count($records) > 0): ?>
                                <?php foreach ($records as $row): ?>
                                    <tr>
                                        <td><?= date('F d, Y', strtotime($row['log_time'])) ?></td>
                                        <td><?= date('h:i A', strtotime($row['log_time'])) ?></td>
                                        <td class="<?= $row['log_type'] === 'login' ? 'log-in' : 'log-out' ?>">
                                            <?= $row['log_type'] === 'login' ? 'Time In' : 'Time Out' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Java Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

    <script>
        function updateDateTime() {
            const now = new Date();

            const dateOptions = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const formattedDate = now.toLocaleDateString(undefined, dateOptions);

            const timeOptions = { 
                hour: 'numeric', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            const formattedTime = now.toLocaleTimeString(undefined, timeOptions);

            document.getElementById("currentDate").textContent = formattedDate;
            document.getElementById("currentTime").textContent = formattedTime;
        }

        function loadLogs() {
            fetch('../get_logs.php')
                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById('logs_table_body');
                    tbody.innerHTML = '';

                    if (data.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="3" class="text-center">No records found.</td>
                            </tr>`;
                        return;
                    }

                    data.forEach(row => {
                        const typeLabel = row.log_type === 'login' ? 'Time In' : 'Time Out';
                        const typeClass = row.log_type === 'login' ? 'log-in' : 'log-out';

                        tbody.innerHTML += `
                            <tr>
                                <td>${new Date(row.log_time).toLocaleDateString()}</td>
                                <td>${new Date(row.log_time).toLocaleTimeString()}</td>
                                <td class="${typeClass}">${typeLabel}</td>
                            </tr>
                        `;
                    });
                });
        }

        updateDateTime();

        // This updates every second (1000 milliseconds)
        setInterval(updateDateTime, 1000);

        getTotalWorkedHours();
        loadLogs()
    </script>
    </body>
    </html>

    <?php

    ?>
