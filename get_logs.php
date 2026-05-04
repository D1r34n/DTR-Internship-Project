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
   EMPTY STATE
========================= */
if (!$records) {
    echo '
    <tr class="emptyRow">
        <td colspan="4">
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
</tr>
<?php endforeach; ?>