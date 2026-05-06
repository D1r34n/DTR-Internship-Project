<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId  = intval($_GET['employee_id'] ?? 0);
$year        = intval($_GET['year']  ?? date('Y'));
$month       = intval($_GET['month'] ?? date('n'));
if (!$employeeId) exit();

$firstDay    = sprintf('%04d-%02d-01', $year, $month);
$lastDay     = date('Y-m-t', strtotime($firstDay));
$today       = date('Y-m-d');
$daysInMonth = (int)date('t', strtotime($firstDay));
$startDow    = (int)date('N', strtotime($firstDay)); // 1=Mon, 7=Sun

// Schedules
$stmt = $pdo->prepare("
    SELECT schedule_date, scheduled_start, scheduled_end, status
    FROM schedules
    WHERE employee_id = ? AND schedule_date BETWEEN ? AND ? AND is_rest_day = 0
    ORDER BY schedule_date
");
$stmt->execute([$employeeId, $firstDay, $lastDay]);
$schedMap = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $schedMap[$row['schedule_date']] = $row;
}

// Attendance records
$attnStmt = $pdo->prepare("
    SELECT work_date, actual_time_in, actual_time_out, status,
           late_minutes, overtime_minutes, undertime_minutes, missed_time_out
    FROM attendances
    WHERE employee_id = ? AND work_date BETWEEN ? AND ?
");
$attnStmt->execute([$employeeId, $firstDay, $lastDay]);
$attnMap = [];
foreach ($attnStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $attnMap[$row['work_date']] = $row;
}
?>
<div class="sched-cal-grid">

    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
        <div class="sched-cal-day-header"><?= $d ?></div>
    <?php endforeach; ?>

    <?php for ($i = 1; $i < $startDow; $i++): ?>
        <div class="sched-cal-day empty"></div>
    <?php endfor; ?>

    <?php for ($day = 1; $day <= $daysInMonth; $day++):
        $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $sched    = $schedMap[$dateStr] ?? null;
        $attn     = $attnMap[$dateStr] ?? null;
        $isToday  = ($dateStr === $today);
        $isPast   = ($dateStr < $today);
        $isFuture = ($dateStr > $today);

        $classes = 'sched-cal-day';
        if ($sched)   $classes .= ' has-sched sched-' . $sched['status'];
        if ($isToday) $classes .= ' is-today';
        if ($isPast && !$sched) $classes .= ' is-past';

        $startHour = $sched ? (int)date('H', strtotime($sched['scheduled_start'])) : 8;
        $isNight   = $sched && ($startHour >= 18 || $startHour < 6);

        // Attendance display logic
        $hasActualIn  = $attn && !empty($attn['actual_time_in']);
        $hasActualOut = $attn && !empty($attn['actual_time_out']);
        $isAbsent     = $attn && $attn['status'] === 'absent';
        $lateMin      = $attn ? (int)$attn['late_minutes'] : 0;
        $missedOut    = $attn && $attn['missed_time_out'];

        $attBadgeClass = '';
        $attBadgeText  = '';
        if ($attn) {
            if ($isAbsent) {
                $attBadgeClass = 'att-badge-absent';
                $attBadgeText  = 'Absent';
            } elseif ($hasActualIn && $hasActualOut) {
                $attBadgeClass = $lateMin > 0 ? 'att-badge-late' : 'att-badge-ontime';
                $attBadgeText  = $lateMin > 0 ? 'Late ' . $lateMin . 'm' : 'On Time';
            } elseif ($hasActualIn) {
                $attBadgeClass = 'att-badge-inprogress';
                $attBadgeText  = $missedOut ? 'No Time Out' : 'In Progress';
            } elseif (!$isFuture) {
                $attBadgeClass = 'att-badge-noin';
                $attBadgeText  = 'No Record';
            }
        }

        $actualInVal  = $hasActualIn  ? date('H:i', strtotime($attn['actual_time_in']))  : '';
        $actualOutVal = $hasActualOut ? date('H:i', strtotime($attn['actual_time_out'])) : '';
    ?>
    <div class="<?= $classes ?>">
        <div class="sched-cal-day-num <?= $isToday ? 'is-today-num' : '' ?>"><?= $day ?></div>

        <?php if ($sched): ?>
            <div class="sched-cal-shift-badge <?= $isNight ? 'night' : 'day' ?>">
                <?= $isNight ? 'Night' : 'Day' ?>
            </div>
            <div class="sched-cal-times">
                <?= date('h:i A', strtotime($sched['scheduled_start'])) ?><br>
                <?= date('h:i A', strtotime($sched['scheduled_end'])) ?>
            </div>

            <?php if ($attn): ?>
                <div class="att-separator"></div>
                <div class="att-times">
                    <span class="att-icon"><i class="bi bi-box-arrow-in-right"></i></span>
                    <span><?= $hasActualIn ? date('h:i A', strtotime($attn['actual_time_in'])) : '<span class="att-empty">--</span>' ?></span>
                </div>
                <div class="att-times">
                    <span class="att-icon"><i class="bi bi-box-arrow-right"></i></span>
                    <span><?= $hasActualOut ? date('h:i A', strtotime($attn['actual_time_out'])) : '<span class="att-empty">--</span>' ?></span>
                </div>
                <?php if ($attBadgeText): ?>
                    <div class="att-badge <?= $attBadgeClass ?>"><?= $attBadgeText ?></div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="sched-cal-day-actions">
                <button class="sched-cal-action-btn edit"
                        title="Edit Schedule"
                        onclick="openEditModal(
                            <?= $employeeId ?>,
                            '<?= $dateStr ?>',
                            '<?= date('H:i', strtotime($sched['scheduled_start'])) ?>',
                            '<?= date('H:i', strtotime($sched['scheduled_end'])) ?>'
                        )">
                    <i class="bi bi-pencil-fill"></i>
                </button>
                <?php if ($attn): ?>
                    <button class="sched-cal-action-btn edit-att"
                            title="Edit Attendance"
                            onclick="openEditAttModal(
                                <?= $employeeId ?>,
                                '<?= $dateStr ?>',
                                '<?= $actualInVal ?>',
                                '<?= $actualOutVal ?>'
                            )">
                        <i class="bi bi-clock-fill"></i>
                    </button>
                <?php endif; ?>
                <button class="sched-cal-action-btn delete"
                        title="Delete Schedule"
                        onclick="deleteScheduleDay(<?= $employeeId ?>, '<?= $dateStr ?>')">
                    <i class="bi bi-trash-fill"></i>
                </button>
            </div>

        <?php elseif ($hasActualIn): ?>
            <!-- Attendance exists but no schedule -->
            <div class="att-separator"></div>
            <div class="att-times">
                <span class="att-icon"><i class="bi bi-box-arrow-in-right"></i></span>
                <span><?= date('h:i A', strtotime($attn['actual_time_in'])) ?></span>
            </div>
            <?php if ($hasActualOut): ?>
                <div class="att-times">
                    <span class="att-icon"><i class="bi bi-box-arrow-right"></i></span>
                    <span><?= date('h:i A', strtotime($attn['actual_time_out'])) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($attBadgeText): ?>
                <div class="att-badge <?= $attBadgeClass ?>"><?= $attBadgeText ?></div>
            <?php endif; ?>
            <div class="sched-cal-day-actions">
                <button class="sched-cal-action-btn edit-att"
                        title="Edit Attendance"
                        onclick="openEditAttModal(
                            <?= $employeeId ?>,
                            '<?= $dateStr ?>',
                            '<?= $actualInVal ?>',
                            '<?= $actualOutVal ?>'
                        )">
                    <i class="bi bi-clock-fill"></i>
                </button>
            </div>
        <?php endif; ?>

    </div>
    <?php endfor; ?>

</div>

<?php if (empty($schedMap) && empty($attnMap)): ?>
<div class="sched-cal-empty">
    <i class="bi bi-calendar-x sched-cal-empty-icon"></i>
    <div>No schedules for this month.</div>
</div>
<?php endif; ?>
