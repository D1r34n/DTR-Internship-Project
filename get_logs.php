<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

$employeeId = $_SESSION['user_id'];
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
        id AS log_id,
        log_time,
        log_type,
        latitude,
        longitude,
        accuracy,
        is_within_office,
        distance_meters
    FROM logs
    WHERE employee_id = ?
";

$params = [$employeeId];

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
$editSql    = "
    SELECT ler.log_id, ler.status,
           ler.initiated_by_id, e_init.role AS initiator_role, e_init.name AS initiator_name
    FROM log_edit_requests ler
    LEFT JOIN employees e_init ON ler.initiated_by_id = e_init.id
    WHERE ler.employee_id = ? AND ler.log_id IS NOT NULL
";
$editParams = [$employeeId];

if ($startDate !== '') {
    $editSql    .= " AND ler.log_id IN (SELECT id FROM logs WHERE employee_id = ? AND log_time >= ?)";
    $editParams[] = $employeeId;
    $editParams[] = $startDate;
}
if ($endDate !== '') {
    $editSql    .= " AND ler.log_id IN (SELECT id FROM logs WHERE employee_id = ? AND log_time < DATE_ADD(?, INTERVAL 1 DAY))";
    $editParams[] = $employeeId;
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
   EMPTY STATE
========================= */
if (!$records) {
    echo '
    <tr class="emptyRow">
        <td colspan="6">
            <div class="logsEmpty">
                <i class="bi bi-calendar-x logsEmptyIcon"></i>
                <div>No logs found for this period.</div>
            </div>
        </td>
    </tr>';
    exit();
}

/* =========================
   OUTPUT ROWS
========================= */
foreach ($records as $row):
    $editEntry = $editMap[$row['log_id']] ?? null;
    $editStatus      = $editEntry['status']          ?? null;
    $initiatedById   = $editEntry['initiated_by_id'] ?? null;
    $initiatorRole   = $editEntry['initiator_role']  ?? null;
    $initiatorName   = $editEntry['initiator_name']  ?? null;

    if ($initiatedById === null) {
        $editRole = null;
    } elseif ((int)$initiatedById === (int)$employeeId) {
        $editRole = 'self';
    } else {
        $editRole = $initiatorRole;
    }

    $isInside = $row['is_within_office'];
    $label    = $isInside ? 'Within Office' : 'Outside Office';
    $class    = $isInside ? 'in-office' : 'out-office';

    $lat = $row['latitude'] ?? 0;
    $lng = $row['longitude'] ?? 0;

    $acc = isset($row['accuracy']) 
        ? round($row['accuracy'], 1) 
        : 'N/A';

    $dist = isset($row['distance_meters']) 
        ? round($row['distance_meters'], 1) 
        : 'N/A';

    $mapUrl = "https://www.google.com/maps?q={$lat},{$lng}";
?>
<tr>
    <td><?= date('F d, Y', strtotime($row['log_time'])) ?></td>
    <td><?= date('h:i A', strtotime($row['log_time'])) ?></td>

    <td>
        <div class="logBackground <?= match($row['log_type']) {
            'IN'        => 'log-in',
            'OUT'       => 'log-out',
            'BREAK_IN'  => 'log-break-in',
            'BREAK_OUT' => 'log-break-out',
            default     => 'log-out'
        } ?>">
            <span class="logLabel">
                <?= match($row['log_type']) {
                    'IN'        => 'Time In',
                    'OUT'       => 'Time Out',
                    'BREAK_IN'  => 'Break In',
                    'BREAK_OUT' => 'Break Out',
                    default     => $row['log_type']
                } ?>
            </span>
        </div>
    </td>

    <td>
        <a href="<?= $mapUrl ?>" target="_blank"
            class="<?= $class ?> loc-trigger"
            style="text-decoration: none;"
            data-lat="<?= htmlspecialchars($lat, ENT_QUOTES) ?>"
            data-lng="<?= htmlspecialchars($lng, ENT_QUOTES) ?>"
            data-label="<?= htmlspecialchars($label, ENT_QUOTES) ?>"
            data-acc="<?= htmlspecialchars($acc, ENT_QUOTES) ?>"
            data-dist="<?= htmlspecialchars($dist, ENT_QUOTES) ?>">

            <i class="bi bi-geo-alt-fill locationIcon"></i>
            <?= $label ?>
        </a>
    </td>

    <td>
        <?php if ($editRole === 'workforce'): ?>
            <span class="leRequestorName"><?= htmlspecialchars($initiatorName ?? '') ?></span>
            <span class="leRequestor le-requestor-workforce">
                <i class="bi bi-person-badge-fill"></i> Workforce
            </span>
        <?php elseif ($editRole === 'admin'): ?>
            <span class="leRequestorName"><?= htmlspecialchars($initiatorName ?? '') ?></span>
            <span class="leRequestor le-requestor-admin">
                <i class="bi bi-shield-fill"></i> Admin
            </span>
        <?php elseif ($editRole === 'self'): ?>
            <span class="leRequestor le-requestor-self">
                <i class="bi bi-person-fill"></i> You
            </span>
        <?php else: ?>
            <span style="color:rgba(255,255,255,0.15);font-size:0.75rem;">—</span>
        <?php endif; ?>
    </td>

    <td>
        <?php if ($editStatus === 'pending'): ?>
            <span class="leEditStatus le-status-pending">
                <i class="bi bi-hourglass-split"></i> Edit Pending
            </span>
        <?php elseif ($editStatus === 'approved'): ?>
            <span class="leEditStatus le-status-approved">
                <i class="bi bi-check-circle-fill"></i> Edit Approved
            </span>
        <?php elseif ($editStatus === 'rejected'): ?>
            <span class="leEditStatus le-status-rejected">
                <i class="bi bi-x-circle-fill"></i> Edit Rejected
            </span>
        <?php else: ?>
            <span style="color:rgba(255,255,255,0.2);font-size:0.75rem;">—</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>