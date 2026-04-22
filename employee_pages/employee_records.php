<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

date_default_timezone_set('Asia/Manila');

// Set current page
$current_page = 'records';

$employeeId = $_SESSION['user_id'];

$startDate = $_GET['start'] ?? null;
$endDate   = $_GET['end'] ?? null;

if (!$startDate && !$endDate) {
    $startDate = date('Y-m-01');
    $endDate   = date('Y-m-t');
}

// Get Attendance table
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
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// DEBUG — To check if inserting data
echo '<pre style="position:fixed;top:0;right:0;background:#000;color:#0f0;padding:10px;z-index:9999;font-size:11px;max-height:100vh;overflow:auto;">';
echo "Records count: " . count($records) . "\n\n";
foreach ($records as $r) {
    echo "Date: {$r['date']}\n";
    echo "  actual_time_in:  " . var_export($r['actual_time_in'], true) . "\n";
    echo "  actual_time_out: " . var_export($r['actual_time_out'], true) . "\n";
    echo "  scheduled_time_in:  " . var_export($r['scheduled_time_in'], true) . "\n";
    echo "  scheduled_time_out: " . var_export($r['scheduled_time_out'], true) . "\n";
    echo "  strtotime(actual_time_in):  " . strtotime($r['actual_time_in']) . "\n";
    echo "  strtotime(actual_time_out): " . strtotime($r['actual_time_out']) . "\n\n";
}
echo '</pre>';

/* =========================
   SCHEDULES
========================= */
// NEW
$stmtSched = $pdo->prepare("
    SELECT schedule_date, scheduled_start_datetime, scheduled_end_datetime, is_rest_day
    FROM schedules
    WHERE employee_id = ?
    AND schedule_date BETWEEN ? AND ?
");
$stmtSched->execute([$employeeId, $startDate, $endDate]);
$schedulesRaw = $stmtSched->fetchAll(PDO::FETCH_ASSOC);

// Index schedules by schedule_date for O(1) lookup inside the loop
$schedules = [];
foreach ($schedulesRaw as $s) {
    $schedules[$s['schedule_date']] = $s;
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Schedule</title>

    <!-- Bootstrap and Poppins Font -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- CSS -->
    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link rel="stylesheet" href="employee_records.css">
</head>
<body>
    <!-- Include sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Include topbar -->
    <?php
    include '../topbar.php';
    ?>

<div class="recordBoxWrapper">
    <div class="recordBox">

        <div class="recordHeader">
            <div class="dateWrapper">
                <input type="text" id="dateRangePicker"
                    value="<?= date('F j', strtotime($startDate)) ?> – <?= date('F j, Y', strtotime($endDate)) ?>"
                    class="recordTitle"
                    readonly>

                <!-- dropdown icon -->
                <i class="bi bi-chevron-down dateIcon"></i>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', () => {
            flatpickr('#dateRangePicker', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'F j, Y',
                defaultDate: ['<?= $startDate ?>', '<?= $endDate ?>'],

                onChange(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {

                        const start = instance.formatDate(selectedDates[0], "Y-m-d");
                        const end   = instance.formatDate(selectedDates[1], "Y-m-d");

                        window.location.href = `?start=${start}&end=${end}`;
                    }
                }
            });
        });
        </script>

        <div class="ganttContainer">
            <!-- Helper functions -->
            <?php
            // Renders the hover cursor line and its time label
            function gantt_cursor(): string {
                return '<div class="ganttCursor">
                            <div class="ganttCursorLine"></div>
                            <div class="ganttCursorLabel"></div>
                        </div>';
            }

            // Renders hourly time markers along the bottom of the bar container
            function gantt_scale(int $rangeStart, int $rangeEnd): string {
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
            ?>

            <!-- PHP for gantt chart rows -->
            <?php foreach ($records as $row): ?>
            <?php

            // Resets per row to prevent variable bleed throughout iterations
            $schedIn  = null;
            $schedOut = null;

            $dateKey  = $row['work_date'];
            $isToday  = ($dateKey === date('Y-m-d'));
            $isFuture = ($dateKey > date('Y-m-d'));
            $dayLabel = $isToday ? 'Today' : date('l', strtotime($dateKey));
            $dateNum  = date('M d', strtotime($dateKey));
            $sched    = $schedules[$dateKey] ?? null;

            $lateMinutes     = $row['late_minutes'];
            $overtimeMinutes = $row['overtime_minutes'];
            $overtimeStatus  = $row['overtime_status'];
            $status          = $row['status'];

            // scheduled_start_datetime / scheduled_end_datetime are full datetimes,
            // so strtotime() parses them directly — no date prefix needed
            $schedStartDt = ($sched['scheduled_start_datetime'] ?? null) ?: ($row['scheduled_start_datetime'] ?? null);
            $schedEndDt   = ($sched['scheduled_end_datetime']   ?? null) ?: ($row['scheduled_end_datetime']   ?? null);
         
            if ($schedStartDt && $schedStartDt !== '0000-00-00 00:00:00') {
                $schedIn  = strtotime($schedStartDt);
                $schedOut = strtotime($schedEndDt);

                // Handle overnight shifts where end is past midnight
                if ($schedOut && $schedOut <= $schedIn) {
                    $schedOut = strtotime('+1 day', $schedOut);
                }
            }

            /*
            ABSENT / FUTURE ROWS Rows with no actual_time_in or a future date 
            show a grey/pending bar over the scheduled window
            */

            $isFutureOrAbsent = $isFuture || !$row['actual_time_in'];

            if ($isFutureOrAbsent) {
                // Skip rows that have no schedule to display
                if (!$schedIn || !$schedOut) continue;

                // Chart window: 2 hrs padding on each side of the scheduled block
                $rangeStart = strtotime('-2 hours', $schedIn);
                $rangeEnd   = strtotime('+2 hours', $schedOut);
                $range      = max(1, $rangeEnd - $rangeStart);

                // Bar position and width as percentages of the chart window
                $barLeft = (($schedIn  - $rangeStart) / $range) * 100;
                $barWidth = (($schedOut - $schedIn)   / $range) * 100;
                $midLeft  = $barLeft + ($barWidth / 2);

                if ($isFuture) {
                    $barClass   = 'ganttBarPending';
                    $labelClass = 'ganttPendingLabel';
                    $labelText  = 'Pending Schedule';
                } else {
                    $barClass   = 'ganttBarAbsent';
                    $labelClass = 'ganttAbsentLabel';
                    $labelText  = ucfirst($status);
                }
                ?>

                <div class="ganttRow">
                    <div class="ganttLabel">
                        <div><?= $dayLabel ?></div>
                        <div style="font-size: 0.75rem; color: #aaa;"><?= $dateNum ?></div>
                    </div>
                    <div class="ganttBarContainer"
                        data-range-start="<?= $rangeStart ?>"
                        data-range-end="<?= $rangeEnd ?>">

                        <?= gantt_cursor() ?>
                        <?= gantt_scale($rangeStart, $rangeEnd) ?>

                        <div class="ganttBar <?= $barClass ?>"
                            style="left: <?= $barLeft ?>%; width: <?= $barWidth ?>%;"></div>
                        <div class="<?= $labelClass ?>" style="left: <?= $midLeft ?>%">
                            <?= $labelText ?>
                        </div>
                    </div>
                </div>

                <?php continue; ?>
            <?php } ?>

            <?php

            // PRESENT / LATE — has actual_time_in
            $actualIn  = strtotime($row['actual_time_in']);
            $actualOut = $row['actual_time_out'] ? strtotime($row['actual_time_out']) : null;

            // Handle overnight actual shifts where time_out is past midnight
            if ($actualOut && $actualOut <= $actualIn) {
                $actualOut = strtotime('+1 day', $actualOut);
            }

            // Determine no time out BEFORE setting the fallback so rangeMax
            // isn't bloated by the 23:59:59 placeholder
            $noTimeOut = ($actualOut === null && !$isToday);

            // rangeMax: for no time out, cap at schedOut to avoid a massive chart window
            $rangeMin = $schedIn ? min($schedIn, $actualIn) : $actualIn;
            $rangeMax = $noTimeOut
                ? ($schedOut ?? $actualIn)
                : ($schedOut ? max($schedOut, ($actualOut ?? time())) : ($actualOut ?? time()));

            // Chart window: 2 hrs padding on each side of the earliest/latest event
            $rangeStart = strtotime('-2 hours', $rangeMin);
            $rangeEnd   = strtotime('+2 hours', $rangeMax);
            $range      = max(1, $rangeEnd - $rangeStart);

            // Set actualOut fallback only after range is calculated
            // — today: live end at current time; past with no timeout: end of day placeholder
            if ($actualOut === null) {
                $actualOut = $isToday ? time() : strtotime($dateKey . ' 23:59:59');
            }

            // Convert any timestamp to a left % position within the chart window
            $toLeft = fn($ts) => (($ts - $rangeStart) / $range) * 100;

            $actualLeft   = $toLeft($actualIn);
            $actualInPos  = $toLeft($actualIn);
            $actualOutPos = $toLeft($actualOut);

            // Scheduled bar position and width
            $schedLeft  = $schedIn  ? $toLeft($schedIn)  : null;
            $schedWidth = ($schedIn && $schedOut) ? (($schedOut - $schedIn) / $range) * 100 : null;

            // Bar state flags
            $isTardy          = ($lateMinutes > 0);
            $undertimeMinutes = $row['undertime_minutes'];
            $isUndertime      = ($undertimeMinutes > 0 && !$noTimeOut && !$isToday); // only show on past days

            // On-time/no-time-out bar end:
            // — no time out  → cap at scheduled end (purple bar spans sched_in to sched_out)
            // — has overtime → cap at schedOut so the overtime bar picks up from there
            // — normal       → ends at actual time out
            $onTimeEnd = $noTimeOut
                ? ($schedOut ?? $actualOut)
                : (($overtimeMinutes > 0 && $schedOut) ? $schedOut : $actualOut);

            $onTimeWidth = (($onTimeEnd - $actualIn) / $range) * 100;

            // Overtime bar color depends on approval status
            $otColorClass = match($overtimeStatus) {
                'approved' => 'ganttBarOvertimeApproved',
                'rejected' => 'ganttBarOvertimeRejected',
                default    => 'ganttBarOvertimePending'
            };
            ?>

            <div class="ganttRow">
                <div class="ganttLabel">
                    <div><?= $dayLabel ?></div>
                    <div class="ganttSubLabel"><?= $dateNum ?></div>
                </div>

                <div class="ganttBarContainer"
                    data-is-today="<?= $isToday ? '1' : '0' ?>"
                    data-range-start="<?= $rangeStart ?>"
                    data-range-end="<?= $rangeEnd ?>"
                    data-sched-in="<?= $schedIn ? date('g:i A', $schedIn) : '--' ?>"
                    data-sched-out="<?= $schedOut ? date('g:i A', $schedOut) : '--' ?>"
                    data-actual-in="<?= date('g:i A', $actualIn) ?>"
                    data-actual-out="<?= 
                        $row['actual_time_out'] 
                            ? date('g:i A', $actualOut) 
                            : ($isToday ? 'In Progress' : 'No Time Out')
                    ?>"
                    data-late="<?= $lateMinutes > 0 ? $lateMinutes . ' min' : '' ?>"
                    data-overtime="<?= $overtimeMinutes > 0 ? $overtimeMinutes . ' min' : '' ?>"
                    data-overtime-status="<?= $overtimeMinutes > 0 ? $overtimeStatus : '' ?>"
                    data-undertime="<?= $undertimeMinutes > 0 ? $undertimeMinutes . ' min' : '' ?>">
                    <?= gantt_cursor() ?>
                    <?= gantt_scale($rangeStart, $rangeEnd) ?>

                    <!-- Scheduled block — ghost bar showing the expected shift window -->
                    <?php if ($schedIn !== null): ?>
                        <div class="ganttBar ganttBarScheduled"
                            style="left: <?= $schedLeft ?>%; width: <?= $schedWidth ?>%;"></div>
                    <?php endif; ?>

                    <!-- Late bar — fills the gap between sched_in and actual_in -->
                    <?php if ($isTardy): ?>
                        <div class="ganttBar ganttBarTardy"
                            style="left: <?= $toLeft($schedIn) ?>%; width: <?= (($actualIn - $schedIn) / $range) * 100 ?>%;">
                            <span class="ganttBarLabel">Late</span>
                        </div>
                    <?php endif; ?>

                    <!-- Main bar — green (on time) or purple (no time out recorded) -->
                    <div class="ganttBar <?= $noTimeOut ? 'ganttBarNoTimeOut' : 'ganttBarOnTime' ?>"
                        style="left: <?= $actualLeft ?>%; width: <?= $onTimeWidth ?>%;">
                        <span class="ganttBarLabel"><?= $noTimeOut ? 'No Time Out' : 'On Time' ?></span>
                    </div>

                    <!-- Undertime bar — fills the gap between actual_out and sched_out
                         only shown on past days where the employee left early -->
                    <?php if ($isUndertime && $schedOut): ?>
                        <div class="ganttBar ganttBarUndertime"
                            style="left: <?= $toLeft($actualOut) ?>%; width: <?= (($schedOut - $actualOut) / $range) * 100 ?>%;">
                            <span class="ganttBarLabel">Undertime</span>
                        </div>
                    <?php endif; ?>

                    <!-- Overtime bar — extends past sched_out; color reflects approval status -->
                    <?php if ($overtimeMinutes > 0 && $schedOut): ?>
                        <div class="ganttBar <?= $otColorClass ?>"
                            style="left: <?= $toLeft($schedOut) ?>%; width: <?= (($actualOut - $schedOut) / $range) * 100 ?>%;">
                            <span class="ganttBarLabel">Overtime</span>
                        </div>
                    <?php endif; ?>

                    <!-- Markers — thin lines pinpointing exact time in and time out -->
                    <div class="ganttMarker ganttMarkerActualStart" style="left: <?= $actualInPos ?>%"></div>
                    <div class="ganttMarker ganttMarkerActualEnd"   style="left: <?= $actualOutPos ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
</div>

<!-- Tooltip -->
<div id="gantt_tooltip">
    <div class="ganttToolTipRow"><span class="ganttToolTipLabel">Scheduled</span><span class="ganttToolTipValue" id="gt-sched"></span></div>
    <div class="ganttToolTipRow"><span class="ganttToolTipLabel">Time In</span><span class="ganttToolTipValue" id="gt-actual-in"></span></div>
    <div class="ganttToolTipRow"><span class="ganttToolTipLabel">Time Out</span><span class="ganttToolTipValue" id="gt-actual-out"></span></div>
    <div class="ganttToolTipRow ganttToolTipLate" id="gt-late-row"><span class="ganttToolTipLabel">Late</span><span class="ganttToolTipValue" id="gt-late"></span></div>
    <div class="ganttToolTipRow ganttToolTipOverTime" id="gt-ot-row"><span class="ganttToolTipLabel">Overtime</span><span class="ganttToolTipValue" id="gt-ot"></span></div>
    <div class="ganttToolTipRow ganttToolTipUnderTime" id="gt-ut-row"><span class="ganttToolTipLabel">Undertime</span><span class="ganttToolTipValue" id="gt-ut"></span></div>
</div>

<!-- Java Script -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        initGanttCursors();
    });
</script>
</body>
</html>