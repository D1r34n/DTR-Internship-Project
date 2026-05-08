<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId = intval($_GET['employee_id'] ?? 0);
if (!$employeeId) { exit(); }

$startDate = !empty($_GET['start']) ? date('Y-m-d', strtotime($_GET['start'])) : '';
$endDate   = !empty($_GET['end'])   ? date('Y-m-d', strtotime($_GET['end']))   : '';
$type      = $_GET['type'] ?? 'ALL';

$allowedSort = [
    'date'     => 'DATE(l.log_time)',
    'time'     => 'TIME(l.log_time)',
    'type'     => 'l.log_type',
    'location' => 'l.is_within_office',
];
$sort    = $_GET['sort'] ?? 'date';
$dir     = strtolower($_GET['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
$orderBy = isset($allowedSort[$sort])
    ? "{$allowedSort[$sort]} $dir, l.log_time DESC"
    : "l.log_time DESC";

$sql    = "SELECT l.id AS log_id, l.log_time, l.log_type, l.latitude, l.longitude, l.accuracy, l.is_within_office, l.distance_meters FROM logs l WHERE l.employee_id = ?";
$params = [$employeeId];

if ($startDate) { $sql .= " AND l.log_time >= ?";                              $params[] = $startDate; }
if ($endDate)   { $sql .= " AND l.log_time < DATE_ADD(?, INTERVAL 1 DAY)";    $params[] = $endDate;   }
if ($type !== 'ALL' && in_array($type, ['IN', 'OUT', 'BREAK_IN', 'BREAK_OUT'])) {
    $sql .= " AND l.log_type = ?";
    $params[] = $type;
}
$sql .= " ORDER BY $orderBy LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Edit request map
$editMap = [];
$erStmt  = $pdo->prepare("
    SELECT ler.log_id, ler.status, ler.initiated_by_id,
           r_init.role_key AS initiator_role, CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name
    FROM log_edit_requests ler
    LEFT JOIN employees e_init ON ler.initiated_by_id = e_init.id
    LEFT JOIN roles r_init ON r_init.id = e_init.role_id
    WHERE ler.employee_id = ?
    ORDER BY ler.created_at DESC
");
$erStmt->execute([$employeeId]);
foreach ($erStmt->fetchAll(PDO::FETCH_ASSOC) as $er) {
    if ($er['log_id'] && !isset($editMap[$er['log_id']])) {
        $editMap[$er['log_id']] = [
            'status'         => $er['status'],
            'initiated_by_id'=> $er['initiated_by_id'],
            'initiator_role' => $er['initiator_role'],
            'initiator_name' => $er['initiator_name'],
        ];
    }
}

if (!$records) {
     echo "<tr class='emptyRow'><td colspan='7'><div class='logsEmpty'><i class='bi bi-calendar-x logsEmptyIcon'></i><div>No logs found for this period.</div></div></td></tr>";
   exit();
}

foreach ($records as $row):
    $editEntry     = $editMap[$row['log_id']] ?? null;
    $editStatus    = $editEntry['status']          ?? null;
    $initiatedById = $editEntry['initiated_by_id'] ?? null;
    $initiatorRole = $editEntry['initiator_role']  ?? null;
    $initiatorName = $editEntry['initiator_name']  ?? null;

    $isInside = $row['is_within_office'];
    $label    = $isInside ? 'Within Office' : 'Outside Office';
    $lat      = $row['latitude']        ?? 0;
    $lng      = $row['longitude']       ?? 0;
    $acc      = isset($row['accuracy'])        ? round($row['accuracy'], 1)        : 'N/A';
    $dist     = isset($row['distance_meters']) ? round($row['distance_meters'], 1) : 'N/A';
    $mapUrl   = "https://www.google.com/maps?q={$lat},{$lng}";
?>
<tr>
    <td><?= date('F d, Y', strtotime($row['log_time'])) ?></td>
    <td><?= date('h:i A', strtotime($row['log_time'])) ?></td>
    <td>
        <span class="pill <?= match($row['log_type']) {
            // Should be your status tokens
            'IN' => 'status-approved',
            'OUT' => 'status-rejected',
            'BREAK_IN' => 'status-pending',
            'BREAK_OUT' => 'status-info',
            default     => ''
        } ?>">
            <?= match($row['log_type']) {
                'IN'        => 'Time In',
                'OUT'       => 'Time Out',
                'BREAK_IN'  => 'Break In',
                'BREAK_OUT' => 'Break Out',
                default     => htmlspecialchars($row['log_type'])
            } ?>
        </span>
    </td>
    <td>
        <a href="<?= $mapUrl ?>" target="_blank"
            class="pill <?= $isInside ? 'btn-success' : 'btn-danger' ?> loc-trigger"
            style="text-decoration:none;"
            data-lat="<?= htmlspecialchars($lat, ENT_QUOTES) ?>"
            data-lng="<?= htmlspecialchars($lng, ENT_QUOTES) ?>"
            data-label="<?= htmlspecialchars($label, ENT_QUOTES) ?>"
            data-acc="<?= htmlspecialchars($acc, ENT_QUOTES) ?>"
            data-dist="<?= htmlspecialchars($dist, ENT_QUOTES) ?>">
            <i class="bi bi-geo-alt-fill"></i>
            <?= $label ?>
        </a>
    </td>
    <td>
        <?php if ($initiatorRole === 'workforce'): ?>
            <span class="pill empRole-workforce"><i class="bi bi-person-badge-fill"></i> <?= htmlspecialchars($initiatorName ?? 'Workforce') ?></span>
        <?php elseif ($initiatorRole === 'admin'): ?>
            <span class="pill empRole-admin"><i class="bi bi-shield-fill"></i> <?= htmlspecialchars($initiatorName ?? 'Admin') ?></span>
        <?php elseif ($initiatorRole === 'employee'): ?>
            <span class="pill"><i class="bi bi-person-fill"></i> <?= htmlspecialchars($initiatorName ?? 'Employee') ?></span>
        <?php else: ?>
            <span style="color:rgba(255,255,255,0.15);font-size:0.75rem;">—</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($editStatus === 'pending'): ?>
            <span class="pill btn-info"><i class="bi bi-hourglass-split"></i> Pending</span>
        <?php elseif ($editStatus === 'approved'): ?>
            <span class="pill btn-success"><i class="bi bi-check-circle-fill"></i> Approved</span>
        <?php elseif ($editStatus === 'rejected'): ?>
            <span class="pill btn-danger"><i class="bi bi-x-circle-fill"></i> Rejected</span>
        <?php else: ?>
            <span style="color:rgba(255,255,255,0.2);font-size:0.75rem;">—</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>