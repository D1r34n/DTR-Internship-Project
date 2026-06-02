<?php
set_exception_handler(function ($e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    exit();
});
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

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

$employeeId = $scopedToEmployee
    ? intval($_GET['employee_id'])
    : (int)($_SESSION['user_id'] ?: 0);

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
$sort  = $_GET['sort'] ?? '';
$dir   = $_GET['dir']  ?? 'desc';
$isAsc = strtolower($dir) === 'asc';

/* =========================
   WHAT TO SHOW
========================= */
$BASE_LOG_TYPES = ['IN', 'OUT', 'BREAK_IN', 'BREAK_OUT'];

$showLogs    = $type === 'ALL' || in_array($type, $BASE_LOG_TYPES);
$showOT      = $type === 'ALL' || $type === 'REQUEST_OT';
$showLeave   = $type === 'ALL' || $type === 'REQUEST_LEAVE';
$showOB      = $type === 'ALL' || $type === 'REQUEST_OB';
$showLogEdit = $type === 'ALL' || $type === 'REQUEST_LOG_EDIT';

$allRows = [];

/* =========================
   HELPER — APPLY VISIBILITY FILTER
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
            ler.status       AS edit_status,
            ler.requested_by AS edit_requested_by,
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
        LEFT JOIN log_edit_requests ler
            ON  ler.log_id = l.id
            AND ler.id = (SELECT MAX(id) FROM log_edit_requests WHERE log_id = l.id)
        LEFT JOIN employees e_init ON ler.requested_by = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        WHERE 1=1
          AND l.log_type NOT IN ('ADD_EMPLOYEE', 'EDIT_EMPLOYEE', 'DELETE_EMPLOYEE', 'ADD_SCHEDULE', 'EDIT_SCHEDULE', 'DELETE_SCHEDULE', 'ADD_DEPARTMENT', 'EDIT_DEPARTMENT', 'DELETE_DEPARTMENT')
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql     .= " AND l.log_time >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql     .= " AND l.log_time < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $endDate;
    }
    if ($type !== 'ALL') {
        $sql     .= " AND l.log_type = ?";
        $params[] = $type;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $mgmtTypes = ['ADD_EMPLOYEE','EDIT_EMPLOYEE','DELETE_EMPLOYEE','ADD_SCHEDULE','EDIT_SCHEDULE'];
    $clockDetails = [
        'IN'        => 'Clocked in:',
        'OUT'       => 'Clocked out:',
        'BREAK_IN'  => 'Break started:',
        'BREAK_OUT' => 'Break ended:',
    ];

    foreach ($logRecords as $row) {
        if (in_array($row['log_type'], $mgmtTypes)) continue;

        $editStatus    = $row['edit_status']       ?? null;
        $initiatedById = $row['edit_requested_by'] ?? null;
        $initiatorRole = $row['initiator_role']    ?? null;
        $initiatorName = $row['initiator_name']    ?? null;

        if ($initiatedById === null) {
            $editRole = null;
        } elseif ((int)$initiatedById === $currentUserId) {
            $editRole = 'self';
        } else {
            $editRole = $initiatorRole;
        }

        $acc  = isset($row['accuracy'])        ? round($row['accuracy'], 1)        : null;
        $dist = isset($row['distance_meters']) ? round($row['distance_meters'], 1) : null;

        $ts      = strtotime($row['log_time']);
        $reqDate = strtotime(date('Y-m-d', $ts));

        $allRows[] = [
            'log_id'           => (int)$row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => $row['log_type'],
            'details'          => $clockDetails[$row['log_type']] ?? $row['log_type'],
            'is_within_office' => (bool)$row['is_within_office'],
            'latitude'         => (float)($row['latitude']  ?? 0),
            'longitude'        => (float)($row['longitude'] ?? 0),
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
            '_date_ts'         => $reqDate,
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
            ot.status,
            ot.reason AS ot_reason,
            a.scheduled_start,
            a.scheduled_end,
            a.actual_time_in,
            a.actual_time_out,
            a.late_minutes,
            a.overtime_minutes,
            a.overtime_status
        FROM overtime_requests ot
        LEFT JOIN employees e ON ot.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN attendances a ON a.employee_id = ot.employee_id
            AND a.work_date = ot.date
            AND a.id = (
                SELECT id FROM attendances
                WHERE employee_id = ot.employee_id AND work_date = ot.date
                ORDER BY FIELD(status,'present','incomplete','absent'), id DESC
                LIMIT 1
            )
        WHERE 1=1
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'ot.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql     .= " AND DATE(ot.created_at) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql     .= " AND DATE(ot.created_at) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts      = strtotime($row['created_at']);
        $reqDate = strtotime($row['req_date']);

        // Build structured attendance detail for the modal
        $otData = null;
        $schedStart  = $row['scheduled_start'] ?? null;
        $schedEnd    = $row['scheduled_end']   ?? null;
        $actualIn    = $row['actual_time_in']  ?? null;
        $actualOut   = $row['actual_time_out'] ?? null;
        $lateMin     = (int)($row['late_minutes']     ?? 0);
        $otMin       = (int)($row['overtime_minutes'] ?? 0);
        $otStatus    = $row['overtime_status']         ?? null;

        $earlyMin = 0;
        if ($actualIn && $schedStart && strtotime($actualIn) < strtotime($schedStart)) {
            $earlyMin = (int)round((strtotime($schedStart) - strtotime($actualIn)) / 60);
        }

        $otData = [
            'scheduled_start'  => $schedStart  ? date('h:i A', strtotime($schedStart))  : null,
            'scheduled_end'    => $schedEnd    ? date('h:i A', strtotime($schedEnd))    : null,
            'actual_time_in'   => $actualIn    ? date('h:i A', strtotime($actualIn))    : null,
            'actual_time_out'  => $actualOut   ? date('h:i A', strtotime($actualOut))   : null,
            'late_minutes'     => $lateMin,
            'early_minutes'    => $earlyMin,
            'overtime_minutes' => $otMin,
            'overtime_status'  => $otStatus,
            'reason'           => $row['ot_reason'] ?? null,
        ];

        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $reqDate),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'REQUEST_OT',
            'details'          => 'Overtime request submitted',
            'ot_data'          => $otData,
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role'        => ((int)$row['employee_id'] === $currentUserId) ? 'self' : $row['employee_role'],
            'edit_status'      => $row['status'],
            'initiator_name'   => $row['employee_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
            '_date_ts'         => $reqDate,
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
            lr.end_date,
            lr.selected_dates,
            lr.reason AS leave_reason,
            lr.created_at,
            lr.status,
            lt.name AS leave_type_name
        FROM leave_requests lr
        LEFT JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE lr.leave_type_id != (SELECT id FROM leave_types WHERE name = 'ob leave')
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'lr.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql     .= " AND DATE(lr.created_at) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql     .= " AND DATE(lr.created_at) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts      = strtotime($row['created_at']);
        $reqDate = strtotime($row['req_date']);

        $selectedDates = json_decode($row['selected_dates'] ?? '[]', true);
        if (!is_array($selectedDates) || empty($selectedDates)) {
            $cur = new DateTime($row['req_date']);
            $fin = new DateTime($row['end_date'] ?? $row['req_date']);
            while ($cur <= $fin) {
                $selectedDates[] = $cur->format('Y-m-d');
                $cur->modify('+1 day');
            }
        }
        $formattedDates = array_map(
            fn($d) => date('D, F j, Y', strtotime($d)),
            $selectedDates
        );

        $leaveData = [
            'leave_type' => $row['leave_type_name'] ? ucwords($row['leave_type_name']) : null,
            'dates'      => $formattedDates,
            'reason'     => $row['leave_reason'] ?? null,
        ];

        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $reqDate),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'REQUEST_LEAVE',
            'details'          => 'Leave request submitted',
            'leave_data'       => $leaveData,
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role'        => ((int)$row['employee_id'] === $currentUserId) ? 'self' : $row['employee_role'],
            'edit_status'      => $row['status'],
            'initiator_name'   => $row['employee_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
            '_date_ts'         => $reqDate,
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
            lr.end_date,
            lr.selected_dates,
            lr.client_name,
            lr.reason AS ob_reason,
            lr.created_at,
            lr.status
        FROM leave_requests lr
        LEFT JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE lr.leave_type_id = (SELECT id FROM leave_types WHERE name = 'ob leave')
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'lr.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql     .= " AND DATE(lr.created_at) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql     .= " AND DATE(lr.created_at) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts      = strtotime($row['created_at']);
        $reqDate = strtotime($row['req_date']);

        $selectedDates = json_decode($row['selected_dates'] ?? '[]', true);
        if (!is_array($selectedDates) || empty($selectedDates)) {
            $cur = new DateTime($row['req_date']);
            $fin = new DateTime($row['end_date'] ?? $row['req_date']);
            while ($cur <= $fin) {
                $selectedDates[] = $cur->format('Y-m-d');
                $cur->modify('+1 day');
            }
        }
        $formattedDates = array_map(
            fn($d) => date('D, F j, Y', strtotime($d)),
            $selectedDates
        );

        $obData = [
            'dates'       => $formattedDates,
            'client_name' => $row['client_name'] ?? null,
            'reason'      => $row['ob_reason']   ?? null,
        ];

        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $reqDate),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'REQUEST_OB',
            'details'          => 'Official business request submitted',
            'ob_data'          => $obData,
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $row['employee_name'],
            'employee_role'    => $row['employee_role'],
            'department_name'  => $row['department_name'],
            'edit_role'        => ((int)$row['employee_id'] === $currentUserId) ? 'self' : $row['employee_role'],
            'edit_status'      => $row['status'],
            'initiator_name'   => $row['employee_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
            '_date_ts'         => $reqDate,
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
            DATE(lg.log_time) AS req_date,
            lg.log_type AS original_log_type,
            ler.original_log_time,
            ler.proposed_log_time,
            ler.reason AS edit_reason,
            ler.created_at,
            ler.status,
            ler.requested_by AS initiated_by_id,
            r_init.role_key AS initiator_role,
            CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name
        FROM log_edit_requests ler
        JOIN logs lg ON ler.log_id = lg.id
        LEFT JOIN employees e ON ler.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees e_init ON ler.requested_by = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        WHERE 1=1
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'ler.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql     .= " AND DATE(lg.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql     .= " AND DATE(lg.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['created_at']);
        $reqDate       = strtotime($row['req_date']);
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);

        if (!$initiatedById) {
            $editRole = null;
        } elseif ($initiatedById === $currentUserId) {
            $editRole = 'self';
        } else {
            $editRole = $row['initiator_role'];
        }

        $logTypeLabels = [
            'IN'        => 'Time In',
            'OUT'       => 'Time Out',
            'BREAK_IN'  => 'Break In',
            'BREAK_OUT' => 'Break Out',
        ];
        $logEditData = [
            'log_type'          => $logTypeLabels[$row['original_log_type']] ?? ($row['original_log_type'] ?? '—'),
            'original_log_time' => $row['original_log_time'] ? date('M j, Y h:i A', strtotime($row['original_log_time'])) : '—',
            'proposed_log_time' => $row['proposed_log_time'] ? date('M j, Y h:i A', strtotime($row['proposed_log_time'])) : '—',
            'reason'            => $row['edit_reason'] ?? null,
        ];

        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $reqDate),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'REQUEST_LOG_EDIT',
            'details'          => 'Requested log edit',
            'log_edit_data'    => $logEditData,
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
            '_date_ts'         => $reqDate,
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
        $sql     .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql     .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $reqDate       = strtotime(date('Y-m-d', $ts));
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'ADD_EMPLOYEE',
            'details'          => 'Employee added',
            'emp_data'         => [
                ['Employee ID', $row['emp_ref_id']      ?? '—'],
                ['Name',        $row['employee_name']   ?? '—'],
                ['Email',       $row['email']           ?? '—'],
                ['Birthdate',   $row['birthdate'] ? date('F j, Y', strtotime($row['birthdate'])) : '—'],
                ['Role',        $row['employee_role']   ?? '—'],
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
            '_date_ts'         => $reqDate,
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
        $sql     .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql     .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $reqDate       = strtotime(date('Y-m-d', $ts));
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $diffData      = $row['edit_reason'] ? json_decode($row['edit_reason'], true) : null;
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
            '_date_ts'         => $reqDate,
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
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name
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
        $sql     .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql     .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $reqDate       = strtotime(date('Y-m-d', $ts));
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $info          = $row['edit_reason'] ? json_decode($row['edit_reason'], true) : [];
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
            'employee_name'    => $info['employee_name'] ?? ($row['employee_name'] ?? '—'),
            'employee_role'    => $info['employee_role'] ?? ($row['employee_role'] ?? '—'),
            'department_name'  => $info['department_name'] ?? ($row['department_name'] ?? '—'),
            'edit_role'        => ($initiatedById === $currentUserId) ? 'self' : $row['initiator_role'],
            'edit_status'      => in_array($row['initiator_role'], ['superadmin', 'admin']) ? 'approved' : 'pending',
            'initiator_name'   => $row['initiator_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
            '_date_ts'         => $reqDate,
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
            ser.status AS edit_status,
            l.edit_requested_by AS initiated_by_id,
            CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name,
            r_init.role_key AS initiator_role,
            (SELECT MIN(s.schedule_date)   FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = ser.batch_id AND ser.batch_id IS NOT NULL) AS sched_min_date,
            (SELECT MAX(s.schedule_date)   FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = ser.batch_id AND ser.batch_id IS NOT NULL) AS sched_max_date,
            (SELECT MIN(s.scheduled_start) FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = ser.batch_id AND ser.batch_id IS NOT NULL) AS sched_start,
            (SELECT MIN(s.scheduled_end)   FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = ser.batch_id AND ser.batch_id IS NOT NULL) AS sched_end
        FROM logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees e_init ON l.edit_requested_by = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        LEFT JOIN schedule_edit_requests ser ON ser.id = l.schedule_request_id
        WHERE l.log_type = 'ADD_SCHEDULE'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    // Date filter intentionally omitted: all schedule submission history must always be visible
    // so that both rejected and approved submissions for the same date are never hidden.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $reqDate       = strtotime(date('Y-m-d', $ts));
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $minDate       = $row['sched_min_date'] ?? null;
        $maxDate       = $row['sched_max_date'] ?? null;
        $schedStart    = $row['sched_start']    ?? null;
        $schedEnd      = $row['sched_end']      ?? null;

        if ($minDate) {
            $minFmt      = date('M j, Y', strtotime($minDate));
            $maxFmt      = date('M j, Y', strtotime($maxDate));
            $dateStr     = ($minDate === $maxDate) ? $minFmt : "$minFmt – $maxFmt";
            $timeStr     = ($schedStart && $schedEnd)
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
            '_date_ts'         => $reqDate,
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
            ser.status AS edit_status,
            l.edit_requested_by AS initiated_by_id,
            CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name,
            r_init.role_key AS initiator_role,
            (SELECT MIN(s.schedule_date)        FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = ser.batch_id AND ser.batch_id IS NOT NULL) AS sched_min_date,
            (SELECT MAX(s.schedule_date)        FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = ser.batch_id AND ser.batch_id IS NOT NULL) AS sched_max_date,
            (SELECT MIN(s.orig_scheduled_start) FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = ser.batch_id AND ser.batch_id IS NOT NULL) AS orig_sched_start,
            (SELECT MIN(s.orig_scheduled_end)   FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = ser.batch_id AND ser.batch_id IS NOT NULL) AS orig_sched_end,
            (SELECT MIN(s.scheduled_start)      FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = ser.batch_id AND ser.batch_id IS NOT NULL) AS sched_start,
            (SELECT MIN(s.scheduled_end)        FROM schedules s WHERE s.employee_id = l.employee_id AND s.batch_id = ser.batch_id AND ser.batch_id IS NOT NULL) AS sched_end
        FROM logs l
        LEFT JOIN employees e ON l.employee_id = e.id
        LEFT JOIN roles r ON r.id = e.role_id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees e_init ON l.edit_requested_by = e_init.id
        LEFT JOIN roles r_init ON r_init.id = e_init.role_id
        LEFT JOIN schedule_edit_requests ser ON ser.id = l.schedule_request_id
        WHERE l.log_type = 'EDIT_SCHEDULE'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    // Date filter intentionally omitted: same reason as ADD_SCHEDULE above.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts             = strtotime($row['log_time']);
        $reqDate        = strtotime(date('Y-m-d', $ts));
        $initiatedById  = (int)($row['initiated_by_id'] ?? 0);
        $minDate        = $row['sched_min_date']   ?? null;
        $maxDate        = $row['sched_max_date']   ?? null;
        $origSchedStart = $row['orig_sched_start'] ?? null;
        $origSchedEnd   = $row['orig_sched_end']   ?? null;
        $schedStart     = $row['sched_start']      ?? null;
        $schedEnd       = $row['sched_end']        ?? null;

        if ($minDate) {
            $minFmt      = date('M j, Y', strtotime($minDate));
            $maxFmt      = date('M j, Y', strtotime($maxDate));
            $dateStr     = ($minDate === $maxDate) ? $minFmt : "$minFmt – $maxFmt";
            $schedDetails = "Schedule updated:\n$dateStr";
            if ($origSchedStart && $origSchedEnd && $schedStart && $schedEnd) {
                $beforeTime    = date('g:i A', strtotime($origSchedStart)) . ' - ' . date('g:i A', strtotime($origSchedEnd));
                $afterTime     = date('g:i A', strtotime($schedStart))     . ' - ' . date('g:i A', strtotime($schedEnd));
                $schedDetails .= "\n\nBefore:\n$beforeTime\nNow:\n$afterTime";
            } elseif ($schedStart && $schedEnd) {
                $afterTime     = date('g:i A', strtotime($schedStart)) . ' - ' . date('g:i A', strtotime($schedEnd));
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
            '_date_ts'         => $reqDate,
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
        $sql     .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql     .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $reqDate       = strtotime(date('Y-m-d', $ts));
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $info          = $row['edit_reason'] ? json_decode($row['edit_reason'], true) : [];
        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'DELETE_SCHEDULE',
            'details'          => 'Schedule deleted',
            'emp_data'         => [
                ['Employee',     $info['employee_name'] ?? '—'],
                ['Deleted Date', $info['schedule_date'] ?? '—'],
                ['Time',         $info['time']          ?? '—'],
            ],
            'is_within_office' => null,
            'latitude'         => null,
            'longitude'        => null,
            'accuracy'         => null,
            'distance_meters'  => null,
            'employee_id'      => (int)$row['employee_id'],
            'employee_name'    => $info['employee_name'] ?? ($row['employee_name'] ?? '—'),
            'employee_role'    => $row['employee_role']  ?? '—',
            'department_name'  => $row['department_name'] ?? '—',
            'edit_role'        => ($initiatedById === $currentUserId) ? 'self' : $row['initiator_role'],
            'edit_status'      => in_array($row['initiator_role'], ['superadmin', 'admin']) ? 'approved' : 'pending',
            'initiator_name'   => $row['initiator_name'],
            'photo_path'       => null,
            '_ts'              => $ts,
            '_date_ts'         => $reqDate,
        ];
    }
}

/* =========================
   13. ADD DEPARTMENT
========================= */
$showAddDepartment = $type === 'ALL' || $type === 'ADD_DEPARTMENT';
if ($showAddDepartment) {
    $sql = "
        SELECT
            CONCAT('adddept_', l.id) AS log_id,
            l.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name,
            l.log_time,
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
        WHERE l.log_type = 'ADD_DEPARTMENT'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql     .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql     .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $reqDate       = strtotime(date('Y-m-d', $ts));
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $info          = $row['edit_reason'] ? json_decode($row['edit_reason'], true) : [];

        $deptData = [
            'department_name' => $info['department_name'] ?? '—',
            'department_code' => $info['department_code'] ?? '—',
            'parent_name'     => $info['parent_name']     ?? null,
        ];

        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'ADD_DEPARTMENT',
            'details'          => 'Department added',
            'dept_data'        => $deptData,
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
            '_date_ts'         => $reqDate,
        ];
    }
}

/* =========================
   14. EDIT DEPARTMENT
========================= */
$showEditDepartment = $type === 'ALL' || $type === 'EDIT_DEPARTMENT';
if ($showEditDepartment) {
    $sql = "
        SELECT
            CONCAT('editdept_', l.id) AS log_id,
            l.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name,
            l.log_time,
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
        WHERE l.log_type = 'EDIT_DEPARTMENT'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql     .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql     .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $reqDate       = strtotime(date('Y-m-d', $ts));
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $diffData      = $row['edit_reason'] ? json_decode($row['edit_reason'], true) : null;

        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'EDIT_DEPARTMENT',
            'details'          => 'Department updated',
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
            '_date_ts'         => $reqDate,
        ];
    }
}

/* =========================
   15. DELETE DEPARTMENT
========================= */
$showDeleteDepartment = $type === 'ALL' || $type === 'DELETE_DEPARTMENT';
if ($showDeleteDepartment) {
    $sql = "
        SELECT
            CONCAT('deldept_', l.id) AS log_id,
            l.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            r.role_key AS employee_role,
            d.department_name,
            l.log_time,
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
        WHERE l.log_type = 'DELETE_DEPARTMENT'
    ";
    $params = [];
    applyLogsFilter($sql, $params, 'l.employee_id', $scopedToEmployee, $deptScopeRoles, $deptScopeId, (int)$employeeId, $userRole);
    if ($startDate !== '') {
        $sql     .= " AND DATE(l.log_time) >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql     .= " AND DATE(l.log_time) <= ?";
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ts            = strtotime($row['log_time']);
        $reqDate       = strtotime(date('Y-m-d', $ts));
        $initiatedById = (int)($row['initiated_by_id'] ?? 0);
        $info          = $row['edit_reason'] ? json_decode($row['edit_reason'], true) : [];

        $deptData = [
            'department_name' => $info['department_name'] ?? '—',
            'department_code' => $info['department_code'] ?? '—',
            'parent_name'     => $info['parent_name']     ?? null,
        ];

        $allRows[] = [
            'log_id'           => $row['log_id'],
            'date'             => date('F d, Y', $ts),
            'time'             => date('h:i A', $ts),
            'log_datetime'     => date('Y-m-d\TH:i', $ts),
            'log_type'         => 'DELETE_DEPARTMENT',
            'details'          => 'Department deleted',
            'dept_data'        => $deptData,
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
            '_date_ts'         => $reqDate,
        ];
    }
}

/* =========================
   PHP SORT
========================= */
usort($allRows, function ($a, $b) use ($sort, $isAsc) {
    switch ($sort) {
        case 'date':
            $cmp = ($a['_date_ts'] ?? 0) <=> ($b['_date_ts'] ?? 0);
            break;
        case 'time':
            $cmp = (($a['_ts'] ?? 0) % 86400) <=> (($b['_ts'] ?? 0) % 86400);
            break;
        case 'type':
            $cmp = strcmp($a['log_type'] ?? '', $b['log_type'] ?? '');
            break;
        case 'employee':
            $cmp = strcmp($a['employee_name'] ?? '', $b['employee_name'] ?? '');
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
            return ($b['_ts'] ?? 0) <=> ($a['_ts'] ?? 0);
    }
    return $isAsc ? $cmp : -$cmp;
});

// Remove internal sort keys
foreach ($allRows as &$row) {
    unset($row['_ts'], $row['_date_ts']);
}
unset($row);

$total = count($allRows);

if (empty($_GET['bypass_pagination'])) {
    $page    = max(1, intval($_GET['page']  ?? 1));
    $limit   = max(1, min(200, intval($_GET['limit'] ?? 25)));
    $offset  = ($page - 1) * $limit;
    $allRows = array_slice($allRows, $offset, $limit);
}

echo json_encode([
    'meta' => [
        'user_role'          => $userRole,
        'scoped_to_employee' => $scopedToEmployee,
    ],
    'rows'  => $allRows,
    'total' => $total,
]);