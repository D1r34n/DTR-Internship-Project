<?php
/**
 * attendance_service.php
 * -------------------------------------------------
 * SERVICE LAYER (BUSINESS LOGIC CORE)
 *
 * Responsibilities:
 * - Determine IN/OUT logic
 * - Fetch last log
 * - Handle schedule + shift detection
 * - Compute first log of shift
 * - Insert attendance log
 * - Coordinate with utility functions (attendance_library.php)
 *
 * This file contains ALL business rules.
 * NO HTTP logic. NO session handling.
 */

require_once 'system_library.php';


/**
 * MAIN ENTRY POINT
 */
function processAttendanceTap($pdo, $employee_id, $lat, $lng, $accuracy)
{
    // -------------------------------------------------
    // 1. CONFIG (office rules)
    // -------------------------------------------------
    $officeLat = 14.584415691940826;
    $officeLng = 120.99562631352414;
    $radius = 100;


    // -------------------------------------------------
    // 2. COMPUTE DISTANCE (UTILITY LAYER)
    // -------------------------------------------------
    $distance = distanceMeters($lat, $lng, $officeLat, $officeLng);
    $isWithin = $distance <= $radius ? 1 : 0;


    // -------------------------------------------------
    // 3. GET LAST LOG
    // -------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT log_type, log_time
        FROM logs
        WHERE employee_id = ?
        ORDER BY log_time DESC
        LIMIT 1
    ");
    $stmt->execute([$employee_id]);

    $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);


    // -------------------------------------------------
    // 4. DETERMINE IN / OUT STATE
    // -------------------------------------------------
    $isIn = (!$lastLog || $lastLog['log_type'] === 'OUT');
    $nextType = $isIn ? 'IN' : 'OUT';
    $response = $isIn ? 'timed_in' : 'timed_out';


    // -------------------------------------------------
    // 5. SHIFT DETECTION (IN AND OUT)
    // -------------------------------------------------
    $isFirstLogOfShift = false;
    $schedule = null;

    $stmt = $pdo->prepare("
        SELECT schedule_date, scheduled_start_datetime, scheduled_end_datetime
        FROM schedules
        WHERE employee_id = ?
          AND NOW() BETWEEN scheduled_start_datetime
                        AND DATE_ADD(scheduled_end_datetime, INTERVAL 6 HOUR)
        ORDER BY scheduled_end_datetime DESC
        LIMIT 1
    ");

    $stmt->execute([$employee_id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($nextType === 'IN' && $schedule) {

        // Check if there is already an IN log within shift window
        $stmt = $pdo->prepare("
            SELECT 1
            FROM logs
            WHERE employee_id = ?
              AND log_type = 'IN'
              AND log_time BETWEEN ? AND DATE_ADD(?, INTERVAL 6 HOUR)
            LIMIT 1
        ");

        $stmt->execute([
            $employee_id,
            $schedule['scheduled_start_datetime'],
            $schedule['scheduled_end_datetime']
        ]);

        $isFirstLogOfShift = !$stmt->fetchColumn();
    }


    // -------------------------------------------------
    // 6. INSERT LOG
    // -------------------------------------------------
    $stmt = $pdo->prepare("
        INSERT INTO logs (
            employee_id,
            log_type,
            log_time,
            latitude,
            longitude,
            accuracy,
            is_within_office,
            distance_meters
        )
        VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $employee_id,
        $nextType,
        $lat,
        $lng,
        $accuracy,
        $isWithin,
        $distance
    ]);


    // -------------------------------------------------
    // 7. FINALIZE ATTENDANCE (OPTIONAL BUSINESS HOOK)
    // -------------------------------------------------
    if ($schedule) {
        try {
            finalizeEmployeeAttendance(
                $pdo,
                $employee_id,
                $schedule
            );
        } catch (Throwable $e) {
            error_log("Attendance finalize error: " . $e->getMessage());
        }
    }

    // -------------------------------------------------
    // 8. RESPONSE PAYLOAD
    // -------------------------------------------------
    return [
        'tap' => $response,
        'log_type' => $nextType,
        'distance_meters' => round($distance, 2),
        'is_within_office' => $isWithin,
        'is_first_log_of_shift' => $isFirstLogOfShift
    ];
}

/**
 * FINALIZE EMPLOYEE ATTENDANCE
 * -------------------------------------------------
 * SERVICE-LEVEL FUNCTION (Business Rule Processor)
 *
 * Purpose:
 * - Computes final attendance state for a given work date
 * - Calculates late, undertime, overtime minutes
 * - Upserts into attendances table
 *
 * Triggered when:
 * - First IN of a shift is recorded
 */
function finalizeEmployeeAttendance(PDO $pdo, int $employeeId, array $schedule)
{
    $date           = $schedule['schedule_date'];
    $scheduledStart = $schedule['scheduled_start_datetime'];
    $scheduledEnd   = $schedule['scheduled_end_datetime'];

    // -------------------------------------------------
    // 1. Get all logs for the day
    // -------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT log_type, log_time
        FROM logs
        WHERE employee_id = ?
          AND DATE(log_time) = ?
        ORDER BY log_time ASC
    ");

    $stmt->execute([$employeeId, $date]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$logs) {
        return;
    }

    // -------------------------------------------------
    // 2. Extract first IN and last OUT
    // -------------------------------------------------
    $firstIn = null;
    $lastOut = null;

    foreach ($logs as $log) {
        if ($log['log_type'] === 'IN' && !$firstIn) {
            $firstIn = $log['log_time'];
        }
        if ($log['log_type'] === 'OUT') {
            $lastOut = $log['log_time'];
        }
    }

    // -------------------------------------------------
    // 3. Compute derived fields
    // -------------------------------------------------
    $totalWorkHours   = 0;
    $lateMinutes      = 0;
    $undertimeMinutes = 0;
    $overtimeMinutes  = 0;

    if ($firstIn && $lastOut) {
        $totalWorkHours = (strtotime($lastOut) - strtotime($firstIn)) / 3600;
    }

    if ($firstIn) {
        $lateSeconds = strtotime($firstIn) - strtotime($scheduledStart);
        $lateMinutes = max(0, (int) round($lateSeconds / 60));
    }

    if ($lastOut) {
        $diffEnd          = strtotime($scheduledEnd) - strtotime($lastOut);
        $undertimeMinutes = max(0, (int) round($diffEnd / 60));
        $overtimeMinutes  = max(0, (int) round(-$diffEnd / 60));
    }

    $status = 'incomplete';
    if ($firstIn && $lastOut) {
        if ($lateMinutes > 0)      $status = 'late';
        elseif ($undertimeMinutes > 0) $status = 'undertime';
        else                           $status = 'present';
    }

    // -------------------------------------------------
    // 4. Upsert into attendances
    // -------------------------------------------------
    $stmt = $pdo->prepare("
        INSERT INTO attendances (
            employee_id,
            work_date,
            scheduled_start_datetime,
            scheduled_end_datetime,
            actual_time_in,
            actual_time_out,
            total_work_hours,
            late_minutes,
            undertime_minutes,
            overtime_minutes,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            scheduled_start_datetime = VALUES(scheduled_start_datetime),
            scheduled_end_datetime   = VALUES(scheduled_end_datetime),
            actual_time_in           = VALUES(actual_time_in),
            actual_time_out          = VALUES(actual_time_out),
            total_work_hours         = VALUES(total_work_hours),
            late_minutes             = VALUES(late_minutes),
            undertime_minutes        = VALUES(undertime_minutes),
            overtime_minutes         = VALUES(overtime_minutes),
            status                   = VALUES(status)
    ");

    $stmt->execute([
        $employeeId,
        $date,
        $scheduledStart,
        $scheduledEnd,
        $firstIn,
        $lastOut,
        round($totalWorkHours, 2),
        $lateMinutes,
        $undertimeMinutes,
        $overtimeMinutes,
        $status
    ]);
}

/* =========================
   RECORDS PAGE QUERIES
========================= */

function getAttendanceRecords(PDO $pdo, int $employeeId, string $startDate, string $endDate): array
{
    $stmt = $pdo->prepare("
        SELECT
            work_date,
            scheduled_start_datetime,
            scheduled_end_datetime,
            actual_time_in,
            actual_time_out,
            total_work_hours,
            status,
            late_minutes,
            undertime_minutes,
            overtime_minutes,
            overtime_status
        FROM attendances
        WHERE employee_id = ?
        AND work_date BETWEEN ? AND ?
        ORDER BY work_date DESC
    ");
    $stmt->execute([$employeeId, $startDate, $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSchedulesByDateRange(PDO $pdo, int $employeeId, string $startDate, string $endDate): array
{
    $stmt = $pdo->prepare("
        SELECT schedule_date, scheduled_start_datetime, scheduled_end_datetime, is_rest_day
        FROM schedules
        WHERE employee_id = ?
        AND schedule_date BETWEEN ? AND ?
    ");
    $stmt->execute([$employeeId, $startDate, $endDate]);

    $indexed = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $indexed[$s['schedule_date']] = $s;
    }
    return $indexed;
}