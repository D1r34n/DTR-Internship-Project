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
        date,
        scheduled_time_in,
        scheduled_time_out,
        actual_time_in,
        actual_time_out,
        total_work_hours,
        status,
        late_minutes,
        undertime_minutes,
        overtime_minutes,
        overtime_status
    FROM attendance
    WHERE employee_id = ?
    AND date BETWEEN ? AND ?
    ORDER BY date DESC
");
$stmt->execute([$employeeId, $startDate, $endDate]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// // DEBUG — To check if inserting data
// echo '<pre style="position:fixed;top:0;right:0;background:#000;color:#0f0;padding:10px;z-index:9999;font-size:11px;max-height:100vh;overflow:auto;">';
// echo "Records count: " . count($records) . "\n\n";
// foreach ($records as $r) {
//     echo "Date: {$r['date']}\n";
//     echo "  actual_time_in:  " . var_export($r['actual_time_in'], true) . "\n";
//     echo "  actual_time_out: " . var_export($r['actual_time_out'], true) . "\n";
//     echo "  scheduled_time_in:  " . var_export($r['scheduled_time_in'], true) . "\n";
//     echo "  scheduled_time_out: " . var_export($r['scheduled_time_out'], true) . "\n";
//     echo "  strtotime(actual_time_in):  " . strtotime($r['actual_time_in']) . "\n";
//     echo "  strtotime(actual_time_out): " . strtotime($r['actual_time_out']) . "\n\n";
// }
// echo '</pre>';

/* =========================
   SCHEDULES
========================= */
$stmtSched = $pdo->prepare("
    SELECT work_date, time_in, time_out, is_rest_day
    FROM schedules
    WHERE employee_id = ?
    AND work_date BETWEEN ? AND ?
");
$stmtSched->execute([$employeeId, $startDate, $endDate]);
$schedulesRaw = $stmtSched->fetchAll(PDO::FETCH_ASSOC);

$schedules = [];
foreach ($schedulesRaw as $s) {
    $schedules[$s['work_date']] = $s;
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTR Project Acer</title>

    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="employee_records.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
<?php include '../sidebar.php'; ?>

<?php
include '../topbar.php';
?>

<div class="recordBoxWrapper">
    <div class="recordBox">

        <div class="recordHeader">
            <div class="recordTitle">
                <?= date('F d, Y', strtotime($startDate)) ?>
                -
                <?= date('F d, Y', strtotime($endDate)) ?>
            </div>

            <form method="GET" class="datePickerForm">
                <input type="date" name="start" value="<?= $startDate ?>">
                <span style="color:#aaa;">to</span>
                <input type="date" name="end" value="<?= $endDate ?>">
                <button type="submit">
                    <i class="bi bi-funnel"></i> Filter
                </button>
            </form>
        </div>

        <div class="gantt">

        <?php foreach ($records as $row): ?>
            <?php
            
            // Scheduled date
            $dateKey = $row['date'];

            $isToday = ($dateKey=== date('Y-m-d'));
            $isFuture = ($dateKey > date('Y-m-d'));

            $dayName = date('l', strtotime($row['date']));
            $dateNum = date('M d', strtotime($row['date']));

            $dayLabel = $isToday ? 'Today' : $dayName;

            $sched   = $schedules[$dateKey] ?? null;

            $lateMinutes     = $row['late_minutes'];
            $overtimeMinutes = $row['overtime_minutes'];
            $overtimeStatus  = $row['overtime_status'];
            $status          = $row['status'];
            
            /* =========================
            SCHEDULE TIMES
            ========================= */
            $timeIn  = ($sched['time_in']  ?? null) ?: ($row['scheduled_time_in']  ?? null);
            $timeOut = ($sched['time_out'] ?? null) ?: ($row['scheduled_time_out'] ?? null);

            if ($timeIn && $timeIn !== '00:00:00') {
                $schedIn  = strtotime($row['date'] . ' ' . $timeIn);
                $schedOut = strtotime($row['date'] . ' ' . $timeOut);
            }

            if ($schedIn && $schedOut && $schedOut <= $schedIn) {
                $schedOut = strtotime('+1 day', $schedOut);
            }

            // Future schedule or pending schedule
            if ($isFuture) {
                // optional: still show schedule range if exists
                if (!$schedIn || !$schedOut) continue;

                $rangeStart = strtotime('-2 hours', $schedIn);
                $rangeEnd   = strtotime('+2 hours', $schedOut);
                $range      = max(1, $rangeEnd - $rangeStart);

                $left  = (($schedIn  - $rangeStart) / $range) * 100;
                $width = (($schedOut - $schedIn)    / $range) * 100;
                ?>

                <div class="gantt-row">
                    <div class="gantt-label">
                        <div><?= $dayLabel ?></div>
                        <div style="font-size: 0.75rem; color: #aaa;">
                            <?= $dateNum ?>
                        </div>
                    </div>

                    <div class="gantt-bar-container"
                        data-range-start="<?= $rangeStart ?>"
                        data-range-end="<?= $rangeEnd ?>">


                        <div class="gantt-cursor">
                            <div class="gantt-cursor-line"></div>
                            <div class="gantt-cursor-label"></div>
                        </div>

                        <div class="gantt-scale">
                            <?php
                            $step = 3600;
                            for ($t = $rangeStart; $t <= $rangeEnd; $t += $step):
                                $pos = (($t - $rangeStart) / ($rangeEnd - $rangeStart)) * 100;
                            ?>
                                <div class="gantt-scale-item" style="left: <?= $pos ?>%">
                                    <?= date('g:i A', $t) ?>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <!-- Pending schedule bar -->
                        <div class="gantt-bar gantt-bar--pending"
                            style="left: <?= $left ?>%; width: <?= $width ?>%;">
                        </div>

                        <div class="gantt-pending-label"
                            style="left: <?= $left + ($width / 2) ?>%">
                            Pending Schedule
                        </div>

                    </div>
                </div>
                <?php continue;
            }

            /* =========================
            ABSENT / INCOMPLETE — no actual_time_in
            ========================= */
            if (!$row['actual_time_in']) {

                // Skip if no schedule to show either
                if (!$schedIn || !$schedOut) continue;

                $rangeStart = strtotime('-2 hours', $schedIn);
                $rangeEnd   = strtotime('+2 hours', $schedOut);
                $range      = max(1, $rangeEnd - $rangeStart);

                $absentLeft  = (($schedIn  - $rangeStart) / $range) * 100;
                $absentWidth = (($schedOut - $schedIn)    / $range) * 100;
                ?>

                <div class="gantt-row">
                    <div class="gantt-label">
                        <div><?= $dayLabel ?></div>
                        <div style="font-size: 0.75rem; color: #aaa;">
                            <?= $dateNum ?>
                        </div>
                    </div>

                    <div class="gantt-bar-container"
                        data-range-start="<?= $rangeStart ?>"
                        data-range-end="<?= $rangeEnd ?>">
                        
                        <div class="gantt-cursor">
                            <div class="gantt-cursor-line"></div>
                            <div class="gantt-cursor-label"></div>
                        </div>

                        <div class="gantt-scale">
                            <?php
                            $step = 3600;
                            for ($t = $rangeStart; $t <= $rangeEnd; $t += $step):
                                $pos = (($t - $rangeStart) / ($rangeEnd - $rangeStart)) * 100;
                            ?>
                                <div class="gantt-scale-item" style="left: <?= $pos ?>%">
                                    <?= date('g:i A', $t) ?>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <!-- Full red bar for absent/incomplete -->
                        <div class="gantt-bar gantt-bar--absent"
                            style="left: <?= $absentLeft ?>%; width: <?= $absentWidth ?>%;">
                        </div>

                        <!-- Status label inside the bar -->
                        <div class="gantt-absent-label"
                            style="left: <?= $absentLeft + ($absentWidth / 2) ?>%">
                            <?= ucfirst($status) ?>
                        </div>

                    </div>
                </div>

                <?php continue; ?>
            <?php } ?>

            <?php
            /* =========================
            PRESENT / LATE — has actual_time_in
            ========================= */
            $actualIn  = strtotime($row['actual_time_in']);
            $actualOut = $row['actual_time_out']
                ? strtotime($row['actual_time_out'])
                : time();

            if ($row['actual_time_out'] && $actualOut <= $actualIn) {
                $actualOut = strtotime('+1 day', $actualOut);
            }

            $rangeMin   = $schedIn ? min($schedIn, $actualIn) : $actualIn;
            $rangeMax   = $schedOut ? max($schedOut, $actualOut) : $actualOut;
            $rangeStart = strtotime('-2 hours', $rangeMin);
            $rangeEnd   = strtotime('+2 hours', $rangeMax);
            $range      = max(1, $rangeEnd - $rangeStart);

            $actualLeft  = (($actualIn  - $rangeStart) / $range) * 100;
            $actualWidth = (($actualOut - $actualIn)   / $range) * 100;

            $schedLeft  = $schedIn ? (($schedIn  - $rangeStart) / $range) * 100 : null;
            $schedWidth = ($schedIn && $schedOut) ? (($schedOut - $schedIn) / $range) * 100 : null;

            $isTardy     = ($lateMinutes > 0);
            $tardLeft    = null;
            $tardWidth   = null;
            $onTimeLeft  = null;
            $onTimeWidth = null;

            if ($isTardy && $schedIn) {
                $tardLeft    = (($schedIn   - $rangeStart) / $range) * 100;
                $tardWidth   = (($actualIn  - $schedIn)    / $range) * 100;
                $onTimeLeft  = (($actualIn  - $rangeStart) / $range) * 100;
                $onTimeWidth = (($actualOut - $actualIn)   / $range) * 100;
            }

            $actualInPos  = (($actualIn  - $rangeStart) / $range) * 100;
            $actualOutPos = (($actualOut - $rangeStart) / $range) * 100;
            ?>

            <div class="gantt-row">
                <div class="gantt-label">
                    <div><?= $dayLabel ?></div>
                    <div class="gantt-sublabel">
                        <?= $dateNum ?>
                    </div>
                </div>
                
                <div class="gantt-bar-container"
                    data-range-start="<?= $rangeStart ?>"
                    data-range-end="<?= $rangeEnd ?>"
                    data-sched-in="<?= $schedIn ? date('g:i A', $schedIn) : '--' ?>"
                    data-sched-out="<?= $schedOut ? date('g:i A', $schedOut) : '--' ?>"
                    data-actual-in="<?= date('g:i A', $actualIn) ?>"
                    data-actual-out="<?= $row['actual_time_out'] ? date('g:i A', $actualOut) : 'In Progress' ?>"
                    data-late="<?= $lateMinutes > 0 ? $lateMinutes . ' min' : '' ?>"
                    data-overtime="<?= $overtimeMinutes > 0 ? $overtimeMinutes . ' min' : '' ?>"
                    data-overtime-status="<?= $overtimeMinutes > 0 ? $overtimeStatus : '' ?>">

                    <div class="gantt-cursor">
                        <div class="gantt-cursor-line"></div>
                        <div class="gantt-cursor-label"></div>
                    </div>

                    <div class="gantt-scale">
                        <?php
                        $step = 3600;
                        for ($t = $rangeStart; $t <= $rangeEnd; $t += $step):
                            $pos = (($t - $rangeStart) / ($rangeEnd - $rangeStart)) * 100;
                        ?>
                            <div class="gantt-scale-item" style="left: <?= $pos ?>%">
                                <?= date('g:i A', $t) ?>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <?php if ($schedIn !== null): ?>
                        <div class="gantt-bar gantt-bar--scheduled"
                            style="left: <?= $schedLeft ?>%; width: <?= $schedWidth ?>%;">
                        </div>
                    <?php endif; ?>
                    
                    <!-- Capping gantt bars -->
                    <?php if ($isTardy): ?>
                        <div class="gantt-bar gantt-bar--tardy"
                            style="left: <?= $tardLeft ?>%; width: <?= $tardWidth ?>%;">
                            <span class="gantt-bar-label">Late</span>
                        </div>
                        <?php
                        // Cap on-time bar at schedOut if overtime exists
                        $onTimeEnd = ($overtimeMinutes > 0 && $schedOut) ? $schedOut : $actualOut;
                        $onTimeWidth = (($onTimeEnd - $actualIn) / $range) * 100;
                        ?>
                        <div class="gantt-bar gantt-bar--ontime"
                            style="left: <?= $onTimeLeft ?>%; width: <?= $onTimeWidth ?>%;">
                            <span class="gantt-bar-label">On Time</span>
                        </div>
                    <?php else: ?>
                        <?php
                        // Cap on-time bar at schedOut if overtime exists
                        $onTimeEnd = ($overtimeMinutes > 0 && $schedOut) ? $schedOut : $actualOut;
                        $actualWidth = (($onTimeEnd - $actualIn) / $range) * 100;
                        ?>
                        <div class="gantt-bar gantt-bar--ontime"
                            style="left: <?= $actualLeft ?>%; width: <?= $actualWidth ?>%;">
                            <span class="gantt-bar-label">On Time</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($overtimeMinutes > 0 && $schedOut): ?>
                        <?php
                        $otLeft  = (($schedOut  - $rangeStart) / $range) * 100;
                        $otWidth = (($actualOut - $schedOut)   / $range) * 100;
                        $otColorClass = match($overtimeStatus) {
                            'approved' => 'gantt-bar--ot-approved',
                            'rejected' => 'gantt-bar--ot-rejected',
                            default    => 'gantt-bar--ot-pending'
                        };
                        ?>
                        <div class="gantt-bar <?= $otColorClass ?>"
                            style="left: <?= $otLeft ?>%; width: <?= $otWidth ?>%;">
                            <span class="gantt-bar-label">Overtime</span>
                        </div>
                    <?php endif; ?>

                    <div class="gantt-marker gantt-marker--actual-start"
                        style="left: <?= $actualInPos ?>%"></div>
                    <div class="gantt-marker gantt-marker--actual-end"
                        style="left: <?= $actualOutPos ?>%"></div>

                </div>
            </div>

        <?php endforeach; ?>

        </div>
    </div>
</div>

<div id="gantt-tooltip">
    <div class="gt-row"><span class="gt-label">Scheduled</span><span class="gt-value" id="gt-sched"></span></div>
    <div class="gt-row"><span class="gt-label">Time In</span><span class="gt-value" id="gt-actual-in"></span></div>
    <div class="gt-row"><span class="gt-label">Time Out</span><span class="gt-value" id="gt-actual-out"></span></div>
    <div class="gt-row gt-late" id="gt-late-row"><span class="gt-label">Late</span><span class="gt-value" id="gt-late"></span></div>
    <div class="gt-row gt-ot" id="gt-ot-row"><span class="gt-label">Overtime</span><span class="gt-value" id="gt-ot"></span></div>
</div>
<script>
    initGanttCursors();
</script>

</body>
</html>