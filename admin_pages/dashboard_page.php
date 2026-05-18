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

// ── Total employees ───────────────────────────────────────
$count = (int) $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();

// ── Present today ─────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM attendances a
    WHERE a.work_date = ?
      AND a.status    = 'present'
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

// ── Random quote ─────────────────────────────────────────
$quoteText   = '';
$quoteAuthor = '';
try {
    $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
    $html = @file_get_contents('https://quotes.toscrape.com/random', false, $ctx);
    if ($html) {
        preg_match('/<span class="text"[^>]*>(.*?)<\/span>/s', $html, $tm);
        preg_match('/<small class="author"[^>]*>(.*?)<\/small>/s', $html, $am);
        $quoteText   = isset($tm[1]) ? html_entity_decode(strip_tags($tm[1]), ENT_QUOTES) : '';
        $quoteAuthor = isset($am[1]) ? strip_tags($am[1]) : '';
    }
} catch (Exception $e) {}

// ── Weekly attendance overview (Mon–Sun of current week) ──
$todayDow = (int)date('N'); // 1=Mon, 7=Sun
$weekMon  = date('Y-m-d', strtotime('-' . ($todayDow - 1) . ' days'));
$weekSun  = date('Y-m-d', strtotime('+' . (7 - $todayDow) . ' days'));

$weeklyStmt = $pdo->prepare("
    SELECT
        a.work_date,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count
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
    $weeklyAbsent[$idx]  = $count - (int)$row['present_count']; // absent = total - present
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

                    <!-- PRESENT TODAY -->
                    <div class="col-6 col-lg">
                        <div class="summaryCard card-success h-100">
                            <div class="summaryTop">
                                <div class="icon-box icon-box-success">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <p>Present Today</p>
                            </div>
                            <div class="summaryInfo">
                                <h5><?= $present ?></h5>
                                <span><?= $count > 0 ? round($present / $count * 100) : 0 ?>% of total employees</span>
                            </div>
                        </div>
                    </div>

                    <!-- ABSENT TODAY -->
                    <div class="col-6 col-lg">
                        <div class="summaryCard card-danger h-100">
                            <div class="summaryTop">
                                <div class="icon-box icon-box-danger">
                                    <i class="bi bi-clock-fill"></i>
                                </div>
                                <p>Absent Today</p>
                            </div>
                            <div class="summaryInfo">
                                <h5><?= $absent ?></h5>
                                <span><?= $count > 0 ? round($absent / $count * 100) : 0 ?>% of total employees</span>
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

            <!-- Row 3: Attendance Overview full width -->
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
            </div><!-- /.col-12 (chart) -->

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