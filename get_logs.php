<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

header('Content-Type: application/json');

$userRole      = $_SESSION['user_role'] ?? 'employee';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

$scopedToEmployee =
    $userRole !== 'employee' &&
    !empty($_GET['employee_id']);
$employeeId       = $scopedToEmployee ? intval($_GET['employee_id']) : $currentUserId;
if ($scopedToEmployee) {
    $employeeId = intval($_GET['employee_id']);
} else {
    $employeeId = (int)($_SESSION['user_id'] ?? 0);
}
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

$allRows = [];


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
            l.edit_status,
            l.edit_requested_by,
            CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name,
            r_init.role_key AS initiator_role,

            e.id AS employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name
        FROM logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees e_init ON l.edit_requested_by = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        WHERE 1=1 AND l.log_type NOT IN ('ADD_EMPLOYEE', 'EDIT_EMPLOYEE', 'DELETE_EMPLOYEE', 'ADD_SCHEDULE', 'EDIT_SCHEDULE', 'DELETE_SCHEDULE')
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

    $mgmtTypes = ['ADD_EMPLOYEE','EDIT_EMPLOYEE','DELETE_EMPLOYEE','ADD_SCHEDULE','EDIT_SCHEDULE','DELETE_SCHEDULE'];
    foreach ($logRecords as $row) {
        if (in_array($row['log_type'], $mgmtTypes)) continue;
        $editStatus    = $row['edit_status']      ?? null;
        $initiatedById = $row['edit_requested_by'] ?? null;
        $initiatorRole = $row['initiator_role']   ?? null;
        $initiatorName = $row['initiator_name']   ?? null;

        if ($initiatedById === null) {
            $editRole = null;
        } elseif ((int)$initiatedById === $currentUserId) {
            $editRole = 'self';
        } else {
            $editRole = $initiatorRole;
        }

        $lat  = $row['latitude']  ?? 0;
        $lng  = $row['longitude'] ?? 0;
        $acc  = isset($row['accuracy'])        ? round($row['accuracy'], 1)        : null;
        $dist = isset($row['distance_meters']) ? round($row['distance_meters'], 1) : null;

        $clockDetails = [
            'IN' => 'Clocked in:', 'OUT' => 'Clocked out:',
            'BREAK_IN' => 'Break started:', 'BREAK_OUT' => 'Break ended:',
        ];

        $ts = strtotime($row['log_time']);
        $allRows[] = [
            'log_id'           => (int)$row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => $row['log_type'],
            'details'          => $clockDetails[$row['log_type']] ?? $row['log_type'],
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
            'details'          => 'Overtime request submitted',
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role' => ((int)$row['employee_id'] === $currentUserId) ? 'self' : $row['employee_role'],
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
            'details'          => 'Leave request submitted',
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role' => ((int)$row['employee_id'] === $currentUserId) ? 'self' : $row['employee_role'],
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
            'details'          => 'Official business request submitted',
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role' => ((int)$row['employee_id'] === $currentUserId) ? 'self' : $row['employee_role'],
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
            CONCAT('logedit_', l.id) AS log_id,
            l.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name,
            DATE(l.log_time) AS req_date,
            l.created_at,
            l.edit_status AS status,
            l.edit_requested_by AS initiated_by_id,
            r_init.role_key AS initiator_role,
            CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name
        FROM logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees e_init ON l.edit_requested_by = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        WHERE l.edit_status IS NOT NULL AND l.log_type NOT IN ('ADD_EMPLOYEE', 'EDIT_EMPLOYEE', 'DELETE_EMPLOYEE', 'ADD_SCHEDULE', 'EDIT_SCHEDULE')
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts = strtotime($row['created_at']);
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);

        if (!$initiatedById) {
            $editRole = null;
        } elseif ($initiatedById === $currentUserId) {
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
            'details'          => 'Requested log edit',
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
   7. ADD EMPLOYEE
========================= */
$showAddEmployee = $type === 'ALL' || $type === 'ADD_EMPLOYEE';
if ($showAddEmployee) {
    $sql = "
        SELECT
            CONCAT('addemp_', l.id) AS log_id,
            l.employee_id,
            e.employee_id AS emp_ref_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            e.email,
            e.birthdate,
            r.role_key AS employee_role,
            d.department_name,
            l.log_time,
            l.created_at,
            l.edit_requested_by AS initiated_by_id,
            CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name,
            r_init.role_key AS initiator_role
        FROM logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees e_init ON l.edit_requested_by = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        WHERE l.log_type = 'ADD_EMPLOYEE'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'ADD_EMPLOYEE',
            'details'          => 'Employee added',
            'emp_data'         => [
                ['Employee ID', $row['emp_ref_id']    ?? '—'],
                ['Name',        $row['employee_name'] ?? '—'],
                ['Email',       $row['email']         ?? '—'],
                ['Birthdate',   $row['birthdate'] ? date('F j, Y', strtotime($row['birthdate'])) : '—'],
                ['Role',        $row['employee_role'] ?? '—'],
                ['Department',  $row['department_name'] ?? '—'],
            ],
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role'        => ($initiatedById === $currentUserId) ? 'self' : $row['initiator_role'],
            'edit_status'      => in_array($row['initiator_role'], ['superadmin', 'admin']) ? 'approved' : 'pending',
            'initiator_name'   => $row['initiator_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
        ];
    }
}

/* =========================
   8. EDIT EMPLOYEE
========================= */
$showEditEmployee = $type === 'ALL' || $type === 'EDIT_EMPLOYEE';
if ($showEditEmployee) {
    $sql = "
        SELECT
            CONCAT('editemp_', l.id) AS log_id,
            l.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name,
            l.log_time,
            l.created_at,
            l.edit_reason,
            l.edit_requested_by AS initiated_by_id,
            CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name,
            r_init.role_key AS initiator_role
        FROM logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees e_init ON l.edit_requested_by = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        WHERE l.log_type = 'EDIT_EMPLOYEE'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $diffData = $row['edit_reason'] ? json_decode($row['edit_reason'], true) : null;
        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'EDIT_EMPLOYEE',
            'details'          => 'Employee information updated',
            'diff_data'        => ($diffData && is_array($diffData)) ? $diffData : null,
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role'        => ($initiatedById === $currentUserId) ? 'self' : $row['initiator_role'],
            'edit_status'      => in_array($row['initiator_role'], ['superadmin', 'admin']) ? 'approved' : 'pending',
            'initiator_name'   => $row['initiator_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
        ];
    }
}

/* =========================
   9. DELETE EMPLOYEE
========================= */
$showDeleteEmployee = $type === 'ALL' || $type === 'DELETE_EMPLOYEE';
if ($showDeleteEmployee) {
    $sql = "
        SELECT
            CONCAT('delemp_', l.id) AS log_id,
            l.employee_id,
            l.log_time,
            l.created_at,
            l.edit_reason,
            l.edit_requested_by AS initiated_by_id,
            CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name,
            r_init.role_key AS initiator_role,
            e.department_id,
            r.role_key AS employee_role_join
        FROM logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees e_init ON l.edit_requested_by = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        WHERE l.log_type = 'DELETE_EMPLOYEE'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $info = $row['edit_reason'] ? json_decode($row['edit_reason'], true) : [];
        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'DELETE_EMPLOYEE',
            'details'          => 'Employee deleted',
            'emp_data'         => [
                ['Employee ID', $info['emp_ref_id']      ?? '—'],
                ['Name',        $info['employee_name']   ?? '—'],
                ['Role',        $info['employee_role']   ?? '—'],
                ['Department',  $info['department_name'] ?? '—'],
            ],
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $info['employee_name'] ?? '—',
            'employee_role'    => $info['employee_role'] ?? '—',
            'department_name'  => $info['department_name'] ?? '—',
            'edit_role'        => ($initiatedById === $currentUserId) ? 'self' : $row['initiator_role'],
            'edit_status'      => in_array($row['initiator_role'], ['superadmin', 'admin']) ? 'approved' : 'pending',
            'initiator_name'   => $row['initiator_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
        ];
    }
}

/* =========================
   10. ADD SCHEDULE
========================= */
$showAddSchedule = $type === 'ALL' || $type === 'ADD_SCHEDULE';
if ($showAddSchedule) {
    $sql = "
        SELECT
            CONCAT('addsched_', l.id) AS log_id,
            l.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name,
            l.log_time,
            l.created_at,
            l.edit_status,
            l.edit_requested_by AS initiated_by_id,
            CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name,
            r_init.role_key AS initiator_role,
            (SELECT MIN(s.schedule_date)    FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = l.edit_reason AND l.edit_reason IS NOT NULL) AS sched_min_date,
            (SELECT MAX(s.schedule_date)    FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = l.edit_reason AND l.edit_reason IS NOT NULL) AS sched_max_date,
            (SELECT MIN(s.scheduled_start)  FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = l.edit_reason AND l.edit_reason IS NOT NULL) AS sched_start,
            (SELECT MIN(s.scheduled_end)    FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = l.edit_reason AND l.edit_reason IS NOT NULL) AS sched_end
        FROM logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees e_init ON l.edit_requested_by = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        WHERE l.log_type = 'ADD_SCHEDULE'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $minDate   = $row['sched_min_date'] ?? null;
        $maxDate   = $row['sched_max_date'] ?? null;
        $schedStart = $row['sched_start'] ?? null;
        $schedEnd   = $row['sched_end']   ?? null;
        if ($minDate) {
            $minFmt  = date('M j, Y', strtotime($minDate));
            $maxFmt  = date('M j, Y', strtotime($maxDate));
            $dateStr = ($minDate === $maxDate) ? $minFmt : "$minFmt – $maxFmt";
            $timeStr = ($schedStart && $schedEnd)
                ? date('g:i A', strtotime($schedStart)) . ' - ' . date('g:i A', strtotime($schedEnd))
                : '';
            $schedDetails = "Schedule assigned:\n$dateStr" . ($timeStr ? "\n\nTime:\n$timeStr" : '');
        } else {
            $schedDetails = 'Schedule assigned';
        }
        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'ADD_SCHEDULE',
            'details'          => $schedDetails,
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role'        => ($initiatedById === $currentUserId) ? 'self' : $row['initiator_role'],
            'edit_status'      => $row['edit_status'] ?? (in_array($row['initiator_role'], ['superadmin', 'admin']) ? 'approved' : ($initiatedById ? 'pending' : null)),
            'initiator_name'   => $row['initiator_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
        ];
    }
}

/* =========================
   11. EDIT SCHEDULE
========================= */
$showEditSchedule = $type === 'ALL' || $type === 'EDIT_SCHEDULE';
if ($showEditSchedule) {
    $sql = "
        SELECT
            CONCAT('editsched_', l.id) AS log_id,
            l.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name,
            l.log_time,
            l.created_at,
            l.edit_status,
            l.edit_requested_by AS initiated_by_id,
            CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name,
            r_init.role_key AS initiator_role,
            (SELECT MIN(s.schedule_date)        FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = l.edit_reason AND l.edit_reason IS NOT NULL) AS sched_min_date,
            (SELECT MAX(s.schedule_date)        FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = l.edit_reason AND l.edit_reason IS NOT NULL) AS sched_max_date,
            (SELECT MIN(s.orig_scheduled_start) FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = l.edit_reason AND l.edit_reason IS NOT NULL) AS orig_sched_start,
            (SELECT MIN(s.orig_scheduled_end)   FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = l.edit_reason AND l.edit_reason IS NOT NULL) AS orig_sched_end,
            (SELECT MIN(s.scheduled_start)      FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = l.edit_reason AND l.edit_reason IS NOT NULL) AS sched_start,
            (SELECT MIN(s.scheduled_end)        FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = l.edit_reason AND l.edit_reason IS NOT NULL) AS sched_end
        FROM logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees e_init ON l.edit_requested_by = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        WHERE l.log_type = 'EDIT_SCHEDULE'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $minDate        = $row['sched_min_date']   ?? null;
        $maxDate        = $row['sched_max_date']   ?? null;
        $origSchedStart = $row['orig_sched_start'] ?? null;
        $origSchedEnd   = $row['orig_sched_end']   ?? null;
        $schedStart     = $row['sched_start']      ?? null;
        $schedEnd       = $row['sched_end']        ?? null;
        if ($minDate) {
            $minFmt  = date('M j, Y', strtotime($minDate));
            $maxFmt  = date('M j, Y', strtotime($maxDate));
            $dateStr = ($minDate === $maxDate) ? $minFmt : "$minFmt – $maxFmt";
            $schedDetails = "Schedule updated:\n$dateStr";
            if ($origSchedStart && $origSchedEnd && $schedStart && $schedEnd) {
                $beforeTime = date('g:i A', strtotime($origSchedStart)) . ' - ' . date('g:i A', strtotime($origSchedEnd));
                $afterTime  = date('g:i A', strtotime($schedStart))     . ' - ' . date('g:i A', strtotime($schedEnd));
                $schedDetails .= "\n\nBefore:\n$beforeTime\nNow:\n$afterTime";
            } elseif ($schedStart && $schedEnd) {
                $afterTime    = date('g:i A', strtotime($schedStart)) . ' - ' . date('g:i A', strtotime($schedEnd));
                $schedDetails .= "\nTime:\n$afterTime";
            }
        } else {
            $schedDetails = 'Schedule updated';
        }
        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'EDIT_SCHEDULE',
            'details'          => $schedDetails,
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role'        => ($initiatedById === $currentUserId) ? 'self' : $row['initiator_role'],
            'edit_status'      => $row['edit_status'] ?? (in_array($row['initiator_role'], ['superadmin', 'admin']) ? 'approved' : ($initiatedById ? 'pending' : null)),
            'initiator_name'   => $row['initiator_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
        ];
    }
}
/* =========================
   12. DELETE SCHEDULE
========================= */
$showDeleteSchedule = $type === 'ALL' || $type === 'DELETE_SCHEDULE';
if ($showDeleteSchedule) {
    $sql = "
        SELECT
            CONCAT('delsched_', l.id) AS log_id,
            l.employee_id,
            l.log_time,
            l.created_at,
            l.edit_reason,
            l.edit_requested_by AS initiated_by_id,
            CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name,
            r_init.role_key AS initiator_role,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name
        FROM logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees e_init ON l.edit_requested_by = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        WHERE l.log_type = 'DELETE_SCHEDULE'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $info = $row['edit_reason'] ? json_decode($row['edit_reason'], true) : [];
        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'DELETE_SCHEDULE',
            'details'          => 'Schedule deleted',
            'emp_data'         => [
                ['Employee',      $info['employee_name'] ?? '—'],
                ['Deleted Date',  $info['schedule_date'] ?? '—'],
                ['Time',          $info['time']          ?? '—'],
                ['Deleted by',    $info['deleted_by']    ?? '—'],
            ],
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $info['employee_name'] ?? ($row['employee_name'] ?? '—'),
            'employee_role'    => $row['employee_role'] ?? '—',
            'department_name'  => $row['department_name'] ?? '—',
            'edit_role'        => ($initiatedById === $currentUserId) ? 'self' : $row['initiator_role'],
            'edit_status'      => in_array($row['initiator_role'], ['superadmin', 'admin']) ? 'approved' : 'pending',
            'initiator_name'   => $row['initiator_name'],
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
            $cmp = $a['_ts'] <=> $b['_ts'];
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
