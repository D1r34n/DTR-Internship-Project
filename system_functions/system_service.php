<?php
// Service layer - contains all business logic for attendance processing
// No HTTP logic or session handling here

require_once 'system_library.php';


// Main entry point for processing a time in/out tap
function processAttendanceTap($pdo, $employee_id, $lat, $lng, $accuracy)
{
    // Debug log collector - visible in browser network tab response
    $debug = [];

    // Office location and allowed radius in meters
    $officeLat = 14.584415691940826;
    $officeLng = 120.99562631352414;
    $radius = 100;

    // Compute distance from office using haversine formula
    $distance = distanceMeters($lat, $lng, $officeLat, $officeLng);
    $isWithin = $distance <= $radius ? 1 : 0;
    $debug[] = "Distance from office: {$distance}m, within radius: " . ($isWithin ? 'yes' : 'no');

    // Get the employee's most recent log entry
    $stmt = $pdo->prepare("
        SELECT log_type, log_time
        FROM logs
        WHERE employee_id = ?
        ORDER BY log_time DESC
        LIMIT 1
    ");
    $stmt->execute([$employee_id]);
    $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug[] = "Last log: " . ($lastLog ? "{$lastLog['log_type']} at {$lastLog['log_time']}" : "none");

    // If last log was OUT or no log exists, next action is IN
    $isIn     = (!$lastLog || $lastLog['log_type'] === 'OUT');
    $nextType = $isIn ? 'IN' : 'OUT';
    $response = $isIn ? 'timed_in' : 'timed_out';
    $debug[] = "Next tap type: {$nextType}";

    // Check if there is an active schedule for the employee right now
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
    $debug[] = "Active schedule: " . ($schedule ? "found for {$schedule['schedule_date']}" : "none found");

    // Check if this is the first IN tap for the current shift
    if ($nextType === 'IN' && $schedule) {
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
        $debug[] = "Is first log of shift: " . ($isFirstLogOfShift ? 'yes' : 'no');
    }

    // prevent late first time-in after shift end
    $now = date('Y-m-d H:i:s');

    if ($nextType === 'IN' && $schedule && $isFirstLogOfShift) {

        if ($now > $schedule['scheduled_end_datetime']) {
            $debug[] = "Rejected: first time-in after shift end";

            return [
                'tap'    => 'rejected',
                'reason' => 'Shift already ended (invalid first time-in)',
                'debug'  => $debug
            ];
        }
    }

    // If employee forgot to time out from a previous shift, auto-finalize those expired shifts
    if ($nextType === 'IN' && $lastLog && $lastLog['log_type'] === 'IN') {
        $debug[] = "Detected missed time-out from previous shift, checking for expired shifts";

        $stmt = $pdo->prepare("
            SELECT
                s.schedule_date,
                s.scheduled_start_datetime,
                s.scheduled_end_datetime
            FROM schedules s
            LEFT JOIN attendances a
                ON  a.employee_id = s.employee_id
                AND a.work_date   = s.schedule_date
            WHERE s.employee_id = ?
            AND DATE_ADD(s.scheduled_end_datetime, INTERVAL 6 HOUR) < NOW()
            AND s.is_rest_day = 0
            AND (
                    a.work_date IS NULL
                OR  a.status = 'incomplete'
            )
            ORDER BY s.scheduled_end_datetime DESC
        ");
        $stmt->execute([$employee_id]);
        $expiredShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debug[] = "Expired shifts found: " . count($expiredShifts);

        // Finalize each expired shift individually
        foreach ($expiredShifts as $expiredShift) {
            try {
                finalizeEmployeeAttendance($pdo, $employee_id, $expiredShift);
                $debug[] = "Auto-finalized expired shift: {$expiredShift['schedule_date']}";
            } catch (Throwable $e) {
                $debug[] = "Auto-finalize error for {$expiredShift['schedule_date']}: {$e->getMessage()}";
                error_log("Auto-finalize expired shift error: " . $e->getMessage());
            }
        }
    }

    // Insert the new log entry into the logs table
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
    $debug[] = "Log inserted: {$nextType}";

    // Finalize attendance record for the current shift
    if ($schedule) {
        try {
            finalizeEmployeeAttendance($pdo, $employee_id, $schedule);
            $debug[] = "Attendance finalized for schedule: {$schedule['schedule_date']}";
        } catch (Throwable $e) {
            $debug[] = "Finalize error: {$e->getMessage()}";
            error_log("Attendance finalize error: " . $e->getMessage());
        }
    } else if ($nextType === 'OUT' && $lastLog) {
        // Fallback: find schedule by the date of the last IN log if no active schedule found
        $lastInDate = date('Y-m-d', strtotime($lastLog['log_time']));
        $debug[] = "No active schedule, trying fallback by last IN date: {$lastInDate}";

        $stmt = $pdo->prepare("
            SELECT schedule_date, scheduled_start_datetime, scheduled_end_datetime
            FROM schedules
            WHERE employee_id = ?
              AND schedule_date = ?
            LIMIT 1
        ");
        $stmt->execute([$employee_id, $lastInDate]);
        $fallbackSchedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fallbackSchedule) {
            try {
                finalizeEmployeeAttendance($pdo, $employee_id, $fallbackSchedule);
                $debug[] = "Attendance finalized via fallback for: {$lastInDate}";
            } catch (Throwable $e) {
                $debug[] = "Fallback finalize error: {$e->getMessage()}";
                error_log("Attendance finalize fallback error: " . $e->getMessage());
            }
        } else {
            $debug[] = "Fallback schedule not found for date: {$lastInDate}";
        }
    } else {
        $debug[] = "No schedule found and no fallback triggered";
    }

    // Return result to the controller
    return [
        'tap'                   => $response,
        'log_type'              => $nextType,
        'distance_meters'       => round($distance, 2),
        'is_within_office'      => $isWithin,
        'is_first_log_of_shift' => $isFirstLogOfShift,
        'debug'                 => $debug
    ];
}


// Computes and upserts the final attendance record for a given shift
function finalizeEmployeeAttendance(PDO $pdo, int $employeeId, array $schedule)
{
    $date           = $schedule['schedule_date'];
    $scheduledStart = $schedule['scheduled_start_datetime'];
    $scheduledEnd   = $schedule['scheduled_end_datetime'];

    // Fetch all logs within the shift window, including cross-day taps
    $stmt = $pdo->prepare("
        SELECT log_type, log_time
        FROM logs
        WHERE employee_id = ?
        AND log_time BETWEEN ? AND DATE_ADD(?, INTERVAL 6 HOUR)
        ORDER BY log_time ASC
    ");
    $stmt->execute([$employeeId, $scheduledStart, $scheduledEnd]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // No logs found at all, mark as absent
    if (!$logs) {
        $stmt = $pdo->prepare("
            INSERT INTO attendances (
                employee_id, work_date,
                scheduled_start_datetime, scheduled_end_datetime,
                actual_time_in, actual_time_out,
                total_work_hours, late_minutes,
                undertime_minutes, overtime_minutes, status
            )
            VALUES (?, ?, ?, ?, NULL, NULL, 0, 0, 0, 0, 'absent')
            ON DUPLICATE KEY UPDATE
                status = IF(status IN ('incomplete', 'absent'), 'absent', status)
        ");
        $stmt->execute([$employeeId, $date, $scheduledStart, $scheduledEnd]);
        return;
    }

    // Extract the first IN and last OUT from the shift logs
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

    // Initialize computed fields
    $totalWorkHours   = 0;
    $lateMinutes      = 0;
    $undertimeMinutes = 0;
    $overtimeMinutes  = 0;

    // Total hours worked between first IN and last OUT
    if ($firstIn && $lastOut) {
        $totalWorkHours = (strtotime($lastOut) - strtotime($firstIn)) / 3600;
    }

    // Minutes late = how much after scheduled start the employee timed in
    if ($firstIn) {
        $lateSeconds = strtotime($firstIn) - strtotime($scheduledStart);
        $lateMinutes = max(0, (int) round($lateSeconds / 60));
    }

    // Undertime = left early, overtime = stayed late
    if ($lastOut) {
        $diffEnd          = strtotime($scheduledEnd) - strtotime($lastOut);
        $undertimeMinutes = max(0, (int) round($diffEnd / 60));
        $overtimeMinutes  = max(0, (int) round(-$diffEnd / 60));
    }

    // Determine attendance status based on computed values
    $status = 'incomplete';
    if ($firstIn && $lastOut) {
        if ($lateMinutes > 0)          $status = 'late';
        elseif ($undertimeMinutes > 0) $status = 'undertime';
        else                           $status = 'present';
    }

    // Upsert attendance record, preserving original time_in on duplicate
    $stmt = $pdo->prepare("
        INSERT INTO attendances (
            employee_id, work_date,
            scheduled_start_datetime, scheduled_end_datetime,
            actual_time_in, actual_time_out,
            total_work_hours, late_minutes,
            undertime_minutes, overtime_minutes, status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            scheduled_start_datetime = VALUES(scheduled_start_datetime),
            scheduled_end_datetime   = VALUES(scheduled_end_datetime),
            actual_time_in           = COALESCE(actual_time_in, VALUES(actual_time_in)),
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


// Returns attendance records for a given employee within a date range
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
            overtime_status,
            DATE(actual_time_out) != work_date AS timeout_next_day
        FROM attendances
        WHERE employee_id = ?
        AND work_date BETWEEN ? AND ?
        ORDER BY work_date DESC
    ");
    $stmt->execute([$employeeId, $startDate, $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Returns schedules for a given employee within a date range, indexed by date
function getSchedulesByDateRange(PDO $pdo, int $employeeId, string $startDate, string $endDate): array
{
    $stmt = $pdo->prepare("
        SELECT schedule_date, scheduled_start_datetime, scheduled_end_datetime, is_rest_day
        FROM schedules
        WHERE employee_id = ?
        AND schedule_date BETWEEN ? AND ?
    ");
    $stmt->execute([$employeeId, $startDate, $endDate]);

    // Index by schedule_date for easy lookup
    $indexed = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $indexed[$s['schedule_date']] = $s;
    }
    return $indexed;
}