<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

$employeeId = $_SESSION['user_id'];
date_default_timezone_set('Asia/Manila');
$today = date("Y-m-d");

$startDate = !empty($_GET['start']) ? date('Y-m-d', strtotime($_GET['start'])) : $today;
$endDate   = !empty($_GET['end'])   ? date('Y-m-d', strtotime($_GET['end']))   : $today;

$stmt = $pdo->prepare("
    SELECT log_time, log_type, latitude, longitude, accuracy, is_within_office, distance_meters
    FROM logs
    WHERE employee_id = ?
    AND DATE(log_time) BETWEEN ? AND ?
    ORDER BY log_time DESC
");
$stmt->execute([$employeeId, $startDate, $endDate]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($records) === 0) {
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

foreach ($records as $row):
    $isInside = $row['is_within_office'];
    $label    = $isInside ? 'Within Office' : 'Outside Office';
    $class    = $isInside ? 'in-office' : 'out-office';
    $lat      = $row['latitude']  ?? 0;
    $lng      = $row['longitude'] ?? 0;
    $acc      = isset($row['accuracy'])        ? round($row['accuracy'], 1)        : 'N/A';
    $dist     = isset($row['distance_meters']) ? round($row['distance_meters'], 1) : 'N/A';
    $mapUrl   = "https://www.google.com/maps?q={$lat},{$lng}";
?>
<tr>
    <td><?= date('F d, Y', strtotime($row['log_time'])) ?></td>
    <td><?= date('h:i A',  strtotime($row['log_time'])) ?></td>
    <td>
        <div class="logBackground <?php
            echo match($row['log_type']) {
                'IN'        => 'log-in',
                'OUT'       => 'log-out',
                'BREAK_IN'  => 'log-break-in',
                'BREAK_OUT' => 'log-break-out',
                default     => 'log-out'
            };
        ?>">
            <span class="logLabel">
                <?php
                echo match($row['log_type']) {
                    'IN'        => 'Time In',
                    'OUT'       => 'Time Out',
                    'BREAK_IN'  => 'Break In',
                    'BREAK_OUT' => 'Break Out',
                    default     => $row['log_type']
                };
                ?>
            </span>
        </div>
    </td>
    <td>
        <a href="<?= $mapUrl ?>" target="_blank"
            class="<?= $class ?> loc-trigger"
            style="text-decoration: none;"
            data-lat="<?= $lat ?>"
            data-lng="<?= $lng ?>"
            data-label="<?= $label ?>"
            data-acc="<?= $acc ?>"
            data-dist="<?= $dist ?>">
            <i class="bi bi-geo-alt-fill locationIcon"></i>
            <?= $label ?>
        </a>
    </td>
</tr>
<?php endforeach; ?>