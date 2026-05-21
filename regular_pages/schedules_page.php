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

// ── Birthdays (admin only) ────────────────────────────────
$schedBirthdayEvents = [];
if ($isAdmin) {
    $curYear = (int) date('Y');
    foreach ($pdo->query("SELECT CONCAT(first_name,' ',last_name) AS full_name, birthdate FROM employees WHERE birthdate IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $md = date('m-d', strtotime($r['birthdate']));
        foreach (range($curYear - 1, $curYear + 2) as $yr) {
            $schedBirthdayEvents[] = ['title' => $r['full_name'], 'start' => "$yr-$md", 'allDay' => true, 'color' => '#8b5cf6', 'textColor' => '#fff', 'extendedProps' => ['shift_type' => 'birthday']];
        }
    }
}

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

        <div class="card card-neutral schedules-card">
            <div class="card-body d-flex flex-column schedules-card-body">
                <?php include 'schedules_widget.php'; ?>
            </div>
        </div>

    </div><!-- #main-wrapper -->

</body>
</html>
