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

// Check if dayshift or nightsift
CONST isNightShift = '9:00 AM';
CONST isDayShift = '7:00 AM';


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
    $dateKey  = $row['work_date'];
    $isToday  = ($dateKey === date('Y-m-d'));
    $isFuture = ($dateKey > date('Y-m-d'));
    $dayLabel = $isToday ? 'Today' : date('l', strtotime($dateKey));
    $dateNum  = date('M d', strtotime($dateKey));

    $lateMinutes      = (int) $row['late_minutes'];
    $undertimeMinutes = (int) $row['undertime_minutes'];
    $overtimeMinutes  = (int) $row['overtime_minutes'];
    $overtimeStatus   = $row['overtime_status'];
    $status           = $row['status'];

    $schedStartDt = ($sched['scheduled_start_datetime'] ?? null) ?: ($row['scheduled_start_datetime'] ?? null);
    $schedEndDt   = ($sched['scheduled_end_datetime']   ?? null) ?: ($row['scheduled_end_datetime']   ?? null);

    $schedIn  = null;
    $schedOut = null;
    if ($schedStartDt && $schedStartDt !== '0000-00-00 00:00:00') {
        $schedIn  = strtotime($schedStartDt);
        $schedOut = strtotime($schedEndDt);
        if ($schedOut && $schedOut <= $schedIn) {
            $schedOut = strtotime('+1 day', $schedOut);
        }
    }

    $isFutureOrAbsent = $isFuture || !$row['actual_time_in'];

    if ($isFutureOrAbsent) {
        if (!$schedIn || !$schedOut) return null;

        $rangeStart = strtotime('-2 hours', $schedIn);
        $rangeEnd   = strtotime('+2 hours', $schedOut);
        $range      = max(1, $rangeEnd - $rangeStart);
        $barLeft    = (($schedIn  - $rangeStart) / $range) * 100;
        $barWidth   = (($schedOut - $schedIn)    / $range) * 100;

        if ($isFuture) {
            $barClass   = 'ganttBarPending';
            $labelClass = 'ganttPendingLabel';
            $labelText  = 'Pending Schedule';
        } else {
            $barClass   = 'ganttBarAbsent';
            $labelClass = 'ganttAbsentLabel';
            $labelText  = ucfirst($status);
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
    $actualIn  = strtotime($row['actual_time_in']);
    $actualOut = $row['actual_time_out'] ? strtotime($row['actual_time_out']) : null;

    if ($actualOut && $actualOut <= $actualIn) {
        $actualOut = strtotime('+1 day', $actualOut);
    }

    $noTimeOut = ($actualOut === null && !$isToday);

    $rangeMin = $schedIn ? min($schedIn, $actualIn) : $actualIn;
    $rangeMax = $noTimeOut
        ? ($schedOut ?? $actualIn)
        : ($schedOut ? max($schedOut, ($actualOut ?? time())) : ($actualOut ?? time()));

    $rangeStart = strtotime('-2 hours', $rangeMin);
    $rangeEnd   = strtotime('+2 hours', $rangeMax);
    $range      = max(1, $rangeEnd - $rangeStart);

    if ($actualOut === null) {
        $actualOut = $isToday ? time() : strtotime($dateKey . ' 23:59:59');
    }

    $toLeft = fn($ts) => (($ts - $rangeStart) / $range) * 100;

    $isTardy     = ($lateMinutes > 0);
    $isUndertime = ($undertimeMinutes > 0 && !$noTimeOut && !$isToday);

    $onTimeEnd   = $noTimeOut
        ? ($schedOut ?? $actualOut)
        : (($overtimeMinutes > 0 && $schedOut) ? $schedOut : $actualOut);
    $onTimeWidth = (($onTimeEnd - $actualIn) / $range) * 100;

    $otColorClass = match($overtimeStatus) {
        'approved' => 'ganttBarOvertimeApproved',
        'rejected' => 'ganttBarOvertimeRejected',
        default    => 'ganttBarOvertimePending'
    };

    return [
        'type'               => 'present',
        'dayLabel'           => $dayLabel,
        'dateNum'            => $dateNum,
        'rangeStart'         => $rangeStart,
        'rangeEnd'           => $rangeEnd,
        'isToday'            => $isToday,
        'schedIn'            => $schedIn,
        'schedOut'           => $schedOut,
        'schedLeft'          => $schedIn  !== null ? $toLeft($schedIn)  : null,
        'schedWidth'         => ($schedIn && $schedOut) ? (($schedOut - $schedIn) / $range) * 100 : null,
        'actualLeft'         => $toLeft($actualIn),
        'actualInPos'        => $toLeft($actualIn),
        'actualOutPos'       => $toLeft($actualOut),
        'noTimeOut'          => $noTimeOut,
        'onTimeWidth'        => $onTimeWidth,
        'isTardy'            => $isTardy,
        'tardyLeft'          => $schedIn  !== null ? $toLeft($schedIn)  : null,
        'tardyWidth'         => ($isTardy && $schedIn) ? (($actualIn - $schedIn) / $range) * 100 : 0,
        'lateMinutes'        => $lateMinutes,
        'isUndertime'        => $isUndertime,
        'undertimeMinutes'   => $undertimeMinutes,
        'undertimeLeft'      => $toLeft($actualOut),
        'undertimeWidth'     => ($isUndertime && $schedOut) ? (($schedOut - $actualOut) / $range) * 100 : 0,
        'overtimeMinutes'    => $overtimeMinutes,
        'overtimeStatus'     => $overtimeStatus,
        'otColorClass'       => $otColorClass,
        'overtimeLeft'       => $schedOut !== null ? $toLeft($schedOut) : null,
        'overtimeWidth'      => ($overtimeMinutes > 0 && $schedOut) ? (($actualOut - $schedOut) / $range) * 100 : 0,
        'schedInLabel'       => $schedIn  ? date('g:i A', $schedIn)  : '--',
        'schedOutLabel'      => $schedOut ? date('g:i A', $schedOut) : '--',
        'actualInLabel'      => date('g:i A', $actualIn),
        'actualOutLabel'     => $row['actual_time_out']
                                    ? date('g:i A', $actualOut)
                                    : ($isToday ? 'In Progress' : 'No Time Out'),
        'lateLabel'          => $lateMinutes     > 0 ? $lateMinutes     . ' min' : '',
        'overtimeLabel'      => $overtimeMinutes > 0 ? $overtimeMinutes . ' min' : '',
        'overtimeStatusLabel'=> $overtimeMinutes > 0 ? $overtimeStatus  : '',
        'undertimeLabel'     => $undertimeMinutes > 0 ? $undertimeMinutes . ' min' : '',
    ];
}
