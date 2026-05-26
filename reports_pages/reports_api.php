<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? null;
$start  = $_GET['start'] ?? null;
$end    = $_GET['end'] ?? null;

if (!$action) {
    echo json_encode(['error' => 'Missing action']);
    exit;
}

/* ─────────────────────────────────────────────
    ATTENDANCE REPORT
───────────────────────────────────────────── */
if ($action === 'attendance') {

    if (!$start || !$end) {
        echo json_encode(['error' => 'Missing date range parameters']);
        exit;
    }

    // Get Server Pagination & Filter parameters passed from Fetch API
    $page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $status = $_GET['status'] ?? 'ALL';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $bypass = isset($_GET['bypass_pagination']) && $_GET['bypass_pagination'] === '1';

    $userRole = $_SESSION['user_role'];
    $userId   = (int)$_SESSION['user_id'];

    // 1. Core WHERE conditions base array
    $whereClauses = ["a.work_date BETWEEN ? AND ?"];
    $params = [$start, $end];

    /* Role Restrictions: isolate department ID natively if restricted */
    if (in_array($userRole, ['manager', 'workforce'])) {
        $stmt = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
        $stmt->execute([$userId]);
        $dept = $stmt->fetchColumn();

        if ($dept) {
            $whereClauses[] = "e.department_id = ?";
            $params[] = $dept;
        } else {
            // Return clean empty structure matching new pagination API expectation
            echo json_encode(['total' => 0, 'data' => []]);
            exit;
        }
    }

    /* Database Status Filter Engine */
    if ($status !== 'ALL') {
        $whereClauses[] = "a.status = ?";
        $params[] = $status;
    }

    /* Database Search Engine */
    if ($search !== '') {
        $whereClauses[] = "(e.employee_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR d.department_name LIKE ? OR r.role_name LIKE ?)";
        $searchWildcard = '%' . $search . '%';
        // Apply the wildcard token across all searchable query text matches
        array_push($params, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard);
    }

    $whereSql = "WHERE " . implode(" AND ", $whereClauses);

    try {
        // 2. Query A: Get Count for Total Matches (Essential metadata for front-end interface)
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

        // 3. Query B: Grab Data Rows safely
        $dataSql = "
            SELECT
                e.employee_id,
                CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                d.department_name,
                r.role_name,
                a.work_date,
                a.actual_time_in,
                a.actual_time_out,
                a.status
            FROM attendances a
            LEFT JOIN employees e ON e.id = a.employee_id
            LEFT JOIN roles r ON r.id = e.role_id
            LEFT JOIN departments d ON d.id = e.department_id
            $whereSql
            ORDER BY a.work_date DESC, employee_name ASC
        ";

        // Append SQL limits unless the request explicitly flags a complete report export file bypass
        if (!$bypass) {
            $offset = ($page - 1) * $limit;
            $dataSql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        $stmtData = $pdo->prepare($dataSql);
        
        // Execute dynamic typed assignments matching traditional binding elements safely
        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmtData->bindValue($key + 1, $value, $paramType);
        }
        
        $stmtData->execute();
        $data = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        // Send payload structure back configured precisely to match our updated JavaScript
        echo json_encode([
            'total' => $totalRecords,
            'data'  => $data
        ]);
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database failure processing payload values.']);
        exit;
    }
}

/* ─────────────────────────────────────────────
    LEAVES (FUTURE MODULE)
───────────────────────────────────────────── */
if ($action === 'leaves') {
    echo json_encode([
        'message' => 'Leaves module not implemented yet'
    ]);
    exit;
}

/* ─────────────────────────────────────────────
    DEFAULT
───────────────────────────────────────────── */
echo json_encode([
    'error' => 'Invalid action'
]);