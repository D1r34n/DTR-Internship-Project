<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    exit();
}

require_once '../db.php';
require_once '../system_functions/system_library.php';
require_once '../system_functions/system_service.php';
date_default_timezone_set('Asia/Manila');

$employeeId = intval($_GET['employee_id'] ?? 0);
$startDate  = $_GET['start'] ?? date('Y-m-01');
$endDate    = $_GET['end']   ?? date('Y-m-t');

if (!$employeeId) { exit(); }

$records   = getAttendanceRecords($pdo, $employeeId, $startDate, $endDate);
$schedules = getSchedulesByDateRange($pdo, $employeeId, $startDate, $endDate);

// ---- Leave requests ----
$leaveStmt = $pdo->prepare("
    SELECT start_date, end_date, selected_dates, status
    FROM leave_requests
    WHERE employee_id = ?
    AND (start_date BETWEEN ? AND ? OR end_date BETWEEN ? AND ? OR (start_date <= ? AND end_date >= ?))
");
$leaveStmt->execute([$employeeId, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
$leaveMap = [];
foreach ($leaveStmt->fetchAll(PDO::FETCH_ASSOC) as $leave) {
    $dates = json_decode($leave['selected_dates'], true);
    if (is_array($dates) && !empty($dates)) {
        foreach ($dates as $d) {
            if ($d >= $startDate && $d <= $endDate) $leaveMap[$d] = $leave['status'];
        }
    } else {
        $cur = new DateTime($leave['start_date']);
        $end = new DateTime($leave['end_date']);
        while ($cur <= $end) {
            $d = $cur->format('Y-m-d');
            if ($d >= $startDate && $d <= $endDate) $leaveMap[$d] = $leave['status'];
            $cur->modify('+1 day');
        }
    }
}

// ---- OB requests ----
$obStmt = $pdo->prepare("
    SELECT start_date AS ob_date, status FROM leave_requests
    WHERE employee_id = ? AND leave_type = 'ob leave' AND start_date BETWEEN ? AND ?
");
$obStmt->execute([$employeeId, $startDate, $endDate]);
$obMap = [];
foreach ($obStmt->fetchAll(PDO::FETCH_ASSOC) as $ob) {
    $obMap[$ob['ob_date']] = $ob['status'];
}

$cPresent = $cAbsent = $cIncomplete = 0;
foreach ($records as $r) {
    if ($r['status'] === 'present')    $cPresent++;
    elseif ($r['status'] === 'absent') $cAbsent++;
    else                               $cIncomplete++;
}
echo '<!--SUMMARY:' . json_encode(['present' => $cPresent, 'absent' => $cAbsent, 'incomplete' => $cIncomplete]) . '-->';

$hasRows = false;

foreach ($records as $row):
    $sched    = $schedules[$row['work_date']] ?? null;
    $ganttBar = computeGanttRow($row, $sched);
    if ($ganttBar === null) continue;
    $hasRows = true;

    // Override bar for approved leave / OB
    $dateKey     = $row['work_date'];
    $leaveStatus = $leaveMap[$dateKey] ?? null;
    $obStatus    = $obMap[$dateKey]    ?? null;

    if ($leaveStatus === 'approved') {
        $ganttBar['type']       = 'absent_or_future';
        $ganttBar['barClass']   = 'ganttBarLeave';
        $ganttBar['labelClass'] = 'ganttAbsentLabel';
        $ganttBar['labelText']  = 'On Leave';
        $ganttBar['barLeft']    = 0;
        $ganttBar['barWidth']   = 100;
        $ganttBar['midLeft']    = 50;
    } elseif ($obStatus === 'approved') {
        $ganttBar['type']       = 'absent_or_future';
        $ganttBar['barClass']   = 'ganttBarOB';
        $ganttBar['labelClass'] = 'ganttAbsentLabel';
        $ganttBar['labelText']  = 'On OB';
        $ganttBar['barLeft']    = 0;
        $ganttBar['barWidth']   = 100;
        $ganttBar['midLeft']    = 50;
    }

    if ($ganttBar['type'] === 'absent_or_future'): ?>
<div class="ganttRow">
    <div class="ganttLabel">
        <div><?= $ganttBar['dayLabel'] ?></div>
        <div style="font-size:0.75rem;color:#aaa;"><?= $ganttBar['dateNum'] ?></div>
    </div>
    <div class="ganttBarContainer"
        data-range-start="<?= $ganttBar['rangeStart'] ?>"
        data-range-end="<?= $ganttBar['rangeEnd'] ?>">
        <?= gantt_cursor() ?>
        <?= gantt_scale($ganttBar['rangeStart'], $ganttBar['rangeEnd']) ?>
        <div class="ganttBar <?= $ganttBar['barClass'] ?>"
            style="left:<?= $ganttBar['barLeft'] ?>%;width:<?= $ganttBar['barWidth'] ?>%;"></div>
        <div class="<?= $ganttBar['labelClass'] ?>" style="left:<?= $ganttBar['midLeft'] ?>%">
            <?= $ganttBar['labelText'] ?>
        </div>
    </div>
</div>
    <?php else: ?>
<div class="ganttRow">
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
        data-early="<?= $ganttBar['earlyLabel'] ?>"
        data-late="<?= $ganttBar['lateLabel'] ?>"
        data-overtime="<?= $ganttBar['overtimeLabel'] ?>"
        data-overtime-status="<?= $ganttBar['overtimeStatusLabel'] ?>"
        data-undertime="<?= $ganttBar['undertimeLabel'] ?>"
        data-overbreak="<?= $ganttBar['overbreakLabel'] ?>">
        <?= gantt_cursor() ?>
        <?= gantt_scale($ganttBar['rangeStart'], $ganttBar['rangeEnd']) ?>
        <?php if ($ganttBar['schedIn'] !== null): ?>
            <div class="ganttBar ganttBarScheduled"
                style="left:<?= $ganttBar['schedLeft'] ?>%;width:<?= $ganttBar['schedWidth'] ?>%;"></div>
        <?php endif; ?>
        <?php if ($ganttBar['isEarly'] && $ganttBar['schedIn']): ?>
            <div class="ganttBar ganttBarEarly"
                style="left:<?= $ganttBar['earlyLeft'] ?>%;width:<?= $ganttBar['earlyWidth'] ?>%;">
                <span class="ganttBarLabel">Early</span>
            </div>
        <?php endif; ?>
        <?php if ($ganttBar['isTardy']): ?>
            <div class="ganttBar ganttBarTardy"
                style="left:<?= $ganttBar['tardyLeft'] ?>%;width:<?= $ganttBar['tardyWidth'] ?>%;">
                <span class="ganttBarLabel">Late</span>
            </div>
        <?php endif; ?>
        <?php if ($ganttBar['hasClockedIn']): ?>
            <?php if ($ganttBar['onTimeSplit']): ?>
                <div class="ganttBar <?= $ganttBar['noTimeOut'] ? 'ganttBarNoTimeOut' : 'ganttBarOnTime' ?>"
                    style="left:<?= $ganttBar['actualLeft'] ?>%;width:<?= $ganttBar['onTimeLeftWidth'] ?>%;">
                    <span class="ganttBarLabel"><?= $ganttBar['noTimeOut'] ? 'No Time Out' : 'On Time' ?></span>
                </div>
                <div class="ganttBar ganttBarBreak"
                    style="left:<?= $ganttBar['breakLeft'] ?>%;width:<?= $ganttBar['breakWidth'] ?>%;">
                    <span class="ganttBarLabel">Break</span>
                </div>
                <div class="ganttBar <?= $ganttBar['noTimeOut'] ? 'ganttBarNoTimeOut' : 'ganttBarOnTime' ?>"
                    style="left:<?= $ganttBar['onTimeRightLeft'] ?>%;width:<?= $ganttBar['onTimeRightWidth'] ?>%;">
                    <?php if ($ganttBar['onTimeRightWidth'] > 5): ?>
                        <span class="ganttBarLabel"><?= $ganttBar['noTimeOut'] ? 'No Time Out' : 'On Time' ?></span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="ganttBar <?= $ganttBar['noTimeOut'] ? 'ganttBarNoTimeOut' : 'ganttBarOnTime' ?>"
                    style="left:<?= $ganttBar['actualLeft'] ?>%;width:<?= $ganttBar['onTimeWidth'] ?>%;">
                    <span class="ganttBarLabel"><?= $ganttBar['noTimeOut'] ? 'No Time Out' : 'On Time' ?></span>
                </div>
            <?php endif; ?>
            <?php if ($ganttBar['isUndertime'] && $ganttBar['schedOut']): ?>
                <div class="ganttBar ganttBarUndertime"
                    style="left:<?= $ganttBar['undertimeLeft'] ?>%;width:<?= $ganttBar['undertimeWidth'] ?>%;">
                    <span class="ganttBarLabel">Undertime</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($ganttBar['overtimeMinutes'] > 0 && $ganttBar['schedOut']): ?>
            <div class="ganttBar <?= $ganttBar['otColorClass'] ?>"
                style="left:<?= $ganttBar['overtimeLeft'] ?>%;width:<?= $ganttBar['overtimeWidth'] ?>%;">
                <span class="ganttBarLabel">Overtime</span>
            </div>
        <?php endif; ?>
        <?php if ($ganttBar['actualInPos'] !== null): ?>
            <div class="ganttMarker ganttMarkerActualStart" style="left:<?= $ganttBar['actualInPos'] ?>%"></div>
        <?php endif; ?>
        <?php if ($ganttBar['actualOutPos'] !== null): ?>
            <div class="ganttMarker ganttMarkerActualEnd" style="left:<?= $ganttBar['actualOutPos'] ?>%"></div>
        <?php endif; ?>
    </div>
</div>
    <?php endif; ?>
<?php endforeach; ?>

<?php if (!$hasRows): ?>
<div class="ganttEmpty">
    <i class="bi bi-calendar-x ganttEmptyIcon"></i>
    <div>No records found for this period.</div>
</div>
<?php endif; ?>
