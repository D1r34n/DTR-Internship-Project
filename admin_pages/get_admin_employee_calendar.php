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
$daysInMonth = (int) date('t', strtotime($firstDay));
$startDow    = (int) date('N', strtotime($firstDay)); // 1=Mon … 7=Sun

// ---- Schedules (including rest days) ----
$stmt = $pdo->prepare("
    SELECT schedule_date, scheduled_start, scheduled_end, is_rest_day, status
    FROM schedules
    WHERE employee_id = ? AND schedule_date BETWEEN ? AND ?
    ORDER BY schedule_date
");
$stmt->execute([$employeeId, $firstDay, $lastDay]);
$schedMap = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $schedMap[$row['schedule_date']] = $row;
}

// ---- Leave requests ----
$leaveStmt = $pdo->prepare("
    SELECT start_date, end_date, selected_dates, status
    FROM leave_requests
    WHERE employee_id = ?
    AND (start_date BETWEEN ? AND ? OR end_date BETWEEN ? AND ? OR (start_date <= ? AND end_date >= ?))
");
$leaveStmt->execute([$employeeId, $firstDay, $lastDay, $firstDay, $lastDay, $firstDay, $lastDay]);
$leaveMap = [];
foreach ($leaveStmt->fetchAll(PDO::FETCH_ASSOC) as $leave) {
    $dates = json_decode($leave['selected_dates'], true);
    if (is_array($dates) && !empty($dates)) {
        foreach ($dates as $d) {
            if ($d >= $firstDay && $d <= $lastDay) $leaveMap[$d] = $leave['status'];
        }
    } else {
        $cur = new DateTime($leave['start_date']);
        $end = new DateTime($leave['end_date']);
        while ($cur <= $end) {
            $d = $cur->format('Y-m-d');
            if ($d >= $firstDay && $d <= $lastDay) $leaveMap[$d] = $leave['status'];
            $cur->modify('+1 day');
        }
    }
}

// ---- OB requests ----
$obStmt = $pdo->prepare("
    SELECT ob_date, status FROM ob_requests
    WHERE employee_id = ? AND ob_date BETWEEN ? AND ?
");
$obStmt->execute([$employeeId, $firstDay, $lastDay]);
$obMap = [];
foreach ($obStmt->fetchAll(PDO::FETCH_ASSOC) as $ob) {
    $obMap[$ob['ob_date']] = $ob['status'];
}

// ---- Overnight continuation dates ----
$nightContDates = [];
foreach ($schedMap as $date => $sched) {
    if ($sched['is_rest_day'] || !$sched['scheduled_start'] || !$sched['scheduled_end']) continue;
    $endDate = date('Y-m-d', strtotime($sched['scheduled_end']));
    if ($endDate > $date && $endDate <= $lastDay) {
        $nightContDates[$endDate] = true;
    }
}
?>
<div class="sched-cal-grid">

    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $h): ?>
        <div class="sched-cal-day-header"><?= $h ?></div>
    <?php endforeach; ?>

    <?php for ($i = 1; $i < $startDow; $i++): ?>
        <div class="sched-cal-day empty"></div>
    <?php endfor; ?>

    <?php for ($day = 1; $day <= $daysInMonth; $day++):
        $dateStr     = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $sched       = $schedMap[$dateStr] ?? null;
        $leaveStatus = $leaveMap[$dateStr] ?? null;
        $obStatus    = $obMap[$dateStr]    ?? null;
        $isNightCont = isset($nightContDates[$dateStr]);
        $isToday     = ($dateStr === $today);
        $isPast      = ($dateStr < $today);

        // ---- Determine display ----
        $schedInVal  = '';
        $schedOutVal = '';

        // Night-continuation block (always shown first if applicable)
        $contBadgeClass = '';
        $contTimeStr    = '';
        if ($isNightCont) {
            $contBadgeClass = 'night-cont';
            foreach ($schedMap as $_sd => $_s) {
                if (!empty($_s['scheduled_end']) && date('Y-m-d', strtotime($_s['scheduled_end'])) === $dateStr) {
                    $contTimeStr = 'until ' . date('g:i A', strtotime($_s['scheduled_end']));
                    break;
                }
            }
        }

        // Own schedule / leave / OB block
        $badgeClass          = '';
        $badgeText           = '';
        $showTimes           = false;
        $timeInStr           = '';
        $timeOutStr          = '';
        $isRejectedLeaveOrOB = false;

        if ($leaveStatus === 'approved') {
            $badgeClass = 'on-leave';
            $badgeText  = 'On Leave';
        } elseif ($obStatus === 'approved') {
            $badgeClass = 'on-ob';
            $badgeText  = 'On OB';
        } elseif ($leaveStatus === 'pending') {
            $badgeClass = 'leave-pending';
            $badgeText  = 'Leave Pending';
        } elseif ($obStatus === 'pending') {
            $badgeClass = 'leave-pending';
            $badgeText  = 'OB Pending';
        } elseif ($leaveStatus === 'rejected') {
            $badgeClass          = 'leave-rejected';
            $badgeText           = 'Leave Rejected';
            $isRejectedLeaveOrOB = true;
        } elseif ($obStatus === 'rejected') {
            $badgeClass          = 'leave-rejected';
            $badgeText           = 'OB Rejected';
            $isRejectedLeaveOrOB = true;
        } elseif ($sched && $sched['is_rest_day']) {
            $badgeClass = 'rest';
            $badgeText  = 'Rest Day';
        } elseif ($sched) {
            $endTs       = strtotime($sched['scheduled_end']);
            $isOvernight = date('Y-m-d', $endTs) > $dateStr;
            $sh          = (int) date('H', strtotime($sched['scheduled_start']));
            $badgeClass  = ($sh >= 18 || $sh < 6) ? 'night' : 'day';
            $badgeText   = ($sh >= 18 || $sh < 6) ? 'Night' : 'Day';
            $showTimes   = true;
            $timeInStr   = date('g:i A', strtotime($sched['scheduled_start']));
            $timeOutStr  = date('g:i A', $endTs) . ($isOvernight ? ' ↪' : '');
            $schedInVal  = date('H:i', strtotime($sched['scheduled_start']));
            $schedOutVal = date('H:i', $endTs);
        }

        // For rejected leave/OB also show the underlying shift times
        if ($isRejectedLeaveOrOB && $sched && !$sched['is_rest_day']) {
            $endTs       = strtotime($sched['scheduled_end']);
            $isOvernight = date('Y-m-d', $endTs) > $dateStr;
            $showTimes   = true;
            $timeInStr   = date('g:i A', strtotime($sched['scheduled_start']));
            $timeOutStr  = date('g:i A', $endTs) . ($isOvernight ? ' ↪' : '');
            $schedInVal  = date('H:i', strtotime($sched['scheduled_start']));
            $schedOutVal = date('H:i', $endTs);
        }

        $hasContent = $isNightCont || !empty($badgeText);
        $classes    = 'sched-cal-day';
        if ($hasContent)   $classes .= ' has-sched';
        if ($isNightCont && empty($badgeText)) $classes .= ' night-cont-day';
        if ($isToday)      $classes .= ' is-today';
        if ($isPast && !$hasContent) $classes .= ' is-past';
        if (!$hasContent)  $classes .= ' no-sched';
    ?>
    <div class="<?= $classes ?>" data-date="<?= $dateStr ?>"
        <?= !$hasContent ? "onclick=\"openManageModalWithDate('" . $dateStr . "')\"" : '' ?>>
        <div class="sched-cal-day-num <?= $isToday ? 'is-today-num' : '' ?>"><?= $day ?></div>

        <?php if ($isNightCont): ?>
            <span class="sched-cal-shift-badge night-cont"><?= $contTimeStr ?></span>
        <?php endif; ?>

        <?php if (!empty($badgeText)): ?>
            <span class="sched-cal-shift-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
            <?php if ($showTimes): ?>
                <div class="sched-cal-times"><?= $timeInStr ?><br><?= $timeOutStr ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$hasContent): ?>
            <div class="sched-cal-add-overlay">
                <i class="bi bi-plus-circle"></i>
            </div>
        <?php endif; ?>

        <?php $hasActiveLeaveOrOB = in_array($leaveStatus, ['approved','pending']) || in_array($obStatus, ['approved','pending']); ?>
        <?php if ($sched && !$sched['is_rest_day'] && !($isNightCont && empty($badgeText)) && !$hasActiveLeaveOrOB): ?>
            <div class="sched-cal-day-actions">
                <button class="sched-cal-action-btn edit" title="Edit"
                    onclick="openEditModal(
                        <?= $employeeId ?>,
                        '<?= $dateStr ?>',
                        '<?= $schedInVal ?>',
                        '<?= $schedOutVal ?>'
                    ); event.stopPropagation();">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="sched-cal-action-btn delete" title="Delete"
                    onclick="deleteScheduleDay(<?= $employeeId ?>, '<?= $dateStr ?>'); event.stopPropagation();">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>
    <?php endfor; ?>

</div>

