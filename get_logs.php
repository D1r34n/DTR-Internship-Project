<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

header('Content-Type: application/json');

$userRole      = $_SESSION['user_role'] ?? 'employee';
$employeeId    = $_SESSION['user_id']   ?? null;
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

// Admin viewing a specific employee's profile — scope to that employee, hide employee columns
$scopedToEmployee = $userRole === 'admin' && !empty($_GET['employee_id']);
if ($scopedToEmployee) $employeeId = intval($_GET['employee_id']);
date_default_timezone_set('Asia/Manila');

$startDate = !empty($_GET['start']) ? date('Y-m-d', strtotime($_GET['start'])) : '';
$endDate   = !empty($_GET['end'])   ? date('Y-m-d', strtotime($_GET['end']))   : '';

$type = $_GET['type'] ?? 'ALL';

/* =========================
   SORTING
========================= */
$sort = $_GET['sort'] ?? '';
$dir  = $_GET['dir']  ?? '';

// Each key maps to the actual SQL column to sort by
$allowedSort = [
    'date'     => 'DATE(log_time)',      // sort by date portion only
    'time'     => 'TIME(log_time)',      // sort by time portion only
    'type'     => 'log_type',            // alphabetic on log type
    'location' => 'is_within_office',   // in-office first (1) or out-of-office first (0)
];

$orderClause = "log_time DESC"; // default

if ($sort && isset($allowedSort[$sort])) {
    $column    = $allowedSort[$sort];
    $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
    $orderClause = "$column $direction, log_time DESC";
}

/* =========================
   QUERY
========================= */
$sql = "
    SELECT
        l.id AS log_id,
        l.log_time,
        l.log_type,
        l.latitude,
        l.longitude,
        l.accuracy,
        l.is_within_office,
        l.distance_meters,

        e.id AS employee_id,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
        r.role_key AS employee_role,
        d.department_name
    FROM logs l
    LEFT JOIN employees e ON l.employee_id = e.id
    LEFT JOIN roles r ON r.id = e.role_id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE 1=1
";

$params = [];

// restrict to own logs (employee) or one specific employee (admin profile view)
if ($userRole !== 'admin' || $scopedToEmployee) {
    $sql .= " AND l.employee_id = ?";
    $params[] = $employeeId;
}

if ($startDate !== '') {
    $sql .= " AND log_time >= ?";
    $params[] = $startDate;
}
if ($endDate !== '') {
    $sql .= " AND log_time < DATE_ADD(?, INTERVAL 1 DAY)";
    $params[] = $endDate;
}

if ($type !== 'ALL') {
    $sql .= " AND log_type = ?";
    $params[] = $type;
}

$sql .= " ORDER BY $orderClause";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   LOG EDIT REQUEST STATUS MAP
   work_date → request_type → status (most recent)
========================= */
// editMap keyed by log_id → edit request entry
$editMap    = [];
$editSql = "
    SELECT ler.log_id, ler.status,
           ler.initiated_by_id, r_init.role_key AS initiator_role, e_init.first_name AS initiator_name
    FROM log_edit_requests ler
    LEFT JOIN employees e_init ON ler.initiated_by_id = e_init.id
    LEFT JOIN roles r_init ON r_init.id = e_init.role_id
    WHERE 1=1
";
$editParams = [];

if ($userRole !== 'admin' || $scopedToEmployee) {
    $editSql .= " AND ler.employee_id = ?";
    $editParams[] = $employeeId;
}
if ($startDate !== '') {
    if ($userRole !== 'admin' || $scopedToEmployee) {
        $editSql    .= " AND ler.log_id IN (SELECT id FROM logs WHERE employee_id = ? AND log_time >= ?)";
        $editParams[] = $employeeId;
    } else {
        $editSql    .= " AND ler.log_id IN (SELECT id FROM logs WHERE log_time >= ?)";
    }
    $editParams[] = $startDate;
}
if ($endDate !== '') {
    if ($userRole !== 'admin' || $scopedToEmployee) {
        $editSql    .= " AND ler.log_id IN (SELECT id FROM logs WHERE employee_id = ? AND log_time < DATE_ADD(?, INTERVAL 1 DAY))";
        $editParams[] = $employeeId;
    } else {
        $editSql    .= " AND ler.log_id IN (SELECT id FROM logs WHERE log_time < DATE_ADD(?, INTERVAL 1 DAY))";
    }
    $editParams[] = $endDate;
}
$editSql .= " ORDER BY ler.created_at DESC";

$erStmt = $pdo->prepare($editSql);
$erStmt->execute($editParams);
foreach ($erStmt->fetchAll(PDO::FETCH_ASSOC) as $er) {
    $logId = $er['log_id'];
    if ($logId && !isset($editMap[$logId])) {
        $editMap[$logId] = [
            'status'          => $er['status'],
            'initiated_by_id' => $er['initiated_by_id'],
            'initiator_role'  => $er['initiator_role'],
            'initiator_name'  => $er['initiator_name'],
        ];
    }
}

/* =========================
   BUILD JSON RESPONSE
========================= */
$rows = [];
foreach ($records as $row) {
    $editEntry     = $editMap[$row['log_id']] ?? null;
    $editStatus    = $editEntry['status']          ?? null;
    $initiatedById = $editEntry['initiated_by_id'] ?? null;
    $initiatorRole = $editEntry['initiator_role']  ?? null;
    $initiatorName = $editEntry['initiator_name']  ?? null;

    if ($initiatedById === null) {
        $editRole = null;
    } elseif (!$scopedToEmployee && (int)$initiatedById === $currentUserId) {
        $editRole = 'self';
    } else {
        $editRole = $initiatorRole;
    }

    $lat  = $row['latitude']  ?? 0;
    $lng  = $row['longitude'] ?? 0;
    $acc  = isset($row['accuracy'])        ? round($row['accuracy'], 1)        : null;
    $dist = isset($row['distance_meters']) ? round($row['distance_meters'], 1) : null;

    $rows[] = [
        'log_id'           => (int)$row['log_id'],
        'date'             => date('F d, Y', strtotime($row['log_time'])),
        'time'             => date('h:i A', strtotime($row['log_time'])),
        'log_datetime'     => date('Y-m-d\TH:i', strtotime($row['log_time'])),
        'log_type'         => $row['log_type'],
        'is_within_office' => (bool)$row['is_within_office'],
        'latitude'         => (float)$lat,
        'longitude'        => (float)$lng,
        'accuracy'         => $acc,
        'distance_meters'  => $dist,
        'employee_id'      => (int)$row['employee_id'],
        'employee_name'    => $row['employee_name'],
        'employee_role'    => $row['employee_role'],
        'department_name'  => $row['department_name'],
        'edit_role'        => $editRole,
        'edit_status'      => $editStatus,
        'initiator_name'   => $initiatorName,
    ];
}

echo json_encode([
    'meta' => [
        'user_role'          => $userRole,
        'scoped_to_employee' => $scopedToEmployee,
    ],
    'rows' => $rows,
]);