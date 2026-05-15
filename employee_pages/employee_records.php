<?php
// Start the session to access session variables
session_start();

// Redirect unauthenticated users to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Load database connection and helper libraries
require_once '../db.php';
require_once '../system_functions/system_library.php';
require_once '../system_functions/system_service.php';

// Set timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// Set the active sidebar item and get the logged-in employee's ID
$current_page = 'records';
$employeeId   = $_SESSION['user_id'];

// Month-based navigation: ?month=YYYY-MM
$rawMonth = $_GET['month'] ?? date('Y-m');
[$yr, $mn] = array_pad(array_map('intval', explode('-', $rawMonth)), 2, 0);
if ($yr < 2000 || $mn < 1 || $mn > 12) { $yr = (int)date('Y'); $mn = (int)date('n'); }
$startDate  = sprintf('%04d-%02d-01', $yr, $mn);
$endDate    = date('Y-m-t', strtotime($startDate));
$monthLabel = date('F Y', strtotime($startDate));

// Fetch attendance records and schedules for the employee within the selected range
$records   = getAttendanceRecords($pdo, $employeeId, $startDate, $endDate);
$schedules = getSchedulesByDateRange($pdo, $employeeId, $startDate, $endDate);

// ---- Leave requests ----
$leaveStmt = $pdo->prepare("
    SELECT start_date, end_date, selected_dates, status
    FROM leave_requests
    WHERE employee_id = ?
    AND (start_date BETWEEN ? AND ? OR end_date BETWEEN ? AND ? OR (start_date <= ? AND end_date >= ?))
");
$leaveStmt->execute([$employeeId, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
$leaveMap = [];
foreach ($leaveStmt->fetchAll(PDO::FETCH_ASSOC) as $leave) {
    $dates = json_decode($leave['selected_dates'], true);
    if (is_array($dates) && !empty($dates)) {
        foreach ($dates as $d) {
            if ($d >= $startDate && $d <= $endDate) $leaveMap[$d] = $leave['status'];
        }
    } else {
        $cur = new DateTime($leave['start_date']);
        $lEnd = new DateTime($leave['end_date']);
        while ($cur <= $lEnd) {
            $d = $cur->format('Y-m-d');
            if ($d >= $startDate && $d <= $endDate) $leaveMap[$d] = $leave['status'];
            $cur->modify('+1 day');
        }
    }
}

// ---- OB requests ----
$obStmt = $pdo->prepare("
    SELECT start_date AS ob_date, status FROM leave_requests
    WHERE employee_id = ? AND leave_type = 'ob leave' AND start_date BETWEEN ? AND ?
");
$obStmt->execute([$employeeId, $startDate, $endDate]);
$obMap = [];
foreach ($obStmt->fetchAll(PDO::FETCH_ASSOC) as $ob) {
    $obMap[$ob['ob_date']] = $ob['status'];
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Schedule</title>

    <!-- 1. Third-party CSS FIRST -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>

    <!-- 2. Your global CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <!-- 3. Page-specific CSS -->
    <link rel="stylesheet" href="employee_records.css">

</head>
<body>
    <?php $currentPage = 'records'; include '../sidebar_revised.php'; ?>

    <!-- MAIN WRAPPER -->
    <div id="main-wrapper">
        <?php include '../topbar_revised.php'; ?>

        <!-- SUMMARY CARDS -->
        <div class="container-fluid flex-shrink-0 px-3 pt-2">
            <?php
                $presenCount = $absentCount = $pendingCount = 0;
                foreach ($records as $r) {
                    if ($r['status'] === 'present')    $presenCount++;
                    elseif ($r['status'] === 'absent') $absentCount++;
                    else                               $pendingCount++;
                }
            ?>

            <div class="row g-3">

                <!-- PENDING -->
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card card-warning p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-warning">
                                <i class="bi bi-hourglass-split fs-4"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number"><?= $pendingCount ?></div>
                                <div class="text-meta">Pending</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PRESENT -->
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card card-success p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-success">
                                <i class="bi bi-check-circle-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number"><?= $presenCount ?></div>
                                <div class="text-meta">Present</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ABSENT -->
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card card-danger p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-danger">
                                <i class="bi bi-x-circle-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number"><?= $absentCount ?></div>
                                <div class="text-meta">Absent</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- MAIN CARD -->
        <div class="card card-neutral records-card">

                <!-- HEADER -->
                <div class="record-header">
                   <div class="d-flex align-items-center gap-2">
                        <div class="dropdown">
                            <button class="btn btn-sm dropdown-toggle" id="month-picker-btn" type="button">
                                <i class="bi bi-calendar3"></i>
                                <span id="dateRangeLabel"><?= $monthLabel ?></span>
                            </button>
                        </div>
                    </div>
                </div>

            <!-- BODY -->
            <div class="card-body d-flex flex-column records-card-body">

                <!-- GANTT CHART -->
                <div class="gantt-container">
                    <?php 
                    $hasRows = false;
                    foreach ($records as $row): ?>
                    <?php
                    // Look up the schedule for this specific work date (may be null if unscheduled)
                    $sched = $schedules[$row['work_date']] ?? null;

                    // Compute all Gantt positioning values for this row
                    $ganttBar = computeGanttRow($row, $sched);

                    // Skip rows that have no renderable data
                    if ($ganttBar === null) continue;
                    $hasRows = true;

                    // Override bar for approved leave / OB
                    $leaveStatus = $leaveMap[$row['work_date']] ?? null;
                    $obStatus    = $obMap[$row['work_date']]    ?? null;
                    if ($leaveStatus === 'approved') {
                        $ganttBar['type']       = 'absent_or_future';
                        $ganttBar['barClass']   = 'ganttBarLeave';
                        $ganttBar['labelClass'] = 'ganttAbsentLabel';
                        $ganttBar['labelText']  = 'On Leave';
                        $ganttBar['barLeft']    = 0;
                        $ganttBar['barWidth']   = 100;
                        $ganttBar['midLeft']    = 50;
                    } elseif ($obStatus === 'approved') {
                        $ganttBar['type']       = 'absent_or_future';
                        $ganttBar['barClass']   = 'ganttBarOB';
                        $ganttBar['labelClass'] = 'ganttAbsentLabel';
                        $ganttBar['labelText']  = 'On OB';
                        $ganttBar['barLeft']    = 0;
                        $ganttBar['barWidth']   = 100;
                        $ganttBar['midLeft']    = 50;
                    }
                    ?>

                    <!-- For absent or pending schedules -->
                    <?php if ($ganttBar['type'] === 'absent_or_future'): ?>
                    <div class="ganttRow">
                        <div class="ganttLabel">
                            <div><?= $ganttBar['dayLabel'] ?></div>
                            <div class="ganttSubLabel"><?= $ganttBar['dateNum'] ?></div>
                        </div>
                        <div class="ganttBarContainer"
                            data-range-start="<?= $ganttBar['rangeStart'] ?>"
                            data-range-end="<?= $ganttBar['rangeEnd'] ?>">
                            <?= gantt_cursor() ?>
                            <?= gantt_scale($ganttBar['rangeStart'], $ganttBar['rangeEnd']) ?>
                            <div class="ganttBar <?= $ganttBar['barClass'] ?>"
                                style="left:<?= $ganttBar['barLeft'] ?>%; width:<?= $ganttBar['barWidth'] ?>%;">
                                <span class="<?= $ganttBar['labelClass'] ?>" style="left:<?= $ganttBar['midLeft'] ?>%;">
                                    <?= $ganttBar['labelText'] ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <?php else: ?>
                    <!-- ── Normal attendance row ── -->
                    <!-- Contains layered bars for schedule, tardiness, worked time, undertime, and overtime -->
                    <div class="ganttRow">
                        <div class="ganttLabel">
                            <div><?= $ganttBar['dayLabel'] ?></div><!-- Abbreviated day name, e.g. "Tue" -->
                            <div class="ganttSubLabel"><?= $ganttBar['dateNum'] ?></div><!-- Numeric date, e.g. "15" -->
                        </div>

                        <!-- Bar container — data-* attributes power the hover tooltip -->
                        <div class="ganttBarContainer"
                            data-is-today="<?= $ganttBar['isToday'] ? '1' : '0' ?>"
                            data-range-start="<?= $ganttBar['rangeStart'] ?>"
                            data-range-end="<?= $ganttBar['rangeEnd'] ?>"
                            data-sched-in="<?= $ganttBar['schedInLabel'] ?>"
                            data-sched-out="<?= $ganttBar['schedOutLabel'] ?>"
                            data-actual-in="<?= $ganttBar['actualInLabel'] ?>"
                            data-actual-out="<?= $ganttBar['actualOutLabel'] ?>"
                            data-early="<?= $ganttBar['earlyLabel'] ?>"
                            data-late="<?= $ganttBar['lateLabel'] ?>"
                            data-overtime="<?= $ganttBar['overtimeLabel'] ?>"
                            data-overtime-status="<?= $ganttBar['overtimeStatusLabel'] ?>"
                            data-undertime="<?= $ganttBar['undertimeLabel'] ?>"
                            data-overbreak="<?= $ganttBar['overbreakLabel'] ?>">

                            <!-- Animated cursor line showing the current time of day -->
                            <?= gantt_cursor() ?>

                            <!-- Scale tick marks (hours) spanning the visible range -->
                            <?= gantt_scale($ganttBar['rangeStart'], $ganttBar['rangeEnd']) ?>

                            <!-- Scheduled block — ghost/outline bar showing the expected shift window -->
                            <?php if ($ganttBar['schedIn'] !== null): ?>
                                <div class="ganttBar ganttBarScheduled"
                                    style="left: <?= $ganttBar['schedLeft'] ?>%; width: <?= $ganttBar['schedWidth'] ?>%;"></div>
                            <?php endif; ?>
                            
                            <!-- Early bar — fills the gap between actual check-in and scheduled start -->
                            <?php if ($ganttBar['isEarly'] && $ganttBar['schedIn']): ?>
                                <div class="ganttBar ganttBarEarly"
                                    style="left: <?= $ganttBar['earlyLeft'] ?>%; width: <?= $ganttBar['earlyWidth'] ?>%;">
                                    <span class="ganttBarLabel">Early</span>
                                </div>
                            <?php endif; ?>

                            <!-- Late bar — fills the gap between scheduled check-in and actual check-in -->
                            <?php if ($ganttBar['isTardy']): ?>
                                <div class="ganttBar ganttBarTardy"
                                    style="left: <?= $ganttBar['tardyLeft'] ?>%; width: <?= $ganttBar['tardyWidth'] ?>%;">
                                    <span class="ganttBarLabel">Late</span>
                                </div>
                            <?php endif; ?>

                            <!-- Main bar — only render if employee has actually clocked in -->
                            <?php if ($ganttBar['hasClockedIn']): ?>
                                <?php if ($ganttBar['onTimeSplit']): ?>
                                    <!-- Left segment: time-in to break-in -->
                                    <div class="ganttBar <?= $ganttBar['noTimeOut'] ? 'ganttBarNoTimeOut' : 'ganttBarOnTime' ?>"
                                        style="left: <?= $ganttBar['actualLeft'] ?>%; width: <?= $ganttBar['onTimeLeftWidth'] ?>%;">
                                        <span class="ganttBarLabel"><?= $ganttBar['noTimeOut'] ? 'No Time Out' : 'On Time' ?></span>
                                    </div>

                                    <!-- Break gap -->
                                    <div class="ganttBar ganttBarBreak"
                                        style="left: <?= $ganttBar['breakLeft'] ?>%; width: <?= $ganttBar['breakWidth'] ?>%;">
                                        <span class="ganttBarLabel">Break</span>
                                    </div>

                                    <!-- Right segment: break-out to time-out -->
                                    <div class="ganttBar <?= $ganttBar['noTimeOut'] ? 'ganttBarNoTimeOut' : 'ganttBarOnTime' ?>"
                                        style="left: <?= $ganttBar['onTimeRightLeft'] ?>%; width: <?= $ganttBar['onTimeRightWidth'] ?>%;">
                                        <?php if ($ganttBar['onTimeRightWidth'] > 5): ?>
                                            <span class="ganttBarLabel"><?= $ganttBar['noTimeOut'] ? 'No Time Out' : 'On Time' ?></span>
                                        <?php endif; ?>
                                    </div>

                                <?php else: ?>
                                    <!-- No break — single bar as before -->
                                    <div class="ganttBar <?= $ganttBar['noTimeOut'] ? 'ganttBarNoTimeOut' : 'ganttBarOnTime' ?>"
                                        style="left: <?= $ganttBar['actualLeft'] ?>%; width: <?= $ganttBar['onTimeWidth'] ?>%;">
                                        <span class="ganttBarLabel"><?= $ganttBar['noTimeOut'] ? 'No Time Out' : 'On Time' ?></span>
                                    </div>
                                <?php endif; ?>

                                <!-- Undertime bar — fills the gap between actual check-out and scheduled end time -->
                                <?php if ($ganttBar['isUndertime'] && $ganttBar['schedOut']): ?>
                                    <div class="ganttBar ganttBarUndertime"
                                        style="left: <?= $ganttBar['undertimeLeft'] ?>%; width: <?= $ganttBar['undertimeWidth'] ?>%;">
                                        <span class="ganttBarLabel">Undertime</span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>      
                            
                            <!-- Overtime bar — extends beyond the scheduled end; color reflects approval status -->
                            <?php if ($ganttBar['overtimeMinutes'] > 0 && $ganttBar['schedOut']): ?>
                                <div class="ganttBar <?= $ganttBar['otColorClass'] ?>"
                                    style="left: <?= $ganttBar['overtimeLeft'] ?>%; width: <?= $ganttBar['overtimeWidth'] ?>%;">
                                    <span class="ganttBarLabel">Overtime</span>
                                </div>
                            <?php endif; ?>

                            <!-- Marker lines — thin vertical lines pinpointing exact time-in and time-out moments -->
                            <?php if ($ganttBar['actualInPos'] !== null): ?>
                                <div class="ganttMarker ganttMarkerActualStart" style="left: <?= $ganttBar['actualInPos'] ?>%"></div>
                            <?php endif; ?>

                            <?php if ($ganttBar['actualOutPos'] !== null): ?>
                                <div class="ganttMarker ganttMarkerActualEnd" style="left: <?= $ganttBar['actualOutPos'] ?>%"></div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <!-- Show empty card if no records -->
                    <?php if (!$hasRows): ?>
                        <div class="gantt-empty">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta">No records found for this period.</div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- Hover tooltip — populated dynamically by initGanttCursors() -->
        <div id="gantt_tooltip">
            <div class="ganttToolTipRow">
                <span class="ganttToolTipLabel">Scheduled</span>
                <span class="ganttToolTipValue" id="gt-sched"></span>
            </div>
            
            <!-- Actual in label -->
            <div class="ganttToolTipRow">
                <span class="ganttToolTipLabel">Time In</span>
                <span class="ganttToolTipValue" id="gt-actual-in"></span>
            </div>
            
            <!-- Actual out label -->
            <div class="ganttToolTipRow">
                <span class="ganttToolTipLabel">Time Out</span>
                <span class="ganttToolTipValue" id="gt-actual-out"></span>
            </div>
            
            <!-- Early row — hidden by default, shown only when employee arrived early -->
            <div class="ganttToolTipRow ganttToolTipEarly" id="gt-early-row">
                <span class="ganttToolTipLabel">Early</span>
                <span class="ganttToolTipValue" id="gt-early"></span>
            </div>

            <!-- Late row — hidden by default, shown only when the employee was tardy -->
            <div class="ganttToolTipRow ganttToolTipLate" id="gt-late-row">
                <span class="ganttToolTipLabel">Late</span>
                <span class="ganttToolTipValue" id="gt-late"></span>
            </div>
            
            <!-- Overbreak row — hidden by default, shown only when the employee exceeded breaktime -->
            <div class="ganttToolTipRow ganttToolTipOverBreak" id="gt-ob-row">
                <span class="ganttToolTipLabel">Overbreak</span>
                <span class="ganttToolTipValue" id="gt-ob"></span>
            </div>

            <!-- Overtime row — hidden by default, shown only when overtime exists -->
            <div class="ganttToolTipRow ganttToolTipOverTime" id="gt-ot-row">
                <span class="ganttToolTipLabel" id="gt-ot-label">Overtime</span>
                <span class="ganttToolTipValue" id="gt-ot"></span>
            </div>
            
            <!-- Undertime row — hidden by default, shown only when undertime exists -->
            <div class="ganttToolTipRow ganttToolTipUnderTime" id="gt-ut-row">
                <span class="ganttToolTipLabel">Undertime</span>
                <span class="ganttToolTipValue" id="gt-ut"></span>
            </div>
        </div>
    </div><!-- #main-wrapper -->

<!-- Bootstrap JS bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="../system_functions/gantt.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Wire up cursor tracking and tooltip behavior for all Gantt rows
        initGanttCursors();
    });

    flatpickr("#month-picker-btn", {
        plugins: [
            new monthSelectPlugin({
                shorthand: true,
                dateFormat: "Y-m",
                altFormat: "F Y"
            })
        ],
        defaultDate: "<?= sprintf('%04d-%02d', $yr, $mn) ?>",
        onChange: function(selectedDates, dateStr) {
            // dateStr = YYYY-MM
            window.location.href = `?month=${dateStr}`;
        }
    });
</script>
</body>
</html>