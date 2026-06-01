<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin';

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

// ── Handle POST (create / update / delete events) ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit(); }

    $data   = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'create';

    if ($action === 'delete') {
        $id = intval($data['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id.']); exit(); }
        $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit();
    }

    $title       = trim($data['title']       ?? '');
    $eventType   = $data['event_type']       ?? 'other';
    $startDate   = $data['start_date']       ?? '';
    $description = trim($data['description'] ?? '');

    if (!$title || !$startDate) {
        http_response_code(400);
        echo json_encode(['error' => 'Title and date are required.']);
        exit();
    }

    $validTypes = ['holiday', 'party', 'meeting', 'announcement', 'other'];
    if (!in_array($eventType, $validTypes)) $eventType = 'other';

    $colors = [
        'holiday' => '#ef4444', 'party' => '#ec4899',
        'meeting' => '#3b82f6', 'announcement' => '#f59e0b', 'other' => '#6b7280',
    ];

    if ($action === 'update') {
        $id = intval($data['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id.']); exit(); }
        $pdo->prepare("UPDATE events SET title=?, description=?, event_type=?, start_datetime=?, end_datetime=NULL, color=? WHERE id=?")
            ->execute([$title, $description ?: null, $eventType, $startDate, $colors[$eventType], $id]);
        echo json_encode(['success' => true]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO events (title, description, event_type, start_datetime, end_datetime, color, created_by) VALUES (?, ?, ?, ?, NULL, ?, ?)");
        $stmt->execute([$title, $description ?: null, $eventType, $startDate, $colors[$eventType], $_SESSION['user_id']]);
        echo json_encode(['success' => true, 'id' => (int) $pdo->lastInsertId()]);
    }
    exit();
}

// ── Pre-fetch calendar events ─────────────────────────────
$schedCalEvents = [];
foreach ($pdo->query("SELECT id, title, description, event_type, start_datetime, end_datetime, color FROM events ORDER BY start_datetime ASC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $schedCalEvents[] = [
        'id'            => $r['id'],
        'title'         => $r['title'],
        'start'         => $r['start_datetime'],
        'end'           => $r['end_datetime'] ?: null,
        'allDay'        => true,
        'color'         => $r['color'] ?: '#0d6efd',
        'textColor'     => '#fff',
        'extendedProps' => ['shift_type' => 'cal_event', 'event_type' => $r['event_type'], 'description' => $r['description']],
    ];
}

// ── Birthdays ─────────────────────────────────────────────
$schedBirthdayEvents = [];
$curYear = (int) date('Y');

$userRole = $_SESSION['user_role'] ?? '';
if ($isAdmin || $userRole === 'admin') {
    $bdayRows = $pdo->query("SELECT CONCAT(first_name,' ',last_name) AS full_name, birthdate FROM employees WHERE birthdate IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $deptStmt = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
    $deptStmt->execute([$_SESSION['user_id']]);
    $userDeptId = $deptStmt->fetchColumn();

    if ($userDeptId) {
        $bdayStmt = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) AS full_name, birthdate FROM employees WHERE birthdate IS NOT NULL AND department_id = ?");
        $bdayStmt->execute([$userDeptId]);
        $bdayRows = $bdayStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $bdayRows = [];
    }
}

foreach ($bdayRows as $r) {
    $md = date('m-d', strtotime($r['birthdate']));
    foreach (range($curYear - 1, $curYear + 2) as $yr) {
        $schedBirthdayEvents[] = ['title' => $r['full_name'], 'start' => "$yr-$md", 'allDay' => true, 'color' => '#8b5cf6', 'textColor' => '#fff', 'extendedProps' => ['shift_type' => 'birthday']];
    }
}

// ── Summary card stats ────────────────────────────────────
$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');
$weekEnd    = date('Y-m-d', strtotime('+6 days'));

$statEventsMonth  = count(array_filter($schedCalEvents, fn($e) => substr($e['start'], 0, 10) >= $monthStart && substr($e['start'], 0, 10) <= $monthEnd));
$statUpcomingWeek = count(array_filter($schedCalEvents, fn($e) => substr($e['start'], 0, 10) >= $today && substr($e['start'], 0, 10) <= $weekEnd));
$statBirthdays    = count(array_filter($bdayRows, fn($r) => date('m', strtotime($r['birthdate'])) === date('m')));

$currentPage        = 'schedule';
$schedEventApiPath  = 'schedules_page.php';
$schedCurrentMonth  = date('Y-m');
$schedInitialDate   = date('Y-m-01');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Schedule</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <link rel="stylesheet" href="schedules_page.css">
    <link rel="stylesheet" href="schedules_widget.css">
</head>
<body>
    <?php include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">
        <?php include '../topbar_revised.php'; ?>

        <!-- SUMMARY CARDS -->
        <div class="container-fluid flex-shrink-0 px-3">
            <div class="d-flex flex-nowrap gap-2 overflow-auto pb-2">

                <!-- Day Shifts -->
                <div class="flex-shrink-0">
                    <div class="card card-success p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-success">
                                <i class="bi bi-sun-fill fs-4"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number" id="sched-stat-day">—</div>
                                <div class="text-meta">Day Shifts</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Night Shifts -->
                <div class="flex-shrink-0">
                    <div class="card card-info p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-info">
                                <i class="bi bi-moon-stars-fill fs-4"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number" id="sched-stat-night">—</div>
                                <div class="text-meta">Night Shifts</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rest Days -->
                <div class="flex-shrink-0">
                    <div class="card card-warning p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-warning">
                                <i class="bi bi-cup-hot-fill fs-4"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number" id="sched-stat-rest">—</div>
                                <div class="text-meta">Rest Days</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- On Leave -->
                <div class="flex-shrink-0">
                    <div class="card card-danger p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-coral">
                                <i class="bi bi-person-check-fill fs-4"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number" id="sched-stat-leave">—</div>
                                <div class="text-meta">On Leave</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- On OB -->
                <div class="flex-shrink-0">
                    <div class="card card-purple p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-purple">
                                <i class="bi bi-briefcase-fill fs-4"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number" id="sched-stat-ob">—</div>
                                <div class="text-meta">On OB</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Events This Month -->
                <div class="flex-shrink-0">
                    <div class="card card-neutral p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-neutral">
                                <i class="bi bi-calendar-event-fill fs-4"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number"><?= $statEventsMonth ?></div>
                                <div class="text-meta">Events This Month</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="flex-shrink-0">
                    <div class="card card-warning p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-warning">
                                <i class="bi bi-hourglass-split fs-4"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number"><?= $statUpcomingWeek ?></div>
                                <div class="text-meta">Upcoming This Week</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Birthdays This Month -->
                <div class="flex-shrink-0">
                    <div class="card card-purple p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-purple">
                                <i class="bi bi-cake2-fill fs-4"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number"><?= $statBirthdays ?></div>
                                <div class="text-meta">Birthdays This Month</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="card card-neutral schedules-card">
            <div class="card-body d-flex flex-column schedules-card-body">
                <?php include 'schedules_widget.php'; ?>
            </div>
        </div>

    </div><!-- #main-wrapper -->

<script>
    const cardStrip = document.querySelector('.d-flex.overflow-auto');
    cardStrip.addEventListener('wheel', (e) => {
        if (e.deltaY === 0) return;
        e.preventDefault();
        cardStrip.scrollBy({ left: e.deltaY * 2, behavior: 'smooth' });
    }, { passive: false });
</script>

</body>
</html>
