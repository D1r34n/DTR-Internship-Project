<?php
// Service layer - contains all business logic for attendance processing
// No HTTP logic or session handling here

require_once 'system_library.php';


// Inserting custom time log in console (F12)
/*

fetch('../system_functions/attendance_tap.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        lat: 14.584415691940826,
        lng: 120.99562631352414,
        accuracy: 10,
        simulated_now: '2026-04-24 18:02:00'
    })
}).then(r => r.json()).then(console.log);

*/

// Main entry point for processing a time in/out tap
function processAttendanceTap($pdo, $employee_id, $lat, $lng, $accuracy, $now = null, $photoPath = null)
{
    // Debug log collector - visible in browser network tab response
    $now = $now ?? date('Y-m-d H:i:s');
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
        SELECT log_type, log_time, schedule_id
        FROM logs
        WHERE employee_id = ?
        ORDER BY log_time DESC
        LIMIT 1
    ");
    $stmt->execute([$employee_id]);
    $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug[] = "Last log: " . ($lastLog ? "{$lastLog['log_type']} at {$lastLog['log_time']}" : "none");

    // Corrupted log check
    if ($lastLog && $lastLog['log_time'] > $now) {
        $debug[] = "Log corruption detected: last log {$lastLog['log_time']} is ahead of current time {$now}";
        return [
            'tap'           => 'error',
            'error'         => 'log_corrupted',
            'message'       => 'Your attendance log is corrupted. Please contact your administrator to fix your records.',
            'last_log_time' => $lastLog['log_time'],
            'current_time'  => $now,
            'debug'         => $debug,
        ];
    }

    // If last log was OUT or no log exists, next action is IN
    $isIn     = (!$lastLog || $lastLog['log_type'] === 'OUT');
    $nextType = $isIn ? 'IN' : 'OUT';
    $response = $isIn ? 'timed_in' : 'timed_out';
    $debug[] = "Next tap type: {$nextType}";

    $scheduleId = null;
    $schedule = null;

    if ($nextType === 'IN') {
        // Find the schedule whose window matches the current clock time
        $stmt = $pdo->prepare("
            SELECT id, schedule_date, scheduled_start, scheduled_end
            FROM schedules
            WHERE employee_id = ?
              AND ? BETWEEN DATE_SUB(scheduled_start, INTERVAL 2 HOUR)
                        AND DATE_ADD(scheduled_end, INTERVAL 6 HOUR)
            ORDER BY scheduled_end DESC
            LIMIT 1
        ");
        $stmt->execute([$employee_id, $now]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        $scheduleId = $schedule ? $schedule['id'] : null;
    } else {
        // For OUT taps, lock onto the schedule track from the original IN log.
        // The last log may be BREAK_OUT or BREAK_IN (not IN) after a break cycle,
        // so fall back to querying the most recent IN log when needed.
        if ($lastLog && $lastLog['log_type'] === 'IN' && $lastLog['schedule_id']) {
            $scheduleId = $lastLog['schedule_id'];
        } else {
            $stmt = $pdo->prepare("
                SELECT schedule_id FROM logs
                WHERE employee_id = ? AND log_type = 'IN'
                ORDER BY log_time DESC
                LIMIT 1
            ");
            $stmt->execute([$employee_id]);
            $inLog      = $stmt->fetch(PDO::FETCH_ASSOC);
            $scheduleId = $inLog ? $inLog['schedule_id'] : null;
        }

        if ($scheduleId) {
            $stmt = $pdo->prepare("SELECT id, schedule_date, scheduled_start, scheduled_end FROM schedules WHERE id = ?");
            $stmt->execute([$scheduleId]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    $debug[] = "Active schedule ID: " . ($scheduleId ? $scheduleId : "none found");

    // Block tap if trying to log IN after a shift has completely ended
    if ($nextType === 'IN' && $schedule && $now > $schedule['scheduled_end']) {
        $debug[] = "Late first time-in after shift end → blocking tap";

        try {
            finalizeEmployeeAttendance($pdo, $employee_id, $scheduleId, $now);
            $debug[] = "Shift finalized with absence: {$scheduleId}";
        } catch (Throwable $e) {
            $debug[] = "Finalize error: {$e->getMessage()}";
        }

        finalizeExpiredShifts($pdo, $employee_id, $now, $debug);

        return [
            'tap'    => 'error',
            'error'  => 'shift_ended',
            'reason' => 'Shift already ended',
            'debug'  => $debug
        ];
    }

    // Insert the new log entry into the logs table containing the track-locked schedule_id
    $stmt = $pdo->prepare("
        INSERT INTO logs (
            employee_id,
            schedule_id,
            log_type,
            log_time,
            latitude,
            longitude,
            accuracy,
            is_within_office,
            distance_meters,
            photo_path
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $employee_id,
        $scheduleId,
        $nextType,
        $now,
        $lat,
        $lng,
        $accuracy,
        $isWithin,
        $distance,
        $photoPath
    ]);
    $debug[] = "Log inserted with Schedule ID [{$scheduleId}]: {$nextType}";

    // Finalize all expired unfinalized shifts
    finalizeExpiredShifts($pdo, $employee_id, $now, $debug);

    // Finalize attendance record for the current specific schedule path
    if ($scheduleId) {
        try {
            finalizeEmployeeAttendance($pdo, $employee_id, $scheduleId, $now);
            $debug[] = "Attendance finalized for schedule: {$scheduleId}";
        } catch (Throwable $e) {
            $debug[] = "Finalize error: {$e->getMessage()}";
            error_log("Attendance finalize error: " . $e->getMessage());
        }
    } else {
        $debug[] = "No schedule track linked to this tap transaction";
    }

    return [
        'tap'               => $response,
        'log_type'          => $nextType,
        'distance_meters'   => round($distance, 2),
        'is_within_office'  => $isWithin,
        'schedule_id'       => $scheduleId,
        'debug'             => $debug
    ];
}

// Relaxed version of processAttendanceTap for testing purposes
function processAttendanceTapTest(PDO $pdo, int $employeeId, string $now): array
{
    $debug = [];
    $debug[] = "TEST MODE: using simulated time {$now}";

    $stmt = $pdo->prepare("
        SELECT log_type, log_time, schedule_id
        FROM logs
        WHERE employee_id = ?
        AND log_time <= ?
        ORDER BY log_time DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId, $now]);
    $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug[] = "Last log (up to {$now}): " . ($lastLog ? "{$lastLog['log_type']} at {$lastLog['log_time']}" : "none");

    $isIn     = (!$lastLog || $lastLog['log_type'] === 'OUT');
    $nextType = $isIn ? 'IN' : 'OUT';
    $response = $isIn ? 'timed_in' : 'timed_out';
    $debug[] = "Next tap type: {$nextType}";

    // Match simulated schedule window
    $scheduleId = null;
    if ($nextType === 'IN') {
        $stmt = $pdo->prepare("
            SELECT id FROM schedules
            WHERE employee_id = ?
            AND ? BETWEEN DATE_SUB(scheduled_start, INTERVAL 2 HOUR)
                      AND DATE_ADD(scheduled_end, INTERVAL 6 HOUR)
            ORDER BY scheduled_end DESC LIMIT 1
        ");
        $stmt->execute([$employeeId, $now]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        $scheduleId = $schedule ? $schedule['id'] : null;
    } else {
        if ($lastLog && $lastLog['log_type'] === 'IN' && $lastLog['schedule_id']) {
            $scheduleId = $lastLog['schedule_id'];
        } else {
            $stmt = $pdo->prepare("
                SELECT schedule_id FROM logs
                WHERE employee_id = ? AND log_type = 'IN'
                ORDER BY log_time DESC
                LIMIT 1
            ");
            $stmt->execute([$employeeId]);
            $inLog      = $stmt->fetch(PDO::FETCH_ASSOC);
            $scheduleId = $inLog ? $inLog['schedule_id'] : null;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO logs (
            employee_id,
            schedule_id,
            log_type,
            log_time,
            latitude,
            longitude,
            accuracy,
            is_within_office,
            distance_meters
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $employeeId,
        $scheduleId,
        $nextType,
        $now,
        14.584415691940826,
        120.99562631352414,
        10,
        1,
        0
    ]);
    $debug[] = "Log inserted: {$nextType} at {$now} with track Schedule ID [{$scheduleId}]";

    finalizeExpiredShifts($pdo, $employeeId, $now, $debug);

    if ($scheduleId) {
        finalizeEmployeeAttendance($pdo, $employeeId, $scheduleId, $now);
        $debug[] = "Attendance finalized for: {$scheduleId}";
    } else {
        $debug[] = "No schedule tracked for simulated time: {$now}";
    }

    return [
        'tap'      => $response,
        'log_type' => $nextType,
        'log_time' => $now,
        'debug'    => $debug,
    ];
}

// Handles break in / break out tap
function processBreakTap(PDO $pdo, int $employeeId, ?float $lat, ?float $lng, ?float $accuracy, ?string $now = null): array
{
    $now   = $now ?? date('Y-m-d H:i:s');
    $debug = [];

    $officeLat = 14.584415691940826;
    $officeLng = 120.99562631352414;
    $radius    = 100;

    $distance = ($lat && $lng) ? distanceMeters($lat, $lng, $officeLat, $officeLng) : 0;
    $isWithin = $distance <= $radius ? 1 : 0;
    $debug[]  = "Distance from office: {$distance}m, within radius: " . ($isWithin ? 'yes' : 'no');

    $stmt = $pdo->prepare("
        SELECT log_type, log_time, schedule_id
        FROM logs
        WHERE employee_id = ?
        ORDER BY log_time DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId]);
    $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug[] = "Last log: " . ($lastLog ? "{$lastLog['log_type']} at {$lastLog['log_time']}" : "none");

    $isBreakIn = match($lastLog['log_type'] ?? null) {
        'IN'       => true,
        'BREAK_IN' => false,
        default    => null,
    };

    if ($isBreakIn === null) {
        $debug[] = "Break blocked: employee is not timed in";
        return [
            'tap'     => 'error',
            'error'   => 'not_timed_in',
            'message' => 'You must be timed in to use break.',
            'debug'   => $debug,
        ];
    }

    $logType  = $isBreakIn ? 'BREAK_IN'  : 'BREAK_OUT';
    $response = $isBreakIn ? 'break_in'  : 'break_out';
    $debug[]  = "Break tap type: {$logType}";

    // Secure the schedule tracking reference from the initial shift-linked check-in
    $scheduleId = $lastLog ? $lastLog['schedule_id'] : null;

    $stmt = $pdo->prepare("
        INSERT INTO logs (
            employee_id,
            schedule_id,
            log_type,
            log_time,
            latitude,
            longitude,
            accuracy,
            is_within_office,
            distance_meters
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $employeeId,
        $scheduleId,
        $logType,
        $now,
        $lat ?? $officeLat,
        $lng ?? $officeLng,
        $accuracy ?? 10,
        $isWithin,
        round($distance, 2),
    ]);
    $debug[] = "Break log inserted: {$logType} for Schedule ID [{$scheduleId}]";

    // Instantly refresh metrics upon checkout
    if ($logType === 'BREAK_OUT' && $scheduleId) {
        try {
            finalizeEmployeeAttendance($pdo, $employeeId, $scheduleId, $now);
            $debug[] = "Attendance finalized after BREAK_OUT for schedule track: {$scheduleId}";
        } catch (Throwable $e) {
            $debug[] = "Finalize error after BREAK_OUT: {$e->getMessage()}";
            error_log("processBreakTap finalize error: " . $e->getMessage());
        }
    }

    return [
        'tap'      => $response,
        'log_type' => $logType,
        'log_time' => $now,
        'debug'    => $debug,
    ];
}

// Finds all expired unfinalized shifts and marks them absent
// Called on every tap — handles missed time-outs, no-shows, and orphaned shifts
function finalizeExpiredShifts(PDO $pdo, int $employeeId, string $now, array &$debug): void
{
    // Fetch expired schedules, linking specifically on schedule_id to isolate multi-shifts
    $stmt = $pdo->prepare("
        SELECT s.id, s.schedule_date, s.scheduled_start, s.scheduled_end
        FROM schedules s
        LEFT JOIN attendances a
            ON a.employee_id = s.employee_id
           AND a.schedule_id = s.id
        WHERE s.employee_id = ?
        AND DATE_ADD(s.scheduled_end, INTERVAL 6 HOUR) < ?
        AND s.is_rest_day = 0
        AND (
            a.id IS NULL
            OR a.status = 'incomplete'
        )
        ORDER BY s.scheduled_end ASC
    ");
    $stmt->execute([$employeeId, $now]);
    $expiredShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug[] = "Expired unfinalized shifts found: " . count($expiredShifts);

    foreach ($expiredShifts as $shift) {
        try {
            // Updated to pass the single schedule ID integer matching Approach 2 design
            finalizeEmployeeAttendance($pdo, $employeeId, (int)$shift['id'], $now);
            $debug[] = "Finalized expired shift ID: {$shift['id']} ({$shift['schedule_date']})";
        } catch (Throwable $e) {
            $debug[] = "Error finalizing shift ID {$shift['id']}: {$e->getMessage()}";
            error_log("finalizeExpiredShifts error: " . $e->getMessage());
        }
    }
}

// Computes and upserts the final attendance record for a given shift
function finalizeEmployeeAttendance(PDO $pdo, int $employeeId, int $scheduleId, string $now)
{
    // 1. Fetch the absolute parameters of the schedule directly
    $stmt = $pdo->prepare("SELECT schedule_date, scheduled_start, scheduled_end FROM schedules WHERE id = ?");
    $stmt->execute([$scheduleId]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$schedule) return;

    $date           = $schedule['schedule_date'];
    $scheduledStart = $schedule['scheduled_start'];
    $scheduledEnd   = $schedule['scheduled_end'];

    // 2. Fetch all logs cleanly mapped to THIS schedule ID (Destroys the time window bug)
    $stmt = $pdo->prepare("
        SELECT log_type, log_time
        FROM logs
        WHERE employee_id = ? AND schedule_id = ? AND log_time <= ?
        ORDER BY log_time ASC
    ");
    $stmt->execute([$employeeId, $scheduleId, $now]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Parse your metrics (Clean chronological processing loop)
    $firstIn      = null;
    $lastOut      = null;
    $lastIn       = null;
    $breakStart   = null;
    $breakMinutes = 0;

    foreach ($logs as $log) {
        if ($log['log_type'] === 'IN') {
            if (!$firstIn) $firstIn = $log['log_time'];
            $lastIn = $log['log_time'];
        }
        if ($log['log_type'] === 'OUT') {
            $lastOut = $log['log_time'];
        }
        if ($log['log_type'] === 'BREAK_IN' && $breakStart === null) {
            $breakStart = $log['log_time'];
        }
        if ($log['log_type'] === 'BREAK_OUT' && $breakStart !== null) {
            $breakMinutes += (strtotime($log['log_time']) - strtotime($breakStart)) / 60;
            $breakStart = null;
        }
    }

    // Auto-close break if they are still on break when checking
    if ($breakStart !== null) {
        $endTime = $lastOut ?: ($lastIn ?: $now);
        $breakMinutes += (strtotime($endTime) - strtotime($breakStart)) / 60;
    }

    // 4. Calculate Absolute Metrics
    $totalWorkHours   = 0;
    $lateMinutes      = 0;
    $undertimeMinutes = 0;
    $overtimeMinutes  = 0;

    // Check if the overall shift timeline has passed +6 hours to mark absent
    $shiftFullyExpired = strtotime($scheduledEnd . ' +6 hours') < strtotime($now);

    if (!$firstIn) {
        if ($shiftFullyExpired) {
            // Write standard absence row to your attendances table
            $stmt = $pdo->prepare("
                INSERT INTO attendances (employee_id, schedule_id, work_date, scheduled_start, scheduled_end, status, total_work_minutes)
                VALUES (?, ?, ?, ?, ?, 'absent', 0)
                ON DUPLICATE KEY UPDATE status = 'absent'
            ");
            $stmt->execute([$employeeId, $scheduleId, $date, $scheduledStart, $scheduledEnd]);
        }
        return; 
    }

    // Check if they missed clocking out after shift completed
    $shiftExpired   = $now > $scheduledEnd;
    $missedTimeOut = $shiftExpired && $lastIn && (!$lastOut || $lastOut < $lastIn);

    if ($missedTimeOut) {
        $lastOut = null; // Enforce missing state rules
    }

    // Math metrics calculations
    if ($firstIn && $lastOut) {
        $totalWorkHours = (strtotime($lastOut) - strtotime($firstIn)) / 3600;
    }

    // Early Time vs Late calculation
    $lateSeconds = strtotime($firstIn) - strtotime($scheduledStart);
    $lateMinutes = max(0, (int) floor($lateSeconds / 60)); // 0 means they were on-time or early

    // Overtime vs Undertime calculation
    if ($lastOut) {
        $diffEnd          = strtotime($scheduledEnd) - strtotime($lastOut);
        $undertimeMinutes = max(0, (int) round($diffEnd / 60));
        $overtimeMinutes  = max(0, (int) round(-$diffEnd / 60));
    }

    // Determine finalized ledger status string
    $status = 'incomplete';
    if ($firstIn && $lastOut) {
        $status = 'present'; 
    }

    // 5. Upsert into your attendances schema table layout
    $stmt = $pdo->prepare("
        INSERT INTO attendances (
            employee_id, schedule_id, work_date,
            scheduled_start, scheduled_end,
            actual_time_in, actual_time_out,
            total_work_minutes, late_minutes,
            undertime_minutes, overtime_minutes,
            break_minutes, status, missed_time_out
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            actual_time_in     = IF(status = 'incomplete', VALUES(actual_time_in), actual_time_in),
            actual_time_out    = IF(status = 'incomplete', COALESCE(VALUES(actual_time_out), actual_time_out), actual_time_out),
            total_work_minutes = IF(status = 'incomplete', VALUES(total_work_minutes), total_work_minutes),
            late_minutes       = IF(status = 'incomplete', VALUES(late_minutes),       late_minutes),
            undertime_minutes  = IF(status = 'incomplete', VALUES(undertime_minutes),  undertime_minutes),
            overtime_minutes   = IF(status = 'incomplete', VALUES(overtime_minutes),   overtime_minutes),
            break_minutes      = IF(status = 'incomplete', VALUES(break_minutes),      break_minutes),
            status             = IF(status = 'incomplete', VALUES(status),             status),
            missed_time_out    = IF(status = 'incomplete', VALUES(missed_time_out),    missed_time_out)
    ");

    $stmt->execute([
        $employeeId, $scheduleId, $date,
        $scheduledStart, $scheduledEnd,
        $firstIn, $lastOut,
        round($totalWorkHours * 60, 2), // store as minutes matching int type
        $lateMinutes,
        $undertimeMinutes,
        $overtimeMinutes,
        round($breakMinutes, 2),
        $status,
        $missedTimeOut ? 1 : 0
    ]);
}

// Returns attendance records for a given employee within a date range
function getAttendanceRecords(PDO $pdo, int $employeeId, string $startDate, string $endDate): array
{
    // Updated break subqueries to leverage the explicit layout
    $stmt = $pdo->prepare("
        SELECT
            work_date,
            scheduled_start,
            scheduled_end,
            actual_time_in,
            actual_time_out,
            total_work_minutes,
            status,
            late_minutes,
            undertime_minutes,
            overtime_minutes,
            break_minutes,
            overtime_status,
            missed_time_out,
            (actual_time_out IS NOT NULL AND DATE(actual_time_out) != work_date) AS timeout_next_day,
            (
                SELECT MIN(l.log_time)
                FROM logs l
                WHERE l.employee_id = a.employee_id
                AND DATE(l.log_time) = a.work_date
                AND l.log_type = 'BREAK_IN'
            ) AS first_break_in,
            (
                SELECT MAX(l.log_time)
                FROM logs l
                WHERE l.employee_id = a.employee_id
                AND DATE(l.log_time) = a.work_date
                AND l.log_type = 'BREAK_OUT'
            ) AS last_break_out
        FROM attendances a
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
        SELECT schedule_date, scheduled_start, scheduled_end, is_rest_day
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