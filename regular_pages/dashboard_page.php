<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication_pages/login.php");
    exit();
}

require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quote_text'])) {
    header('Content-Type: application/json');
    if (($_SESSION['user_role'] ?? '') !== 'superadmin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    $quoteText   = trim($_POST['quote_text']   ?? '');
    $quoteAuthor = trim($_POST['quote_author'] ?? '');
    if ($quoteText === '') {
        echo json_encode(['success' => false, 'message' => 'Quote text is required.']);
        exit();
    }
    try {
        $stmt = $pdo->prepare("
            INSERT INTO quote_of_the_day (id, quote_text, quote_author)
            VALUES (1, ?, ?)
            ON DUPLICATE KEY UPDATE quote_text = VALUES(quote_text), quote_author = VALUES(quote_author), updated_at = NOW()
        ");
        $stmt->execute([$quoteText, $quoteAuthor]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit();
}
date_default_timezone_set('Asia/Manila');

$today        = date('Y-m-d');
$daysInMonth  = (int)date('t');

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

// ── Absent today (scheduled workday, no clock-in) ─────────
// Only counts employees with an approved non-rest-day schedule today
// who have not clocked in — excludes unscheduled and rest-day employees.
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT s.employee_id)
    FROM schedules s
    WHERE s.schedule_date = ?
      AND s.status = 'approved'
      AND (s.is_rest_day = 0 OR s.is_rest_day IS NULL)
      AND NOT EXISTS (
          SELECT 1 FROM attendances a
          WHERE a.employee_id = s.employee_id
            AND a.work_date = ?
            AND a.actual_time_in IS NOT NULL
      )
");
$stmt->execute([$today, $today]);
$absent = (int) $stmt->fetchColumn();

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

// ── Quote of the Day (from DB) ────────────────────────────
$quoteText   = '';
$quoteAuthor = '';
$qRow = $pdo->query("SELECT quote_text, quote_author FROM quote_of_the_day WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
if ($qRow) {
    $quoteText   = $qRow['quote_text'];
    $quoteAuthor = $qRow['quote_author'];
}

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

$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin';

// ── All users: employee ID ────────────────────────────────
$empId = (int)$_SESSION['user_id'];

// ── Employee-only KPI + leave balances ───────────────────
$empMonthPresent = 0;
$empMonthAbsent  = 0;
$empTotalPending = 0;
$leaveTotal      = 0;
$leaveUsed       = 0;
$leaveData       = [];

if (!$isAdmin) {
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

    $s = $pdo->prepare("SELECT COUNT(*) FROM leave_requests    WHERE employee_id = ? AND status = 'pending'");
    $s->execute([$empId]);
    $empPendingLeave = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM overtime_requests WHERE employee_id = ? AND status = 'pending'");
    $s->execute([$empId]);
    $empPendingOT = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM log_edit_requests  WHERE employee_id = ? AND status = 'pending'");
    $s->execute([$empId]);
    $empPendingLogEdit = (int)$s->fetchColumn();

    $empTotalPending = $empPendingLeave + $empPendingOT + $empPendingLogEdit;

    $leaveTypeConfig = [
        ['key' => 'buffer_leave',      'label' => 'Buffer',      'icon' => 'bi-shield-fill',      'color' => 'var(--primary-color)',  'card' => 'card-success', 'req_key' => null],
        ['key' => 'vacation_leave',    'label' => 'Vacation',    'icon' => 'bi-umbrella-fill',    'color' => 'var(--info-color)',     'card' => 'card-info',    'req_key' => 'vacation leave'],
        ['key' => 'sick_leave',        'label' => 'Sick',        'icon' => 'bi-heart-pulse-fill', 'color' => 'var(--danger-color)',   'card' => 'card-danger',  'req_key' => 'sick leave'],
        ['key' => 'paternity_leave',   'label' => 'Paternity',   'icon' => 'bi-person-fill',      'color' => '#7dd9a8',               'card' => 'card-mint',    'req_key' => null],
        ['key' => 'maternity_leave',   'label' => 'Maternity',   'icon' => 'bi-person-hearts',    'color' => '#fd7e14',               'card' => 'card-coral',   'req_key' => null],
        ['key' => 'solo_parent_leave', 'label' => 'Solo Parent', 'icon' => 'bi-people-fill',      'color' => '#a07de0',               'card' => 'card-purple',  'req_key' => 'solo parent leave'],
        ['key' => 'birthday_leave',    'label' => 'Birthday',    'icon' => 'bi-gift-fill',        'color' => 'var(--warning-color)',  'card' => 'card-warning', 'req_key' => 'birthday leave'],
    ];

    $s = $pdo->prepare("SELECT * FROM employee_leave_balances WHERE employee_id = ?");
    $s->execute([$empId]);
    $leaveBal = $s->fetch(PDO::FETCH_ASSOC) ?: [];

    $s = $pdo->prepare("
        SELECT leave_type, selected_dates, start_date, end_date
        FROM leave_requests
        WHERE employee_id = ? AND status = 'approved' AND leave_type != 'ob leave'
          AND YEAR(start_date) = YEAR(CURDATE())
    ");
    $s->execute([$empId]);
    $usedPerType = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rKey  = str_replace(' ', '_', $r['leave_type']);
        $dates = json_decode($r['selected_dates'], true);
        if (is_array($dates) && !empty($dates)) {
            $usedPerType[$rKey] = ($usedPerType[$rKey] ?? 0) + count($dates);
        } else {
            $d1 = new DateTime($r['start_date']);
            $d2 = new DateTime($r['end_date']);
            $usedPerType[$rKey] = ($usedPerType[$rKey] ?? 0) + $d1->diff($d2)->days + 1;
        }
    }

    foreach ($leaveTypeConfig as $lt) {
        $total       = (int)($leaveBal[$lt['key']] ?? 0);
        $used        = (int)($usedPerType[$lt['key']] ?? 0);
        $leaveData[] = array_merge($lt, ['total' => $total, 'used' => $used]);
        $leaveTotal += $total;
        $leaveUsed  += $used;
    }
}

// ── My Week — ALL roles ───────────────────────────────────
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

$s = $pdo->prepare("
    SELECT work_date, status, late_minutes FROM attendances
    WHERE employee_id = ? AND work_date BETWEEN ? AND ?
");
$s->execute([$empId, $weekMon, $weekSun]);
$empAttMap  = [];
$empLateMap = [];
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $empAttMap[$r['work_date']]  = $r['status'];
    $empLateMap[$r['work_date']] = (int)$r['late_minutes'];
}

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
    $empOBSet = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $empOBSet[$r['start_date']] = true;
    }

$empWeekDays = [];
for ($i = 0; $i < 7; $i++) {
    $date  = date('Y-m-d', strtotime($weekMon . " +$i days"));
    $sched = $empSchedMap[$date] ?? null;

        if (!$sched) {
            $status = 'none';
        } elseif ($sched['is_rest_day']) {
            $status = 'rest';
        } elseif (isset($empOBSet[$date])) {
            $status = 'ob';
        } elseif (isset($empLeaveSet[$date])) {
            $status = 'leave';
        } elseif (in_array(strtolower($empAttMap[$date] ?? ''), ['present', 'undertime', 'overtime', 'incomplete'])) {
            $status = ($empLateMap[$date] ?? 0) > 0 ? 'late' : 'present';
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

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <link rel="stylesheet" href="dashboard_page.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="../regular_pages/logs_widget.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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

                <?php if ($isAdmin): ?>
                <!-- Quote of the Day (admin) -->
                <div class="flex-shrink-0 mb-2">
                    <div class="card card-pink d-flex flex-column">
                        <div class="card-body d-flex flex-column py-2" style="min-height:0;">
                            <div class="summaryTop mb-2">
                                <div class="summaryIcon bg-pink">
                                    <i class="bi bi-chat-quote-fill"></i>
                                </div>
                                <p>Quote of the Day</p>
                                <button class="btn btn-sm btn-outline-light ms-auto" data-bs-toggle="modal" data-bs-target="#editQuoteModal">
                                    <i class="bi bi-pencil-fill me-1"></i>Edit
                                </button>
                            </div>
                            <hr class="section-divider my-2">
                            <?php if ($quoteText): ?>
                                <p class="text-tertiary"><?= htmlspecialchars($quoteText) ?></p>
                                <?php if ($quoteAuthor): ?>
                                    <p class="text-meta">— <?= htmlspecialchars($quoteAuthor) ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted" style="font-size:11px;">No quote set yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Quote of the Day (employee) -->
                <div class="flex-shrink-0 mb-2">
                    <div class="card card-pink d-flex flex-column">
                        <div class="card-body d-flex flex-column py-2" style="min-height:0;">
                            <div class="summaryTop mb-2">
                                <div class="summaryIcon bg-pink">
                                    <i class="bi bi-chat-quote-fill"></i>
                                </div>
                                <p>Quote of the Day</p>
                            </div>
                            <hr class="section-divider my-2">
                            <?php if ($quoteText): ?>
                                <p class="text-tertiary"><?= htmlspecialchars($quoteText) ?></p>
                                <?php if ($quoteAuthor): ?>
                                    <p class="text-meta">— <?= htmlspecialchars($quoteAuthor) ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted" style="font-size:11px;">No quote set yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                    <!-- Summary Cards (admin) -->
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <div class="card card-success p-3">
                                <div class="card-body d-flex flex-column gap-2 p-0">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-box icon-box-success">
                                            <i class="bi bi-check-circle-fill fs-3"></i>
                                        </div>
                                        <div class="d-flex flex-column ms-auto text-end">
                                            <div class="hstack gap-1 justify-content-end align-items-baseline">
                                                <div class="stats-number" style="color:var(--status-success-color)"><?= $present ?></div>
                                                <span class="text-meta">/ <?= $count ?></span>
                                            </div>
                                            <div class="text-meta">Present Today</div>
                                            <small class="text-meta-secondary"><?= $count > 0 ? round($present / $count * 100) : 0 ?>% of employees</small>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 4px;">
                                        <div class="progress-bar"
                                            style="width: <?= $count > 0 ? ($present / $count * 100) : 0 ?>%; background-color:var(--status-success-color)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card card-danger p-3">
                                <div class="card-body d-flex flex-column gap-2 p-0">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-box icon-box-danger">
                                            <i class="bi bi-clock-fill fs-3"></i>
                                        </div>
                                        <div class="d-flex flex-column ms-auto text-end">
                                            <div class="hstack gap-1 justify-content-end align-items-baseline">
                                                <div class="stats-number" style="color:var(--danger-color)"><?= $absent ?></div>
                                                <span class="text-meta">/ <?= $count ?></span>
                                            </div>
                                            <div class="text-meta">Absent Today</div>
                                            <small class="text-meta-secondary"><?= $count > 0 ? round($absent / $count * 100) : 0 ?>% of employees</small>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 4px;">
                                        <div class="progress-bar"
                                            style="width: <?= $count > 0 ? ($absent / $count * 100) : 0 ?>%; background-color:var(--danger-color)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card card-warning p-3">
                                <div class="card-body d-flex flex-column gap-2 p-0">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-box icon-box-warning">
                                            <i class="bi bi-bell-fill fs-3"></i>
                                        </div>
                                        <div class="d-flex flex-column ms-auto text-end">
                                            <div class="stats-number" style="color:var(--warning-color)"><?= $totalPending ?></div>
                                            <div class="text-meta">Pending <?= $totalPending == 1 ? 'Request' : 'Requests' ?></div>
                                            <small class="text-meta-secondary">Awaiting Approval</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>

                    <!-- Summary Cards (employee) -->
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <div class="card card-success p-3">
                                <div class="card-body d-flex flex-column gap-2 p-0">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-box icon-box-success">
                                            <i class="bi bi-check-circle-fill fs-3"></i>
                                        </div>
                                        <div class="d-flex flex-column ms-auto text-end">
                                            <div class="hstack gap-1 justify-content-end align-items-baseline">
                                                <div class="stats-number" style="color:var(--status-success-color)"><?= $empMonthPresent ?></div>
                                                <span class="text-meta">/ <?= $daysInMonth ?></span>
                                            </div>
                                            <div class="text-meta">Present <?= $empMonthPresent == 1 ? 'Day' : 'Days' ?></div>
                                            <small class="text-meta-secondary">This <?= date('F') ?></small>
                                        </div>
                                    </div>
                                    <div class="progress" style="height:4px;">
                                        <div class="progress-bar"
                                            style="width:<?= $daysInMonth > 0 ? ($empMonthPresent / $daysInMonth * 100) : 0 ?>%; background-color:var(--status-success-color)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card card-danger p-3">
                                <div class="card-body d-flex flex-column gap-2 p-0">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-box icon-box-danger">
                                            <i class="bi bi-clock-fill fs-3"></i>
                                        </div>
                                        <div class="d-flex flex-column ms-auto text-end">
                                            <div class="hstack gap-1 justify-content-end align-items-baseline">
                                                <div class="stats-number" style="color:var(--danger-color)"><?= $empMonthAbsent ?></div>
                                                <span class="text-meta">/ <?= $daysInMonth ?></span>
                                            </div>
                                            <div class="text-meta">Absent <?= $empMonthAbsent == 1 ? 'Day' : 'Days' ?></div>
                                            <small class="text-meta-secondary">This <?= date('F') ?></small>
                                        </div>
                                    </div>
                                    <div class="progress" style="height:4px;">
                                        <div class="progress-bar"
                                            style="width:<?= $daysInMonth > 0 ? ($empMonthAbsent / $daysInMonth * 100) : 0 ?>%; background-color:var(--danger-color)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card card-warning p-3">
                                <div class="card-body d-flex flex-column gap-2 p-0">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-box icon-box-warning">
                                            <i class="bi bi-bell-fill fs-3"></i>
                                        </div>
                                        <div class="d-flex flex-column ms-auto text-end">
                                            <div class="stats-number" style="color:var(--warning-color)"><?= $empTotalPending ?></div>
                                            <div class="text-meta">Pending <?= $empTotalPending == 1 ? 'Request' : 'Requests' ?></div>
                                            <small class="text-meta-secondary">Awaiting Approval</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Leave Balance Strip -->
                    <div class="lb-strip mb-2">
                        <?php foreach ($leaveData as $lt):
                            $remaining = max(0, $lt['total'] - $lt['used']);
                            $pct = $lt['total'] > 0 ? min(100, round($remaining / $lt['total'] * 100)) : 0;
                        ?>
                        <div class="card <?= $lt['card'] ?> lb-strip-card p-3">
                            <div class="card-body d-flex flex-column gap-1 p-0">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi <?= $lt['icon'] ?>" style="color:<?= $lt['color'] ?>; font-size:0.85rem; flex-shrink:0;"></i>
                                    <span class="lb-label"><?= $lt['label'] ?></span>
                                </div>
                                <div class="lb-nums">
                                    <span class="lb-remaining" style="color:<?= $lt['color'] ?>"><?= $remaining ?></span>
                                    <span class="lb-total">/ <?= $lt['total'] ?></span>
                                </div>
                                <div class="progress lb-progress">
                                    <div class="progress-bar" style="width:<?= $pct ?>%; background-color:<?= $lt['color'] ?>;"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

                <!-- Fills remaining space -->
                <div class="dash-grow d-flex flex-column gap-2">
                <?php if ($isAdmin): ?>
                <div class="row g-2" style="flex:1 1 0;min-height:0;">

                    <!-- Birthday Summary -->
                    <div class="col-6 d-flex flex-column">
                        <div class="card card-purple h-100">
                            <div class="card-body d-flex flex-column overflow-hidden">
                                <div class="hstack gap-2 align-items-center">
                                    <div class="icon-box icon-box-sm icon-box-purple">
                                        <i class="bi bi-cake"></i>
                                    </div>
                                    <h5 class="text-primary mb-0">Birthdays This Month</h5>
                                    <h5 class="birthdays-summary-count mb-0 ms-auto">
                                        <?= count($birthdaysThisMonth ?? []) ?>
                                    </h5>
                                </div>

                                <div class="list-scroll">
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

                                <div class="d-flex">
                                    <a class="btn btn-sm btn-purple ms-auto" href="../regular_pages/employee_schedule.php?filter=birthday">
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
                                <div class="hstack gap-2 align-items-center">
                                    <div class="icon-box icon-box-sm icon-box-success">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <h5 class="text-primary mb-0">Upcoming Events</h5>
                                    <h5 class="events-summary-count mb-0 ms-auto">
                                        <?= $upcomingEventsCount ?>
                                    </h5>
                                </div>

                                <div class="list-scroll">
                                    <?php if (empty($upcomingEvents)): ?>
                                        <small class="text-muted">No events</small>
                                    <?php else: ?>
                                        <ul class="list-unstyled mb-0 dash-list">
                                            <?php foreach ($upcomingEvents as $h): ?>
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

                                <div class="d-flex">
                                    <a class="btn btn-sm btn-success ms-auto" href="../regular_pages/employee_schedule.php?filter=events">
                                        View All Events <i class="bi bi-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /.row -->
                <?php else: ?>
                <!-- My Week (employee) -->
                <div class="card card-info flex-shrink-0">
                    <div class="card-body py-2">
                        <div class="summaryTop mb-4">
                            <div class="summaryIcon bg-blue">
                                <i class="bi bi-calendar-week-fill"></i>
                            </div>
                            <h5 class="text-primary mb-0">My Week</h5>
                            <span class="wa-week-range ms-auto">
                                <?= date('M d', strtotime($weekMon)) ?> – <?= date('M d', strtotime($weekSun)) ?>
                            </span>
                        </div>
                        <div class="wa-grid mb-1">
                            <?php
                            $dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                            foreach ($empWeekDays as $i => $day):
                                $isToday = ($day['date'] === $today);
                                $glassClass = match($day['status']) {
                                    'present' => ' wa-icon-glass',
                                    'late'    => ' wa-icon-glass-warning',
                                    'absent'  => ' wa-icon-glass-danger',
                                    'leave'   => ' wa-icon-glass-warning',
                                    'ob'      => ' wa-icon-glass-purple',
                                    'rest'    => ' wa-icon-glass-neutral',
                                    default   => '',
                                };
                                $tooltipTitle = match($day['status']) {
                                    'present'  => 'Present',
                                    'late'     => 'Late',
                                    'absent'   => 'Absent',
                                    'rest'     => 'Rest Day',
                                    'leave'    => 'On Leave',
                                    'ob'       => 'On OB',
                                    'upcoming' => 'Upcoming',
                                    default    => 'No Schedule',
                                };
                            ?>
                            <div class="wa-day<?= $isToday ? ' wa-today' : '' ?><?= $day['status'] === 'late' ? ' wa-late' : '' ?>">
                                <span class="wa-label"><?= $dayLabels[$i] ?></span>
                                <span class="wa-icon-wrap<?= $glassClass ?>" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= $tooltipTitle ?>">
                                    <?php if ($day['status'] === 'present'): ?>
                                        <i class="bi bi-check-lg" style="color:var(--status-success-color)"></i>
                                    <?php elseif ($day['status'] === 'late'): ?>
                                        <i class="bi bi-check-lg" style="color:var(--status-warning-color)"></i>
                                    <?php elseif ($day['status'] === 'absent'): ?>
                                        <i class="bi bi-x-lg" style="color:var(--danger-color)"></i>
                                    <?php elseif ($day['status'] === 'rest'): ?>
                                        <i class="bi bi-moon" style="color:var(--text-muted)"></i>
                                    <?php elseif ($day['status'] === 'ob'): ?>
                                        <i class="bi bi-dash-lg" style="color:var(--superadmin)"></i>
                                    <?php elseif ($day['status'] === 'leave'): ?>
                                        <i class="bi bi-dash-lg" style="color:var(--status-warning-color)"></i>
                                    <?php elseif ($day['status'] === 'upcoming'): ?>
                                        <i class="bi bi-circle" style="color:rgba(255,255,255,0.15)"></i>
                                    <?php else: ?>
                                        <i class="bi bi-calendar-x" style="color:rgba(255,255,255,0.35)"></i>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                </div><!-- /.dash-grow -->

            </div><!-- /.col left -->

            <!-- Right Column -->
            <div class="col-6 dash-col">

                <?php if ($isAdmin): ?>
                <!-- My Week (admin) -->
                <div class="card card-info mb-2 flex-shrink-0">
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
                                $glassClass = match($day['status']) {
                                    'present' => ' wa-icon-glass',
                                    'late'    => ' wa-icon-glass-warning',
                                    'absent'  => ' wa-icon-glass-danger',
                                    'leave'   => ' wa-icon-glass-warning',
                                    'ob'      => ' wa-icon-glass-purple',
                                    'rest'    => ' wa-icon-glass-neutral',
                                    default   => '',
                                };
                                $tooltipTitle = match($day['status']) {
                                    'present'  => 'Present',
                                    'late'     => 'Late',
                                    'absent'   => 'Absent',
                                    'rest'     => 'Rest Day',
                                    'leave'    => 'On Leave',
                                    'ob'       => 'On OB',
                                    'upcoming' => 'Upcoming',
                                    default    => 'No Schedule',
                                };
                            ?>
                            <div class="wa-day<?= $isToday ? ' wa-today' : '' ?><?= $day['status'] === 'late' ? ' wa-late' : '' ?>">
                                <span class="wa-label"><?= $dayLabels[$i] ?></span>
                                <span class="wa-icon-wrap<?= $glassClass ?>" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= $tooltipTitle ?>">
                                    <?php if ($day['status'] === 'present'): ?>
                                        <i class="bi bi-check-lg" style="color:var(--status-success-color)"></i>
                                    <?php elseif ($day['status'] === 'late'): ?>
                                        <i class="bi bi-check-lg" style="color:var(--status-warning-color)"></i>
                                    <?php elseif ($day['status'] === 'absent'): ?>
                                        <i class="bi bi-x-lg" style="color:var(--danger-color)"></i>
                                    <?php elseif ($day['status'] === 'rest'): ?>
                                        <i class="bi bi-moon" style="color:var(--text-muted)"></i>
                                    <?php elseif ($day['status'] === 'ob'): ?>
                                        <i class="bi bi-dash-lg" style="color:var(--superadmin)"></i>
                                    <?php elseif ($day['status'] === 'leave'): ?>
                                        <i class="bi bi-dash-lg" style="color:var(--status-warning-color)"></i>
                                    <?php elseif ($day['status'] === 'upcoming'): ?>
                                        <i class="bi bi-circle" style="color:rgba(255,255,255,0.15)"></i>
                                    <?php else: ?>
                                        <i class="bi bi-calendar-x" style="color:rgba(255,255,255,0.35)"></i>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Birthdays + Events (employee) -->
                <div class="row g-2 mb-2" style="flex:1 1 0;min-height:0;">
                    <div class="col-6 d-flex flex-column">
                        <div class="card card-purple h-100">
                            <div class="card-body d-flex flex-column overflow-hidden">
                                <div class="hstack gap-2 align-items-center">
                                    <div class="icon-box icon-box-sm icon-box-purple">
                                        <i class="bi bi-cake"></i>
                                    </div>
                                    <h5 class="text-primary mb-0">Birthdays This Month</h5>
                                    <h5 class="birthdays-summary-count mb-0 ms-auto">
                                        <?= count($birthdaysThisMonth ?? []) ?>
                                    </h5>
                                </div>
                                <div class="list-scroll">
                                    <?php if (empty($birthdaysThisMonth)): ?>
                                        <small class="text-muted">No birthdays this month</small>
                                    <?php else: ?>
                                        <ul class="list-unstyled mb-0 dash-list">
                                            <?php foreach ($birthdaysThisMonth as $b):
                                                $avatarSrc    = !empty($b['profile_image'])
                                                    ? '../assets/user_profiles/' . htmlspecialchars($b['profile_image'])
                                                    : '../assets/user_profiles/default_avatar.png';
                                                $bdayThisYear = date('Y') . '-' . date('m-d', strtotime($b['birthdate']));
                                                $diff         = (int) (strtotime($bdayThisYear) - strtotime($today)) / 86400;
                                                if ($diff === 0)      { $daysLabel = 'Today!'; $daysClass = 'bday-days status-approved'; }
                                                elseif ($diff > 0)    { $daysLabel = 'In ' . $diff . ' ' . ($diff === 1 ? 'day' : 'days'); $daysClass = 'bday-days status-info'; }
                                                else                  { $daysLabel = abs($diff) . ' ' . (abs($diff) === 1 ? 'day' : 'days') . ' ago'; $daysClass = 'bday-days'; }
                                            ?>
                                            <li>
                                                <div class="hstack gap-2 align-items-center">
                                                    <img src="<?= $avatarSrc ?>" class="bday-avatar" alt="">
                                                    <div class="vstack">
                                                        <strong class="text-tertiary"><?= htmlspecialchars($b['full_name']) ?></strong>
                                                        <span class="text-meta"><?= date('l', strtotime($b['birthdate'])) ?>, <?= date('F d', strtotime($b['birthdate'])) ?></span>
                                                    </div>
                                                    <span class="pill <?= $daysClass ?> ms-auto"><?= $daysLabel ?></span>
                                                </div>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex">
                                    <a class="btn btn-sm btn-success ms-auto" href="../regular_pages/employee_schedule.php?filter=birthday">
                                        View All Birthdays <i class="bi bi-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 d-flex flex-column">
                        <div class="card card-success h-100">
                            <div class="card-body d-flex flex-column overflow-hidden">
                                <div class="hstack gap-2 align-items-center">
                                    <div class="icon-box icon-box-sm icon-box-success">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <h5 class="text-primary mb-0">Upcoming Events</h5>
                                    <h5 class="events-summary-count mb-0 ms-auto">
                                        <?= $upcomingEventsCount ?>
                                    </h5>
                                </div>
                                <div class="list-scroll">
                                    <?php if (empty($upcomingEvents)): ?>
                                        <small class="text-muted">No events</small>
                                    <?php else: ?>
                                        <ul class="list-unstyled mb-0 dash-list">
                                            <?php foreach ($upcomingEvents as $h):
                                                $diff = (int) ((strtotime(date('Y-m-d', strtotime($h['start_datetime']))) - strtotime($today)) / 86400);
                                                if ($diff === 0)      { $evtLabel = 'Today!'; $evtClass = 'bday-days status-approved'; }
                                                elseif ($diff > 0)    { $evtLabel = 'In ' . $diff . ' ' . ($diff === 1 ? 'day' : 'days'); $evtClass = 'bday-days status-info'; }
                                                else                  { $evtLabel = abs($diff) . ' ' . (abs($diff) === 1 ? 'day' : 'days') . ' ago'; $evtClass = 'bday-days'; }
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
                                <div class="d-flex">
                                    <a class="btn btn-sm btn-success ms-auto" href="../regular_pages/employee_schedule.php?filter=events">
                                        View All Events <i class="bi bi-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Activity Logs Card -->
                <div class="card card-info mb-2 d-flex flex-column" style="flex:1.2 1 0;min-height:0;overflow:hidden;">
                    <div class="card-body d-flex flex-column overflow-hidden" style="flex:1 1 0;min-height:0;padding-right:0;padding-bottom:0;">
                        <div class="hstack gap-2 align-items-center mb-2 pe-3">
                            <div class="icon-box icon-box-sm icon-box-info">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <h5 class="text-primary mb-0">Activity Logs</h5>
                            <div class="ms-auto hstack gap-1">
                                <button class="btn btn-sm dropdown-toggle" id="datePickerBtn" type="button">
                                    <i class="bi bi-calendar3"></i>
                                    <span id="dateRangeLabel">Today</span>
                                </button>
                                <div class="dropdown">
                                    <button class="btn btn-sm dropdown-toggle" type="button" id="logTypeToggle"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-funnel"></i>
                                        <span id="logTypeLabel">All Types</span>
                                    </button>
                                    <ul class="dropdown-menu" id="logTypeMenu" style="max-height:340px;overflow-y:auto!important;overflow-x:hidden!important;">
                                        <li><a class="dropdown-item" href="#" data-value="ALL">All Types</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="#" data-value="IN">Time In</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="OUT">Time Out</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="BREAK_IN">Break In</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="BREAK_OUT">Break Out</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="#" data-value="REQUEST_OT">Request OT</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="REQUEST_LEAVE">Request Leave</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="REQUEST_OB">Request OB</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="REQUEST_LOG_EDIT">Request Log Edit</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="REQUEST_CHANGE_SCHEDULE">Request Change Schedule</a></li>
                                    </ul>
                                </div>
                                <input type="hidden" id="logTypeFilter" value="ALL">
                                <input type="hidden" id="startDate" value="<?= $today ?>">
                                <input type="hidden" id="endDate" value="<?= $today ?>">
                            </div>
                        </div>
                        <?php
                            $startDate = $today;
                            $endDate   = $today;
                            $logsApiPath = '../get_logs.php';
                            $logsInlineHeader = true;
                            include '../regular_pages/logs_widget.php';
                        ?>
                    </div>
                </div>

                <!-- Attendance Overview (admin) -->
                <?php if ($isAdmin): ?>
                <div class="card card-info d-flex flex-column" style="flex:0.8 1 0;min-height:0;">
                    <div class="card-body d-flex flex-column py-2" style="min-height:0;">
                        <div class="summaryTop mb-2">
                            <div class="summaryIcon icon-box-sm bg-blue">
                                <i class="bi bi-bar-chart-line-fill" style="font-size:0.8rem;"></i>
                            </div>
                            <h6 class="text-primary mb-0">Attendance Overview</h6>
                        </div>
                        <div class="attendanceChartWrap" style="flex:1 1 0;min-height:0;">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /.col right -->

        </div><!-- /.row -->
    </div><!-- /.dash-content -->

</div><!-- /#main-wrapper -->


<?php if ($isAdmin): ?>
<!-- Edit Quote Modal -->
<div class="modal fade" id="editQuoteModal" tabindex="-1" aria-labelledby="editQuoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editQuoteModalLabel"><i class="bi bi-chat-quote me-2"></i>Quote of the Day</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Quote</label>
                    <textarea class="form-control" id="quoteTextInput" rows="4" placeholder="Enter a quote..."><?= htmlspecialchars($quoteText) ?></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Author</label>
                    <input type="text" class="form-control" id="quoteAuthorInput" value="<?= htmlspecialchars($quoteAuthor) ?>" placeholder="Author name (optional)">
                </div>
                <div id="quoteSaveMsg" class="d-none mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="saveQuoteBtn">
                    <i class="bi bi-floppy me-1"></i>Save Quote
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('saveQuoteBtn').addEventListener('click', function () {
    const quoteText   = document.getElementById('quoteTextInput').value.trim();
    const quoteAuthor = document.getElementById('quoteAuthorInput').value.trim();
    const msgEl       = document.getElementById('quoteSaveMsg');
    const btn         = this;

    if (!quoteText) {
        msgEl.className = 'alert alert-danger mt-2';
        msgEl.textContent = 'Quote text is required.';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    const form = new FormData();
    form.append('quote_text', quoteText);
    form.append('quote_author', quoteAuthor);

    fetch('dashboard_page.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                msgEl.className = 'alert alert-success mt-2';
                msgEl.textContent = 'Quote saved! Employees will see it on their dashboard.';
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('editQuoteModal')).hide();
                    msgEl.className = 'd-none';
                }, 1500);
            } else {
                msgEl.className = 'alert alert-danger mt-2';
                msgEl.textContent = data.message || 'Failed to save quote.';
            }
        })
        .catch(() => {
            msgEl.className = 'alert alert-danger mt-2';
            msgEl.textContent = 'Network error. Please try again.';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Save Quote';
        });
});
</script>

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
                        font: { size: 11, family: 'Poppins' }
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
                        font: { size: 10, family: 'Poppins' }
                    },
                    grid:   { color: 'rgba(255,255,255,0.07)' },
                    border: { color: 'transparent' }
                },
                x: {
                    ticks: {
                        color: 'rgba(255,255,255,0.5)',
                        font: { size: 10, family: 'Poppins' }
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el, { container: 'body', trigger: 'hover' });
    });
});
</script>

<script>
document.querySelectorAll('.lb-strip').forEach(function (el) {
    el.addEventListener('wheel', function (e) {
        if (e.deltaY !== 0) {
            e.preventDefault();
            const card = el.querySelector('.lb-strip-card');
            const gap  = parseFloat(getComputedStyle(el).gap) || 8;
            const step = card ? card.offsetWidth + gap : 160;
            el.scrollBy({ left: e.deltaY > 0 ? step : -step, behavior: 'smooth' });
        }
    }, { passive: false });
});
</script>
</body>
</html>