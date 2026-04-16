<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'db.php';

$employeeId = $_SESSION['user_id'];

// Get current week Monday to Sunday
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));

$stmt = $pdo->prepare("
    SELECT * FROM schedules 
    WHERE employee_id = ? 
    AND work_date BETWEEN ? AND ?
    ORDER BY work_date ASC
");
$stmt->execute([$employeeId, $monday, $sunday]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTR Project Acer</title>
    <link rel="stylesheet" href="schedule_page.css">
    <link rel="stylesheet" href="side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body::before { background-image: url('images/drt_bg.jpg'); }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <?php include 'side_bar.php'; ?>

    <!-- TOPBAR -->
    <?php 
    $current_page = 'schedule';
    include 'top_bar.php'; 
    ?>

    <div class="scheduleBoxWrapper">
        <div class="scheduleBox">
            <div class="scheduleHeader">
                <h5 class="tableTitle">My Schedule</h5>
                <span class="weekLabel">Week of <?= date('F d', strtotime($monday)) ?> – <?= date('F d, Y', strtotime($sunday)) ?></span>
            </div>

            <table class="table table-bordered table-hover mt-3">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($schedules) > 0): ?>
                        <?php foreach ($schedules as $row): ?>
                            <?php
                                $isRestDay = $row['is_rest_day'];
                                $isToday = $row['work_date'] === date('Y-m-d');
                            ?>
                            <tr class="<?= $isRestDay ? 'rest-day' : '' ?> <?= $isToday ? 'today-row' : '' ?>">
                                <td><strong><?= date('l', strtotime($row['work_date'])) ?></strong></td>
                                <td><?= date('F d, Y', strtotime($row['work_date'])) ?></td>
                                <td><?= !$isRestDay ? date('h:i A', strtotime($row['time_in'])) : '—' ?></td>
                                <td><?= !$isRestDay ? date('h:i A', strtotime($row['time_out'])) : '—' ?></td>
                                <td>
                                    <?php if ($isRestDay): ?>
                                        <span class="badge restBadge">Rest Day</span>
                                    <?php elseif ($isToday): ?>
                                        <span class="badge todayBadge">Today</span>
                                    <?php else: ?>
                                        <span class="badge workBadge">Work Day</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No schedule found for this week.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>