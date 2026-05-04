<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

$employeeId = $_SESSION['user_id'];

// Set timezone
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// Fetch today's logs
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>

    <!-- CSS Load Order: root → typography → components → navbars → page -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="employee_dashboard.css">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>

    <!-- SIDEBAR (left column) -->
    <?php
    $currentPage = 'dashboard';
    include '../sidebar_revised.php';
    ?>

    <!-- RIGHT COLUMN: topbar + content -->
    <div id="main-wrapper">

        <!-- TOPBAR -->
        <?php include '../topbar_revised.php'; ?>

        <!-- DASHBOARD CONTENT -->
        <div class="dashboard-wrapper">
            <div class="dashboard-content">
                
            </div>
        </div>

    </div>

    <!-- JAVASCRIPT -->
    <!-- <script>
        // Update date and time display every second
        function updateDateTime() {
            const now = new Date();

            const dateOptions = {
                weekday: 'long',
                year:    'numeric',
                month:   'long',
                day:     'numeric'
            };

            const timeOptions = {
                hour:   'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };

            document.getElementById('current-date').textContent = now.toLocaleDateString(undefined, dateOptions);
            document.getElementById('current-time').textContent = now.toLocaleTimeString(undefined, timeOptions);
        }

        // Fetch and render today's logs
        function fetchLogs() {
            fetch('../get_logs.php')
                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById('logs-table-body');
                    tbody.innerHTML = '';

                    if (data.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="3" class="text-center text-meta">No records found.</td>
                            </tr>
                        `;
                        return;
                    }

                    data.forEach(row => {
                        const typeLabel = row.log_type === 'IN' ? 'Time In'  : 'Time Out';
                        const typeClass = row.log_type === 'IN' ? 'log-in'   : 'log-out';

                        tbody.innerHTML += `
                            <tr>
                                <td>${new Date(row.log_time).toLocaleDateString()}</td>
                                <td>${new Date(row.log_time).toLocaleTimeString()}</td>
                                <td class="${typeClass}">${typeLabel}</td>
                            </tr>
                        `;
                    });
                })
                .catch(err => console.error('Failed to fetch logs:', err));
        }

        updateDateTime();
        setInterval(updateDateTime, 1000);

        getTotalWorkedHours();
        fetchLogs();
    </script> -->
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>