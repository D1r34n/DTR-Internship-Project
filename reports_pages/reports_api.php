<?php
/* ================================================
   REPORTS API ENDPOINT CONTROLLER
   ================================================ */

/* ------------------------------------------------
   Session Validation & Headers Setup
   ------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db.php';

header('Content-Type: application/json');

// Security Standard: Enforce strict session validation
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access. Please log back in.']);
    exit();
}

$action = $_GET['action'] ?? null;
$start  = $_GET['start'] ?? null;
$end    = $_GET['end'] ?? null;

if (!$action) {
    echo json_encode(['error' => 'Missing action parameter.']);
    exit();
}

/* ================================================
   ATTENDANCE REPORT MODULE PROCESSING
   ================================================ */
if ($action === 'attendance') {

    if (!$start || !$end) {
        echo json_encode(['error' => 'Missing date range parameters.']);
        exit();
    }

    // Capture Server Pagination & Filtering state
    $page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $status = $_GET['status'] ?? 'ALL';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $bypass = isset($_GET['bypass_pagination']) && $_GET['bypass_pagination'] === '1';

    $userRole = $_SESSION['user_role'];
    $userId   = (int)$_SESSION['user_id'];

    // 1. Structural WHERE conditions array
    $whereClauses = ["a.work_date BETWEEN ? AND ?"];
    $params = [$start, $end];

    /* Role Restrictions: Isolate department ID natively if restricted */
    if (in_array($userRole, ['manager', 'workforce'])) {
        $stmtDept = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
        $stmtDept->execute([$userId]);
        $departmentId = $stmtDept->fetchColumn();

        if ($departmentId) {
            $whereClauses[] = "e.department_id = ?";
            $params[] = $departmentId;
        } else {
            // Safe fallback structure matching front-end interface data expectations
            echo json_encode(['total' => 0, 'data' => []]);
            exit();
        }
    }

    /* Database Status Filter Processing */
    if ($status !== 'ALL') {
        $whereClauses[] = "a.status = ?";
        $params[] = $status;
    }

    /* Database Search Query Normalization */
    if ($search !== '') {
        $whereClauses[] = "(e.employee_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR d.department_name LIKE ? OR r.role_name LIKE ?)";
        $searchWildcard = '%' . $search . '%';
        
        // Push standardized string parameters to matching array tokens
        array_push($params, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard);
    }

    $whereSql = "WHERE " . implode(" AND ", $whereClauses);

    try {
        // 2. Query A: Extract contextual records count
        $countSql = "
            SELECT COUNT(*) 
            FROM attendances a
            LEFT JOIN employees e ON e.id = a.employee_id
            LEFT JOIN roles r ON r.id = e.role_id
            LEFT JOIN departments d ON d.id = e.department_id
            $whereSql
        ";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        // 3. Query B: Build secure operational records list
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
            LEFT JOIN employees e ON e.id = a.employee_id
            LEFT JOIN roles r ON r.id = e.role_id
            LEFT JOIN departments d ON d.id = e.department_id
            $whereSql
            ORDER BY a.work_date DESC, employee_name ASC
        ";

        // Inject statement limits dynamically if data bypass is inactive
        if (!$bypass) {
            $offset = ($page - 1) * $limit;
            $dataSql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        $stmtData = $pdo->prepare($dataSql);
        
        // Execute dynamically typed query bindings
        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmtData->bindValue($key + 1, $value, $paramType);
        }
        
        $stmtData->execute();
        $dataRows = $stmtData->fetchAll();

        // Standard Payload Structure Output
        echo json_encode([
            'total' => $totalRecords,
            'data'  => $dataRows
        ]);
        exit();

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database failure processing payload values.']);
        exit();
    }
}

/* ================================================
   LEAVES MODULE CONTROLLER (DYNAMIC ROUTING FIXED)
   ================================================ */
if ($action === 'reports') {

    $start = isset($_GET['start']) ? trim($_GET['start']) : null;
    $end   = isset($_GET['end']) ? trim($_GET['end']) : null;

    if (!$start || !$end) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing date range parameters.']);
        exit();
    }

    $page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'ALL';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $bypass = isset($_GET['bypass_pagination']) && $_GET['bypass_pagination'] === '1';

    $userRole = $_SESSION['user_role'] ?? '';
    $userId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    // Resolve Boundaries Upfront
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

    /* ── PHASE 1: CHOOSE TARGET TABLE QUERIES DYNAMICALLY ── */
    
    // Core definition blocks mapped out as individual array segments
    $queries = [];
    $leaveTypeNames   = $pdo->query("SELECT name FROM leave_types")->fetchAll(PDO::FETCH_COLUMN);
    $normalizedStatus = strtolower($status);

    // Block A: Leave Requests Query Template (JOIN leave_types for the name filter)
    $leaveQuery = "SELECT 'leave' AS request_type, lr.id AS source_id, e.employee_id, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, d.department_name, r.role_name, DATE(lr.created_at) AS request_date, lr.status AS status, e.department_id FROM leave_requests lr JOIN leave_types lt ON lt.id = lr.leave_type_id LEFT JOIN employees e ON e.id = lr.employee_id LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN roles r ON r.id = e.role_id WHERE lr.status IS NOT NULL AND lr.status != ''";

    // Block B: Overtime Requests Query Template
    $otQuery = "SELECT 'overtime' AS request_type, ot.id AS source_id, e.employee_id, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, d.department_name, r.role_name, DATE(ot.created_at) AS request_date, ot.status AS status, e.department_id FROM overtime_requests ot LEFT JOIN employees e ON e.id = ot.employee_id LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN roles r ON r.id = e.role_id WHERE ot.status IS NOT NULL AND ot.status != ''";

    // Block C: Log Edits Query Template
    $logQuery = "SELECT 'log_edit' AS request_type, l.id AS source_id, e.employee_id, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, d.department_name, r.role_name, DATE(l.created_at) AS request_date, l.edit_status AS status, e.department_id FROM logs l LEFT JOIN employees e ON e.id = l.employee_id LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN roles r ON r.id = e.role_id WHERE l.edit_status IS NOT NULL AND l.edit_status != ''";

    // Block D: Schedule Edits Query Template
    $schedQuery = "SELECT 'schedule_edit' AS request_type, s.id AS source_id, e.employee_id, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, d.department_name, r.role_name, DATE(s.updated_at) AS request_date, s.status AS status, e.department_id FROM schedules s LEFT JOIN employees e ON e.id = s.employee_id LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN roles r ON r.id = e.role_id WHERE s.status IS NOT NULL AND s.status != ''";

    
    /* ── PHASE 2: ROUTING LOGIC ── */
    
    if ($normalizedStatus === 'all') {
        // Fetch from absolutely everywhere if 'ALL' is chosen
        $queries[] = $leaveQuery;
        $queries[] = $otQuery;
        $queries[] = $logQuery;
        $queries[] = $schedQuery;
    } 
    elseif ($normalizedStatus === 'log_edit') {
        $queries[] = $logQuery;
    } 
    elseif ($normalizedStatus === 'overtime' || $normalizedStatus === 'ot request') {
        $queries[] = $otQuery;
    } 
    elseif ($normalizedStatus === 'schedule_edit') {
        $queries[] = $schedQuery;
    } 
    elseif (in_array($normalizedStatus, $leaveTypeNames)) {
        $queries[] = $leaveQuery . " AND lt.name = " . $pdo->quote($normalizedStatus);
    } 
    else {
        // Safe fallback in case no routes match
        $queries[] = $leaveQuery;
    }

    // Combine whatever routes were targeted into the base string
    $baseQuery = implode(" UNION ALL ", $queries);


    /* ── PHASE 3: OUTER FILTER BUILDER ── */
    
    $outerWhere = ["u.request_date BETWEEN ? AND ?"];
    $params = [$start, $end];

    if ($departmentId !== null) {
        $outerWhere[] = "u.department_id = ?";
        $params[] = $departmentId;
    }

    if ($search !== '') {
        $outerWhere[] = "(u.employee_id LIKE ? OR u.employee_name LIKE ? OR u.department_name LIKE ? OR u.role_name LIKE ?)";
        $wildcard = '%' . $search . '%';
        array_push($params, $wildcard, $wildcard, $wildcard, $wildcard);
    }

    $whereSql = "WHERE " . implode(" AND ", $outerWhere);

    try {
        /* ── COUNT RUN ──────────────────────────────── */
        $countSql = "SELECT COUNT(*) FROM ($baseQuery) AS u $whereSql";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        /* ── DATA RUN ───────────────────────────────── */
        $dataSql = "SELECT * FROM ($baseQuery) AS u $whereSql ORDER BY u.request_date DESC";

        if (!$bypass) {
            $offset = (int)(($page - 1) * $limit);
            $limit  = (int)$limit;
            $dataSql .= " LIMIT " . $limit . " OFFSET " . $offset;
        }

        $stmtData = $pdo->prepare($dataSql);
        $bindIndex = 1;

        foreach ($params as $value) {
            $stmtData->bindValue($bindIndex++, $value, PDO::PARAM_STR);
        }

        $stmtData->execute();
        $dataRows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'total' => $totalRecords,
            'data'  => $dataRows
        ]);
        exit();

    } catch (PDOException $e) {
        http_response_code(200); 
        echo json_encode([
            'error' => 'Database structure error processing reports.',
            'debug' => $e->getMessage()
        ]);
        exit();
    }
}

/* ================================================
   LEAVE CONSUMED REPORT MODULE
   Shows approved leave days used per employee in a date range.
   ================================================ */
if ($action === 'leave') {

    if (!$start || !$end) {
        echo json_encode(['error' => 'Missing date range parameters.']);
        exit();
    }

    $page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $bypass = isset($_GET['bypass_pagination']) && $_GET['bypass_pagination'] === '1';

    $userRole = $_SESSION['user_role'] ?? '';
    $userId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    // ── 1. Collect leave types from leave_types table (FK guarantees all types are here) ──
    $allTypes = $pdo->query("
        SELECT id, name, label FROM leave_types ORDER BY sort_order, id
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── 2. Build dynamic CASE clauses ──
    $caseClauses = [];
    foreach ($allTypes as $type) {
        $typeId   = (int)$type['id'];
        $colAlias = preg_replace('/[^a-z0-9]+/', '_', strtolower($type['name']));
        $caseClauses[] = "COALESCE(SUM(CASE WHEN lr.leave_type_id = $typeId THEN JSON_LENGTH(lr.selected_dates) ELSE 0 END), 0) AS `{$colAlias}`";
    }
    $casesSql = $caseClauses ? implode(",\n                ", $caseClauses) : "0 AS no_types";

    // ── 3. Outer WHERE (employees table filters) ──
    $whereClauses = [];
    $params       = [$start, $end]; // used in the lr subquery

    if (in_array($userRole, ['manager', 'workforce'])) {
        $stmtDept = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
        $stmtDept->execute([$userId]);
        $departmentId = $stmtDept->fetchColumn();
        if (!$departmentId) {
            echo json_encode(['total' => 0, 'leave_types' => $allTypes, 'data' => []]);
            exit();
        }
        $whereClauses[] = "e.department_id = ?";
        $params[]       = $departmentId;
    }

    if ($search !== '') {
        $whereClauses[] = "(e.employee_id LIKE ? OR CONCAT(e.first_name,' ',e.last_name) LIKE ? OR d.department_name LIKE ? OR r.role_name LIKE ?)";
        $w = '%' . $search . '%';
        array_push($params, $w, $w, $w, $w);
    }

    $whereSql = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";

    try {
        $baseQuery = "
            SELECT
                e.employee_id,
                CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                d.department_name,
                r.role_name,
                $casesSql
            FROM employees e
            LEFT JOIN (
                SELECT lr2.employee_id, lr2.leave_type_id, lr2.selected_dates
                FROM leave_requests lr2
                WHERE lr2.status = 'approved' AND lr2.start_date BETWEEN ? AND ?
            ) lr ON lr.employee_id = e.id
            LEFT JOIN roles r ON r.id = e.role_id
            LEFT JOIN departments d ON d.id = e.department_id
            $whereSql
            GROUP BY e.id, e.employee_id, e.first_name, e.last_name, d.department_name, r.role_name
        ";

        $countSql  = "SELECT COUNT(*) FROM ($baseQuery) AS _c";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        $dataSql = "$baseQuery ORDER BY employee_name ASC";
        $dataParams = $params;
        if (!$bypass) {
            $offset   = ($page - 1) * $limit;
            $dataSql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        $stmtData = $pdo->prepare($dataSql);
        $stmtData->execute($dataParams);
        $dataRows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'total'       => $totalRecords,
            'leave_types' => $allTypes,
            'data'        => $dataRows
        ]);
        exit();

    } catch (PDOException $e) {
        http_response_code(200);
        echo json_encode(['error' => 'Database error.', 'debug' => $e->getMessage()]);
        exit();
    }
}

/* ================================================
   FALLBACK DEFAULT HANDLER
   ================================================ */
echo json_encode([
    'error' => 'Invalid action specification.'
]);
exit();