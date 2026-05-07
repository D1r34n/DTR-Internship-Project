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

// Read optional date range from GET params (used when user picks a range)
$startDate = $_GET['start'] ?? null;
$endDate   = $_GET['end'] ?? null;

// Default to today if no date range is provided
if (!$startDate && !$endDate) {
    $startDate = date('Y-m-d');
    $endDate   = date('Y-m-d');
}

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
    SELECT ob_date, status FROM ob_requests WHERE employee_id = ? AND ob_date BETWEEN ? AND ?
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

    <!-- 2. Your global CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <!-- 3. Page-specific CSS -->
    <link rel="stylesheet" href="employee_records.css">

    <!-- 4. Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>

    <style>
        html, body { height: 100%; margin: 0; }
        body { display: flex; min-height: 100vh; }
        #sidebar { flex-shrink: 0; }
        #main-wrapper {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            min-width: 0;
            overflow-y: auto;
        }
        #topbar { flex-shrink: 0; position: sticky; top: 0; z-index: 100; }
    </style>
</head>
<body>
    <?php $currentPage = 'records'; include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">
        <?php include '../topbar_revised.php'; ?>

        <div class="card card-glass records-card">
            <div class="card-body d-flex flex-column records-card-body">

                <!-- Header: shows a clickable date range that opens the date picker -->
                <div class="recordHeader">
                    <!-- Date Range Picker (Dropdown Style) -->
                    <div class="dropdown">
                        <button class="btn dropdown-toggle" id="datePickerBtn" type="button">
                            <i class="bi bi-calendar3"></i>
                            <span id="dateRangeLabel">
                                <?php
                                $today = date('Y-m-d');
                                if ($startDate === $today && $endDate === $today) {
                                    echo 'Today';
                                } else {
                                    echo date('F j', strtotime($startDate)) . ' – ' . date('F j, Y', strtotime($endDate));
                                }
                                ?>
                            </span>
                        </button>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', () => {

                        const dateLabel = document.getElementById('dateRangeLabel');
                        const btn       = document.getElementById('datePickerBtn');

                        const fp = flatpickr(btn, {
                            mode: 'range',
                            dateFormat: 'Y-m-d',

                            defaultDate: [
                                '<?= $startDate ?>',
                                '<?= $endDate ?>'
                            ],

                            // OPEN calendar when clicking button
                            onOpen() {
                                btn.classList.add('active');
                            },

                            onClose() {
                                btn.classList.remove('active');
                            },

                            // UPDATE + reload
                            onChange(selectedDates, dateStr, instance) {
                                if (selectedDates.length === 2) {

                                    const start = instance.formatDate(selectedDates[0], "Y-m-d");
                                    const end   = instance.formatDate(selectedDates[1], "Y-m-d");
                                    const todayStr = instance.formatDate(new Date(), "Y-m-d");

                                    dateLabel.textContent = (start === end && start === todayStr)
                                        ? 'Today'
                                        : instance.formatDate(selectedDates[0], "F j") + " – " + instance.formatDate(selectedDates[1], "F j, Y");

                                    window.location.href = `?start=${start}&end=${end}`;
                                }
                            }
                        });

                        // open picker when clicking button
                        btn.addEventListener('click', () => {
                            fp.open();
                        });

                    });
                </script>

                <!-- Gantt chart: one row per attendance record -->
                <div class="ganttContainer">
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

                    <?php if ($ganttBar['type'] === 'absent_or_future'): ?>
                    <!-- ── Absent / future day row ── -->
                    <!-- Shows only the schedule ghost bar (or an empty bar for future dates) -->
                    <div class="ganttRow">
                        <div class="ganttLabel">
                            <div><?= $ganttBar['dayLabel'] ?></div><!-- e.g. "Mon" -->
                            <div style="font-size: 0.75rem; color: #aaa;"><?= $ganttBar['dateNum'] ?></div><!-- e.g. "14" -->
                        </div>
                        <div class="ganttBarContainer"
                            data-range-start="<?= $ganttBar['rangeStart'] ?>"
                            data-range-end="<?= $ganttBar['rangeEnd'] ?>">

                            <!-- Animated cursor line showing the current time -->
                            <?= gantt_cursor() ?>
                            <!-- Hour/half-hour tick marks along the timeline axis -->
                            <?= gantt_scale($ganttBar['rangeStart'], $ganttBar['rangeEnd']) ?>

                            <!-- Single bar representing the absent/future status -->
                            <div class="ganttBar <?= $ganttBar['barClass'] ?>"
                                style="left: <?= $ganttBar['barLeft'] ?>%; width: <?= $ganttBar['barWidth'] ?>%;">
                            </div>
                            
                            <!-- Status label centered inside the bar -->
                            <div class="<?= $ganttBar['labelClass'] ?>" style="left: <?= $ganttBar['midLeft'] ?>%">
                                <?= $ganttBar['labelText'] ?>
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

                    <?php if (!$hasRows): ?>
                        <div class="ganttEmpty">
                            <i class="bi bi-calendar-x ganttEmptyIcon"></i>
                            <div>No records found for this period.</div>
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
                <span class="ganttToolTipLabel">Overtime</span>
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
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Wire up cursor tracking and tooltip behavior for all Gantt rows
        initGanttCursors();
    });
</script>
</body>
</html>