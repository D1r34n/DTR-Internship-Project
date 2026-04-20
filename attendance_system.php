<?php
require_once 'db.php';

date_default_timezone_set('Asia/Manila');

$testDate  = $_GET['test_date'] ?? null;
$debug     = isset($_GET['debug']);
$skipGuard = false;

if ($testDate) {
    $processDate = $testDate;
    $skipGuard   = true;
} else {
    $processDate = date('Y-m-d', strtotime('-1 day'));
}

$GRACE_MINUTES        = 10;
$OT_THRESHOLD_MINUTES = 30;
$BREAK_SECONDS        = 3600;

function getSystemValue($pdo, $key) {
    $stmt = $pdo->prepare("SELECT value FROM system_state WHERE key_name = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}

function setSystemValue($pdo, $key, $value) {
    $stmt = $pdo->prepare("
        INSERT INTO system_state (key_name, value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    $stmt->execute([$key, $value]);
}

$lastRun = getSystemValue($pdo, 'attendance_last_finalize');

if (!$skipGuard && $lastRun === $processDate) {
    exit("Already finalized for $processDate");
}

$employees = $pdo->query("SELECT id FROM employees")->fetchAll(PDO::FETCH_COLUMN);

foreach ($employees as $employeeId) {

    /* ✅ Fetch schedule FIRST */
    $stmt = $pdo->prepare("
        SELECT time_in, time_out, is_rest_day
        FROM schedules
        WHERE employee_id = ?
        AND work_date = ?
    ");
    $stmt->execute([$employeeId, $processDate]);
    $sched = $stmt->fetch(PDO::FETCH_ASSOC);

    /* Skip rest days and missing/empty schedules */
    if (!$sched || $sched['is_rest_day'] || !$sched['time_in'] || $sched['time_in'] === '00:00:00') {
        if ($debug) {
            echo "<b>Employee:</b> $employeeId | <b>Date:</b> $processDate | <b>Skipped</b> (rest day or no schedule)<hr>";
        }
        continue;
    }

    /* ✅ Ensure attendance row exists WITH scheduled times */
    $stmt = $pdo->prepare("
        INSERT INTO attendance (employee_id, date, scheduled_time_in, scheduled_time_out, status)
        VALUES (?, ?, ?, ?, 'absent')
        ON DUPLICATE KEY UPDATE
            scheduled_time_in  = VALUES(scheduled_time_in),
            scheduled_time_out = VALUES(scheduled_time_out)
    ");
    $stmt->execute([$employeeId, $processDate, $sched['time_in'], $sched['time_out']]);

    /* Fetch attendance row */
    $stmt = $pdo->prepare("
        SELECT * FROM attendance
        WHERE employee_id = ? AND date = ?
    ");
    $stmt->execute([$employeeId, $processDate]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    $scheduledIn  = strtotime($processDate . ' ' . $sched['time_in']);
    $scheduledOut = strtotime($processDate . ' ' . $sched['time_out']);

    $late            = 0;
    $undertime       = 0;
    $overtime        = 0;
    $totalHours      = 0;
    $status          = 'absent';
    $overtime_status = 'none';

    if ($attendance['actual_time_in'] && $attendance['actual_time_out']) {

        $actualIn  = strtotime($attendance['actual_time_in']);
        $actualOut = strtotime($attendance['actual_time_out']);

        if ($actualIn > ($scheduledIn + ($GRACE_MINUTES * 60))) {
            $late = (int) floor(($actualIn - $scheduledIn) / 60);
        }

        if ($actualOut < $scheduledOut) {
            $undertime = (int) floor(($scheduledOut - $actualOut) / 60);
        }

        $otThreshold = $scheduledOut + ($OT_THRESHOLD_MINUTES * 60);
        if ($actualOut > $otThreshold) {
            $overtime        = (int) floor(($actualOut - $otThreshold) / 60);
            $overtime_status = 'pending';
        }

        $workedSeconds = $actualOut - $actualIn - $BREAK_SECONDS;
        $totalHours    = round(max(0, $workedSeconds / 3600), 2);

        $status = ($late > 0) ? 'late' : 'present';

    } elseif ($attendance['actual_time_in'] && !$attendance['actual_time_out']) {

        $status = 'incomplete';

    } else {

        $status = 'absent';
    }

    $stmt = $pdo->prepare("
        UPDATE attendance
        SET
            scheduled_time_in  = ?,
            scheduled_time_out = ?,
            late_minutes       = ?,
            undertime_minutes  = ?,
            overtime_minutes   = ?,
            total_work_hours   = ?,
            status             = ?,
            overtime_status    = ?
        WHERE employee_id = ? AND date = ?
    ");
    $stmt->execute([
        $sched['time_in'],
        $sched['time_out'],
        $late,
        $undertime,
        $overtime,
        $totalHours,
        $status,
        $overtime_status,
        $employeeId,
        $processDate
    ]);

    if ($debug) {
        echo "
            <b>Employee:</b> $employeeId |
            <b>Date:</b> $processDate |
            <b>Status:</b> $status |
            <b>Late:</b> {$late}min |
            <b>OT:</b> {$overtime}min |
            <b>UT:</b> {$undertime}min |
            <b>Hours:</b> {$totalHours}
            <hr>
        ";
    }
}

setSystemValue($pdo, 'attendance_last_finalize', $processDate);

if ($debug) {
    echo "<br><b>✅ Finalized for:</b> $processDate";
}

// if ($user_email === 'earl@gmail.com') {
//     // Automatic Time in
// }