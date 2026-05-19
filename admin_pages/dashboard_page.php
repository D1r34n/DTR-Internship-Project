<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication_pages/login.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$today = date('Y-m-d');

// ── Total employees ───────────────────────────────────────
$count = (int) $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();

// ── Present today ─────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM attendances a
    WHERE a.work_date = ?
      AND a.actual_time_in IS NOT NULL
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

// ── Random quote (cached in session for 1 hour) ───────────
$quoteText   = '';
$quoteAuthor = '';
$quoteCacheAge = isset($_SESSION['quote_fetched_at']) ? (time() - $_SESSION['quote_fetched_at']) : PHP_INT_MAX;
if ($quoteCacheAge > 3600) {
    try {
        $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
        $html = @file_get_contents('https://quotes.toscrape.com/random', false, $ctx);
        if ($html) {
            preg_match('/<span class="text"[^>]*>(.*?)<\/span>/s', $html, $tm);
            preg_match('/<small class="author"[^>]*>(.*?)<\/small>/s', $html, $am);
            $_SESSION['quote_text']       = isset($tm[1]) ? html_entity_decode(strip_tags($tm[1]), ENT_QUOTES) : '';
            $_SESSION['quote_author']     = isset($am[1]) ? strip_tags($am[1]) : '';
            $_SESSION['quote_fetched_at'] = time();
        }
    } catch (Exception $e) {}
}
$quoteText   = $_SESSION['quote_text']   ?? '';
$quoteAuthor = $_SESSION['quote_author'] ?? '';

// ── Weekly attendance overview (Mon–Sun of current week) ──
$todayDow = (int)date('N'); // 1=Mon, 7=Sun
$weekMon  = date('Y-m-d', strtotime('-' . ($todayDow - 1) . ' days'));
$weekSun  = date('Y-m-d', strtotime('+' . (7 - $todayDow) . ' days'));

$weeklyStmt = $pdo->prepare("
    SELECT
        a.work_date,
        SUM(CASE WHEN a.actual_time_in IS NOT NULL THEN 1 ELSE 0 END) AS present_count
    FROM attendances a
    WHERE a.work_date BETWEEN ? AND ?
    GROUP BY a.work_date
    ORDER BY a.work_date
");
$weeklyStmt->execute([$weekMon, $weekSun]);
$weeklyRows = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

$weeklyPresent = array_fill(0, 7, null);
$weeklyAbsent  = array_fill(0, 7, null);
foreach ($weeklyRows as $row) {
    $idx = (int)date('N', strtotime($row['work_date'])) - 1; // 0=Mon … 6=Sun
    $weeklyPresent[$idx] = (int)$row['present_count'];
    $weeklyAbsent[$idx]  = $count - (int)$row['present_count'];
}

$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// ── Employee: monthly attendance/absent counts ────────────
$empMonthPresent = 0;
$empMonthAbsent  = 0;

// ── Employee: personal weekly attendance tracker ──────────
$empWeekDays = [];
if (!$isAdmin) {
    $empId = (int)$_SESSION['user_id'];

    $s = $pdo->prepare("
        SELECT
            SUM(CASE WHEN status IN ('present','late','undertime','overtime') THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_count
        FROM attendances
        WHERE employee_id = ?
          AND MONTH(work_date) = MONTH(CURDATE())
          AND YEAR(work_date)  = YEAR(CURDATE())
    ");
    $s->execute([$empId]);
    $monthRow        = $s->fetch(PDO::FETCH_ASSOC);
    $empMonthPresent = (int)($monthRow['present_count'] ?? 0);
    $empMonthAbsent  = (int)($monthRow['absent_count']  ?? 0);

    // Schedules
    $s = $pdo->prepare("
        SELECT schedule_date, is_rest_day, scheduled_start
        FROM schedules
        WHERE employee_id = ? AND schedule_date BETWEEN ? AND ? AND status = 'approved'
    ");
    $s->execute([$empId, $weekMon, $weekSun]);
    $empSchedMap = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $empSchedMap[$r['schedule_date']] = $r;
    }

    // Attendance
    $s = $pdo->prepare("
        SELECT work_date, status FROM attendances
        WHERE employee_id = ? AND work_date BETWEEN ? AND ?
    ");
    $s->execute([$empId, $weekMon, $weekSun]);
    $empAttMap = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $empAttMap[$r['work_date']] = $r['status'];
    }

    // Leave (approved, non-OB)
    $s = $pdo->prepare("
        SELECT selected_dates, start_date, end_date
        FROM leave_requests
        WHERE employee_id = ? AND leave_type != 'ob leave' AND status = 'approved'
          AND (start_date BETWEEN ? AND ? OR end_date BETWEEN ? AND ?
               OR (start_date <= ? AND end_date >= ?))
    ");
    $s->execute([$empId, $weekMon, $weekSun, $weekMon, $weekSun, $weekMon, $weekSun]);
    $empLeaveSet = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $dates = json_decode($r['selected_dates'], true);
        if (is_array($dates) && !empty($dates)) {
            foreach ($dates as $d) {
                if ($d >= $weekMon && $d <= $weekSun) $empLeaveSet[$d] = true;
            }
        } else {
            $cur = new DateTime($r['start_date']);
            $fin = new DateTime($r['end_date']);
            while ($cur <= $fin) {
                $d = $cur->format('Y-m-d');
                if ($d >= $weekMon && $d <= $weekSun) $empLeaveSet[$d] = true;
                $cur->modify('+1 day');
            }
        }
    }

    // OB (approved)
    $s = $pdo->prepare("
        SELECT start_date FROM leave_requests
        WHERE employee_id = ? AND leave_type = 'ob leave' AND status = 'approved'
          AND start_date BETWEEN ? AND ?
    ");
    $s->execute([$empId, $weekMon, $weekSun]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $empLeaveSet[$r['start_date']] = true;
    }

    // Build 7-day status array
    for ($i = 0; $i < 7; $i++) {
        $date  = date('Y-m-d', strtotime($weekMon . " +$i days"));
        $sched = $empSchedMap[$date] ?? null;

        if (!$sched) {
            $status = 'none';
        } elseif ($sched['is_rest_day']) {
            $status = 'rest';
        } elseif (isset($empLeaveSet[$date])) {
            $status = 'leave';
        } elseif (in_array($empAttMap[$date] ?? '', ['present', 'late', 'undertime', 'overtime', 'incomplete'])) {
            $status = 'present';
        } elseif ($date < $today) {
            // Fully past day with no attendance → absent
            $status = 'absent';
        } elseif ($date === $today && $sched['scheduled_start'] && time() >= strtotime($sched['scheduled_start'])) {
            // Today: only absent once the shift has actually started
            $status = 'absent';
        } else {
            $status = 'upcoming';
        }

        $empWeekDays[] = ['date' => $date, 'status' => $status];
    }
}

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

    <link rel="stylesheet" href="dashboard_page.css">
</head>
<body>

<?php $currentPage = 'dashboard'; include '../sidebar_revised.php'; ?>

<div id="main-wrapper">

    <?php include '../topbar_revised.php'; ?>

    <div class="dashboardContent">

        <div class="row g-3 align-items-start dash-main-row">

        <!-- ── Left Column ──────────────────────────────────────── -->
        <div class="col-12 col-lg-6 dash-left-col">

        <!-- Clock -->
        <p id="currentDate"></p>
        <h1 id="currentTime"></h1>

        <!-- ── Dashboard Layout ─────────────────────────────────── -->
        <div class="dashRow row g-2">

            <!-- Row 1: Stats Cards -->
            <div class="col-12">
                <div class="row g-2">

                    <!-- TOTAL EMPLOYEES -->
                    <?php if ($isAdmin): ?>
                    <div class="col-6 col-lg">
                        <div class="summaryCard card-info h-100">
                            <div class="summaryTop">
                                <div class="icon-box icon-box-info">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <p>Total Employees</p>
                            </div>
                            <div class="summaryInfo">
                                <h5><?= $count ?></h5>
                                <span>All registered employees</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- PRESENT TODAY / MY ATTENDANCE -->
                    <div class="col-6 col-lg">
                        <div class="summaryCard card-success h-100">
                            <div class="summaryTop">
                                <div class="icon-box icon-box-success">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <p><?= $isAdmin ? 'Present Today' : 'My Attendance' ?></p>
                            </div>
                            <div class="summaryInfo">
                                <?php if ($isAdmin): ?>
                                    <h5><?= $present ?></h5>
                                    <span><?= $count > 0 ? round($present / $count * 100) : 0 ?>% of total employees</span>
                                <?php else: ?>
                                    <h5><?= $empMonthPresent ?></h5>
                                    <span>Days present this <?= date('F') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ABSENT TODAY / MY ABSENTS -->
                    <div class="col-6 col-lg">
                        <div class="summaryCard card-danger h-100">
                            <div class="summaryTop">
                                <div class="icon-box icon-box-danger">
                                    <i class="bi bi-clock-fill"></i>
                                </div>
                                <p><?= $isAdmin ? 'Absent Today' : 'My Absents' ?></p>
                            </div>
                            <div class="summaryInfo">
                                <?php if ($isAdmin): ?>
                                    <h5><?= $absent ?></h5>
                                    <span><?= $count > 0 ? round($absent / $count * 100) : 0 ?>% of total employees</span>
                                <?php else: ?>
                                    <h5><?= $empMonthAbsent ?></h5>
                                    <span>Days absent this <?= date('F') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($totalPending > 0): ?>
                    <!-- PENDING REQUESTS -->
                    <div class="col-6 col-lg">
                        <div class="summaryCard h-100">
                            <div class="summaryTop">
                                <div class="summaryIcon bg-red"><i class="bi bi-bell-fill"></i></div>
                                <p>Pending Requests</p>
                            </div>
                            <div class="summaryInfo">
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
                    </div>
                    <?php endif; ?>

                </div><!-- /.row (stats) -->
            </div><!-- /.col-12 (stats) -->

            <!-- Row 2: Birthdays + Events side by side -->
            <div class="col-md-6">
                <div class="summaryBday card card-purple h-100">
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
            </div><!-- /.col-md-6 (birthdays) -->

            <div class="col-md-6">
                <div class="summaryEvents card card-success h-100">
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
                    <a href="../employee_pages/employee_schedule.php?filter=events" class="event-view-all">
                        View All Events <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div><!-- /.col-md-6 (events) -->

            <?php if ($isAdmin): ?>
            <!-- Row 3: Attendance Overview (admin) -->
            <div class="col-12">
                <div class="attendanceOverviewCard card card-info">
                    <div class="summaryTop">
                        <div class="summaryIcon bg-blue">
                            <i class="bi bi-bar-chart-line-fill"></i>
                        </div>
                        <p>Attendance Overview</p>
                    </div>
                    <div class="attendanceChartWrap">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Row 3: My Week (employee) -->
            <div class="col-12">
                <div class="weeklyAttendCard card card-info">
                    <div class="summaryTop">
                        <div class="summaryIcon bg-blue">
                            <i class="bi bi-calendar-week-fill"></i>
                        </div>
                        <p>My Week</p>
                        <span class="wa-week-range ms-auto">
                            <?= date('M d', strtotime($weekMon)) ?> – <?= date('M d', strtotime($weekSun)) ?>
                        </span>
                    </div>
                    <div class="wa-grid">
                        <?php
                        $dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        foreach ($empWeekDays as $i => $day):
                            $isToday = ($day['date'] === $today);
                        ?>
                        <div class="wa-day<?= $isToday ? ' wa-today' : '' ?>">
                            <span class="wa-label"><?= $dayLabels[$i] ?></span>
                            <?php
                                $glassClass = match($day['status']) {
                                    'present' => ' wa-icon-glass',
                                    'absent'  => ' wa-icon-glass-danger',
                                    'leave'   => ' wa-icon-glass-warning',
                                    'rest'    => ' wa-icon-glass-neutral',
                                    default   => '',
                                };
                            ?>
                            <span class="wa-icon-wrap<?= $glassClass ?>">
                                <?php if ($day['status'] === 'present'): ?>
                                    <i class="bi bi-check-lg" style="color:var(--status-success-color)"></i>
                                <?php elseif ($day['status'] === 'absent'): ?>
                                    <i class="bi bi-x-lg" style="color:var(--danger-color)"></i>
                                <?php elseif ($day['status'] === 'rest'): ?>
                                    <i class="bi bi-moon" style="color:var(--text-muted)"></i>
                                <?php elseif ($day['status'] === 'leave'): ?>
                                    <i class="bi bi-dash-lg" style="color:var(--status-warning-color)"></i>
                                <?php elseif ($day['status'] === 'upcoming'): ?>
                                    <i class="bi bi-circle" style="color:rgba(255,255,255,0.15)"></i>
                                <?php else: ?>
                                    <i class="bi bi-circle" style="color:rgba(255,255,255,0.07)"></i>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?><!-- /.col-12 (row 3) -->

        </div><!-- /.dashRow.row -->

        </div><!-- /.col-12.col-lg-6 dash-left-col -->

        <!-- ── Right Column: Activity Logs ──────────────────────── -->
        <div class="col-12 col-lg-6 dash-right-col">
            <div class="activityLogsCard card card-orange">
                <div class="summaryTop">
                    <div class="summaryIcon bg-orange">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <p>Activity Logs</p>
                </div>
                <hr class="section-divider">
                <div class="activityLogsList">
                    <!-- content coming soon -->
                </div>
            </div>

            <!-- Quote of the Day -->
            <div class="quoteCard card card-pink mt-2">
                <div class="summaryTop">
                    <div class="summaryIcon bg-pink">
                        <i class="bi bi-chat-quote-fill"></i>
                    </div>
                    <p>Quote of the Day</p>
                </div>
                <hr class="section-divider">
                <?php if ($quoteText): ?>
                    <p class="quote-text"><?= htmlspecialchars($quoteText) ?></p>
                    <p class="quote-author">— <?= htmlspecialchars($quoteAuthor) ?></p>
                <?php else: ?>
                    <p class="quote-text text-muted" style="font-size:11px;">Could not load quote.</p>
                <?php endif; ?>
            </div>

        </div><!-- /.col-12.col-lg-6 dash-right-col -->

        </div><!-- /.dash-main-row.row -->

    </div><!-- /.dashboardContent -->
</div><!-- /#main-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($isAdmin): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    const present = <?= json_encode(array_values($weeklyPresent)) ?>;
    const absent  = <?= json_encode(array_values($weeklyAbsent)) ?>;
    const total   = <?= $count ?>;

    new Chart(document.getElementById('attendanceChart'), {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [
                {
                    label: 'Present',
                    data: present,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,0.15)',
                    fill: 'start',
                    tension: 0.4,
                    pointBackgroundColor: '#0d6efd',
                    pointBorderColor: '#0d6efd',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2,
                },
                {
                    label: 'Absent',
                    data: absent,
                    borderColor: '#ff9900',
                    backgroundColor: 'rgba(255,153,0,0.15)',
                    fill: 'start',
                    tension: 0.4,
                    pointBackgroundColor: '#ff9900',
                    pointBorderColor: '#ff9900',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { top: 10, bottom: 0 }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        color: 'rgba(255,255,255,0.7)',
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        font: { size: 12, family: 'Poppins' }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(15,15,30,0.85)',
                    titleColor: '#fff',
                    bodyColor: 'rgba(255,255,255,0.7)',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    suggestedMax: total + 1,
                    ticks: {
                        precision: 0,
                        color: 'rgba(255,255,255,0.5)',
                        font: { size: 11, family: 'Poppins' }
                    },
                    grid:   { color: 'rgba(255,255,255,0.07)' },
                    border: { color: 'transparent' }
                },
                x: {
                    ticks: {
                        color: 'rgba(255,255,255,0.5)',
                        font: { size: 11, family: 'Poppins' }
                    },
                    grid:   { color: 'rgba(255,255,255,0.07)' },
                    border: { color: 'transparent' }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>

<script>
(function () {
    const dateEl = document.getElementById('currentDate');
    const timeEl = document.getElementById('currentTime');

    const days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    function tick() {
        const now = new Date();
        const day  = days[now.getDay()];
        const mon  = months[now.getMonth()];
        const date = now.getDate();
        const yr   = now.getFullYear();

        let h = now.getHours();
        const m   = String(now.getMinutes()).padStart(2, '0');
        const s   = String(now.getSeconds()).padStart(2, '0');
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;

        dateEl.textContent = `${day}, ${mon} ${date}, ${yr}`;
        timeEl.textContent = `${h}:${m}:${s} ${ampm}`;
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
</body>
</html>