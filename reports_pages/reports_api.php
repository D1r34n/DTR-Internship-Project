<?php
/* ================================================
   REPORTS API ENDPOINT CONTROLLER
   ================================================ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access. Please log back in.']);
    exit();
}

$action = $_GET['action'] ?? null;
$start  = $_GET['start']  ?? null;
$end    = $_GET['end']    ?? null;

if (!$action) {
    echo json_encode(['error' => 'Missing action parameter.']);
    exit();
}

/* ================================================
   ATTENDANCE REPORT MODULE
   ================================================ */
if ($action === 'attendance') {

    if (!$start || !$end) {
        echo json_encode(['error' => 'Missing date range parameters.']);
        exit();
    }

    $page   = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $status = $_GET['status'] ?? 'ALL';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $bypass = isset($_GET['bypass_pagination']) && $_GET['bypass_pagination'] === '1';

    $userRole = $_SESSION['user_role'];
    $userId   = (int)$_SESSION['user_id'];

    $whereClauses = ["a.work_date BETWEEN ? AND ?"];
    $params       = [$start, $end];

    if (in_array($userRole, ['manager', 'workforce'])) {
        $stmtDept = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
        $stmtDept->execute([$userId]);
        $departmentId = $stmtDept->fetchColumn();
        if ($departmentId) {
            $whereClauses[] = "e.department_id = ?";
            $params[]       = $departmentId;
        } else {
            echo json_encode(['total' => 0, 'data' => []]);
            exit();
        }
    }

    if ($status !== 'ALL') {
        $whereClauses[] = "a.status = ?";
        $params[]       = $status;
    }

    if ($search !== '') {
        $whereClauses[] = "(e.employee_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR d.department_name LIKE ? OR r.role_name LIKE ?)";
        $w = '%' . $search . '%';
        array_push($params, $w, $w, $w, $w, $w);
    }

    $whereSql = "WHERE " . implode(" AND ", $whereClauses);

    $sortMap = [
        'date'      => 'a.work_date',
        'name'      => 'employee_name',
        'regular'   => 'a.total_work_minutes',
        'late'      => 'a.late_minutes',
        'undertime' => 'a.undertime_minutes',
        'overtime'  => 'a.overtime_minutes',
        'status'    => 'a.status',
    ];
    $sortKey  = $_GET['sort'] ?? 'date';
    $sortDir  = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    $orderCol = $sortMap[$sortKey] ?? 'a.work_date';

    $orderSql = "$orderCol $sortDir";
    if ($orderCol !== 'a.work_date')   $orderSql .= ', a.work_date DESC';
    if ($orderCol !== 'employee_name') $orderSql .= ', employee_name ASC';

    try {
        $countSql = "
            SELECT COUNT(*)
            FROM attendances a
            LEFT JOIN employees   e ON e.id = a.employee_id
            LEFT JOIN roles       r ON r.id = e.role_id
            LEFT JOIN departments d ON d.id = e.department_id
            $whereSql
        ";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        $dataSql = "
            SELECT
                e.employee_id,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                d.department_name,
                r.role_name,
                a.work_date,
                a.actual_time_in,
                a.actual_time_out,
                a.total_work_minutes,
                a.late_minutes,
                a.undertime_minutes,
                a.overtime_minutes,
                a.status
            FROM attendances a
            LEFT JOIN employees   e ON e.id = a.employee_id
            LEFT JOIN roles       r ON r.id = e.role_id
            LEFT JOIN departments d ON d.id = e.department_id
            $whereSql
            ORDER BY $orderSql
        ";

        if (!$bypass) {
            $offset   = ($page - 1) * $limit;
            $dataSql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        $stmtData = $pdo->prepare($dataSql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmtData->bindValue($key + 1, $value, $type);
        }
        $stmtData->execute();

        echo json_encode([
            'total' => $totalRecords,
            'data'  => $stmtData->fetchAll(),
        ]);
        exit();

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database failure processing payload values.']);
        exit();
    }
}

/* ================================================
   FILING / REQUESTS MODULE CONTROLLER
   ================================================ */
if ($action === 'reports') {

    $start = isset($_GET['start']) ? trim($_GET['start']) : null;
    $end   = isset($_GET['end'])   ? trim($_GET['end'])   : null;

    if (!$start || !$end) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing date range parameters.']);
        exit();
    }

    $page   = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'ALL';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $bypass = isset($_GET['bypass_pagination']) && $_GET['bypass_pagination'] === '1';

    $userRole = $_SESSION['user_role'] ?? '';
    $userId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    $departmentId = null;
    if (in_array($userRole, ['manager', 'workforce'])) {
        $stmtDept = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
        $stmtDept->execute([$userId]);
        $departmentId = $stmtDept->fetchColumn();
        if (!$departmentId) {
            echo json_encode(['total' => 0, 'data' => []]);
            exit();
        }
    }

    $leaveTypeNames   = $pdo->query("SELECT name FROM leave_types")->fetchAll(PDO::FETCH_COLUMN);
    $normalizedStatus = strtolower($status);

    $leaveQuery = "SELECT 'leave' AS request_type, lr.id AS source_id, e.employee_id, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, d.department_name, r.role_name, DATE(lr.created_at) AS date_filed, DATE(lr.updated_at) AS date_approved, CONCAT(ab.first_name, ' ', ab.last_name) AS approved_by, lr.status AS status, lr.start_date, lr.end_date, lr.reason, lt.label AS request_name, e.department_id FROM leave_requests lr JOIN leave_types lt ON lt.id = lr.leave_type_id LEFT JOIN employees e ON e.id = lr.employee_id LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN roles r ON r.id = e.role_id LEFT JOIN employees ab ON ab.id = lr.approved_by WHERE lr.status IS NOT NULL AND lr.status != ''";
    $otQuery    = "SELECT 'overtime' AS request_type, ot.id AS source_id, e.employee_id, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, d.department_name, r.role_name, DATE(ot.created_at) AS date_filed, DATE(ot.updated_at) AS date_approved, CONCAT(ab.first_name, ' ', ab.last_name) AS approved_by, ot.status AS status, ot.date AS start_date, NULL AS end_date, ot.reason, 'Overtime Request' AS request_name, e.department_id FROM overtime_requests ot LEFT JOIN employees e ON e.id = ot.employee_id LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN roles r ON r.id = e.role_id LEFT JOIN employees ab ON ab.id = ot.approved_by WHERE ot.status IS NOT NULL AND ot.status != ''";
    $logQuery   = "SELECT 'log_edit' AS request_type, ler.id AS source_id, e.employee_id, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, d.department_name, r.role_name, DATE(ler.created_at) AS date_filed, DATE(ler.updated_at) AS date_approved, CONCAT(ab.first_name, ' ', ab.last_name) AS approved_by, ler.status AS status, DATE(ler.proposed_log_time) AS start_date, NULL AS end_date, ler.reason, 'Log Edit Request' AS request_name, e.department_id FROM log_edit_requests ler LEFT JOIN employees e ON e.id = ler.employee_id LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN roles r ON r.id = e.role_id LEFT JOIN employees ab ON ab.id = ler.approved_by WHERE ler.status IS NOT NULL AND ler.status != ''";
    $schedQuery = "SELECT 'schedule_edit' AS request_type, ser.id AS source_id, e.employee_id, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, d.department_name, r.role_name, DATE(ser.created_at) AS date_filed, DATE(ser.updated_at) AS date_approved, CONCAT(ab.first_name, ' ', ab.last_name) AS approved_by, ser.status AS status, NULL AS start_date, NULL AS end_date, ser.reason, 'Schedule Edit Request' AS request_name, e.department_id FROM schedule_edit_requests ser LEFT JOIN employees e ON e.id = ser.employee_id LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN roles r ON r.id = e.role_id LEFT JOIN employees ab ON ab.id = ser.approved_by WHERE ser.status IS NOT NULL AND ser.status != ''";

    $queries = [];
    if ($normalizedStatus === 'all') {
        $queries = [$leaveQuery, $otQuery, $logQuery, $schedQuery];
    } elseif ($normalizedStatus === 'leave') {
        $queries[] = $leaveQuery;
    } elseif ($normalizedStatus === 'request') {
        $queries = [$otQuery, $logQuery, $schedQuery];
    } elseif ($normalizedStatus === 'log_edit') {
        $queries[] = $logQuery;
    } elseif (in_array($normalizedStatus, ['overtime', 'ot request'])) {
        $queries[] = $otQuery;
    } elseif ($normalizedStatus === 'schedule_edit') {
        $queries[] = $schedQuery;
    } elseif (in_array($normalizedStatus, $leaveTypeNames)) {
        $queries[] = $leaveQuery . " AND lt.name = " . $pdo->quote($normalizedStatus);
    } else {
        $queries[] = $leaveQuery;
    }

    $baseQuery = implode(" UNION ALL ", $queries);

    $outerWhere = ["u.date_filed BETWEEN ? AND ?"];
    $params     = [$start, $end];

    if ($departmentId !== null) {
        $outerWhere[] = "u.department_id = ?";
        $params[]     = $departmentId;
    }

    if ($search !== '') {
        $outerWhere[] = "(u.employee_id LIKE ? OR u.employee_name LIKE ? OR u.department_name LIKE ? OR u.role_name LIKE ?)";
        $w = '%' . $search . '%';
        array_push($params, $w, $w, $w, $w);
    }

    $whereSql = "WHERE " . implode(" AND ", $outerWhere);

    $allowedSortColumns = [
        'employee_id'     => 'u.employee_id',
        'employee_name'   => 'u.employee_name',
        'department_name' => 'u.department_name',
        'category'        => 'u.request_type',
        'request_name'    => 'u.request_name',
        'start_date'      => 'u.start_date',
        'end_date'        => 'u.end_date',
        'status'          => 'u.status',
        'date_filed'      => 'u.date_filed',
        'date_approved'   => 'u.date_approved',
        'approved_by'     => 'u.approved_by'
    ];

    $sortColumn = trim($_GET['sort_column'] ?? 'date_filed');
    $sortDirection = strtolower(trim($_GET['sort_direction'] ?? 'desc'));

    if (!array_key_exists($sortColumn, $allowedSortColumns)) {
        $sortColumn = 'date_filed';
    }

    if (!in_array($sortDirection, ['asc', 'desc'], true)) {
        $sortDirection = 'desc';
    }

    $orderCol = $allowedSortColumns[$sortColumn];
    $orderSql = "$orderCol " . strtoupper($sortDirection);

    if ($orderCol !== 'u.date_filed') {
        $orderSql .= ", u.date_filed DESC";
    }
    if ($orderCol !== 'u.employee_name') {
        $orderSql .= ", u.employee_name ASC";
    }

    try {
        $countSql  = "SELECT COUNT(*) FROM ($baseQuery) AS u $whereSql";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        $dataSql = "SELECT * FROM ($baseQuery) AS u $whereSql ORDER BY $orderSql";

        if (!$bypass) {
            $offset   = (int)(($page - 1) * $limit);
            $dataSql .= " LIMIT " . (int)$limit . " OFFSET " . $offset;
        }

        $stmtData = $pdo->prepare($dataSql);
        $i = 1;
        foreach ($params as $value) {
            $stmtData->bindValue($i++, $value, PDO::PARAM_STR);
        }
        $stmtData->execute();

        echo json_encode([
            'total' => $totalRecords,
            'data'  => $stmtData->fetchAll(PDO::FETCH_ASSOC),
        ]);
        exit();

    } catch (PDOException $e) {
        http_response_code(200);
        echo json_encode(['error' => 'Database structure error processing reports.', 'debug' => $e->getMessage()]);
        exit();
    }
}

/* ================================================
   LEAVE CONSUMED REPORT MODULE
   ================================================ */
if ($action === 'leave') {

    if (!$start || !$end) {
        echo json_encode(['error' => 'Missing date range parameters.']);
        exit();
    }

    $page   = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $bypass = isset($_GET['bypass_pagination']) && $_GET['bypass_pagination'] === '1';

    $userRole = $_SESSION['user_role'] ?? '';
    $userId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    $allTypes = $pdo->query("
        SELECT id, name, label, max_days FROM leave_types
        WHERE name != 'ob leave'
        ORDER BY
            CASE name WHEN 'vacation leave' THEN 1 WHEN 'sick leave' THEN 2 ELSE 3 END,
            sort_order, id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $caseClauses = [];
    foreach ($allTypes as $type) {
        $typeId    = (int)$type['id'];
        $colAlias  = preg_replace('/[^a-z0-9]+/', '_', strtolower($type['name']));
        $caseClauses[] = "COALESCE(SUM(CASE WHEN lr.leave_type_id = $typeId THEN JSON_LENGTH(lr.selected_dates) ELSE 0 END), 0) AS `{$colAlias}`";
    }
    $casesSql = $caseClauses ? implode(",\n                ", $caseClauses) : "0 AS no_types";

    $whereClauses = [];
    $baseParams   = [$start, $end];

    if (in_array($userRole, ['manager', 'workforce'])) {
        $stmtDept = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
        $stmtDept->execute([$userId]);
        $departmentId = $stmtDept->fetchColumn();
        if (!$departmentId) {
            echo json_encode(['total' => 0, 'leave_types' => $allTypes, 'data' => []]);
            exit();
        }
        $whereClauses[] = "e.department_id = ?";
        $baseParams[]   = $departmentId;
    }

    if ($search !== '') {
        $whereClauses[] = "(e.employee_id LIKE ? OR CONCAT(e.first_name,' ',e.last_name) LIKE ? OR d.department_name LIKE ? OR r.role_name LIKE ?)";
        $w = '%' . $search . '%';
        array_push($baseParams, $w, $w, $w, $w);
    }

    $whereSql = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";

    $leaveSortMap = [
        'employee_id' => 'employee_id',
        'name'        => 'employee_name',
        'department'  => 'department_name',
        'role'        => 'role_name',
        'buffer'      => 'balance_buffer_leave',
        'balance'     => 'balance_vacation_leave + balance_sick_leave'
    ];

    $sortColumn    = trim($_GET['sort_column'] ?? 'name');
    $sortDirection = strtolower(trim($_GET['sort_direction'] ?? 'asc'));

    if (!array_key_exists($sortColumn, $leaveSortMap)) {
        $sortColumn = 'name';
    }

    if (!in_array($sortDirection, ['asc', 'desc'], true)) {
        $sortDirection = 'asc';
    }

    $lrOrderCol = $leaveSortMap[$sortColumn];
    $lrOrderSql = "$lrOrderCol " . strtoupper($sortDirection);

    // FIXED fallback conditional context logic
    if ($sortColumn !== 'name') {
        $lrOrderSql .= ', employee_name ASC';
    }

    try {
        $baseQuery = "
            SELECT
                e.employee_id,
                CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                d.department_name,
                r.role_name,
                COALESCE(elb.buffer_leave,   0) AS balance_buffer_leave,
                COALESCE(elb.vacation_leave, 0) AS balance_vacation_leave,
                COALESCE(elb.sick_leave,     0) AS balance_sick_leave,
                $casesSql
            FROM employees e
            LEFT JOIN employee_leave_balances elb ON elb.employee_id = e.id
            LEFT JOIN leave_requests lr ON lr.employee_id = e.id AND lr.status = 'approved' AND lr.start_date BETWEEN ? AND ?
            LEFT JOIN roles       r ON r.id = e.role_id
            LEFT JOIN departments d ON d.id = e.department_id
            $whereSql
            GROUP BY e.id, e.employee_id, e.first_name, e.last_name, d.department_name, r.role_name,
                     elb.buffer_leave, elb.vacation_leave, elb.sick_leave
        ";

        $countSql  = "SELECT COUNT(*) FROM ($baseQuery) AS _c";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($baseParams);
        $totalRecords = (int)$stmtCount->fetchColumn();

        $dataSql = "SELECT * FROM ($baseQuery) AS _wrapper ORDER BY $lrOrderSql";
        if (!$bypass) {
            $offset   = ($page - 1) * $limit;
            $dataSql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        $stmtData = $pdo->prepare($dataSql);
        $stmtData->execute($baseParams);

        echo json_encode([
            'total'       => $totalRecords,
            'leave_types' => $allTypes,
            'data'        => $stmtData->fetchAll(PDO::FETCH_ASSOC),
        ]);
        exit();

    } catch (PDOException $e) {
        http_response_code(200);
        echo json_encode(['error' => 'Database failure computing leaves execution sequence.', 'debug' => $e->getMessage()]);
        exit();
    }
}

/* ================================================
   LEAVE BALANCE SUMMARY
   ================================================ */
if ($action === 'leave_summary') {

    $year   = isset($_GET['year'])   ? (int)$_GET['year']   : (int)date('Y');
    $page   = isset($_GET['page'])   ? (int)$_GET['page']   : 1;
    $limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 25;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $bypass = isset($_GET['bypass_pagination']) && $_GET['bypass_pagination'] === '1';

    $userRole = $_SESSION['user_role'] ?? '';
    $userId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    $whereClauses = [];
    $params       = [];

    if (in_array($userRole, ['manager', 'workforce'])) {
        $stmtDept = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
        $stmtDept->execute([$userId]);
        $departmentId = $stmtDept->fetchColumn();
        
        if (!$departmentId) {
            echo json_encode(['total' => 0, 'data' => []]);
            exit();
        }
        $whereClauses[] = "e.department_id = ?";
        $params[]       = $departmentId;
    }

    if ($search !== '') {
        $whereClauses[] = "(e.employee_id LIKE ? OR CONCAT(e.first_name, ' ', e.last_name) LIKE ? OR d.department_name LIKE ? OR r.role_name LIKE ?)";
        $w = '%' . $search . '%';
        array_push($params, $w, $w, $w, $w);
    }

    $whereSql = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";

    $summarySortMap = [
        'employee_id'    => 'main.employee_id',
        'name'           => 'main.employee_name',
        'department'     => 'main.department_name',
        'role'           => 'main.role_name',
        'entitled_vl'    => 'main.entitled_vacation_leave',
        'entitled_sl'    => 'main.entitled_sick_leave',
        'total_entitled' => '(main.entitled_vacation_leave + main.entitled_sick_leave)',
        'carry_over'     => 'main.carry_over_vacation',
        'vl_taken'       => 'main.vacation_leave_taken',
        'sl_taken'       => 'main.sick_leave_taken',
        'total_taken'    => '(main.vacation_leave_taken + main.sick_leave_taken)',
        'remaining_vl'   => 'remaining_vacation_leave',
        'remaining_sl'   => 'remaining_sick_leave'
    ];
    
    // Aligned to accept standard unified parameter keys
    $sortColumn    = trim($_GET['sort_column'] ?? 'name');
    $sortDirection = strtolower(trim($_GET['sort_direction'] ?? 'asc'));

    if (!array_key_exists($sortColumn, $summarySortMap)) {
        $sortColumn = 'name';
    }

    if (!in_array($sortDirection, ['asc', 'desc'], true)) {
        $sortDirection = 'asc';
    }

    $lsOrderCol = $summarySortMap[$sortColumn];
    $lsOrderSql = "$lsOrderCol " . strtoupper($sortDirection);
    
    if ($sortColumn !== 'name') {
        $lsOrderSql .= ', main.employee_name ASC';
    }

    try {
        $baseQuery = "
            SELECT 
                e.employee_id,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                d.department_name,
                r.role_name,
                COALESCE(elb.vacation_leave, 0) AS entitled_vacation_leave,
                COALESCE(elb.sick_leave, 0)     AS entitled_sick_leave,
                COALESCE(elb.buffer_leave, 0)   AS carry_over_vacation,
                (COALESCE(elb.vacation_leave, 0) + COALESCE(elb.sick_leave, 0)) AS total_entitled,
                
                COALESCE((
                    SELECT SUM(JSON_LENGTH(lr.selected_dates))
                    FROM leave_requests lr
                    JOIN leave_types lt ON lt.id = lr.leave_type_id
                    WHERE lr.employee_id = e.id 
                      AND lr.status = 'approved' 
                      AND lt.name = 'vacation leave'
                      AND YEAR(lr.start_date) = ?
                ), 0) AS vacation_leave_taken,

                COALESCE((
                    SELECT SUM(JSON_LENGTH(lr.selected_dates))
                    FROM leave_requests lr
                    JOIN leave_types lt ON lt.id = lr.leave_type_id
                    WHERE lr.employee_id = e.id 
                      AND lr.status = 'approved' 
                      AND lt.name = 'sick leave'
                      AND YEAR(lr.start_date) = ?
                ), 0) AS sick_leave_taken
            FROM employees e
            LEFT JOIN employee_leave_balances elb ON elb.employee_id = e.id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN roles r ON r.id = e.role_id
            $whereSql
        ";

        $countParams = array_merge([$year, $year], $params);

        $countSql  = "SELECT COUNT(*) FROM ($baseQuery) AS _summary_count";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($countParams);
        $totalRecords = (int)$stmtCount->fetchColumn();

        $dataSql = "
            SELECT 
                main.*,
                (main.vacation_leave_taken + main.sick_leave_taken) AS total_taken,
                (main.entitled_vacation_leave + main.carry_over_vacation - main.vacation_leave_taken) AS remaining_vacation_leave,
                (main.entitled_sick_leave - main.sick_leave_taken) AS remaining_sick_leave
            FROM ($baseQuery) AS main
            ORDER BY $lsOrderSql
        ";

        if (!$bypass) {
            $offset    = ($page - 1) * $limit;
            $dataSql  .= " LIMIT ? OFFSET ?";
            $dataParams = array_merge($countParams, [$limit, $offset]);
        } else {
            $dataParams = $countParams;
        }

        $stmtData = $pdo->prepare($dataSql);
        
        $idx = 1;
        foreach ($dataParams as $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmtData->bindValue($idx++, $value, $type);
        }
        
        $stmtData->execute();

        echo json_encode([
            'total' => $totalRecords,
            'data'  => $stmtData->fetchAll(PDO::FETCH_ASSOC)
        ]);
        exit();

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Database failure computing matching balance matrix data.',
            'debug' => $e->getMessage()
        ]);
        exit();
    }
}

/* ================================================
   FALLBACK
   ================================================ */
echo json_encode(['error' => 'Invalid action specification.']);
exit();