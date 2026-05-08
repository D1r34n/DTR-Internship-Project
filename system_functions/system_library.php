<?php
/**
 * system_library.php
 * -------------------------------------------------
 * SHARED UTILITY LAYER (PURE HELPERS ONLY)
 *
 * Purpose:
 * This file contains reusable helper functions that are
 * NOT tied to business rules or database logic.
 *
 * Rules:
 * - NO SQL queries
 * - NO attendance decision logic (IN/OUT, schedules, etc.)
 * - ONLY reusable pure functions
 *
 * Used by:
 * - attendance_service.php
 * - other modules that need common calculations
 */

/**
 * Calculate distance between two GPS coordinates in meters
 *
 * Uses Haversine formula.
 *
 * @param float $lat1
 * @param float $lon1
 * @param float $lat2
 * @param float $lon2
 * @return float distance in meters
 */

function distanceMeters($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371000;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) ** 2 +
         cos(deg2rad($lat1)) *
         cos(deg2rad($lat2)) *
         sin($dLon / 2) ** 2;

    return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
}

/* =========================
   GANTT CHART HELPERS
========================= */

function gantt_cursor(): string
{
    return '<div class="ganttCursor">
                <div class="ganttCursorLine"></div>
                <div class="ganttCursorLabel"></div>
            </div>';
}

// Create scale per row where it takes the scheduled time in and scheduled time out (+2 hours)
function gantt_scale(int $rangeStart, int $rangeEnd): string
{
    $html = '<div class="ganttScale">';
    for ($t = $rangeStart; $t <= $rangeEnd; $t += 3600) {
        $pos   = (($t - $rangeStart) / ($rangeEnd - $rangeStart)) * 100;
        $html .= '<div class="ganttScaleItem" style="left: ' . $pos . '%">'
            . date('g:i A', $t)
            . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Compute all display data for a single gantt row.
 *
 * Returns null if the row has no schedule and should be skipped.
 * Returns an array with type='absent_or_future' or type='present'
 * plus all pre-computed positions, widths, labels, and flags.
 */
function computeGanttRow(array $row, ?array $sched): ?array
{    
    // --- Basic date metadata ---
    $dateKey  = $row['work_date'];
    $isToday  = ($dateKey === date('Y-m-d'));
    $isFuture = ($dateKey > date('Y-m-d'));
    $nextDay  = date('Y-m-d', strtotime('+1 day', strtotime($dateKey)));

    // Determine row intent
    $isIncomplete = ($row['status'] === 'incomplete' || $row['status'] === null);
    $isAbsent     = ($row['status'] === 'absent');
    $hasClockedIn = !empty($row['actual_time_in']);

    // --- Determine render mode ---
    if ($isFuture && !$hasClockedIn) {
        // Future shift with no activity yet → pending
        $isFutureOrAbsent = true;
        $isFuturePending  = true;
    } elseif ($isAbsent) {
        // Explicitly marked absent → absent bar
        $isFutureOrAbsent = true;
        $isFuturePending  = false;
    } elseif ($isIncomplete && !$hasClockedIn) {
        // Incomplete with no clock-in yet — treat as in-progress (current shift)
        $isFutureOrAbsent = false;
        $isFuturePending  = false;
    } else {
        // Has actual_time_in or finalized → normal present row
        $isFutureOrAbsent = false;
        $isFuturePending  = false;
    }

    // --- Resolve scheduled start/end from the schedule record, falling back to the attendance row ---
    $schedStartDt = ($sched['scheduled_start'] ?? null) ?: ($row['scheduled_start'] ?? null);
    $schedEndDt   = ($sched['scheduled_end']   ?? null) ?: ($row['scheduled_end']   ?? null);

    // Check if shift crosses midnight to show a date range label
    // Must be done after $schedIn/$schedOut are resolved, so we use the raw datetimes here
    $schedEndTs      = $schedEndDt ? strtotime($schedEndDt) : null;
    $schedStartTs    = $schedStartDt ? strtotime($schedStartDt) : null;
    $crossesMidnight = ($schedEndTs && $schedStartTs) && (
    $schedEndTs <= $schedStartTs ||                          // same-date stored (e.g. 18:00 → 01:00)
    date('Y-m-d', $schedEndTs) !== date('Y-m-d', $schedStartTs) // different-date stored correctly
    );

    // Day label — show range if shift crosses midnight
    if ($isToday) {
        $dayLabel = $crossesMidnight
            ? 'Today – ' . date('l', strtotime($nextDay))
            : 'Today';
    } else {
        $dayLabel = $crossesMidnight
            ? date('l', strtotime($dateKey)) . ' – ' . date('l', strtotime($nextDay))
            : date('l', strtotime($dateKey));
    }

    // Date number label — show range if shift crosses midnight
    $dateNum = $crossesMidnight
        ? date('M j', strtotime($dateKey)) . ' – ' . date('M j', strtotime($nextDay))
        : date('M d', strtotime($dateKey));


    // --- Attendance metrics from the DB row ---
    $lateMinutes      = (int) $row['late_minutes'];
    $undertimeMinutes = (int) $row['undertime_minutes'];
    $breakMinutes     = (int) $row['break_minutes'];
    $overtimeMinutes  = (int) $row['overtime_minutes'];
    $overtimeStatus   = $row['overtime_status'];       // 'approved', 'rejected', or 'pending'
    $status           = $row['status'];                // e.g. 'absent', 'leave', etc.

    // Convert schedule datetimes to Unix timestamps
    $schedIn  = null;
    $schedOut = null;
    if ($schedStartDt && $schedStartDt !== '0000-00-00 00:00:00') {
        $schedIn  = strtotime($schedStartDt);
        $schedOut = strtotime($schedEndDt);

        // If the shift crosses midnight, push schedOut to the next day to keep it after schedIn
        if ($schedOut && $schedOut <= $schedIn) {
            $schedOut = strtotime('+1 day', $schedOut);
        }
    }

    // --- Determine if this row should render as absent or future (no actual check-in recorded) ---
    if ($isFutureOrAbsent) {
        if (!$schedIn || !$schedOut) return null;

        $rangeStart = strtotime('-2 hours', $schedIn);
        $rangeEnd   = strtotime('+2 hours', $schedOut);
        $range      = max(1, $rangeEnd - $rangeStart);

        $barLeft  = (($schedIn  - $rangeStart) / $range) * 100;
        $barWidth = (($schedOut - $schedIn)    / $range) * 100;

        $isRestDay = !empty($sched['is_rest_day']);

        if ($isRestDay) {
            $barClass   = 'ganttBarRestDay';
            $labelClass = 'ganttRestDayLabel';
            $labelText  = 'Rest Day';
        } elseif ($isFuturePending) {
            $barClass   = 'ganttBarPending';
            $labelClass = 'ganttPendingLabel';
            $labelText  = 'Upcoming';
        } else {
            // absent
            $barClass   = 'ganttBarAbsent';
            $labelClass = 'ganttAbsentLabel';
            $labelText  = 'Absent';
        }

        return [
            'type'       => 'absent_or_future',
            'dayLabel'   => $dayLabel,
            'dateNum'    => $dateNum,
            'rangeStart' => $rangeStart,
            'rangeEnd'   => $rangeEnd,
            'barLeft'    => $barLeft,
            'barWidth'   => $barWidth,
            'midLeft'    => $barLeft + ($barWidth / 2),
            'barClass'   => $barClass,
            'labelClass' => $labelClass,
            'labelText'  => $labelText,
        ];
    }

    // --- PRESENT / LATE row ---
    // For incomplete shifts with no actual_time_in yet, use scheduled start as a stand-in
    $actualIn  = $hasClockedIn
        ? strtotime($row['actual_time_in'])
        : ($schedIn ?? strtotime($schedStartDt));
    $actualOut = $row['actual_time_out'] ? strtotime($row['actual_time_out']) : null;

    // Handle midnight-crossing shifts for the actual out time as well
    if ($actualOut && $actualOut <= $actualIn) {
        $actualOut = strtotime('+1 day', $actualOut);
    }

    // Determine if the shift window has fully expired
    // Use 12 hours past scheduled end to account for OT — mirrors finalizeEmployeeAttendance()
    $shiftEnd = $schedOut ?? ($actualOut ?? time());
    $isPast   = time() > ($shiftEnd + (12 * 3600));

    // Mark as "No Time Out" only if shift is fully expired and no valid time-out exists
    $noTimeOut = $isPast && (($actualIn && $actualOut === null) || $row['missed_time_out'] == 1);

    // Determine the outermost timestamps to fit everything in the visible range
    $rangeMin = $schedIn ? min($schedIn, $actualIn) : $actualIn;
    $rangeMax = $noTimeOut
        ? ($schedOut ?? $actualIn)
        : ($schedOut ? max($schedOut, ($actualOut ?? time())) : ($actualOut ?? time()));

        // Pad the range by 2 hours on each side for visual breathing room
        $rangeStart = strtotime('-2 hours', $rangeMin);
        $rangeEnd   = strtotime('+2 hours', $rangeMax);
        $range      = max(1, $rangeEnd - $rangeStart);

    // Save raw time-out before applying fallback
    $rawActualOut = $row['actual_time_out'] ? strtotime($row['actual_time_out']) : null;

    // If still clocked in use current time; for past days with no time-out use end-of-day
    if ($actualOut === null) {
        $actualOut = !$isPast ? time() : strtotime($dateKey . ' 23:59:59');
    }

    // Helper closure: converts a timestamp to a % position within the visible range
    $toLeft = fn($ts) => (($ts - $rangeStart) / $range) * 100;

    // Break bar positioning
    $breakIn  = isset($row['first_break_in']) ? strtotime($row['first_break_in']) : null;
    $breakOut = isset($row['last_break_out']) ? strtotime($row['last_break_out']) : null;

    // Optional fallback ONLY if break_out is missing AND shift is done
    if ($breakIn && !$breakOut && $rawActualOut) {
        $breakOut = $rawActualOut; // fallback, but only if truly missing
    }

    // --- Status flags ---
    $isTardy     = ($lateMinutes > 0);
    $isEarly     = ($actualIn < $schedIn && $schedIn !== null);
    $isOverBreak = $breakMinutes > 60;
    // Undertime only applies to fully completed past shifts
    $isUndertime = ($undertimeMinutes > 0);

    // The main bar starts at schedIn when early (to avoid overlapping the early bar)
    // and ends at scheduled-out when there's overtime, otherwise at actual-out
    $onTimeStart = ($isEarly && $schedIn) ? $schedIn : $actualIn;
    $onTimeEnd   = $noTimeOut
        ? ($schedOut ?? $actualOut)
        : (($overtimeMinutes > 0 && $schedOut) ? $schedOut : $actualOut);
    $onTimeWidth = (($onTimeEnd - $onTimeStart) / $range) * 100;

    // Map overtime approval status to the corresponding CSS class
    $otColorClass = match($overtimeStatus) {
        'approved' => 'ganttBarOvertimeApproved',
        'rejected' => 'ganttBarOvertimeRejected',
        default    => 'ganttBarOvertimePending'
    };

    // Return array
    return [
        'type'               => 'present',
        'dayLabel'           => $dayLabel,
        'dateNum'            => $dateNum,
        'rangeStart'         => $rangeStart,
        'rangeEnd'           => $rangeEnd,
        'isToday'            => $isToday,
        'hasClockedIn'       => $hasClockedIn,

        // Schedule bar positioning
        'schedIn'            => $schedIn,
        'schedOut'           => $schedOut,
        'schedLeft'          => $schedIn  !== null ? $toLeft($schedIn)  : null,
        'schedWidth'         => ($schedIn && $schedOut) ? (($schedOut - $schedIn) / $range) * 100 : null,

        // Main bar positioning
        'actualLeft'         => $toLeft($onTimeStart),
        'actualInPos'        => $hasClockedIn ? $toLeft($actualIn) : null,   // Marker pin for exact time-in moment
        'actualOutPos'       => ($hasClockedIn && $row['actual_time_out']) ? $toLeft($actualOut) : null,  // Marker pin for exact time-out moment
        'noTimeOut'          => $noTimeOut,
        'onTimeWidth'        => $onTimeWidth,

        // Main bar split around break
        'onTimeSplit'        => $breakIn && $breakOut,
        'onTimeLeftWidth'    => ($breakIn && $breakOut) ? (($breakIn - $onTimeStart) / $range) * 100 : 0,
        'onTimeRightLeft'    => ($breakIn && $breakOut) ? $toLeft($breakOut) : null,
        'onTimeRightWidth'   => ($breakIn && $breakOut) ? (($onTimeEnd - $breakOut) / $range) * 100 : 0,

        // Early bar (arrived before scheduled start)
        'isEarly'            => $isEarly,
        'earlyLeft'          => $isEarly && $schedIn ? $toLeft($actualIn) : null,
        'earlyWidth'         => ($isEarly && $schedIn) ? (($schedIn - $actualIn) / $range) * 100 : 0,
        'earlyMinutes'       => ($isEarly && $schedIn) ? (int) floor(($schedIn - $actualIn) / 60) : 0,
        
        // Tardiness bar (gap between scheduled in and actual in)
        'isTardy'            => $isTardy,
        'tardyLeft'          => $schedIn  !== null ? $toLeft($schedIn)  : null,
        'tardyWidth'         => ($isTardy && $schedIn) ? (($actualIn - $schedIn) / $range) * 100 : 0,
        'lateMinutes'        => $lateMinutes,

        // Breaktime bar 
        'breakMinutes' => $breakMinutes,
        'breakLeft'    => $breakIn  ? $toLeft($breakIn)  : null,
        'breakWidth'   => ($breakIn && $breakOut) ? (($breakOut - $breakIn) / $range) * 100 : 0,

        // Undertime bar (gap between actual out and scheduled out)
        'isUndertime'        => $isUndertime,
        'undertimeMinutes'   => $undertimeMinutes,
        'undertimeLeft'      => $toLeft($actualOut),
        'undertimeWidth'     => ($isUndertime && $schedOut) ? (($schedOut - $actualOut) / $range) * 100 : 0,

        // Overtime bar (extension beyond scheduled out)
        'overtimeMinutes'    => $overtimeMinutes,
        'overtimeStatus'     => $overtimeStatus,
        'otColorClass'       => $otColorClass,
        'overtimeLeft'       => $schedOut !== null ? $toLeft($schedOut) : null,
        'overtimeWidth'      => ($overtimeMinutes > 0 && $schedOut) ? (($actualOut - $schedOut) / $range) * 100 : 0,

        // Human-readable tooltip labels
        'schedInLabel'       => $schedIn  ? date('g:i A', $schedIn)  : '--',
        'schedOutLabel'      => $schedOut ? date('g:i A', $schedOut) : '--',
        'earlyLabel'         => ($isEarly && $schedIn) ? (int) floor(($schedIn - $actualIn) / 60) . ' min' : '',
        'actualInLabel'      => $hasClockedIn ? date('g:i A', $actualIn) : '--',
        'actualOutLabel'     => $hasClockedIn
            ? ($row['actual_time_out']
                ? date('g:i A', $actualOut)
                : ($noTimeOut ? 'No Time Out' : 'In Progress'))
            : '--',
        'lateLabel'          => $lateMinutes     > 0 ? $lateMinutes     . ' min' : '',
        'overtimeLabel'      => $overtimeMinutes > 0 ? $overtimeMinutes . ' min' : '',
        'overtimeStatusLabel'=> $overtimeMinutes > 0 ? $overtimeStatus  : '',
        'undertimeLabel'     => $isUndertime ? $undertimeMinutes . ' min' : '',
        'isOverBreak'        => $isOverBreak,
        'overbreakLabel'     => $isOverBreak ? $breakMinutes . ' min' : '',
    ];
}