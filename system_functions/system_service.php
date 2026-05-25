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
        SELECT log_type, log_time
        FROM logs
        WHERE employee_id = ?
        ORDER BY log_time DESC
        LIMIT 1
    ");
    $stmt->execute([$employee_id]);
    $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug[] = "Last log: " . ($lastLog ? "{$lastLog['log_type']} at {$lastLog['log_time']}" : "none");

    // Corrupted log check
    // If the last log is ahead of $now, the log history is corrupted —
    // likely caused by previous system time manipulation during testing
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

    // Check if there is an active schedule for the employee right now
    $isFirstLogOfShift = false;
    $schedule = null;

    if ($nextType === 'IN') {
        // For IN taps, use the time window as before
        $stmt = $pdo->prepare("
            SELECT schedule_date, scheduled_start, scheduled_end
            FROM schedules
            WHERE employee_id = ?
            AND ? BETWEEN DATE_SUB(scheduled_start, INTERVAL 2 HOUR)
                            AND DATE_ADD(scheduled_end, INTERVAL 6 HOUR)
            ORDER BY scheduled_end DESC
            LIMIT 1
        ");
        $stmt->execute([$employee_id, $now]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // For OUT taps, anchor to the date of the last IN log to avoid
        // bleeding into the next day's schedule window
        $lastInDate = $lastLog ? date('Y-m-d', strtotime($lastLog['log_time'])) : null;
        $schedule = null;

        if ($lastInDate) {
            $stmt = $pdo->prepare("
                SELECT schedule_date, scheduled_start, scheduled_end
                FROM schedules
                WHERE employee_id = ?
                AND schedule_date = ?
                LIMIT 1
            ");
            $stmt->execute([$employee_id, $lastInDate]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    $debug[] = "Active schedule: " . ($schedule ? "found for {$schedule['schedule_date']}" : "none found");

    // Check if this is the first IN tap for the current shift
    if ($nextType === 'IN' && $schedule) {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM logs
            WHERE employee_id = ?
              AND log_type = 'IN'
              AND log_time BETWEEN DATE_SUB(?, INTERVAL 2 HOUR) AND DATE_ADD(?, INTERVAL 6 HOUR)
            LIMIT 1
        ");
        $stmt->execute([
            $employee_id,
            $schedule['scheduled_start'],
            $schedule['scheduled_end']
        ]);
        $isFirstLogOfShift = !$stmt->fetchColumn();
        $debug[] = "Is first log of shift: " . ($isFirstLogOfShift ? 'yes' : 'no');
    }

    // Block tap if this is the first IN for a shift that has already ended
    // finalizeExpiredShifts() will handle marking it absent after log insert
    if ($nextType === 'IN' && $schedule && $isFirstLogOfShift && $now > $schedule['scheduled_end']) {
        $debug[] = "Late first time-in after shift end → blocking tap";

        // Directly finalize this shift as absent — can't use finalizeExpiredShifts
        // because the 6hr buffer may not have passed yet
        try {
            finalizeEmployeeAttendance($pdo, $employee_id, $schedule, $now);
            $debug[] = "Shift marked absent: {$schedule['schedule_date']}";
        } catch (Throwable $e) {
            $debug[] = "Finalize error: {$e->getMessage()}";
        }

        // Still run finalizeExpiredShifts to catch any other old unfinalized shifts
        finalizeExpiredShifts($pdo, $employee_id, $now, $debug);

        return [
            'tap'    => 'error',
            'error'  => 'shift_ended',
            'reason' => 'Shift already ended',
            'debug'  => $debug
        ];
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
            distance_meters,
            photo_path
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $employee_id,
        $nextType,
        $now,
        $lat,
        $lng,
        $accuracy,
        $isWithin,
        $distance,
        $photoPath
    ]);
    $debug[] = "Log inserted: {$nextType}";

    // Finalize all expired unfinalized shifts — runs on every tap regardless of IN or OUT
    // Handles: missed time-outs, absent shifts, shifts never started
    finalizeExpiredShifts($pdo, $employee_id, $now, $debug);

    // Finalize attendance record for the current shift
    if ($schedule) {
        try {
            finalizeEmployeeAttendance($pdo, $employee_id, $schedule, $now);
            $debug[] = "Attendance finalized for schedule: {$schedule['schedule_date']}";
        } catch (Throwable $e) {
            $debug[] = "Finalize error: {$e->getMessage()}";
            error_log("Attendance finalize error: " . $e->getMessage());
        }
    } else {
        $debug[] = "No schedule found for this tap";
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

// Relaxed version of processAttendanceTap for testing purposes
// Bypasses: GPS check, debounce, schedule window, first-log-of-shift check
// Only accessible when $testing_mode = true in attendance_tap.php
function processAttendanceTapTest(PDO $pdo, int $employeeId, string $now): array
{
    $debug = [];
    $debug[] = "TEST MODE: using simulated time {$now}";

    // Get last log up to the simulated time only
    // This prevents future logs from affecting the current simulated tap
    $stmt = $pdo->prepare("
        SELECT log_type, log_time
        FROM logs
        WHERE employee_id = ?
        AND log_time <= ?
        ORDER BY log_time DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId, $now]);
    $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug[] = "Last log (up to {$now}): " . ($lastLog ? "{$lastLog['log_type']} at {$lastLog['log_time']}" : "none");

    // Alternate IN/OUT freely regardless of schedule
    $isIn     = (!$lastLog || $lastLog['log_type'] === 'OUT');
    $nextType = $isIn ? 'IN' : 'OUT';
    $response = $isIn ? 'timed_in' : 'timed_out';
    $debug[] = "Next tap type: {$nextType}";

    // Insert log at the simulated time with fake office coordinates
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
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $employeeId,
        $nextType,
        $now,
        14.584415691940826,
        120.99562631352414,
        10,
        1,
        0
    ]);
    $debug[] = "Log inserted: {$nextType} at {$now}";

    // Finalize all expired unfinalized shifts
    finalizeExpiredShifts($pdo, $employeeId, $now, $debug);

    // Find schedule that covers the simulated time — handles midnight-crossing shifts
    $stmt = $pdo->prepare("
        SELECT schedule_date, scheduled_start, scheduled_end
        FROM schedules
        WHERE employee_id = ?
        AND ? BETWEEN DATE_SUB(scheduled_start, INTERVAL 2 HOUR)
                    AND DATE_ADD(scheduled_end, INTERVAL 6 HOUR)
        ORDER BY scheduled_end DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId, $now]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($schedule) {
        finalizeEmployeeAttendance($pdo, $employeeId, $schedule, $now);
        $debug[] = "Attendance finalized for: {$schedule['schedule_date']}";
    } else {
        $debug[] = "No schedule found for simulated time: {$now}";
    }

    return [
        'tap'      => $response,
        'log_type' => $nextType,
        'log_time' => $now,
        'debug'    => $debug,
    ];
}

// Handles break in / break out tap
// Inserts a BREAK_IN or BREAK_OUT log based on the last log state
function processBreakTap(PDO $pdo, int $employeeId, ?float $lat, ?float $lng, ?float $accuracy, ?string $now = null): array
{
    $now   = $now ?? date('Y-m-d H:i:s');
    $debug = [];

    // Office location for distance calculation
    $officeLat = 14.584415691940826;
    $officeLng = 120.99562631352414;
    $radius    = 100;

    $distance = ($lat && $lng)
        ? distanceMeters($lat, $lng, $officeLat, $officeLng)
        : 0;
    $isWithin = $distance <= $radius ? 1 : 0;
    $debug[]  = "Distance from office: {$distance}m, within radius: " . ($isWithin ? 'yes' : 'no');

    // Get the last log to determine break direction
    $stmt = $pdo->prepare("
        SELECT log_type, log_time
        FROM logs
        WHERE employee_id = ?
        ORDER BY log_time DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId]);
    $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug[] = "Last log: " . ($lastLog ? "{$lastLog['log_type']} at {$lastLog['log_time']}" : "none");

    // Determine break direction from last log
    // null = not allowed (employee not timed in)
    $isBreakIn = match($lastLog['log_type'] ?? null) {
        'IN'       => true,   // just timed in → can break in
        'BREAK_IN' => false,  // currently on break → break out
        default    => null,   // BREAK_OUT, OUT, or no log → not allowed
    };

    // Block break if employee is not timed in
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

    // Insert the break log
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
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $employeeId,
        $logType,
        $now,
        $lat ?? $officeLat,
        $lng ?? $officeLng,
        $accuracy ?? 10,
        $isWithin,
        round($distance, 2),
    ]);
    $debug[] = "Break log inserted: {$logType} at {$now}";

    // Finalize attendance so break_minutes is updated immediately after BREAK_OUT
    if ($logType === 'BREAK_OUT') {
        // Find the schedule anchored to the last IN log date
        $stmt = $pdo->prepare("
            SELECT log_type, log_time
            FROM logs
            WHERE employee_id = ?
              AND log_type = 'IN'
              AND log_time <= ?
            ORDER BY log_time DESC
            LIMIT 1
        ");
        $stmt->execute([$employeeId, $now]);
        $lastIn = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lastIn) {
            $lastInDate = date('Y-m-d', strtotime($lastIn['log_time']));

            $stmt = $pdo->prepare("
                SELECT schedule_date, scheduled_start, scheduled_end
                FROM schedules
                WHERE employee_id = ?
                  AND schedule_date = ?
                LIMIT 1
            ");
            $stmt->execute([$employeeId, $lastInDate]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($schedule) {
                try {
                    finalizeEmployeeAttendance($pdo, $employeeId, $schedule, $now);
                    $debug[] = "Attendance finalized after BREAK_OUT for: {$lastInDate}";
                } catch (Throwable $e) {
                    $debug[] = "Finalize error after BREAK_OUT: {$e->getMessage()}";
                    error_log("processBreakTap finalize error: " . $e->getMessage());
                }
            }
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
    $stmt = $pdo->prepare("
        SELECT s.schedule_date, s.scheduled_start, s.scheduled_end
        FROM schedules s
        LEFT JOIN attendances a
            ON  a.employee_id = s.employee_id
            AND a.work_date   = s.schedule_date
        WHERE s.employee_id = ?
        AND DATE_ADD(s.scheduled_end, INTERVAL 6 HOUR) < ?
        AND s.is_rest_day = 0
        AND (
                a.work_date IS NULL
            OR  a.status NOT IN ('present', 'late', 'undertime', 'overtime', 'absent')
        )
        ORDER BY s.scheduled_end ASC
    ");
    $stmt->execute([$employeeId, $now]);
    $expiredShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug[] = "Expired unfinalized shifts found: " . count($expiredShifts);

    foreach ($expiredShifts as $shift) {
        try {
            finalizeEmployeeAttendance($pdo, $employeeId, $shift, $now);
            $debug[] = "Finalized expired shift: {$shift['schedule_date']}";
        } catch (Throwable $e) {
            $debug[] = "Error finalizing {$shift['schedule_date']}: {$e->getMessage()}";
            error_log("finalizeExpiredShifts error: " . $e->getMessage());
        }
    }
}

// Computes and upserts the final attendance record for a given shift
function finalizeEmployeeAttendance(PDO $pdo, int $employeeId, array $schedule, string $now)
{
    $date           = $schedule['schedule_date'];
    $scheduledStart = $schedule['scheduled_start'];
    $scheduledEnd   = $schedule['scheduled_end'];

    // Fetch all logs within the shift window
    $stmt = $pdo->prepare("
        SELECT log_type, log_time
        FROM logs
        WHERE employee_id = ?
        AND log_time BETWEEN DATE_SUB(?, INTERVAL 2 HOUR)
                        AND DATE_ADD(?, INTERVAL 6 HOUR)
        AND log_time <= ?
        ORDER BY log_time ASC
    ");
    $stmt->execute([$employeeId, $scheduledStart, $scheduledEnd, $now]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialize parsed values
    $firstIn      = null;
    $lastOut      = null;
    $lastIn       = null;
    $breakStart   = null;
    $breakMinutes = 0;

    // Parse logs
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

    // If there's an OUT log in the window but no IN log, the employee's IN may be
    // further back than the -2hr window (e.g. very long shift or missed previous time-out)
    // Look backwards from the OUT log to find the most recent IN
    if (!$firstIn && $lastOut) {
        $stmt = $pdo->prepare("
            SELECT log_type, log_time
            FROM logs
            WHERE employee_id = ?
              AND log_type = 'IN'
              AND log_time < ?
            ORDER BY log_time DESC
            LIMIT 1
        ");
        $stmt->execute([$employeeId, $lastOut]);
        $fallbackIn = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fallback to the current date where it sets the time in and out to the current time out
        if ($fallbackIn) {
            $firstIn = $fallbackIn['log_time'];

            //prevent cross-day / unrelated OUT pairing
            if ($lastOut && strtotime($lastOut) - strtotime($firstIn) > 16 * 3600) {
                $lastOut = null;
            }
        }
    }

    if ($firstIn && $lastOut && strtotime($lastOut) < strtotime($firstIn)) {
    $lastOut = null;
    }

    // Close unclosed break
    if ($breakStart !== null) {
        $endTime       = $lastOut ?: ($lastIn ?: $scheduledEnd);
        $breakMinutes += (strtotime($endTime) - strtotime($breakStart)) / 60;
    }

    // Mark absent if no IN log found within the shift window
    if (!$firstIn) {
        $stmt = $pdo->prepare("
            INSERT INTO attendances (
                employee_id, work_date,
                scheduled_start, scheduled_end,
                actual_time_in, actual_time_out,
                total_work_minutes, late_minutes,
                undertime_minutes, overtime_minutes,
                break_minutes, status, missed_time_out
            )
            VALUES (?, ?, ?, ?, NULL, NULL, 0, 0, 0, 0, 0, 'absent', 0)
            ON DUPLICATE KEY UPDATE
                scheduled_start = IF(status = 'incomplete', VALUES(scheduled_start), scheduled_start),
                scheduled_end   = IF(status = 'incomplete', VALUES(scheduled_end),   scheduled_end),
                status          = IF(status = 'incomplete', 'absent',                status),
                missed_time_out = IF(status = 'incomplete', 0,                       missed_time_out)
        ");
        $stmt->execute([$employeeId, $date, $scheduledStart, $scheduledEnd]);
        return;
    }

    // Missed timeout logic
    $shiftExpired  = $now > $scheduledEnd;
    $missedTimeOut = $shiftExpired && $lastIn && (!$lastOut || $lastOut < $lastIn);

    if ($missedTimeOut) {
        $lastOut = null;
    }

    // Compute metrics
    $totalWorkHours   = 0;
    $lateMinutes      = 0;
    $undertimeMinutes = 0;
    $overtimeMinutes  = 0;

    if ($firstIn && $lastOut) {
        $totalWorkHours = (strtotime($lastOut) - strtotime($firstIn)) / 3600;
    }

    if ($firstIn) {
        $lateSeconds = strtotime($firstIn) - strtotime($scheduledStart);
        $lateMinutes = max(0, (int) floor($lateSeconds / 60));
    }

    if ($lastOut) {
        $diffEnd          = strtotime($scheduledEnd) - strtotime($lastOut);
        $undertimeMinutes = max(0, (int) round($diffEnd / 60));
        $overtimeMinutes  = max(0, (int) round(-$diffEnd / 60));
    }

    // Determine status
    // Note: fallback IN cases force 'incomplete' above, so only set here if not already set
    if (!isset($status)) {
        $status = 'incomplete';
        if ($firstIn && $lastOut && ($totalWorkHours * 60) >= 30) {
            $status = 'present';
        }
    }

    // Upsert attendance
    // Once status reaches a terminal state (present, late, undertime, overtime, absent),
    // the record is considered finalized and will not be overwritten
    $stmt = $pdo->prepare("
        INSERT INTO attendances (
            employee_id, work_date,
            scheduled_start, scheduled_end,
            actual_time_in, actual_time_out,
            total_work_minutes, late_minutes,
            undertime_minutes, overtime_minutes,
            break_minutes, status, missed_time_out
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        scheduled_start    = IF(status = 'incomplete', VALUES(scheduled_start),    scheduled_start),
        scheduled_end      = IF(status = 'incomplete', VALUES(scheduled_end),      scheduled_end),
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
        round($breakMinutes, 2),
        $status,
        $missedTimeOut ? 1 : 0,
    ]);
}

// Returns attendance records for a given employee within a date range
function getAttendanceRecords(PDO $pdo, int $employeeId, string $startDate, string $endDate): array
{
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
            DATE(actual_time_out) != work_date AS timeout_next_day,
            (
                SELECT MIN(l.log_time)
                FROM logs l
                WHERE l.employee_id = a.employee_id
                AND l.log_type = 'BREAK_IN'
                AND l.log_time BETWEEN DATE_SUB(a.scheduled_start, INTERVAL 2 HOUR)
                                    AND DATE_ADD(a.scheduled_end, INTERVAL 6 HOUR)
            ) AS first_break_in,
            (
                SELECT MAX(l.log_time)
                FROM logs l
                WHERE l.employee_id = a.employee_id
                AND l.log_type = 'BREAK_OUT'
                AND l.log_time BETWEEN DATE_SUB(a.scheduled_start, INTERVAL 2 HOUR)
                                    AND DATE_ADD(a.scheduled_end, INTERVAL 6 HOUR)
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