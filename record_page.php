<?php
session_start();

// Check if user is logged in else redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'db.php';

$employeeId = $_SESSION['user_id'];
$today = date('Y-m-d');

// Get all attendance records
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

// Get today's time in and out for chart
$todayStmt = $pdo->prepare("
    SELECT 
        MIN(CASE WHEN log_type = 'login' THEN log_time END) as first_time_in,
        MAX(CASE WHEN log_type = 'logout' THEN log_time END) as last_time_out
    FROM logs
    WHERE employee_id = ? AND DATE(log_time) = ?
");
$todayStmt->execute([$employeeId, $today]);
$todayRecord = $todayStmt->fetch(PDO::FETCH_ASSOC);

$firstTimeIn = $todayRecord['first_time_in'] ? date('H:i', strtotime($todayRecord['first_time_in'])) : null;
$lastTimeOut = $todayRecord['last_time_out'] ? date('H:i', strtotime($todayRecord['last_time_out'])) : null;
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
    
    <?php 
    $current_page = 'records';
    include 'top_bar.php'; ?>

    <div class="recordBoxWrapper">
        <div class="recordBox">

            <!-- DROPDOWN -->
            <div class="dropdownTabWrapper">
            <select class="dropdownTab" onchange="switchTab(this.value)">
            <option value="chart">Chart</option>
            <option value="table">Table</option>
    </select>
            <select class="dropdownTab" onchange="switchPeriod(this.value)">
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
    </select>
</div>

            <!-- TABLE TAB -->
           <div id="table" class="tabContent" style="display:none;">
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

            <!-- CHART TAB -->
            <div id="chart" class="tabContent">
                <h5 class="tableTitle">Today's Timeline — <?= date('F d, Y') ?></h5>
                <div class="timelineWrapper">
                    <div class="timelineBar">
                        <?php if ($firstTimeIn): ?>
                            <?php
                                // Timeline from 8:30 to 17:30 = 540 minutes total
                                $scheduleStart = strtotime('08:30');
                                $scheduleEnd = strtotime('17:30');
                                $totalMinutes = ($scheduleEnd - $scheduleStart) / 60;

                                $inMinutes = (strtotime($firstTimeIn) - $scheduleStart) / 60;
                                $outMinutes = $lastTimeOut ? (strtotime($lastTimeOut) - $scheduleStart) / 60 : (time() - $scheduleStart) / 60;

                                // Clamp values
                                $inMinutes = max(0, min($inMinutes, $totalMinutes));
                                $outMinutes = max(0, min($outMinutes, $totalMinutes));

                                $leftPercent = ($inMinutes / $totalMinutes) * 100;
                                $widthPercent = (($outMinutes - $inMinutes) / $totalMinutes) * 100;
                            ?>
                            <div class="greenBar" style="left: <?= $leftPercent ?>%; width: <?= $widthPercent ?>%;"></div>
                        <?php endif; ?>
                    </div>

                    <!-- TIME LABELS -->
                    <div class="timeLabels">
                        <span>8:30 AM</span>
                        <span>9:30 AM</span>
                        <span>10:30 AM</span>
                        <span>11:30 AM</span>
                        <span>12:30 PM</span>
                        <span>1:30 PM</span>
                        <span>2:30 PM</span>
                        <span>3:30 PM</span>
                        <span>4:30 PM</span>
                        <span>5:30 PM</span>
                    </div>
                </div>

                <?php if ($firstTimeIn): ?>
                    <div class="timeStats">
                        <span><i class="bi bi-box-arrow-in-right"></i> Time In: <strong><?= date('h:i A', strtotime($firstTimeIn)) ?></strong></span>
                        <?php if ($lastTimeOut): ?>
                            <span><i class="bi bi-box-arrow-right"></i> Time Out: <strong><?= date('h:i A', strtotime($lastTimeOut)) ?></strong></span>
                        <?php else: ?>
                            <span><i class="bi bi-clock"></i> Still working...</span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center mt-4 text-muted">No data for today yet.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
   function switchTab(tab) {
    document.querySelectorAll('.tabContent').forEach(t => t.style.display = 'none');
    document.getElementById(tab).style.display = 'block';
}
   function switchPeriod(period) {
    if (period === 'weekly') {
        // will be wired up soon
        console.log('Weekly selected');
    } else if (period === 'monthly') {
        // not yet implemented
        console.log('Monthly — coming soon');
    }
}
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>