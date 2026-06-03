<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['superadmin', 'manager', 'workforce'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../db.php';
header('Content-Type: application/json');

$myRole     = $_SESSION['user_role'];
$myDeptId   = (int)($_SESSION['department_id'] ?? 0);
$deptScoped = in_array($myRole, ['manager', 'workforce']) && $myDeptId;

$type   = $_GET['type']   ?? 'all';
$page   = max(1, (int)($_GET['page']   ?? 1));
$limit  = max(1, min(100, (int)($_GET['limit']  ?? 25)));
$search = trim($_GET['search'] ?? '');
$offset = ($page - 1) * $limit;
$sp     = $search !== '' ? "%{$search}%" : null;

$dw = $deptScoped  ? 'AND e.department_id = ?' : '';
$sw = $sp !== null ? "AND CONCAT(e.first_name,' ',e.last_name) LIKE ?" : '';

function qfetch(PDO $pdo, string $sql, array $p): array {
    $s = $pdo->prepare($sql);
    foreach ($p as $i => $v)
        $s->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $s->execute();
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function qscalar(PDO $pdo, string $sql, array $p): int {
    $s = $pdo->prepare($sql);
    foreach ($p as $i => $v)
        $s->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $s->execute();
    return (int)$s->fetchColumn();
}
function bp(bool $ds, int $did, ?string $sp): array {
    $p = [];
    if ($ds)         $p[] = $did;
    if ($sp !== null) $p[] = $sp;
    return $p;
}

$base  = bp($deptScoped, $myDeptId, $sp);
$total = 0;
$data  = [];

try {
switch ($type) {

    case 'leave':
        $total = qscalar($pdo,
            "SELECT COUNT(*) FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             JOIN employees e ON lr.employee_id = e.id
             WHERE lt.name != 'ob leave' $dw $sw",
            $base);
        $data = qfetch($pdo,
            "SELECT lr.id, lr.status, lr.reason, lr.start_date, lr.end_date,
                    lt.label AS leave_type,
                    CONCAT(e.first_name,' ',e.last_name) AS employee_name
             FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             JOIN employees e ON lr.employee_id = e.id
             WHERE lt.name != 'ob leave' $dw $sw
             ORDER BY lr.created_at DESC LIMIT ? OFFSET ?",
            array_merge($base, [$limit, $offset]));
        foreach ($data as &$r) $r['req_type'] = 'leave';
        break;

    case 'overtime':
        $total = qscalar($pdo,
            "SELECT COUNT(*) FROM overtime_requests or2
             JOIN employees e ON or2.employee_id = e.id
             WHERE 1=1 $dw $sw",
            $base);
        $data = qfetch($pdo,
            "SELECT or2.id, or2.status, or2.reason, or2.date, or2.time_in, or2.time_out,
                    CONCAT(e.first_name,' ',e.last_name) AS employee_name
             FROM overtime_requests or2
             JOIN employees e ON or2.employee_id = e.id
             WHERE 1=1 $dw $sw
             ORDER BY or2.created_at DESC LIMIT ? OFFSET ?",
            array_merge($base, [$limit, $offset]));
        foreach ($data as &$r) $r['req_type'] = 'overtime';
        break;

    case 'ob':
        $total = qscalar($pdo,
            "SELECT COUNT(*) FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             JOIN employees e ON lr.employee_id = e.id
             WHERE lt.name = 'ob leave' $dw $sw",
            $base);
        $data = qfetch($pdo,
            "SELECT lr.id, lr.status, lr.reason, lr.start_date, lr.client_name,
                    CONCAT(e.first_name,' ',e.last_name) AS employee_name
             FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             JOIN employees e ON lr.employee_id = e.id
             WHERE lt.name = 'ob leave' $dw $sw
             ORDER BY lr.created_at DESC LIMIT ? OFFSET ?",
            array_merge($base, [$limit, $offset]));
        foreach ($data as &$r) $r['req_type'] = 'ob';
        break;

    case 'log_edit':
        $total = qscalar($pdo,
            "SELECT COUNT(*) FROM log_edit_requests ler
             JOIN logs lg ON ler.log_id = lg.id
             JOIN employees e ON ler.employee_id = e.id
             WHERE 1=1 $dw $sw",
            $base);
        $data = qfetch($pdo,
            "SELECT ler.id, ler.status, ler.reason,
                    ler.original_log_time, ler.proposed_log_time,
                    ler.requested_by AS requested_by_id,
                    lg.log_type, DATE(lg.log_time) AS work_date,
                    CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    CONCAT(r.first_name,' ',r.last_name) AS requested_by_name,
                    rr.role_key AS requested_by_role
             FROM log_edit_requests ler
             JOIN logs lg ON ler.log_id = lg.id
             JOIN employees e ON ler.employee_id = e.id
             LEFT JOIN employees r ON ler.requested_by = r.id
             LEFT JOIN roles rr ON r.role_id = rr.id
             WHERE 1=1 $dw $sw
             ORDER BY ler.created_at DESC LIMIT ? OFFSET ?",
            array_merge($base, [$limit, $offset]));
        foreach ($data as &$r) $r['req_type'] = 'log_edit';
        break;

    case 'all':
    default:
        $union = "
            SELECT lr.id, 'leave' AS req_type, lr.status, lr.reason, lr.created_at,
                   lt.label AS leave_type, lr.start_date, lr.end_date,
                   NULL AS date, NULL AS time_in, NULL AS time_out, NULL AS client_name,
                   NULL AS log_type, NULL AS original_log_time, NULL AS proposed_log_time,
                   NULL AS work_date, NULL AS requested_by_id,
                   NULL AS requested_by_name, NULL AS requested_by_role,
                   CONCAT(e.first_name,' ',e.last_name) AS employee_name
            FROM leave_requests lr
            JOIN leave_types lt ON lt.id = lr.leave_type_id
            JOIN employees e ON lr.employee_id = e.id
            WHERE lt.name != 'ob leave' $dw $sw

            UNION ALL

            SELECT or2.id, 'overtime' AS req_type, or2.status, or2.reason, or2.created_at,
                   NULL, NULL, NULL,
                   or2.date, or2.time_in, or2.time_out, NULL,
                   NULL, NULL, NULL, NULL, NULL, NULL, NULL,
                   CONCAT(e.first_name,' ',e.last_name)
            FROM overtime_requests or2
            JOIN employees e ON or2.employee_id = e.id
            WHERE 1=1 $dw $sw

            UNION ALL

            SELECT lr2.id, 'ob' AS req_type, lr2.status, lr2.reason, lr2.created_at,
                   NULL, lr2.start_date, NULL,
                   NULL, NULL, NULL, lr2.client_name,
                   NULL, NULL, NULL, NULL, NULL, NULL, NULL,
                   CONCAT(e.first_name,' ',e.last_name)
            FROM leave_requests lr2
            JOIN leave_types lt2 ON lt2.id = lr2.leave_type_id
            JOIN employees e ON lr2.employee_id = e.id
            WHERE lt2.name = 'ob leave' $dw $sw

            UNION ALL

            SELECT ler.id, 'log_edit' AS req_type, ler.status, ler.reason, ler.created_at,
                   NULL, NULL, NULL,
                   NULL, NULL, NULL, NULL,
                   lg.log_type, ler.original_log_time, ler.proposed_log_time,
                   DATE(lg.log_time),
                   ler.requested_by,
                   CONCAT(r.first_name,' ',r.last_name),
                   rr.role_key,
                   CONCAT(e.first_name,' ',e.last_name)
            FROM log_edit_requests ler
            JOIN logs lg ON ler.log_id = lg.id
            JOIN employees e ON ler.employee_id = e.id
            LEFT JOIN employees r ON ler.requested_by = r.id
            LEFT JOIN roles rr ON r.role_id = rr.id
            WHERE 1=1 $dw $sw
        ";

        $allBase = array_merge($base, $base, $base, $base);
        $total   = qscalar($pdo, "SELECT COUNT(*) FROM ($union) AS u", $allBase);
        $data    = qfetch($pdo,
            "SELECT * FROM ($union) AS u ORDER BY created_at DESC LIMIT ? OFFSET ?",
            array_merge($allBase, [$limit, $offset]));
        break;
}

echo json_encode(['data' => $data, 'total' => $total]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
