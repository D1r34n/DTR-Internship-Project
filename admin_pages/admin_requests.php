
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$success = "";
$error   = "";

// ---- HANDLE APPROVE / REJECT ----
if (isset($_GET['action'], $_GET['type'], $_GET['id'])) {
    $action = $_GET['action'];
    $type   = $_GET['type'];
    $id     = $_GET['id'];
    $status = ($action === 'approve') ? 'approved' : 'rejected';

    if ($type === 'leave') {
        $pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?")
            ->execute([$status, $id]);

    } elseif ($type === 'overtime') {
        $pdo->prepare("UPDATE overtime_requests SET status = ? WHERE id = ?")
            ->execute([$status, $id]);

        $pdo->prepare("
            UPDATE attendances a
            JOIN overtime_requests o ON a.employee_id = o.employee_id AND a.work_date = o.date
            SET a.overtime_status = ?
            WHERE o.id = ?
        ")->execute([$status, $id]);

    } elseif ($type === 'log_edit') {
        $pdo->prepare("UPDATE log_edit_requests SET status = ? WHERE id = ?")
            ->execute([$status, $id]);

        if ($status === 'approved') {
            $leStmt = $pdo->prepare("SELECT * FROM log_edit_requests WHERE id = ?");
            $leStmt->execute([$id]);
            $le = $leStmt->fetch(PDO::FETCH_ASSOC);

            if ($le['request_type'] === 'time_in') {
                $pdo->prepare("
                    UPDATE attendances SET
                        actual_time_in     = ?,
                        late_minutes       = GREATEST(0, TIMESTAMPDIFF(MINUTE, scheduled_start, ?)),
                        total_work_minutes = GREATEST(0, TIMESTAMPDIFF(MINUTE, ?, COALESCE(actual_time_out, scheduled_end)) - COALESCE(break_minutes, 0)),
                        status             = 'present'
                    WHERE id = ?
                ")->execute([
                    $le['requested_time_in'],
                    $le['requested_time_in'],
                    $le['requested_time_in'],
                    $le['attendance_id'],
                ]);

                if ($le['log_id']) {
                    $pdo->prepare("UPDATE logs SET log_time = ? WHERE id = ?")
                        ->execute([$le['requested_time_in'], $le['log_id']]);
                }

            } elseif ($le['request_type'] === 'time_out') {
                $pdo->prepare("
                    UPDATE attendances SET
                        actual_time_out    = ?,
                        missed_time_out    = 0,
                        status             = 'present',
                        total_work_minutes = GREATEST(0, TIMESTAMPDIFF(MINUTE, actual_time_in, ?) - COALESCE(break_minutes, 0)),
                        undertime_minutes  = GREATEST(0, TIMESTAMPDIFF(MINUTE, ?, scheduled_end)),
                        overtime_minutes   = GREATEST(0, TIMESTAMPDIFF(MINUTE, scheduled_end, ?))
                    WHERE id = ?
                ")->execute([
                    $le['requested_time_out'],
                    $le['requested_time_out'],
                    $le['requested_time_out'],
                    $le['requested_time_out'],
                    $le['attendance_id'],
                ]);

                if ($le['log_id']) {
                    $pdo->prepare("UPDATE logs SET log_time = ? WHERE id = ?")
                        ->execute([$le['requested_time_out'], $le['log_id']]);
                }

            } else { // both
                $pdo->prepare("
                    UPDATE attendances SET
                        actual_time_in     = ?,
                        actual_time_out    = ?,
                        missed_time_out    = 0,
                        status             = 'present',
                        late_minutes       = GREATEST(0, TIMESTAMPDIFF(MINUTE, scheduled_start, ?)),
                        total_work_minutes = GREATEST(0, TIMESTAMPDIFF(MINUTE, ?, ?) - COALESCE(break_minutes, 0)),
                        undertime_minutes  = GREATEST(0, TIMESTAMPDIFF(MINUTE, ?, scheduled_end)),
                        overtime_minutes   = GREATEST(0, TIMESTAMPDIFF(MINUTE, scheduled_end, ?))
                    WHERE id = ?
                ")->execute([
                    $le['requested_time_in'],
                    $le['requested_time_out'],
                    $le['requested_time_in'],
                    $le['requested_time_in'],
                    $le['requested_time_out'],
                    $le['requested_time_out'],
                    $le['requested_time_out'],
                    $le['attendance_id'],
                ]);

                if ($le['log_id']) {
                    $pdo->prepare("UPDATE logs SET log_time = ? WHERE id = ?")
                        ->execute([$le['requested_time_in'], $le['log_id']]);
                }

                $outLog = $pdo->prepare("
                    SELECT id FROM logs
                    WHERE employee_id = ? AND log_type = 'OUT' AND DATE(log_time) = ?
                    ORDER BY log_time ASC LIMIT 1
                ");
                $outLog->execute([$le['employee_id'], $le['work_date']]);
                $outLogId = $outLog->fetchColumn();
                if ($outLogId) {
                    $pdo->prepare("UPDATE logs SET log_time = ? WHERE id = ?")
                        ->execute([$le['requested_time_out'], $outLogId]);
                }
            }
        }

    }

    $success = "Request has been " . ucfirst($status) . "!";
}

// ---- GET SUMMARY COUNTS ----
$pendingLeave     = $pdo->query("SELECT COUNT(*) FROM leave_requests    WHERE status = 'pending'")->fetchColumn();
$approvedLeave    = $pdo->query("SELECT COUNT(*) FROM leave_requests    WHERE status = 'approved'")->fetchColumn();
$rejectedLeave    = $pdo->query("SELECT COUNT(*) FROM leave_requests    WHERE status = 'rejected'")->fetchColumn();
$pendingOvertime  = $pdo->query("SELECT COUNT(*) FROM overtime_requests WHERE status = 'pending'")->fetchColumn();
$approvedOvertime = $pdo->query("SELECT COUNT(*) FROM overtime_requests WHERE status = 'approved'")->fetchColumn();
$rejectedOvertime = $pdo->query("SELECT COUNT(*) FROM overtime_requests WHERE status = 'rejected'")->fetchColumn();
$pendingOB        = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type = 'ob leave' AND status = 'pending'")->fetchColumn();
$approvedOB       = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type = 'ob leave' AND status = 'approved'")->fetchColumn();
$rejectedOB       = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type = 'ob leave' AND status = 'rejected'")->fetchColumn();
$pendingLogEdit   = $pdo->query("SELECT COUNT(*) FROM log_edit_requests   WHERE status = 'pending'")->fetchColumn();
$approvedLogEdit  = $pdo->query("SELECT COUNT(*) FROM log_edit_requests   WHERE status = 'approved'")->fetchColumn();
$rejectedLogEdit  = $pdo->query("SELECT COUNT(*) FROM log_edit_requests   WHERE status = 'rejected'")->fetchColumn();

$totalPending  = $pendingLeave  + $pendingOvertime  + $pendingOB  + $pendingLogEdit;
$totalApproved = $approvedLeave + $approvedOvertime + $approvedOB + $approvedLogEdit;
$totalRejected = $rejectedLeave + $rejectedOvertime + $rejectedOB + $rejectedLogEdit;
$totalOvertime = $pendingOvertime + $approvedOvertime + $rejectedOvertime;
$totalOB       = $pendingOB + $approvedOB + $rejectedOB;
$totalLogEdit  = $pendingLogEdit + $approvedLogEdit + $rejectedLogEdit;

// ---- GET LEAVE REQUESTS (non-OB) ----
$leaveRequests = $pdo->query("
    SELECT lr.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    WHERE lr.leave_type != 'ob leave'
    ORDER BY lr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ---- GET OVERTIME REQUESTS ----
$overtimeRequests = $pdo->query("
    SELECT or2.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
    FROM overtime_requests or2
    JOIN employees e ON or2.employee_id = e.id
    ORDER BY or2.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ---- GET OB REQUESTS ----
$obRequests = $pdo->query("
    SELECT lr.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    WHERE lr.leave_type = 'ob leave'
    ORDER BY lr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ---- GET LOG EDIT REQUESTS ----
$logEditRequests = $pdo->query("
    SELECT
        le.id,
        le.employee_id,
        le.attendance_id,
        le.log_id,
        le.request_type,
        le.requested_time_in,
        le.requested_time_out,
        le.reason,
        le.status,
        le.created_at,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
        COALESCE(a.work_date, le.work_date) AS work_date,
        l.log_time AS original_log_time
    FROM log_edit_requests le
    JOIN employees e ON le.employee_id = e.id
    LEFT JOIN attendances a ON le.attendance_id = a.id
    LEFT JOIN logs l ON le.log_id = l.id
    ORDER BY le.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ---- HELPER FUNCTIONS ----
function getStatusBadge($status) {
    $badges = [
        'pending'  => '<span class="badge status-pending">Pending</span>',
        'approved' => '<span class="badge status-approved">Approved</span>',
        'rejected' => '<span class="badge status-rejected">Rejected</span>',
    ];
    return $badges[$status] ?? '<span class="badge">Unknown</span>';
}

function getActionButtons($type, $id, $status) {
    if ($status === 'pending') {
        return '
            <a href="admin_requests.php?action=approve&type=' . $type . '&id=' . $id . '" class="btn btn-sm btn-success" onclick="return confirm(\'Approve this request?\')">
                <i class="bi bi-check-lg"></i> Approve
            </a>
            <a href="admin_requests.php?action=reject&type=' . $type . '&id=' . $id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Reject this request?\')">
                <i class="bi bi-x-lg"></i> Reject
            </a>
        ';
    }
    return '<span class="no-action-text">No actions</span>';
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request Management</title>

    <!-- 1. Bootstrap FIRST -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <!-- 2. Your global CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <!-- 3. Page-specific CSS -->
    <link rel="stylesheet" href="admin_requests.css">
</head>
<body>

    <?php $currentPage = 'employee_requests'; include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">

        <?php include '../topbar_revised.php'; ?>

        <!-- PAGE WRAPPER -->
        <div class="card card-glass requests-card">
            <div class="card-body d-flex flex-column requests-card-body">

                <!-- SUMMARY CARDS -->
                
                <div class="row g-2 mb-4">
                    <!-- Pending -->
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card card-glass card-pending h-100">
                            <div class="card-body d-flex align-items-center justify-content-between">

                                <!-- ICON -->
                                <div class="icon-wrap">
                                    <i class="bi bi-hourglass-split fs-4"></i>
                                </div>

                                <!-- TEXT -->
                                <div class="d-flex flex-column text-end">
                                    <p class="mb-0 text-meta">Pending</p>
                                    <h5 class="mb-0 stats-number"><?= $totalPending ?></h5>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Approved -->
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card card-glass card-success h-100">
                            <div class="card-body d-flex align-items-center justify-content-between">

                                <div class="icon-wrap icon-success">
                                    <i class="bi bi-check-circle-fill fs-4"></i>
                                </div>

                                <div class="d-flex flex-column text-end">
                                    <p class="mb-0 text-meta">Approved</p>
                                    <h5 class="mb-0 stats-number"><?= $totalApproved ?></h5>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Rejected -->
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card card-glass card-danger h-100">
                            <div class="card-body d-flex align-items-center justify-content-between">

                                <div class="icon-wrap icon-danger">
                                    <i class="bi bi-x-circle-fill fs-4"></i>
                                </div>

                                <div class="d-flex flex-column text-end">
                                    <p class="mb-0 text-meta">Rejected</p>
                                    <h5 class="mb-0 stats-number"><?= $totalRejected ?></h5>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Overtime -->
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card card-glass card-overtime h-100">
                            <div class="card-body d-flex align-items-center justify-content-between">

                                <div class="icon-wrap icon-info">
                                    <i class="bi bi-clock-history fs-4"></i>
                                </div>

                                <div class="d-flex flex-column text-end">
                                    <p class="mb-0 text-meta">Overtime</p>
                                    <h5 class="mb-0 stats-number"><?= $totalOvertime ?></h5>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Official Business -->
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card card-glass card-ob h-100">
                            <div class="card-body d-flex align-items-center justify-content-between">

                                <div class="icon-wrap icon-info">
                                    <i class="bi bi-briefcase-fill fs-4"></i>
                                </div>

                                <div class="d-flex flex-column text-end">
                                    <p class="mb-0 text-meta">OB</p>
                                    <h5 class="mb-0 stats-number"><?= $totalOB ?></h5>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Log Edits -->
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card card-glass card-log-edit h-100">
                            <div class="card-body d-flex align-items-center justify-content-between">

                                <div class="icon-wrap icon-warning">
                                    <i class="bi bi-pencil-square fs-4"></i>
                                </div>

                                <div class="d-flex flex-column text-end">
                                    <p class="mb-0 text-meta">Log Edits</p>
                                    <h5 class="mb-0 stats-number"><?= $totalLogEdit ?></h5>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>

                <!-- TITLE ROW -->
                <div class="adminTitleRow">
                    <span class="text-primary">Request Management</span>
                    <input type="text" id="searchInput" class="searchInput" placeholder="Search employee..." onkeyup="searchTable()">
                </div>

                <!-- SUCCESS ALERT -->
               <!-- TOAST -->
                <?php if ($success || $error): ?>
                    <div class="custom-toast <?= $success ? 'toast-success' : 'toast-error' ?>" id="customToast">
                        <div class="toast-content">
                            <i class="bi <?= $success ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> toast-icon"></i>
                            <span>
                                <?= htmlspecialchars($success ?: $error) ?>
                            </span>
                        </div>
                        <button class="toast-close" onclick="closeToast()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- TABS -->
                <div class="reqTabButtons">
                    <button class="reqTabBtn active" onclick="switchReqTab('all', this)">All</button>
                    <button class="reqTabBtn" onclick="switchReqTab('leave', this)">Leave</button>
                    <button class="reqTabBtn" onclick="switchReqTab('overtime', this)">Overtime</button>
                    <button class="reqTabBtn" onclick="switchReqTab('ob', this)">Official Business</button>
                    <button class="reqTabBtn" onclick="switchReqTab('log-edit', this)">Log Edit</button>
                </div>

                <!-- ALL TAB -->
                <div id="all" class="reqTabContent">
                    <div class="tableScrollWrapper">
                        <table class="table table-bordered table-hover mt-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Details</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaveRequests as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                        <td><span class="badge request-leave"><?= ucfirst($row['leave_type']) ?></span></td>
                                        <td><?= date('M d', strtotime($row['start_date'])) ?> - <?= date('M d, Y', strtotime($row['end_date'])) ?></td>
                                        <td class="reasonCol"><?= htmlspecialchars($row['reason']) ?></td>
                                        <td><?= getStatusBadge($row['status']) ?></td>
                                        <td class="actionsCol"><?= getActionButtons('leave', $row['id'], $row['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php foreach ($overtimeRequests as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                        <td><span class="badge request-overtime">Overtime</span></td>
                                        <td><?= date('M d, Y', strtotime($row['date'])) ?> | <?= date('h:i A', strtotime($row['time_in'])) ?> - <?= date('h:i A', strtotime($row['time_out'])) ?></td>
                                        <td class="reasonCol"><?= htmlspecialchars($row['reason']) ?></td>
                                        <td><?= getStatusBadge($row['status']) ?></td>
                                        <td class="actionsCol"><?= getActionButtons('overtime', $row['id'], $row['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php foreach ($obRequests as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                        <td><span class="badge request-official-business">Official Business</span></td>
                                        <td><?= date('M d, Y', strtotime($row['start_date'])) ?> | <?= htmlspecialchars($row['client_name']) ?></td>
                                        <td class="reasonCol"><?= htmlspecialchars($row['reason']) ?></td>
                                        <td><?= getStatusBadge($row['status']) ?></td>
                                        <td class="actionsCol"><?= getActionButtons('leave', $row['id'], $row['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php foreach ($logEditRequests as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                        <td><span class="badge request-log-edit">Log Edit</span></td>
                                        <td><?= date('M d, Y', strtotime($row['work_date'])) ?> |
                                            <?php
                                            $origTime = $row['original_log_time'] ? date('h:i A', strtotime($row['original_log_time'])) : '—';
                                            ?>
                                            <?php if ($row['request_type'] === 'time_in'): ?>
                                                In: <?= $origTime ?> &rarr; <?= date('h:i A', strtotime($row['requested_time_in'])) ?>
                                            <?php elseif ($row['request_type'] === 'time_out'): ?>
                                                Out: <?= $origTime ?> &rarr; <?= date('h:i A', strtotime($row['requested_time_out'])) ?>
                                            <?php else: ?>
                                                <?= $origTime ?> &rarr; In: <?= date('h:i A', strtotime($row['requested_time_in'])) ?> | Out: <?= date('h:i A', strtotime($row['requested_time_out'])) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="reasonCol"><?= htmlspecialchars($row['reason']) ?></td>
                                        <td><?= getStatusBadge($row['status']) ?></td>
                                        <td class="actionsCol"><?= getActionButtons('log_edit', $row['id'], $row['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($leaveRequests) && empty($overtimeRequests) && empty($obRequests) && empty($logEditRequests)): ?>
                                    <tr><td colspan="6" class="text-center">No requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- LEAVE TAB -->
                <div id="leave" class="reqTabContent" style="display:none;">
                    <div class="tableScrollWrapper">
                        <table class="table table-bordered table-hover mt-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($leaveRequests) > 0): ?>
                                    <?php foreach ($leaveRequests as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                            <td><span class="badge leaveBadge"><?= ucfirst($row['leave_type']) ?></span></td>
                                            <td><?= date('M d, Y', strtotime($row['start_date'])) ?></td>
                                            <td><?= date('M d, Y', strtotime($row['end_date'])) ?></td>
                                            <td class="reasonCol"><?= htmlspecialchars($row['reason']) ?></td>
                                            <td><?= getStatusBadge($row['status']) ?></td>
                                            <td class="actionsCol"><?= getActionButtons('leave', $row['id'], $row['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center">No leave requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- OVERTIME TAB -->
                <div id="overtime" class="reqTabContent" style="display:none;">
                    <div class="tableScrollWrapper">
                        <table class="table table-bordered table-hover mt-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($overtimeRequests) > 0): ?>
                                    <?php foreach ($overtimeRequests as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                            <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                                            <td><?= date('h:i A', strtotime($row['time_in'])) ?></td>
                                            <td><?= date('h:i A', strtotime($row['time_out'])) ?></td>
                                            <td class="reasonCol"><?= htmlspecialchars($row['reason']) ?></td>
                                            <td><?= getStatusBadge($row['status']) ?></td>
                                            <td class="actionsCol"><?= getActionButtons('overtime', $row['id'], $row['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center">No overtime requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- LOG EDIT TAB -->
                <div id="log-edit" class="reqTabContent" style="display:none;">
                    <div class="tableScrollWrapper">
                        <table class="table table-bordered table-hover mt-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Current Log</th>
                                    <th>Correction</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logEditRequests) > 0): ?>
                                    <?php foreach ($logEditRequests as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                            <td><?= date('M d, Y', strtotime($row['work_date'])) ?></td>
                                            <td>
                                                <?php
                                                $typeLabels = ['time_in' => 'Time In', 'time_out' => 'Time Out', 'both' => 'Both'];
                                                echo htmlspecialchars($typeLabels[$row['request_type']] ?? $row['request_type']);
                                                ?>
                                            </td>
                                            <td><?= $row['original_log_time'] ? date('h:i A', strtotime($row['original_log_time'])) : '—' ?></td>
                                            <td>
                                                <?php if ($row['request_type'] === 'time_in'): ?>
                                                    In: <?= date('h:i A', strtotime($row['requested_time_in'])) ?>
                                                <?php elseif ($row['request_type'] === 'time_out'): ?>
                                                    Out: <?= date('h:i A', strtotime($row['requested_time_out'])) ?>
                                                <?php else: ?>
                                                    In: <?= date('h:i A', strtotime($row['requested_time_in'])) ?> &rarr; Out: <?= date('h:i A', strtotime($row['requested_time_out'])) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="reasonCol"><?= htmlspecialchars($row['reason']) ?></td>
                                            <td><?= getStatusBadge($row['status']) ?></td>
                                            <td class="actionsCol"><?= getActionButtons('log_edit', $row['id'], $row['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center">No log edit requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- OFFICIAL BUSINESS TAB -->
                <div id="ob" class="reqTabContent" style="display:none;">
                    <div class="tableScrollWrapper">
                        <table class="table table-bordered table-hover mt-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Client Name</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($obRequests) > 0): ?>
                                    <?php foreach ($obRequests as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                            <td><?= date('M d, Y', strtotime($row['start_date'])) ?></td>
                                            <td><?= htmlspecialchars($row['client_name']) ?></td>
                                            <td class="reasonCol"><?= htmlspecialchars($row['reason']) ?></td>
                                            <td><?= getStatusBadge($row['status']) ?></td>
                                            <td class="actionsCol"><?= getActionButtons('leave', $row['id'], $row['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center">No OB requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL -->
        <div class="modalOverlay" id="modalOverlay" style="display:none;">
            <div class="modalBox">
                <div class="modalHeader">
                    <h6 id="modalTitle">Request Details</h6>
                    <button onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="modalBody" id="modalBody"></div>
            </div>
        </div>

    </div><!-- #main-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        // ---- TAB SWITCHING ----
        function switchReqTab(tab, btn) {
            document.querySelectorAll('.reqTabContent').forEach(t => t.style.display = 'none');
            document.querySelectorAll('.reqTabBtn').forEach(b => b.classList.remove('active'));
            document.getElementById(tab).style.display = 'flex';
            btn.classList.add('active');
        }

        // ---- SEARCH TABLE ----
        function searchTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.reqTabContent:not([style*="display:none"]) tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        // ---- CLOSE MODAL ----
        function closeModal() {
            document.getElementById('modalOverlay').style.display = 'none';
        }

        // ---- TOAST ----
        function closeToast() {
            const toast = document.getElementById('customToast');

            if (toast) {
                toast.classList.add('toast-hide');

                setTimeout(() => {
                    toast.remove();
                }, 400);
            }
        }
        setTimeout(() => {
            closeToast();
        }, 3000);
            </script>
</body>
</html>
