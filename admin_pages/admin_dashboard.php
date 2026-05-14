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
        birthdate,
        profile_image
    FROM employees
    WHERE birthdate IS NOT NULL
      AND MONTH(birthdate) = MONTH(CURDATE())
    ORDER BY
        CASE WHEN DAY(birthdate) >= DAY(CURDATE()) THEN 0 ELSE 1 END ASC,
        DAY(birthdate) ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Upcoming events ───────────────────────────────────────
$upcomingEvents = $pdo->query("
    SELECT title, event_type, start_datetime
    FROM events
    ORDER BY
        CASE WHEN DATE(start_datetime) >= CURDATE() THEN 0 ELSE 1 END ASC,
        CASE WHEN DATE(start_datetime) >= CURDATE() THEN start_datetime END ASC,
        CASE WHEN DATE(start_datetime) <  CURDATE() THEN start_datetime END DESC
")->fetchAll(PDO::FETCH_ASSOC);
$upcomingEventsCount = count(array_filter($upcomingEvents, fn($e) => strtotime(date('Y-m-d', strtotime($e['start_datetime']))) >= strtotime($today)));

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
        <div class="summaryCard card-glass">
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
        <div class="summaryCard card-glass">
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
        <div class="summaryCard card-glass">
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
            <div class="summaryCard ">
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

                   <div class="summaryBday card card-neutral">
                        <div class="summaryTop">
                            <div class="summaryIcon bg-blue">
                                <i class="bi bi-cake"></i>
                            </div>
                            <p>Birthdays This Month</p>
                        </div>

                        <h5 class="summaryCount"><?= count($birthdaysThisMonth ?? []) ?></h5>

                        <hr class="section-divider">

                        <div class="birthdayList">
                            <?php if (empty($birthdaysThisMonth)): ?>
                                <small class="text-muted">No birthdays this month</small>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($birthdaysThisMonth as $b): ?>
                                        <?php
                                            $avatarSrc = !empty($b['profile_image'])
                                                ? '../assets/user_profiles/' . htmlspecialchars($b['profile_image'])
                                                : '../assets/user_profiles/default_avatar.png';

                                            $bdayThisYear = date('Y') . '-' . date('m-d', strtotime($b['birthdate']));
                                            $diff = (int) (strtotime($bdayThisYear) - strtotime($today)) / 86400;
                                            if ($diff === 0) {
                                                $daysLabel = 'Today!';
                                                $daysClass = 'bday-days today';
                                            } elseif ($diff > 0) {
                                                $daysLabel = 'In ' . $diff . ' ' . ($diff === 1 ? 'day' : 'days');
                                                $daysClass = 'bday-days upcoming';
                                            } else {
                                                $daysLabel = abs($diff) . ' ' . (abs($diff) === 1 ? 'day' : 'days') . ' ago';
                                                $daysClass = 'bday-days past';
                                            }
                                        ?>
                                        <li>
                                            <img src="<?= $avatarSrc ?>" class="bday-avatar" alt="">
                                            <div class="bday-info">
                                                <strong><?= htmlspecialchars($b['full_name']) ?></strong>
                                                <span class="bday-date"><?= date('l', strtotime($b['birthdate'])) ?>,
                                                <?= date('F d', strtotime($b['birthdate'])) . ' ' ?></span>
                                            </div>
                                            <span class="<?= $daysClass ?>"><?= $daysLabel ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <a href="../employee_pages/employee_schedule.php?filter=birthday" class="bday-view-all">
                            View All Birthdays <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>


                    <div class="summaryEvents card card-neutral">
                        <div class="summaryTop">
                            <div class="summaryIcon bg-green">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <p>Upcoming Events</p>
                        </div>

                        <h5 class="summaryCount"><?= $upcomingEventsCount ?></h5>

                        <hr class="section-divider">

                        <div class="EventsList">
                            <?php if (empty($upcomingEvents)): ?>
                                <small class="text-muted">No events</small>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($upcomingEvents as $h): ?>
                                        <?php
                                            $diff = (int) ((strtotime(date('Y-m-d', strtotime($h['start_datetime']))) - strtotime($today)) / 86400);
                                            if ($diff === 0) {
                                                $evtLabel = 'Today!';
                                                $evtClass = 'event-days today';
                                            } elseif ($diff > 0) {
                                                $evtLabel = 'In ' . $diff . ' ' . ($diff === 1 ? 'day' : 'days');
                                                $evtClass = 'event-days upcoming';
                                            } else {
                                                $evtLabel = abs($diff) . ' ' . (abs($diff) === 1 ? 'day' : 'days') . ' ago';
                                                $evtClass = 'event-days past';
                                            }
                                        ?>
                                        <li>
                                            <div class="event-cal">
                                                <span class="event-month"><?= date('M', strtotime($h['start_datetime'])) ?></span>
                                                <span class="event-day"><?= date('d', strtotime($h['start_datetime'])) ?></span>
                                            </div>
                                            <div class="event-info">
                                                <strong><?= htmlspecialchars($h['title']) ?></strong>
                                                <span class="event-type-label"><?= ucfirst($h['event_type']) ?></span>
                                            </div>
                                            <span class="<?= $evtClass ?>"><?= $evtLabel ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <a href="../employee_pages/employee_schedule.php?filter=events" class="bday-view-all">
                            View All Events <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
              </div><!-- /.dashboardSummary row 2 -->

    </div><!-- /.dashboardContent -->
</div><!-- /#main-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>