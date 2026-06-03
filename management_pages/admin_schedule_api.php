<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['superadmin', 'admin', 'manager', 'workforce'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../db.php';
header('Content-Type: application/json');

$myRole     = $_SESSION['user_role'];
$myDeptId   = (int)($_SESSION['department_id'] ?? 0);
$deptScoped = in_array($myRole, ['manager', 'workforce']) && $myDeptId;

$page      = max(1, (int)($_GET['page']      ?? 1));
$limit     = max(1, min(100, (int)($_GET['limit']     ?? 25)));
$search    = trim($_GET['search']    ?? '');
$status    = $_GET['status']    ?? 'ALL';
$dateFrom  = $_GET['date_from'] ?? null;
$dateTo    = $_GET['date_to']   ?? null;
$offset    = ($page - 1) * $limit;
$sp        = $search !== '' ? "%{$search}%" : null;

$deptClause   = $deptScoped  ? 'AND e.department_id = ?'                             : '';
$searchClause = $sp !== null ? "AND CONCAT(e.first_name,' ',e.last_name) LIKE ?"     : '';

$innerWhere = "WHERE (s.is_rest_day = 0 OR s.pending_delete = 1 OR COALESCE(s.is_archived,0) = 1)
               $deptClause $searchClause";

$innerParams = [];
if ($deptScoped)  $innerParams[] = $myDeptId;
if ($sp !== null) $innerParams[] = $sp;

$havingParts  = [];
$havingParams = [];

if ($dateFrom && $dateTo) {
    $havingParts[]  = "(g.pending_delete = 1 OR (g.min_date <= ? AND g.max_date >= ?))";
    $havingParams[] = $dateTo;
    $havingParams[] = $dateFrom;
}
if ($status !== 'ALL') {
    $havingParts[]  = "effective_status = ?";
    $havingParams[] = $status;
}

$innerSql = "
    SELECT
        COALESCE(s.batch_id, CONCAT('solo_', s.id))         AS group_key,
        MIN(s.batch_id)                                      AS batch_id,
        MIN(s.id)                                            AS id,
        MIN(s.employee_id)                                   AS employee_id,
        MIN(CONCAT(e.first_name,' ',e.last_name))            AS employee_name,
        MIN(d.department_code)                               AS department_code,
        GROUP_CONCAT(s.schedule_date ORDER BY s.schedule_date SEPARATOR ',') AS all_dates,
        MIN(s.schedule_date)                                 AS min_date,
        MAX(s.schedule_date)                                 AS max_date,
        MIN(s.scheduled_start)                               AS scheduled_start,
        MIN(s.scheduled_end)                                 AS scheduled_end,
        MIN(s.is_rest_day)                                   AS is_rest_day,
        COALESCE(MIN(ser.status), 'approved')                AS status,
        MAX(s.pending_delete)                                AS pending_delete,
        MAX(COALESCE(s.is_archived,0))                       AS is_archived,
        MIN(s.request_type)                                  AS request_type,
        COALESCE(MIN(ser.requested_by), MIN(s.employee_id)) AS requested_by,
        MAX(s.updated_at)                                    AS updated_at,
        COUNT(*)                                             AS date_count,
        CASE
            WHEN MAX(COALESCE(s.is_archived,0)) = 1 THEN 'deleted'
            ELSE COALESCE(MIN(ser.status), 'approved')
        END AS effective_status
    FROM schedules s
    JOIN employees e ON s.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN schedule_edit_requests ser ON ser.batch_id = s.batch_id
    $innerWhere
    GROUP BY COALESCE(s.batch_id, CONCAT('solo_', s.id))
";

$havingClause = $havingParts ? 'HAVING ' . implode(' AND ', $havingParts) : '';

$outerSql = "
    SELECT g.*,
           CONCAT(r.first_name,' ',r.last_name) AS requested_by_name,
           rr.role_key AS requested_by_role
    FROM ($innerSql) g
    LEFT JOIN employees r  ON g.requested_by = r.id
    LEFT JOIN roles     rr ON r.role_id = rr.id
    $havingClause
    ORDER BY g.pending_delete DESC,
             CASE WHEN g.status = 'pending' THEN 0 ELSE 1 END ASC,
             g.updated_at DESC, g.id DESC
";

$allParams = array_merge($innerParams, $havingParams);

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ($outerSql) AS c");
    $countStmt->execute($allParams);
    $total = (int)$countStmt->fetchColumn();

    $dataStmt = $pdo->prepare("$outerSql LIMIT {$limit} OFFSET {$offset}");
    $dataStmt->execute($allParams);
    $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['data' => $data, 'total' => $total]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
