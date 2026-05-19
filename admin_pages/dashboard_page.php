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

    <div class="container-fluid">
        <div class="row g-2 dash-row">

            <!-- Left Column -->
            <div class="col-6 dash-col">

                <!-- Greeting Card -->
                <div class="card card-neutral mb-2">
                    <div class="card-body py-2">
                        <div class="hstack gap-3 align-items-center">
                            <img
                                src="../assets/user_profiles/<?= htmlspecialchars($_SESSION['profile_image'] ?? 'default_profile.png') ?>"
                                class="dashboard-profile-image"
                                data-bs-toggle="modal"
                                data-bs-target="#editProfileImageModal"
                                style="cursor:pointer;">
                            <div>
                                <h4 class="greetings-text">
                                    Mabuhay, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>!
                                </h4>
                                <div class="hstack gap-3">
                                    <h5 class="subsection-title mb-0" id="current-time"></h5>
                                    <div class="vr"></div>
                                    <h5 class="subsection-title mb-0" id="current-date"></h5>
                                </div>
                                <p class="text-meta mb-0">Here is today's overview</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <div class="card card-success p-3">
                            <div class="card-body d-flex align-items-center gap-3 p-0">
                                <div class="icon-box icon-box-success">
                                    <i class="bi bi-check-circle-fill fs-3"></i>
                                </div>
                                <div class="d-flex flex-column ms-auto text-end">
                                    <?php if ($isAdmin): ?>
                                        <div class="stats-number"><?= $present ?></div>
                                        <div class="text-meta"><?= $count > 0 ? round($present / $count * 100) : 0 ?>% of total employees</div>
                                    <?php else: ?>
                                        <div class="stats-number"><?= $empMonthPresent ?></div>
                                        <div class="text-meta"><?= $empMonthPresent == 1 ? 'Day' : 'Days' ?> Present this <?= date('F') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card card-danger p-3">
                            <div class="card-body d-flex align-items-center gap-3 p-0">
                                <div class="icon-box icon-box-danger">
                                    <i class="bi bi-clock-fill fs-3"></i>
                                </div>
                                <div class="d-flex flex-column ms-auto text-end">
                                    <?php if ($isAdmin): ?>
                                        <div class="stats-number"><?= $absent ?></div>
                                        <div class="text-meta"><?= $count > 0 ? round($absent / $count * 100) : 0 ?>% of total employees</div>
                                    <?php else: ?>
                                        <div class="stats-number"><?= $empMonthAbsent ?></div>
                                        <div class="text-meta"><?= $empMonthAbsent == 1 ? 'Day' : 'Days' ?> Absent this <?= date('F') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card card-warning p-3">
                            <div class="card-body d-flex align-items-center gap-3 p-0">
                                <div class="icon-box icon-box-warning">
                                    <i class="bi bi-bell-fill fs-3"></i>
                                </div>
                                <div class="d-flex flex-column ms-auto text-end">
                                    <?php if ($isAdmin): ?>
                                        <div class="stats-number"><?= $totalPending ?></div>
                                        <div class="text-meta"><?= $count > 0 ? round($totalPending / $count * 100) : 0 ?>% of total employees</div>
                                    <?php else: ?>
                                        <div class="stats-number"><?= $totalPending ?></div>
                                        <div class="text-meta"><?= $totalPending == 1 ? 'Request' : 'Requests' ?> Pending</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$isAdmin): ?>
                <!-- My Week -->
                <div class="card card-info mb-2">
                    <div class="card-body py-3">
                        <div class="summaryTop mb-2">
                            <div class="summaryIcon bg-blue">
                                <i class="bi bi-calendar-week-fill"></i>
                            </div>
                            <h5 class="text-primary mb-0">My Week</h5>
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
                <?php endif; ?>

                <!-- Birthdays + Events — fills remaining space -->
                <div class="row g-2 dash-grow">

                    <!-- Birthday Summary -->
                    <div class="col-6 d-flex flex-column">
                        <div class="card card-purple h-100">
                            <div class="card-body d-flex flex-column overflow-hidden">
                                <div class="hstack gap-2 align-items-center mb-2">
                                    <div class="icon-box icon-box-sm icon-box-purple">
                                        <i class="bi bi-cake"></i>
                                    </div>
                                    <h5 class="text-primary mb-0">Birthdays This Month</h5>
                                    <h5 class="birthdays-summary-count mb-0 ms-auto">
                                        <?= count($birthdaysThisMonth ?? []) ?>
                                    </h5>
                                </div>
                                <div class="birthday-list-scroll">
                                    <?php if (empty($birthdaysThisMonth)): ?>
                                        <small class="text-muted">No birthdays this month</small>
                                    <?php else: ?>
                                        <ul class="list-unstyled mb-0 dash-list">
                                            <?php foreach ($birthdaysThisMonth as $b): ?>
                                                <?php
                                                $avatarSrc    = !empty($b['profile_image'])
                                                    ? '../assets/user_profiles/' . htmlspecialchars($b['profile_image'])
                                                    : '../assets/user_profiles/default_avatar.png';
                                                $bdayThisYear = date('Y') . '-' . date('m-d', strtotime($b['birthdate']));
                                                $diff         = (int) (strtotime($bdayThisYear) - strtotime($today)) / 86400;

                                                if ($diff === 0) {
                                                    $daysLabel = 'Today!';
                                                    $daysClass = 'bday-days status-approved';
                                                } elseif ($diff > 0) {
                                                    $daysLabel = 'In ' . $diff . ' ' . ($diff === 1 ? 'day' : 'days');
                                                    $daysClass = 'bday-days status-info';
                                                } else {
                                                    $daysLabel = abs($diff) . ' ' . (abs($diff) === 1 ? 'day' : 'days') . ' ago';
                                                    $daysClass = 'bday-days';
                                                }
                                                ?>
                                                <li>
                                                    <div class="hstack gap-2 align-items-center">
                                                        <img src="<?= $avatarSrc ?>" class="bday-avatar" alt="">
                                                        <div class="vstack">
                                                            <strong class="text-tertiary"><?= htmlspecialchars($b['full_name']) ?></strong>
                                                            <span class="text-meta">
                                                                <?= date('l', strtotime($b['birthdate'])) ?>,
                                                                <?= date('F d', strtotime($b['birthdate'])) ?>
                                                            </span>
                                                        </div>
                                                        <span class="pill <?= $daysClass ?> ms-auto"><?= $daysLabel ?></span>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex">
                                    <a class="btn btn-sm btn-success ms-auto" href="../employee_pages/employee_schedule.php?filter=birthday">
                                        View All Birthdays <i class="bi bi-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Events Summary -->
                    <div class="col-6 d-flex flex-column">
                        <div class="card card-success h-100">
                            <div class="card-body d-flex flex-column overflow-hidden">
                                <div class="hstack gap-2 align-items-center mb-3">
                                    <div class="icon-box icon-box-sm icon-box-success">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <h5 class="text-primary mb-0">Upcoming Events</h5>
                                    <h5 class="events-summary-count mb-0 ms-auto">
                                        <?= $upcomingEventsCount ?>
                                    </h5>
                                </div>
                                <div class="flex-fill overflow-y-auto">
                                    <?php if (empty($upcomingEvents)): ?>
                                        <small class="text-muted">No events</small>
                                    <?php else: ?>
                                        <ul class="list-unstyled mb-0 dash-list">
                                            <?php foreach (array_slice($upcomingEvents, 0, 3) as $h): ?>
                                                <?php
                                                $diff = (int) ((strtotime(date('Y-m-d', strtotime($h['start_datetime']))) - strtotime($today)) / 86400);

                                                if ($diff === 0) {
                                                    $evtLabel = 'Today!';
                                                    $evtClass = 'bday-days status-approved';
                                                } elseif ($diff > 0) {
                                                    $evtLabel = 'In ' . $diff . ' ' . ($diff === 1 ? 'day' : 'days');
                                                    $evtClass = 'bday-days status-info';
                                                } else {
                                                    $evtLabel = abs($diff) . ' ' . (abs($diff) === 1 ? 'day' : 'days') . ' ago';
                                                    $evtClass = 'bday-days';
                                                }
                                                ?>
                                                <li>
                                                    <div class="hstack gap-2 align-items-center">
                                                        <div class="event-cal">
                                                            <span class="event-month"><?= date('M', strtotime($h['start_datetime'])) ?></span>
                                                            <span class="event-day"><?= date('d', strtotime($h['start_datetime'])) ?></span>
                                                        </div>
                                                        <div class="vstack">
                                                            <strong class="text-tertiary"><?= htmlspecialchars($h['title']) ?></strong>
                                                            <span class="text-meta"><?= ucfirst($h['event_type']) ?></span>
                                                        </div>
                                                        <span class="pill <?= $evtClass ?> ms-auto"><?= $evtLabel ?></span>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex">
                                    <a class="btn btn-sm btn-success ms-auto" href="../employee_pages/employee_schedule.php?filter=events">
                                        View All Events <i class="bi bi-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /.dash-grow -->

            </div><!-- /.col left -->

            <!-- Right Column -->
            <div class="col-6 dash-col">

                <!-- Activity Logs Card -->
                <div class="card card-info mb-2">
                    <div class="card-body">
                        <div class="hstack gap-2 align-items-center">
                            <div class="icon-box icon-box-sm icon-box-info">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <h5 class="text-primary mb-0">Activity Logs</h5>
                        </div>
                        <!-- content coming soon -->
                    </div>
                </div>

                <!-- Fills remaining space -->
                <div class="dash-grow">
                    <?php if ($isAdmin): ?>
                    <div class="card card-info h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="summaryTop mb-2">
                                <div class="summaryIcon bg-blue">
                                    <i class="bi bi-bar-chart-line-fill"></i>
                                </div>
                                <h5 class="text-primary mb-0">Attendance Overview</h5>
                            </div>
                            <div class="attendanceChartWrap flex-fill">
                                <canvas id="attendanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="card card-pink h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="summaryTop mb-2">
                                <div class="summaryIcon bg-pink">
                                    <i class="bi bi-chat-quote-fill"></i>
                                </div>
                                <p>Quote of the Day</p>
                            </div>
                            <hr class="section-divider my-2">
                            <?php if ($quoteText): ?>
                                <p class="quote-text"><?= htmlspecialchars($quoteText) ?></p>
                                <p class="quote-author">— <?= htmlspecialchars($quoteAuthor) ?></p>
                            <?php else: ?>
                                <p class="quote-text text-muted" style="font-size:11px;">Could not load quote.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div><!-- /.col right -->

        </div><!-- /.row -->
    </div><!-- /.dash-content -->

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
    const dateEl = document.getElementById('current-date');
    const timeEl = document.getElementById('current-time');

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