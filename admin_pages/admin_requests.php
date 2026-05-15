
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
        l.log_time AS original_log_time,
        CONCAT(r.first_name, ' ', r.last_name) AS requested_by_name
    FROM log_edit_requests le
    JOIN employees e ON le.employee_id = e.id
    LEFT JOIN attendances a ON le.attendance_id = a.id
    LEFT JOIN logs l ON le.log_id = l.id
    LEFT JOIN employees r ON le.initiated_by_id = r.id
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

        <!-- Summary Card -->
        <div class="container-fluid flex-shrink-0 px-3 pt-2">
            <div class="row g-3">

                <!-- Total Pending -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                    <div class="card card-warning p-3">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-warning">
                                <i class="bi bi-hourglass-split fs-4"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number">
                                    <?= $totalPending ?>
                                </div>
                                <div class="text-meta">
                                    Pending
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Approved -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                    <div class="card card-success p-3">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-success">
                                <i class="bi bi-check-circle-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number">
                                    <?= $totalApproved ?>
                                </div>
                                <div class="text-meta">
                                    Approved
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Rejected -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                    <div class="card card-danger p-3">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-danger">
                                <i class="bi bi-x-circle-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number">
                                    <?= $totalRejected ?>
                                </div>
                                <div class="text-meta">
                                    Rejected
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Overtime -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                    <div class="card card-purple p-3">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-purple">
                                <i class="bi bi-clock-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number">
                                    <?= $totalOvertime ?>
                                </div>
                                <div class="text-meta">
                                    Overtime
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Official Business -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                    <div class="card card-info p-3">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-info">
                                <i class="bi bi-briefcase-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number">
                                    <?= $totalOB ?>
                                </div>
                                <div class="text-meta">
                                    OB
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Log Edits -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                    <div class="card card-pink p-3">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-pink">
                                <i class="bi bi-pencil-square fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number">
                                    <?= $totalLogEdit ?>
                                </div>
                                <div class="text-meta">
                                    Log Edits
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- PAGE WRAPPER -->
        <div class="card card-neutral requests-card">

            <div class="card-header p-0">
                <ul class="nav nav-tabs card-header-tabs" id="reqTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#all">All</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#leave">Leave</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#overtime">Overtime</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#ob">Official Business</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#log-edit">Log Edit</button>
                    </li>
                </ul>
            </div>

            <div class="card-body d-flex flex-column requests-card-body">

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

                <!-- Search row -->
                <div class="req-search-row">
                    <div class="input-group input-group-sm" style="max-width:200px;">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text"
                            id="search-input"
                            class="form-control"
                            placeholder="Search..."
                            onkeyup="searchTable()">
                    </div>
                </div>

                <div class="tab-content">

                <!-- ALL TAB -->
                <div id="all" class="tab-pane fade show active reqTabContent" role="tabpanel">
                    <div class="tableHeaderGlass">
                        <table class="table table-borderless mb-0">
                            <colgroup>
                                <col style="width:22%"><col style="width:14%"><col style="width:24%">
                                <col style="width:20%"><col style="width:10%"><col style="width:10%">
                            </colgroup>
                            <thead><tr>
                                <th>Employee</th><th>Type</th><th>Details</th>
                                <th>Reason</th><th>Status</th><th>Actions</th>
                            </tr></thead>
                        </table>
                    </div>
                    <div class="tableScroll">
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col style="width:22%"><col style="width:14%"><col style="width:24%">
                                <col style="width:20%"><col style="width:10%"><col style="width:10%">
                            </colgroup>
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
                                            <?php $origTime = $row['original_log_time'] ? date('h:i A', strtotime($row['original_log_time'])) : '—'; ?>
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
                <div id="leave" class="tab-pane fade reqTabContent" role="tabpanel">
                    <div class="tableHeaderGlass">
                        <table class="table table-borderless mb-0">
                            <colgroup>
                                <col style="width:20%"><col style="width:13%"><col style="width:12%">
                                <col style="width:12%"><col style="width:20%"><col style="width:10%"><col style="width:13%">
                            </colgroup>
                            <thead><tr>
                                <th>Employee</th><th>Leave Type</th><th>Start</th>
                                <th>End</th><th>Reason</th><th>Status</th><th>Actions</th>
                            </tr></thead>
                        </table>
                    </div>
                    <div class="tableScroll">
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col style="width:20%"><col style="width:13%"><col style="width:12%">
                                <col style="width:12%"><col style="width:20%"><col style="width:10%"><col style="width:13%">
                            </colgroup>
                            <tbody>
                                <?php if (count($leaveRequests) > 0): ?>
                                    <?php foreach ($leaveRequests as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                            <td><span class="badge request-leave"><?= ucfirst($row['leave_type']) ?></span></td>
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
                <div id="overtime" class="tab-pane fade reqTabContent" role="tabpanel">
                    <div class="tableHeaderGlass">
                        <table class="table table-borderless mb-0">
                            <colgroup>
                                <col style="width:20%"><col style="width:13%"><col style="width:11%">
                                <col style="width:11%"><col style="width:20%"><col style="width:10%"><col style="width:15%">
                            </colgroup>
                            <thead><tr>
                                <th>Employee</th><th>Date</th><th>Time In</th>
                                <th>Time Out</th><th>Reason</th><th>Status</th><th>Actions</th>
                            </tr></thead>
                        </table>
                    </div>
                    <div class="tableScroll">
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col style="width:20%"><col style="width:13%"><col style="width:11%">
                                <col style="width:11%"><col style="width:20%"><col style="width:10%"><col style="width:15%">
                            </colgroup>
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
                <div id="log-edit" class="tab-pane fade reqTabContent" role="tabpanel">
                    <div class="tableHeaderGlass">
                        <table class="table table-borderless mb-0">
                            <colgroup>
                                <col style="width:13%"><col style="width:8%"><col style="width:8%"><col style="width:9%">
                                <col style="width:13%"><col style="width:14%"><col style="width:12%"><col style="width:9%"><col style="width:14%">
                            </colgroup>
                            <thead><tr>
                                <th>Employee</th><th>Date</th><th>Type</th><th>Current Log</th>
                                <th>Correction</th><th>Reason</th><th>Requested By</th><th>Status</th><th>Actions</th>
                            </tr></thead>
                        </table>
                    </div>
                    <div class="tableScroll">
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col style="width:13%"><col style="width:8%"><col style="width:8%"><col style="width:9%">
                                <col style="width:13%"><col style="width:14%"><col style="width:12%"><col style="width:9%"><col style="width:14%">
                            </colgroup>
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
                                            <td><?= $row['requested_by_name'] ? htmlspecialchars($row['requested_by_name']) : '—' ?></td>
                                            <td><?= getStatusBadge($row['status']) ?></td>
                                            <td class="actionsCol"><?= getActionButtons('log_edit', $row['id'], $row['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center">No log edit requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- OFFICIAL BUSINESS TAB -->
                <div id="ob" class="tab-pane fade reqTabContent" role="tabpanel">
                    <div class="tableHeaderGlass">
                        <table class="table table-borderless mb-0">
                            <colgroup>
                                <col style="width:20%"><col style="width:12%"><col style="width:18%">
                                <col style="width:22%"><col style="width:10%"><col style="width:18%">
                            </colgroup>
                            <thead><tr>
                                <th>Employee</th><th>Date</th><th>Client Name</th>
                                <th>Reason</th><th>Status</th><th>Actions</th>
                            </tr></thead>
                        </table>
                    </div>
                    <div class="tableScroll">
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col style="width:20%"><col style="width:12%"><col style="width:18%">
                                <col style="width:22%"><col style="width:10%"><col style="width:18%">
                            </colgroup>
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

                </div><!-- /.tab-content -->

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

        // ---- SEARCH TABLE ----
        function searchTable() {
            const input = document.getElementById('search-input').value.toLowerCase();
            document.querySelectorAll('.tab-pane.active tbody tr').forEach(row => {
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
