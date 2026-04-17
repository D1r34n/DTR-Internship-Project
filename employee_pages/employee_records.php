<?php
session_start();

// Check if user is logged in else redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

$employeeId = $_SESSION['user_id'];
$today = date('Y-m-d');

// Get all attendance records
$stmt = $pdo->prepare("
    SELECT 
        date,
        time_in as first_time_in,
        time_out as last_time_out,
        total_work_hours,
        status
    FROM attendance
    WHERE employee_id = ?
    ORDER BY date DESC
");
$stmt->execute([$employeeId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's time in and out for chart
$todayStmt = $pdo->prepare("
    SELECT 
        time_in as first_time_in,
        time_out as last_time_out
    FROM attendance
    WHERE employee_id = ?
    AND date = ?
");
$todayStmt->execute([$employeeId, $today]);
$todayRecord = $todayStmt->fetch(PDO::FETCH_ASSOC);

$firstTimeInRaw = $todayRecord['first_time_in'] ?? null;
$lastTimeOutRaw = $todayRecord['last_time_out'] ?? null;

// formatted (for display only)
$firstTimeInDisplay = $firstTimeInRaw ? date('h:i A', strtotime($firstTimeInRaw)) : null;
$lastTimeOutDisplay = $lastTimeOutRaw ? date('h:i A', strtotime($lastTimeOutRaw)) : null;
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTR Project Acer</title>
    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="employee_records.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body::before { background-image: url('../images/drt_bg.jpg'); }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <?php 
    $current_page = 'records';
    include '../topbar.php'; ?>

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
                    <tbody id="attendance_table_body"></tbody>
                </table>
            </div>

            <!-- CHART TAB -->
            <div id="chart" class="tabContent">
                <h5 class="tableTitle">Today's Timeline — <?= date('F d, Y') ?></h5>
                <div class="timelineWrapper">
                    <div class="timelineBar">
                        <?php if ($firstTimeInRaw): ?>
                            <?php
                                // Timeline from 8:30 to 17:30 = 540 minutes total
                                $scheduleStart = strtotime(date('Y-m-d') . ' 08:30:00');
                                $scheduleEnd = strtotime(date('Y-m-d') . ' 17:30:00');
                                $totalMinutes = ($scheduleEnd - $scheduleStart) / 60;

                                $inMinutes = (strtotime($firstTimeInRaw) - $scheduleStart) / 60;

                                $outMinutes = $lastTimeOutRaw 
                                    ? (strtotime($lastTimeOutRaw) - $scheduleStart) / 60 
                                    : (time() - $scheduleStart) / 60;

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

                <div id="timeStats"></div>
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

    document.addEventListener("DOMContentLoaded", function () {
        loadAttendance();
    });

    function loadAttendance() {
        fetch('../get_attendance.php')
            .then(res => res.json())
            .then(data => {

                const tbody = document.getElementById('attendance_table_body');
                const chartBar = document.querySelector('.timelineBar');

                tbody.innerHTML = '';

                if (!data || data.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center">No records found.</td>
                        </tr>`;
                    if (chartBar) chartBar.innerHTML = '';
                    return;
                }

                console.log('Attendance refreshed');

                // =========================
                // TABLE RENDER
                // =========================
                data.forEach(row => {

                    const timeIn = (row.time_in && row.time_in !== "0000-00-00 00:00:00")
                        ? new Date(`${row.date}T${row.time_in}`).toLocaleTimeString()
                        : '—';

                    const timeOut = (row.time_out && row.time_out !== "0000-00-00 00:00:00")
                        ? new Date(`${row.date}T${row.time_out}`).toLocaleTimeString()
                        : '—';

                    tbody.innerHTML += `
                        <tr>
                            <td>${new Date(row.date).toLocaleDateString()}</td>
                            <td>${timeIn}</td>
                            <td>${timeOut}</td>
                            <td>${row.total_work_hours ?? '—'} hrs</td>
                            <td>${row.status ?? '—'}</td>
                        </tr>
                    `;
                });

                // =========================
                // CHART UPDATE (TODAY ONLY)
                // =========================

                const today = new Date().toISOString().slice(0, 10);
                const todayRecord = data.find(r => r.date === today);
                
                const timeStats = document.getElementById('timeStats');
                
                if (!timeStats) return;

                if (!todayRecord || !todayRecord.time_in) {
                    timeStats.innerHTML = `
                        <p class="text-center mt-4 text-muted">No data for today yet.</p>
                    `;
                } else {

                    const timeIn = new Date(`${today}T${todayRecord.time_in}`).toLocaleTimeString();

                    const timeOut = todayRecord.time_out
                        ? new Date(`${today}T${todayRecord.time_out}`).toLocaleTimeString()
                        : null;

                    timeStats.innerHTML = `
                        <div class="timeStats">
                            <span>
                                <i class="bi bi-box-arrow-in-right"></i>
                                Time In: <strong>${timeIn}</strong>
                            </span>

                            ${
                                timeOut
                                    ? `<span>
                                        <i class="bi bi-box-arrow-right"></i>
                                        Time Out: <strong>${timeOut}</strong>
                                    </span>`
                                    : `<span>
                                        <i class="bi bi-clock"></i>
                                        Still working...
                                    </span>`
                            }
                        </div>
                    `;
                }

                if (!todayRecord || !chartBar) return;

                const scheduleStart = new Date(`${today}T08:30:00`);
                const scheduleEnd = new Date(`${today}T17:30:00`);
                const totalMinutes = (scheduleEnd - scheduleStart) / 60000;

                const inTime = new Date(`${today}T${todayRecord.time_in}`);
                const outTime = todayRecord.time_out
                    ? new Date(`${today}T${todayRecord.time_out}`)
                    : new Date();

                const inMinutes = (inTime - scheduleStart) / 60000;
                const outMinutes = (outTime - scheduleStart) / 60000;

                const leftPercent = Math.max(0, Math.min((inMinutes / totalMinutes) * 100, 100));
                const widthPercent = Math.max(0, Math.min(((outMinutes - inMinutes) / totalMinutes) * 100, 100));

                chartBar.innerHTML = `
                    <div class="greenBar"
                        style="left:${leftPercent}%; width:${widthPercent}%;">
                    </div>
                `;
            });
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>