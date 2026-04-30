<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'workforce') {
    http_response_code(401);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$myId       = $_SESSION['user_id'];
$employeeId = intval($_GET['employee_id'] ?? 0);
$year       = intval($_GET['year']  ?? date('Y'));
$month      = intval($_GET['month'] ?? date('n'));
if (!$employeeId) exit();

// Verify the requested employee is in the same department
$myDeptRow = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
$myDeptRow->execute([$myId]);
$myDept = $myDeptRow->fetchColumn();

$empDeptRow = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
$empDeptRow->execute([$employeeId]);
$empDept = $empDeptRow->fetchColumn();

if (!$myDept || $myDept !== $empDept) {
    http_response_code(403);
    exit();
}

$firstDay    = sprintf('%04d-%02d-01', $year, $month);
$lastDay     = date('Y-m-t', strtotime($firstDay));
$today       = date('Y-m-d');
$daysInMonth = (int)date('t', strtotime($firstDay));
$startDow    = (int)date('N', strtotime($firstDay)); // 1=Mon, 7=Sun

$stmt = $pdo->prepare("
    SELECT schedule_date, scheduled_start, scheduled_end, status
    FROM schedules
    WHERE employee_id = ? AND schedule_date BETWEEN ? AND ? AND is_rest_day = 0
    ORDER BY schedule_date
");
$stmt->execute([$employeeId, $firstDay, $lastDay]);

$schedMap = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $schedMap[$row['schedule_date']] = $row;
}
?>
<div class="schedCalGrid">

    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
        <div class="schedCalDayHeader"><?= $d ?></div>
    <?php endforeach; ?>

    <?php for ($i = 1; $i < $startDow; $i++): ?>
        <div class="schedCalDay empty"></div>
    <?php endfor; ?>

    <?php for ($day = 1; $day <= $daysInMonth; $day++):
        $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $sched    = $schedMap[$dateStr] ?? null;
        $isToday  = ($dateStr === $today);
        $isPast   = ($dateStr < $today);

        $classes  = 'schedCalDay';
        if ($sched)   $classes .= ' has-sched sched-' . $sched['status'];
        if ($isToday) $classes .= ' is-today';
        if ($isPast && !$sched) $classes .= ' is-past';

        $startHour = $sched ? (int)date('H', strtotime($sched['scheduled_start'])) : 8;
        $isNight   = $sched && ($startHour >= 18 || $startHour < 6);
    ?>
    <div class="<?= $classes ?>">
        <div class="schedCalDayNum <?= $isToday ? 'is-today-num' : '' ?>"><?= $day ?></div>

        <?php if ($sched): ?>
            <div class="schedCalShiftBadge <?= $isNight ? 'night' : 'day' ?>">
                <?= $isNight ? 'Night' : 'Day' ?>
            </div>
            <div class="schedCalTimes">
                <?= date('h:i A', strtotime($sched['scheduled_start'])) ?><br>
                <?= date('h:i A', strtotime($sched['scheduled_end'])) ?>
            </div>
            <div class="schedCalStatusBadge sched-status-<?= $sched['status'] ?>">
                <?= ucfirst($sched['status']) ?>
            </div>
            <div class="schedCalDayActions">
                <button class="schedCalActionBtn edit"
                        title="Edit &amp; Re-submit for Approval"
                        onclick="openEditModal(
                            <?= $employeeId ?>,
                            '<?= $dateStr ?>',
                            '<?= date('H:i', strtotime($sched['scheduled_start'])) ?>',
                            '<?= date('H:i', strtotime($sched['scheduled_end'])) ?>'
                        )">
                    <i class="bi bi-pencil-fill"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>
    <?php endfor; ?>

</div>

<?php if (empty($schedMap)): ?>
<div class="schedCalEmpty">
    <i class="bi bi-calendar-x schedCalEmptyIcon"></i>
    <div>No schedules for this month.</div>
</div>
<?php endif; ?>
