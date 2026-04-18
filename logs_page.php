<?php
session_start();

// Check if user is logged in else redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'db.php';

$employeeId = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT log_time, log_type
    FROM logs
    WHERE employee_id = ?
    ORDER BY log_time DESC
");

$stmt->execute([$employeeId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTR Project Acer</title>
    <link rel="stylesheet" href="logs_page.css">
    <link rel="stylesheet" href="side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body::before { background-image: url('images/drt_bg.jpg'); }
    </style>
</head>
<body>
    <?php include 'side_bar.php'; ?>
    
    <?php 
    $current_page = 'records';
    include 'top_bar.php'; ?>

    <div class="recordBoxWrapper">
        <div class="recordBox">
            <h5 class="tableTitle">My Attendance Logs</h5>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script>
        function loadLogs() {
            fetch('get_logs.php')
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

        // Load the logs table
        loadLogs();
</script>
</body>
</html>