<?php
if (!defined('BREAK_SECONDS')) {
    define('BREAK_SECONDS', 3600);
}

function finalizeAttendance(PDO $pdo, int $employeeId, string $date): ?array
{
    // ─────────────────────────────
    // 1. LOAD SCHEDULE (NEW SCHEMA)
    // ─────────────────────────────
    $stmt = $pdo->prepare("
        SELECT 
            scheduled_start_datetime,
            scheduled_end_datetime,
            is_rest_day
        FROM schedules
        WHERE employee_id = ?
          AND schedule_date = ?
        LIMIT 1
    ");
    $stmt->execute([$employeeId, $date]);
    $sched = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sched) {
        return null;
    }

    $schedIn  = $sched['scheduled_start_datetime'];
    $schedOut = $sched['scheduled_end_datetime'];
    $isRest   = (int)$sched['is_rest_day'];

    // ─────────────────────────────
    // REST DAY SHORT-CIRCUIT
    // ─────────────────────────────
    if ($isRest === 1) {
        $pdo->prepare("
            UPDATE attendance SET
                status = 'rest_day',
                late_minutes = 0,
                undertime_minutes = 0,
                overtime_minutes = 0,
                total_work_hours = 0
            WHERE employee_id = ? AND date = ?
        ")->execute([$employeeId, $date]);

        return null;
    }

    // ─────────────────────────────
    // 2. LOAD ATTENDANCE
    // ─────────────────────────────
    $stmt = $pdo->prepare("
        SELECT *
        FROM attendance
        WHERE employee_id = ? AND date = ?
    ");
    $stmt->execute([$employeeId, $date]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attendance) {
        return null;
    }

    $actualIn  = $attendance['actual_time_in']  ? strtotime($attendance['actual_time_in'])  : null;
    $actualOut = $attendance['actual_time_out'] ? strtotime($attendance['actual_time_out']) : null;

    $lateMinutes      = 0;
    $undertimeMinutes = 0;
    $overtimeMinutes  = 0;
    $totalWorkHours   = 0;
    $overtimeStatus   = 'none';

    // ─────────────────────────────
    // 3. LATE
    // ─────────────────────────────
    if ($actualIn && $schedIn) {
        $schedInTs = strtotime($schedIn);
        if ($actualIn > $schedInTs) {
            $lateMinutes = (int) floor(($actualIn - $schedInTs) / 60);
        }
    }

    // ─────────────────────────────
    // 4. WORK HOURS FROM LOGS
    // ─────────────────────────────
    if ($actualIn && $actualOut) {

        $stmt = $pdo->prepare("
            SELECT log_type, log_time
            FROM logs
            WHERE employee_id = ?
              AND DATE(log_time) = ?
            ORDER BY log_time ASC
        ");
        $stmt->execute([$employeeId, $date]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalSeconds = 0;
        $lastIn = null;

        foreach ($logs as $log) {
            if ($log['log_type'] === 'IN') {
                $lastIn = strtotime($log['log_time']);
            }

            if ($log['log_type'] === 'OUT' && $lastIn) {
                $totalSeconds += strtotime($log['log_time']) - $lastIn;
                $lastIn = null;
            }
        }

        if ($totalSeconds > BREAK_SECONDS) {
            $totalSeconds -= BREAK_SECONDS;
        }

        $totalWorkHours = round($totalSeconds / 3600, 2);

        // ─────────────────────────────
        // 5. UNDERTIME / OVERTIME
        // ─────────────────────────────
        if ($schedOut) {
            $schedOutTs = strtotime($schedOut);

            if ($actualOut < $schedOutTs) {
                $undertimeMinutes = (int) floor(($schedOutTs - $actualOut) / 60);
            } elseif ($actualOut > $schedOutTs) {
                $overtimeMinutes = (int) floor(($actualOut - $schedOutTs) / 60);
                $overtimeStatus = 'pending';
            }
        }
    }

    // ─────────────────────────────
    // 6. STATUS
    // ─────────────────────────────
    if (!$actualIn || !$actualOut) {
        $status = 'incomplete';
    } elseif ($lateMinutes > 0) {
        $status = 'late';
    } elseif ($undertimeMinutes > 0) {
        $status = 'incomplete';
    } else {
        $status = 'present';
    }

    // ─────────────────────────────
    // 7. SAVE
    // ─────────────────────────────
    $pdo->prepare("
        UPDATE attendance SET
            scheduled_start_datetime = ?,
            scheduled_end_datetime = ?,
            late_minutes = ?,
            undertime_minutes = ?,
            overtime_minutes = ?,
            total_work_hours = ?,
            status = ?,
            overtime_status = ?
        WHERE employee_id = ? AND date = ?
    ")->execute([
        $schedIn,
        $schedOut,
        $lateMinutes,
        $undertimeMinutes,
        $overtimeMinutes,
        $totalWorkHours,
        $status,
        $overtimeStatus,
        $employeeId,
        $date
    ]);

    // ─────────────────────────────
    // 8. RETURN
    // ─────────────────────────────
    $stmt = $pdo->prepare("
        SELECT * FROM attendance
        WHERE employee_id = ? AND date = ?
    ");
    $stmt->execute([$employeeId, $date]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}