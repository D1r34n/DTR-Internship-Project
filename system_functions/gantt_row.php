<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit();
}

require_once '../db.php';
require_once 'system_library.php';
require_once 'system_service.php';

date_default_timezone_set('Asia/Manila');

$employeeId = $_SESSION['user_id'];
$date = $_GET['date'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_date']);
    exit();
}

$records   = getAttendanceRecords($pdo, $employeeId, $date, $date);
$schedules = getSchedulesByDateRange($pdo, $employeeId, $date, $date);

if (empty($records)) {
    header('Content-Type: application/json');
    echo json_encode(['html' => '']);
    exit();
}

$row      = $records[0];
$sched    = $schedules[$row['work_date']] ?? null;
$ganttBar = computeGanttRow($row, $sched);

if ($ganttBar === null) {
    header('Content-Type: application/json');
    echo json_encode(['html' => '']);
    exit();
}

ob_start();

if ($ganttBar['type'] === 'absent_or_future'): ?>
<div class="ganttRow" data-date="<?= htmlspecialchars($row['work_date']) ?>">
    <div class="ganttLabel">
        <div><?= $ganttBar['dayLabel'] ?></div>
        <div style="font-size: 0.75rem; color: #aaa;"><?= $ganttBar['dateNum'] ?></div>
    </div>
    <div class="ganttBarContainer"
        data-range-start="<?= $ganttBar['rangeStart'] ?>"
        data-range-end="<?= $ganttBar['rangeEnd'] ?>">

        <?= gantt_cursor() ?>
        <?= gantt_scale($ganttBar['rangeStart'], $ganttBar['rangeEnd']) ?>

        <div class="ganttBar <?= $ganttBar['barClass'] ?>"
            style="left: <?= $ganttBar['barLeft'] ?>%; width: <?= $ganttBar['barWidth'] ?>%;"></div>
        <div class="<?= $ganttBar['labelClass'] ?>" style="left: <?= $ganttBar['midLeft'] ?>%">
            <?= $ganttBar['labelText'] ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="ganttRow" data-date="<?= htmlspecialchars($row['work_date']) ?>">
    <div class="ganttLabel">
        <div><?= $ganttBar['dayLabel'] ?></div>
        <div class="ganttSubLabel"><?= $ganttBar['dateNum'] ?></div>
    </div>

    <div class="ganttBarContainer"
        data-is-today="<?= $ganttBar['isToday'] ? '1' : '0' ?>"
        data-range-start="<?= $ganttBar['rangeStart'] ?>"
        data-range-end="<?= $ganttBar['rangeEnd'] ?>"
        data-sched-in="<?= $ganttBar['schedInLabel'] ?>"
        data-sched-out="<?= $ganttBar['schedOutLabel'] ?>"
        data-actual-in="<?= $ganttBar['actualInLabel'] ?>"
        data-actual-out="<?= $ganttBar['actualOutLabel'] ?>"
        data-late="<?= $ganttBar['lateLabel'] ?>"
        data-overtime="<?= $ganttBar['overtimeLabel'] ?>"
        data-overtime-status="<?= $ganttBar['overtimeStatusLabel'] ?>"
        data-undertime="<?= $ganttBar['undertimeLabel'] ?>">

        <?= gantt_cursor() ?>
        <?= gantt_scale($ganttBar['rangeStart'], $ganttBar['rangeEnd']) ?>

        <?php if ($ganttBar['schedIn'] !== null): ?>
            <div class="ganttBar ganttBarScheduled"
                style="left: <?= $ganttBar['schedLeft'] ?>%; width: <?= $ganttBar['schedWidth'] ?>%;"></div>
        <?php endif; ?>

        <?php if ($ganttBar['isTardy']): ?>
            <div class="ganttBar ganttBarTardy"
                style="left: <?= $ganttBar['tardyLeft'] ?>%; width: <?= $ganttBar['tardyWidth'] ?>%;">
                <span class="ganttBarLabel">Late</span>
            </div>
        <?php endif; ?>

        <div class="ganttBar <?= $ganttBar['noTimeOut'] ? 'ganttBarNoTimeOut' : 'ganttBarOnTime' ?>"
            style="left: <?= $ganttBar['actualLeft'] ?>%; width: <?= $ganttBar['onTimeWidth'] ?>%;">
            <span class="ganttBarLabel"><?= $ganttBar['noTimeOut'] ? 'No Time Out' : 'On Time' ?></span>
        </div>

        <?php if ($ganttBar['isUndertime'] && $ganttBar['schedOut']): ?>
            <div class="ganttBar ganttBarUndertime"
                style="left: <?= $ganttBar['undertimeLeft'] ?>%; width: <?= $ganttBar['undertimeWidth'] ?>%;">
                <span class="ganttBarLabel">Undertime</span>
            </div>
        <?php endif; ?>

        <?php if ($ganttBar['overtimeMinutes'] > 0 && $ganttBar['schedOut']): ?>
            <div class="ganttBar <?= $ganttBar['otColorClass'] ?>"
                style="left: <?= $ganttBar['overtimeLeft'] ?>%; width: <?= $ganttBar['overtimeWidth'] ?>%;">
                <span class="ganttBarLabel">Overtime</span>
            </div>
        <?php endif; ?>

        <div class="ganttMarker ganttMarkerActualStart" style="left: <?= $ganttBar['actualInPos'] ?>%"></div>
        <div class="ganttMarker ganttMarkerActualEnd"   style="left: <?= $ganttBar['actualOutPos'] ?>%"></div>
    </div>
</div>
<?php endif;

$html = ob_get_clean();

header('Content-Type: application/json');
echo json_encode(['html' => $html]);
