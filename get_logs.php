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
$scopedToEmployee = $userRole === 'superadmin' && !empty($_GET['employee_id']);
if ($scopedToEmployee) $employeeId = intval($_GET['employee_id']);
date_default_timezone_set('Asia/Manila');

// Department-scoped visibility for manager / workforce
$deptScopeRoles = null;
$deptScopeId    = null;
if (in_array($userRole, ['manager', 'workforce'])) {
    $ds = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
    $ds->execute([$currentUserId]);
    $deptScopeId    = $ds->fetchColumn() ?: null;
    $deptScopeRoles = $userRole === 'manager'
        ? ['manager', 'workforce', 'employee']
        : ['workforce', 'employee'];
}

$startDate = !empty($_GET['start']) ? date('Y-m-d', strtotime($_GET['start'])) : '';
$endDate   = !empty($_GET['end'])   ? date('Y-m-d', strtotime($_GET['end']))   : '';

$type = $_GET['type'] ?? 'ALL';

/* =========================
   SORTING
========================= */
$sort = $_GET['sort'] ?? '';
$dir  = $_GET['dir']  ?? 'desc';
$isAsc = strtolower($dir) === 'asc';

/* =========================
   WHAT TO SHOW
========================= */
$BASE_LOG_TYPES = ['IN', 'OUT', 'BREAK_IN', 'BREAK_OUT'];

$showLogs        = $type === 'ALL' || in_array($type, $BASE_LOG_TYPES);
$showOT          = $type === 'ALL' || $type === 'REQUEST_OT';
$showLeave       = $type === 'ALL' || $type === 'REQUEST_LEAVE';
$showOB          = $type === 'ALL' || $type === 'REQUEST_OB';
$showLogEdit     = $type === 'ALL' || $type === 'REQUEST_LOG_EDIT';
$showChangeSched = $type === 'ALL' || $type === 'REQUEST_CHANGE_SCHEDULE';

$allRows = [];

/* =========================
   HELPER — EDIT ROLE FOR REQUEST ROWS
========================= */
function reqEditRole(bool $isAdmin, bool $scoped, int $currentUserId, int $rowEmpId): string {
    if (!$isAdmin || $scoped) return 'self';
    return ($rowEmpId === $currentUserId) ? 'self' : 'employee';
}

/* =========================
   HELPER — APPLY VISIBILITY FILTER
   $empCol  : e.g. "l.employee_id"
   $deptRoles / $deptId : from setup block above
========================= */
function applyLogsFilter(string &$sql, array &$params, string $empCol, bool $scoped, ?array $deptRoles, ?int $deptId, int $empId, string $role): void {
    if ($scoped) {
        $sql     .= " AND $empCol = ?";
        $params[] = $empId;
    } elseif ($deptRoles !== null) {
        if ($deptId) {
            $ph       = implode(',', array_fill(0, count($deptRoles), '?'));
            $sql     .= " AND e.department_id = ? AND r.role_key IN ($ph)";
            $params[] = $deptId;
            foreach ($deptRoles as $dr) $params[] = $dr;
        } else {
            $sql .= " AND 1=0";
        }
    } elseif ($role !== 'superadmin' && $role !== 'admin') {
        $sql     .= " AND $empCol = ?";
        $params[] = $empId;
    }
}

/* =========================
   1. CLOCK LOGS
========================= */
if ($showLogs) {
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
            l.photo_path,

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

    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);

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

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* --- edit request map keyed by log_id --- */
    $editMap    = [];
    $editSql = "
        SELECT ler.log_id, ler.status,
               ler.initiated_by_id, r_init.role_key AS initiator_role, CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name
        FROM log_edit_requests ler
        JOIN logs l ON l.id = ler.log_id
        LEFT JOIN employees e ON ler.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN employees e_init ON ler.initiated_by_id = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        WHERE 1=1
    ";
    $editParams = [];

    applyLogsFilter($editSql, $editParams, 'ler.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $editSql    .= " AND l.log_time >= ?";
        $editParams[] = $startDate;
    }
    if ($endDate !== '') {
        $editSql    .= " AND l.log_time < DATE_ADD(?, INTERVAL 1 DAY)";
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

    foreach ($logRecords as $row) {
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

        $ts = strtotime($row['log_time']);
        $allRows[] = [
            'log_id'           => (int)$row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
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
            'photo_path'       => $row['photo_path'] ?? null,
            '_ts'              => $ts,
        ];
    }
}

/* =========================
   2. OT REQUESTS
========================= */
if ($showOT) {
    $sql = "
        SELECT
            CONCAT('ot_', ot.id) AS log_id,
            ot.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name,
            ot.date AS req_date,
            ot.created_at,
            ot.status
        FROM overtime_requests ot
        LEFT JOIN employees e ON ot.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE 1=1
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'ot.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql .= " AND DATE(ot.created_at) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND DATE(ot.created_at) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts = strtotime($row['created_at']);
        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', strtotime($row['req_date'])),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'REQUEST_OT',
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role'        => reqEditRole($userRole === 'superadmin', $scopedToEmployee, $currentUserId, (int)$row['employee_id']),
            'edit_status'      => $row['status'],
            'initiator_name'   => $row['employee_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
        ];
    }
}

/* =========================
   3. LEAVE REQUESTS
========================= */
if ($showLeave) {
    $sql = "
        SELECT
            CONCAT('leave_', lr.id) AS log_id,
            lr.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name,
            lr.start_date AS req_date,
            lr.created_at,
            lr.status
        FROM leave_requests lr
        LEFT JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE lr.leave_type != 'ob leave'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'lr.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql .= " AND DATE(lr.created_at) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND DATE(lr.created_at) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts = strtotime($row['created_at']);
        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', strtotime($row['req_date'])),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'REQUEST_LEAVE',
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role'        => reqEditRole($userRole === 'superadmin', $scopedToEmployee, $currentUserId, (int)$row['employee_id']),
            'edit_status'      => $row['status'],
            'initiator_name'   => $row['employee_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
        ];
    }
}

/* =========================
   4. OB REQUESTS
========================= */
if ($showOB) {
    $sql = "
        SELECT
            CONCAT('ob_', lr.id) AS log_id,
            lr.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name,
            lr.start_date AS req_date,
            lr.created_at,
            lr.status
        FROM leave_requests lr
        LEFT JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE lr.leave_type = 'ob leave'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'lr.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql .= " AND DATE(lr.created_at) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND DATE(lr.created_at) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts = strtotime($row['created_at']);
        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', strtotime($row['req_date'])),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'REQUEST_OB',
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role'        => reqEditRole($userRole === 'superadmin', $scopedToEmployee, $currentUserId, (int)$row['employee_id']),
            'edit_status'      => $row['status'],
            'initiator_name'   => $row['employee_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
        ];
    }
}

/* =========================
   5. LOG EDIT REQUESTS
========================= */
if ($showLogEdit) {
    $sql = "
        SELECT
            CONCAT('logedit_', ler.id) AS log_id,
            ler.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name,
            ler.work_date AS req_date,
            ler.created_at,
            ler.status,
            ler.initiated_by_id,
            r_init.role_key AS initiator_role,
            CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name
        FROM log_edit_requests ler
        LEFT JOIN employees e ON ler.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees e_init ON ler.initiated_by_id = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        WHERE 1=1
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'ler.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql .= " AND DATE(ler.created_at) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND DATE(ler.created_at) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts = strtotime($row['created_at']);
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);

        if (!$initiatedById) {
            $editRole = null;
        } elseif (!$scopedToEmployee && $initiatedById === $currentUserId) {
            $editRole = 'self';
        } else {
            $editRole = $row['initiator_role'];
        }

        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', strtotime($row['req_date'])),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'REQUEST_LOG_EDIT',
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role'        => $editRole,
            'edit_status'      => $row['status'],
            'initiator_name'   => $row['initiator_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
        ];
    }
}

/* =========================
   6. CHANGE SCHEDULE REQUESTS
========================= */
if ($showChangeSched) {
    $sql = "
        SELECT
            CONCAT('sched_', s.id) AS log_id,
            s.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name,
            s.schedule_date AS req_date,
            s.status
        FROM schedules s
        LEFT JOIN employees e ON s.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE s.is_rest_day = 0
          AND s.status IN ('pending', 'rejected')
    ";
    $params = [];
    applyLogsFilter($sql, $params, 's.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql .= " AND s.schedule_date >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND s.schedule_date <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts = strtotime($row['req_date']);
        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'REQUEST_CHANGE_SCHEDULE',
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role'        => reqEditRole($userRole === 'superadmin', $scopedToEmployee, $currentUserId, (int)$row['employee_id']),
            'edit_status'      => $row['status'],
            'initiator_name'   => $row['employee_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
        ];
    }
}

/* =========================
   PHP SORT
========================= */
usort($allRows, function ($a, $b) use ($sort, $isAsc) {
    switch ($sort) {
        case 'date':
            $cmp = (int)date('Ymd', $a['_ts']) <=> (int)date('Ymd', $b['_ts']);
            break;
        case 'time':
            $cmp = ($a['_ts'] % 86400) <=> ($b['_ts'] % 86400);
            break;
        case 'type':
            $cmp = strcmp($a['log_type'], $b['log_type']);
            break;
        case 'location':
            $av = $a['is_within_office'];
            $bv = $b['is_within_office'];
            if ($av === null && $bv === null) { $cmp = 0; break; }
            if ($av === null) { return 1; }
            if ($bv === null) { return -1; }
            $cmp = $av <=> $bv;
            break;
        default:
            return $b['_ts'] <=> $a['_ts'];
    }
    return $isAsc ? $cmp : -$cmp;
});

// Remove internal sort key
foreach ($allRows as &$row) unset($row['_ts']);
unset($row);

echo json_encode([
    'meta' => [
        'user_role'          => $userRole,
        'scoped_to_employee' => $scopedToEmployee,
    ],
    'rows' => $allRows,
]);
