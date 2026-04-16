<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'db.php';

$employeeId = $_SESSION['user_id'];

// Get first login and last logout per day
$stmt = $pdo->prepare("
    SELECT 
        DATE(log_time) as date,
        MIN(CASE WHEN log_type = 'login' THEN log_time END) as first_time_in,
        MAX(CASE WHEN log_type = 'logout' THEN log_time END) as last_time_out,
        ROUND(
            TIMESTAMPDIFF(MINUTE, 
                MIN(CASE WHEN log_type = 'login' THEN log_time END),
                MAX(CASE WHEN log_type = 'logout' THEN log_time END)
            ) / 60, 2
        ) as total_work_hours
    FROM logs
    WHERE employee_id = ?
    GROUP BY DATE(log_time)
    ORDER BY DATE(log_time) DESC
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
    <link rel="stylesheet" href="record_page.css">
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
    <?php include 'top_bar.php'; ?>

    <div class="recordBoxWrapper">
        <div class="recordBox">
            <h5 class="tableTitle">My Attendance Records</h5>
            <table class="table table-bordered table-hover mt-3">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Total Work Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($records) > 0): ?>
                        <?php foreach ($records as $row): ?>
                            <?php
                                $timeIn = $row['first_time_in'];
                                $timeInOnly = $timeIn ? date('H:i:s', strtotime($timeIn)) : null;
                                $status = '—';
                                if ($timeInOnly) {
                                    $status = ($timeInOnly > '08:30:00') ? 'Late' : 'Present';
                                }
                            ?>
                            <tr>
                                <td><?= date('F d, Y', strtotime($row['date'])) ?></td>
                                <td><?= $timeIn ? date('h:i A', strtotime($timeIn)) : '—' ?></td>
                                <td><?= $row['last_time_out'] ? date('h:i A', strtotime($row['last_time_out'])) : '—' ?></td>
                                <td><?= $row['total_work_hours'] ? $row['total_work_hours'] . ' hrs' : '—' ?></td>
                                <td><?= $status ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>