<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

$userRole   = $_SESSION['user_role'] ?? 'employee';
$employeeId = $_SESSION['user_id']   ?? null;

// Admin viewing a specific employee's profile — scope to that employee, hide employee columns
$scopedToEmployee = $userRole === 'admin' && !empty($_GET['employee_id']);
if ($scopedToEmployee) $employeeId = intval($_GET['employee_id']);
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
        l.id AS log_id,
        l.log_time,
        l.log_type,
        l.latitude,
        l.longitude,
        l.accuracy,
        l.is_within_office,
        l.distance_meters,

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

// restrict to own logs (employee) or one specific employee (admin profile view)
if ($userRole !== 'admin' || $scopedToEmployee) {
    $sql .= " AND l.employee_id = ?";
    $params[] = $employeeId;
}

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
$editSql = "
    SELECT ler.log_id, ler.status,
           ler.initiated_by_id, r_init.role_key AS initiator_role, CONCAT(e_init.first_name, ' ', e_init.last_name) AS initiator_name
    FROM log_edit_requests ler
    LEFT JOIN employees e_init ON ler.initiated_by_id = e_init.id
    LEFT JOIN roles r_init ON r_init.id = e_init.role_id
    WHERE 1=1
";
$editParams = [];

if ($userRole !== 'admin' || $scopedToEmployee) {
    $editSql .= " AND ler.employee_id = ?";
    $editParams[] = $employeeId;
}
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
    $colspan = $userRole !== 'admin' ? 6 : ($scopedToEmployee ? 7 : 9);
    echo "
    <tr class='emptyRow'>
        <td colspan='{$colspan}'>
            <div class='logsEmpty'>
                <i class='bi bi-calendar-x logsEmptyIcon'></i>
                <div>No logs found for this period.</div>
            </div>
        </td>
    </tr>";
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
    $empId   = $row['employee_id'];
    $empName = $row['employee_name'];
    $empRole = $row['employee_role'];
    $dept    = $row['department_name'] ?? '—';

    if ($initiatedById === null) {
        $editRole = null;
    } elseif (!$scopedToEmployee && (int)$initiatedById === (int)$_SESSION['user_id']) {
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
        <span class="pill <?= match($row['log_type']) {
            'IN'        => 'btn-success',
            'OUT'       => 'btn-danger',
            'BREAK_IN'  => 'status-pending',
            'BREAK_OUT' => 'btn-info',
            default     => ''
        } ?>">
            <?= match($row['log_type']) {
                'IN'        => 'Time In',
                'OUT'       => 'Time Out',
                'BREAK_IN'  => 'Break In',
                'BREAK_OUT' => 'Break Out',
                default     => $row['log_type']
            } ?>
        </span>
    </td>

    <td>
        <a href="<?= $mapUrl ?>" target="_blank"
            class="pill <?= $isInside ? 'btn-success' : 'btn-danger' ?> loc-trigger"
            style="text-decoration: none;"
            data-lat="<?= htmlspecialchars($lat, ENT_QUOTES) ?>"
            data-lng="<?= htmlspecialchars($lng, ENT_QUOTES) ?>"
            data-label="<?= htmlspecialchars($label, ENT_QUOTES) ?>"
            data-acc="<?= htmlspecialchars($acc, ENT_QUOTES) ?>"
            data-dist="<?= htmlspecialchars($dist, ENT_QUOTES) ?>">

            <i class="bi bi-geo-alt-fill"></i>
            <?= $label ?>
        </a>
    </td>

    <?php if ($userRole === 'admin' && !$scopedToEmployee): ?>
    <td>
        <span class="empIdBadge">#<?= $empId ?></span>
        <?= htmlspecialchars($empName) ?>
    </td>

    <td>
        <span class="empRoleBadge empRole-<?= htmlspecialchars($empRole) ?>">
            <?= ucfirst($empRole) ?>
        </span>
    </td>

    <td>
        <?= htmlspecialchars($dept) ?>
    </td>
    <?php endif; ?>

    <td>
        <?php if ($editRole === 'workforce'): ?>
            <span class="pill empRole-workforce">
                <i class="bi bi-person-badge-fill"></i> <?= htmlspecialchars($initiatorName ?? 'Workforce') ?>
            </span>
        <?php elseif ($editRole === 'admin'): ?>
            <span class="pill empRole-admin">
                <i class="bi bi-shield-fill"></i> <?= htmlspecialchars($initiatorName ?? 'Admin') ?>
            </span>
        <?php elseif ($editRole === 'self'): ?>
            <span class="pill">
                <i class="bi bi-person-fill"></i> You
            </span>
        <?php elseif ($editRole === 'employee'): ?>
            <span class="pill">
                <i class="bi bi-person-fill"></i> <?= htmlspecialchars($initiatorName ?? 'Employee') ?>
            </span>
        <?php else: ?>
            <span style="color:rgba(255,255,255,0.15);font-size:0.75rem;">—</span>
        <?php endif; ?>
    </td>

    <td>
        <?php if ($editStatus === 'pending'): ?>
            <span class="pill btn-info">
                <i class="bi bi-hourglass-split"></i> Pending
            </span>
        <?php elseif ($editStatus === 'approved'): ?>
            <span class="pill btn-success">
                <i class="bi bi-check-circle-fill"></i> Approved
            </span>
        <?php elseif ($editStatus === 'rejected'): ?>
            <span class="pill btn-danger">
                <i class="bi bi-x-circle-fill"></i> Rejected
            </span>
        <?php else: ?>
            <span style="color:rgba(255,255,255,0.2);font-size:0.75rem;">—</span>
        <?php endif; ?>
    </td>

    <?php if ($scopedToEmployee): ?>
    <td>
        <button class="leEditRowBtn" title="Edit log entry"
            data-log-id="<?= $row['log_id'] ?>"
            data-log-type="<?= htmlspecialchars($row['log_type']) ?>"
            data-log-datetime="<?= date('Y-m-d\TH:i', strtotime($row['log_time'])) ?>"
            data-log-date-label="<?= htmlspecialchars(date('F d, Y', strtotime($row['log_time']))) ?>"
            data-log-time-label="<?= htmlspecialchars(date('h:i A', strtotime($row['log_time']))) ?>"
            onclick="openAdminLogEditModal(this)">
            <i class="bi bi-pencil-fill"></i>
        </button>
    </td>
    <?php endif; ?>
</tr>
<?php endforeach; ?>