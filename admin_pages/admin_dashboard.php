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

$today = date('Y-m-d');
$year  = date('Y');

// ── Total employees ───────────────────────────────────────
$count = (int) $pdo->query("
    SELECT COUNT(*)
    FROM employees e
    JOIN roles r ON r.id = e.role_id
    WHERE r.role_key = 'employee'
")->fetchColumn();

// ── Present today ─────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM attendances a
    JOIN employees e ON e.id = a.employee_id
    JOIN roles r     ON r.id = e.role_id
    WHERE a.work_date = ?
      AND a.status    = 'present'
      AND r.role_key  = 'employee'
");
$stmt->execute([$today]);
$present = (int) $stmt->fetchColumn();
$absent  = $count - $present;

// ── Pending requests ──────────────────────────────────────
$pendingLeave   = (int) $pdo->query("SELECT COUNT(*) FROM leave_requests    WHERE status = 'pending'")->fetchColumn();
$pendingOT      = (int) $pdo->query("SELECT COUNT(*) FROM overtime_requests WHERE status = 'pending'")->fetchColumn();
$pendingLogEdit = (int) $pdo->query("SELECT COUNT(*) FROM log_edit_requests  WHERE status = 'pending'")->fetchColumn();
$totalPending   = $pendingLeave + $pendingOT + $pendingLogEdit;

// ── Birthdays this month ──────────────────────────────────
$birthdaysThisMonth = $pdo->query("
    SELECT 
        CONCAT(first_name, ' ', last_name) AS full_name,
        birthdate
    FROM employees
    WHERE birthdate IS NOT NULL
      AND MONTH(birthdate) = MONTH(CURDATE())
    ORDER BY DAY(birthdate) ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── HOLIDAYS (FIXED — THIS WAS MISSING) PLACEHOLDER ───────────────────
$phHolidays = [
    ['name' => "New Year's Day",        'date' => "$year-01-01"],
    ['name' => "EDSA People Power",     'date' => "$year-02-25"],
    ['name' => "Araw ng Kagitingan",    'date' => "$year-04-09"],
    ['name' => "Labor Day",             'date' => "$year-05-01"],
    ['name' => "Independence Day",      'date' => "$year-06-12"],
    ['name' => "National Heroes Day",   'date' => "$year-08-25"],
    ['name' => "Bonifacio Day",         'date' => "$year-11-30"],
    ['name' => "Christmas Day",         'date' => "$year-12-25"],
    ['name' => "Rizal Day",             'date' => "$year-12-30"],
    ['name' => "Chinese New Year",      'date' => "$year-01-29"],
    ['name' => "Maundy Thursday",       'date' => "$year-04-17"],
    ['name' => "Good Friday",           'date' => "$year-04-18"],
    ['name' => "Black Saturday",        'date' => "$year-04-19"],
    ['name' => "Eid'l Fitr",            'date' => "$year-05-15"],
    ['name' => "Eid'l Adha",            'date' => "$year-06-07"],
    ['name' => "Ninoy Aquino Day",      'date' => "$year-08-21"],
    ['name' => "All Saints' Day",       'date' => "$year-11-01"],
    ['name' => "All Souls' Day",        'date' => "$year-11-02"],
    ['name' => "Feast of Immac. Conc.", 'date' => "$year-12-08"],
    ['name' => "Christmas Eve",         'date' => "$year-12-24"],
    ['name' => "Last Day of Year",      'date' => "$year-12-31"],
];

// ── FILTER UPCOMING HOLIDAYS ───────────────────────────────
$upcomingHolidays = array_filter($phHolidays, fn($h) => $h['date'] >= $today);
usort($upcomingHolidays, fn($a, $b) => strcmp($a['date'], $b['date']));
$upcomingHolidays = array_values($upcomingHolidays);

$upcomingHolidayCount  = count($upcomingHolidays);
$upcomingHolidaysSlice = array_slice($upcomingHolidays, 0, 3);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <link rel="stylesheet" href="admin_dashboard.css">
</head>
<body>

<?php $currentPage = 'dashboard'; include '../sidebar_revised.php'; ?>

<div id="main-wrapper">

    <?php include '../topbar_revised.php'; ?>

    <div class="dashboardContent">

        <!-- Clock -->
        <p id="currentDate"></p>
        <h1 id="currentTime"></h1>

        <!-- ── Row 1: Attendance Summary Cards ────────────────── -->
        <div class="dashboardSummary">

            <!-- TOTAL EMPLOYEES -->
        <div class="summaryCard">
            <div class="summaryTop">
                <div class="summaryIcon bg-green">
                    <i class="bi bi-people-fill"></i>
                </div>
                <p>Total Employees</p>
            </div>

            <div class="summaryInfo">
                <h5><?= $count ?></h5>
                <span>All registered employees</span>
            </div>
        </div>


        <!-- PRESENT TODAY -->
        <div class="summaryCard">
            <div class="summaryTop">
                <div class="summaryIcon bg-blue">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <p>Present Today</p>
            </div>

            <div class="summaryInfo">
                <h5><?= $present ?></h5>
                <span><?= $count > 0 ? round($present / $count * 100) : 0 ?>% of total employees</span>
            </div>
        </div>


        <!-- ABSENT TODAY -->
        <div class="summaryCard">
            <div class="summaryTop">
                <div class="summaryIcon bg-orange">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <p>Absent Today</p>
            </div>

            <div class="summaryInfo">
                <h5><?= $absent ?></h5>
                <span><?= $count > 0 ? round($absent / $count * 100) : 0 ?>% of total employees</span>
            </div>
        </div>


            <?php if ($totalPending > 0): ?>
            <div class="summaryCard">
                <div class="summaryIcon bg-red"><i class="bi bi-bell-fill"></i></div>
                <div class="summaryInfo">
                    <p>Pending Requests</p>
                    <h5><?= $totalPending ?></h5>
                    <span>
                        <?php
                        $parts = [];
                        if ($pendingLeave)   $parts[] = "$pendingLeave leave";
                        if ($pendingOT)      $parts[] = "$pendingOT overtime";
                        if ($pendingLogEdit) $parts[] = "$pendingLogEdit log edit";
                        echo implode(' &bull; ', $parts);
                        ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /.dashboardSummary -->

        <!-- ── Row 2 + Info Panels Combined ───────────────────────── -->
                <div class="dashboardSummary mt-3">

                   <div class="summaryBday">
                        <div class="summaryTop">
                            <div class="summaryIcon bg-blue">
                                <i class="bi bi-cake"></i>
                            </div>
                            <p>Birthdays This Month</p>
                        </div>

                        <h5 class="summaryCount"><?= count($birthdaysThisMonth ?? []) ?></h5>

                        <div class="birthdayList">
                            <?php if (empty($birthdaysThisMonth)): ?>
                                <small class="text-muted">No birthdays this month</small>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($birthdaysThisMonth as $b): ?>
                                        <li>
                                            <strong><?= htmlspecialchars($b['full_name']) ?></strong>
                                            <span>(<?= date('M d', strtotime($b['birthdate'])) ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>


                    <div class="summaryHolidays">
                        <div class="summaryTop">
                            <div class="summaryIcon bg-green">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <p>Upcoming Holidays</p>
                        </div>

                        <h5 class="summaryCount"><?= $upcomingHolidayCount ?></h5>

                        <div class="holidayList">
                            <?php if (empty($upcomingHolidaysSlice)): ?>
                                <small class="text-muted">No upcoming holidays</small>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($upcomingHolidaysSlice as $h): ?>
                                        <li>
                                            <strong><?= htmlspecialchars($h['name']) ?></strong>
                                            <span><?= date('M d', strtotime($h['date'])) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
              </div>