<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['superadmin', 'manager', 'workforce'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$myRole     = $_SESSION['user_role'];
$myDeptId   = $_SESSION['department_id'] ?? null;
$deptScoped = (in_array($myRole, ['manager', 'workforce']) && $myDeptId);

$success = "";
$error   = "";

// ---- HANDLE APPROVE / REJECT ----
if (isset($_GET['action'], $_GET['type'], $_GET['id'])) {
    $action = $_GET['action'];
    $type   = $_GET['type'];
    $id     = $_GET['id'];
    $status = ($action === 'approve') ? 'approved' : 'rejected';

    // Manager: verify the request belongs to an employee in their department
    if ($deptScoped) {
        $empCol = match($type) {
            'leave', 'overtime' => 'employee_id',
            'log_edit'          => 'employee_id',
            default             => null,
        };
        $tblName = match($type) {
            'leave'    => 'leave_requests',
            'overtime' => 'overtime_requests',
            'log_edit' => 'logs',
            default    => null,
        };
        if ($tblName) {
            $chkStmt = $pdo->prepare("SELECT t.id FROM {$tblName} t JOIN employees e ON t.employee_id = e.id WHERE t.id = ? AND e.department_id = ?");
            $chkStmt->execute([$id, $myDeptId]);
            if (!$chkStmt->fetch()) {
                $error = "Unauthorized action.";
                goto skip_action_ar;
            }
        }
    }

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
        $leStmt = $pdo->prepare("SELECT id, employee_id, log_time, proposed_log_time FROM logs WHERE id = ?");
        $leStmt->execute([$id]);
        $le = $leStmt->fetch(PDO::FETCH_ASSOC);

        if ($status === 'approved' && $le && $le['proposed_log_time']) {
            // Apply proposed time, preserve original for audit
            $pdo->prepare("
                UPDATE logs
                SET log_time          = proposed_log_time,
                    edit_status       = 'approved'
                WHERE id = ?
            ")->execute([$id]);

            $workDate = date('Y-m-d', strtotime($le['log_time']));

            // Reset attendance so finalizer can recalculate
            $pdo->prepare("
                UPDATE attendances SET status = 'incomplete'
                WHERE employee_id = ? AND work_date = ?
            ")->execute([$le['employee_id'], $workDate]);

            // Re-finalize attendance
            $schedStmt = $pdo->prepare("
                SELECT schedule_date, scheduled_start, scheduled_end
                FROM schedules
                WHERE employee_id = ? AND schedule_date = ?
            ");
            $schedStmt->execute([$le['employee_id'], $workDate]);
            $schedule = $schedStmt->fetch(PDO::FETCH_ASSOC);

            if ($schedule) {
                require_once __DIR__ . '/../system_functions/system_service.php';
                $effectiveNow = max(array_filter([$le['proposed_log_time'], date('Y-m-d H:i:s')]));
                finalizeEmployeeAttendance($pdo, (int)$le['employee_id'], $schedule, $effectiveNow);
            }
        } else {
            // rejected — clear proposed time
            $pdo->prepare("
                UPDATE logs SET edit_status = 'rejected', proposed_log_time = NULL WHERE id = ?
            ")->execute([$id]);
        }

    }

    $success = "Request has been " . ucfirst($status) . "!";
}
skip_action_ar:

// ---- GET SUMMARY COUNTS ----
if ($deptScoped) {
    $lrC  = $pdo->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.status = ? AND e.department_id = ?");
    $otC  = $pdo->prepare("SELECT COUNT(*) FROM overtime_requests o JOIN employees e ON o.employee_id = e.id WHERE o.status = ? AND e.department_id = ?");
    $obC  = $pdo->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.leave_type = 'ob leave' AND lr.status = ? AND e.department_id = ?");
    $leC  = $pdo->prepare("SELECT COUNT(*) FROM logs l JOIN employees e ON l.employee_id = e.id WHERE l.edit_status = ? AND e.department_id = ?");

    $lrC->execute(['pending',  $myDeptId]); $pendingLeave     = (int)$lrC->fetchColumn();
    $lrC->execute(['approved', $myDeptId]); $approvedLeave    = (int)$lrC->fetchColumn();
    $lrC->execute(['rejected', $myDeptId]); $rejectedLeave    = (int)$lrC->fetchColumn();
    $otC->execute(['pending',  $myDeptId]); $pendingOvertime  = (int)$otC->fetchColumn();
    $otC->execute(['approved', $myDeptId]); $approvedOvertime = (int)$otC->fetchColumn();
    $otC->execute(['rejected', $myDeptId]); $rejectedOvertime = (int)$otC->fetchColumn();
    $obC->execute(['pending',  $myDeptId]); $pendingOB        = (int)$obC->fetchColumn();
    $obC->execute(['approved', $myDeptId]); $approvedOB       = (int)$obC->fetchColumn();
    $obC->execute(['rejected', $myDeptId]); $rejectedOB       = (int)$obC->fetchColumn();
    $leC->execute(['pending',  $myDeptId]); $pendingLogEdit   = (int)$leC->fetchColumn();
    $leC->execute(['approved', $myDeptId]); $approvedLogEdit  = (int)$leC->fetchColumn();
    $leC->execute(['rejected', $myDeptId]); $rejectedLogEdit  = (int)$leC->fetchColumn();
} else {
    $pendingLeave     = $pdo->query("SELECT COUNT(*) FROM leave_requests    WHERE status = 'pending'")->fetchColumn();
    $approvedLeave    = $pdo->query("SELECT COUNT(*) FROM leave_requests    WHERE status = 'approved'")->fetchColumn();
    $rejectedLeave    = $pdo->query("SELECT COUNT(*) FROM leave_requests    WHERE status = 'rejected'")->fetchColumn();
    $pendingOvertime  = $pdo->query("SELECT COUNT(*) FROM overtime_requests WHERE status = 'pending'")->fetchColumn();
    $approvedOvertime = $pdo->query("SELECT COUNT(*) FROM overtime_requests WHERE status = 'approved'")->fetchColumn();
    $rejectedOvertime = $pdo->query("SELECT COUNT(*) FROM overtime_requests WHERE status = 'rejected'")->fetchColumn();
    $pendingOB        = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type = 'ob leave' AND status = 'pending'")->fetchColumn();
    $approvedOB       = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type = 'ob leave' AND status = 'approved'")->fetchColumn();
    $rejectedOB       = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type = 'ob leave' AND status = 'rejected'")->fetchColumn();
    $pendingLogEdit   = $pdo->query("SELECT COUNT(*) FROM logs WHERE edit_status = 'pending'")->fetchColumn();
    $approvedLogEdit  = $pdo->query("SELECT COUNT(*) FROM logs WHERE edit_status = 'approved'")->fetchColumn();
    $rejectedLogEdit  = $pdo->query("SELECT COUNT(*) FROM logs WHERE edit_status = 'rejected'")->fetchColumn();
}

$totalPending  = $pendingLeave  + $pendingOvertime  + $pendingOB  + $pendingLogEdit;
$totalApproved = $approvedLeave + $approvedOvertime + $approvedOB + $approvedLogEdit;
$totalRejected = $rejectedLeave + $rejectedOvertime + $rejectedOB + $rejectedLogEdit;
$totalOvertime = $pendingOvertime + $approvedOvertime + $rejectedOvertime;
$totalOB       = $pendingOB + $approvedOB + $rejectedOB;
$totalLogEdit  = $pendingLogEdit + $approvedLogEdit + $rejectedLogEdit;

// ---- GET LEAVE REQUESTS (non-OB) ----
if ($deptScoped) {
    $s = $pdo->prepare("SELECT lr.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.leave_type != 'ob leave' AND e.department_id = ? ORDER BY lr.created_at DESC");
    $s->execute([$myDeptId]);
    $leaveRequests = $s->fetchAll(PDO::FETCH_ASSOC);
} else {
    $leaveRequests = $pdo->query("SELECT lr.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.leave_type != 'ob leave' ORDER BY lr.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

// ---- GET OVERTIME REQUESTS ----
if ($deptScoped) {
    $s = $pdo->prepare("SELECT or2.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name FROM overtime_requests or2 JOIN employees e ON or2.employee_id = e.id WHERE e.department_id = ? ORDER BY or2.created_at DESC");
    $s->execute([$myDeptId]);
    $overtimeRequests = $s->fetchAll(PDO::FETCH_ASSOC);
} else {
    $overtimeRequests = $pdo->query("SELECT or2.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name FROM overtime_requests or2 JOIN employees e ON or2.employee_id = e.id ORDER BY or2.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

// ---- GET OB REQUESTS ----
if ($deptScoped) {
    $s = $pdo->prepare("SELECT lr.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.leave_type = 'ob leave' AND e.department_id = ? ORDER BY lr.created_at DESC");
    $s->execute([$myDeptId]);
    $obRequests = $s->fetchAll(PDO::FETCH_ASSOC);
} else {
    $obRequests = $pdo->query("SELECT lr.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.leave_type = 'ob leave' ORDER BY lr.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

// ---- GET LOG EDIT REQUESTS ----
$leBaseSql = "
    SELECT l.id, l.employee_id, l.log_type, l.edit_status AS status,
           l.edit_reason AS reason, l.original_log_time, l.proposed_log_time,
           l.edit_requested_by AS requested_by_id,
           DATE(l.log_time) AS work_date, l.created_at,
           CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
           CONCAT(r.first_name, ' ', r.last_name) AS requested_by_name,
           rr.role_key AS requested_by_role
    FROM logs l
    JOIN employees e ON l.employee_id = e.id
    LEFT JOIN employees r ON l.edit_requested_by = r.id
    LEFT JOIN roles rr ON r.role_id = rr.id
    WHERE l.edit_status IS NOT NULL";
if ($deptScoped) {
    $s = $pdo->prepare($leBaseSql . " AND e.department_id = ? ORDER BY l.created_at DESC");
    $s->execute([$myDeptId]);
    $logEditRequests = $s->fetchAll(PDO::FETCH_ASSOC);
} else {
    $logEditRequests = $pdo->query($leBaseSql . " ORDER BY l.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

// ---- HELPER FUNCTIONS ----
function getRolePill(?string $name, ?string $role): string {
    if (!$name) return '<span style="color:rgba(255,255,255,0.3)">—</span>';
    $icon  = $role === 'superadmin' ? 'bi-shield-fill' : 'bi-person-fill';
    $class = $role ? 'empRoleBadge empRole-' . htmlspecialchars($role) : '';
    return '<span class="pill ' . $class . '"><i class="bi ' . $icon . '"></i> ' . htmlspecialchars($name) . '</span>';
}

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
        $approveUrl = 'admin_requests.php?action=approve&type=' . $type . '&id=' . $id;
        $rejectUrl  = 'admin_requests.php?action=reject&type='  . $type . '&id=' . $id;
        return '
            <button type="button" class="btn btn-sm btn-success confirm-action-btn"
                data-url="' . $approveUrl . '"
                data-label="Approve"
                data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                <i class="bi bi-check-lg"></i> Approve
            </button>
            <button type="button" class="btn btn-sm btn-danger confirm-action-btn"
                data-url="' . $rejectUrl . '"
                data-label="Reject"
                data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                <i class="bi bi-x-lg"></i> Reject
            </button>
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
                    <div class="card card-info p-3">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-info">
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
                    <div class="card card-purple p-3">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-purple">
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

            <div class="card-header p-0 d-flex align-items-center">
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
                <div class="ms-auto pe-3">
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
            </div>

            <div class="card-body d-flex flex-column requests-card-body">

<div class="tab-content">

                <!-- ALL TAB -->
                <div id="all" class="tab-pane fade show active reqTabContent" role="tabpanel">
                    <div class="table-scroll-wrapper">
                        <table class="table table-hover mb-0">
                            <thead><tr>
                                <th>Employee</th><th>Type</th><th>Details</th>
                                <th>Reason</th><th>Status</th><th>Actions</th>
                            </tr></thead>
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
                                            $propTime = $row['proposed_log_time']  ? date('h:i A', strtotime($row['proposed_log_time']))  : '—';
                                            $typeLabel = match($row['log_type']) { 'IN' => 'In', 'OUT' => 'Out', 'BREAK_IN' => 'Break In', 'BREAK_OUT' => 'Break Out', default => $row['log_type'] };
                                            ?>
                                            <?= $typeLabel ?>: <?= $origTime ?> &rarr; <?= $propTime ?>
                                        </td>
                                        <td class="reasonCol"><?= htmlspecialchars($row['reason']) ?></td>
                                        <td><?= getStatusBadge($row['status']) ?></td>
                                        <td class="actionsCol"><?= getActionButtons('log_edit', $row['id'], $row['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (empty($leaveRequests) && empty($overtimeRequests) && empty($obRequests) && empty($logEditRequests)): ?>
                        <div class="table-empty">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta">No requests found.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div id="pag-all" class="reqPagination"></div>
                </div>

                <!-- LEAVE TAB -->
                <div id="leave" class="tab-pane fade reqTabContent" role="tabpanel">
                    <div class="table-scroll-wrapper">
                        <table class="table table-hover mb-0">
                            <thead><tr>
                                <th>Employee</th><th>Leave Type</th><th>Start</th>
                                <th>End</th><th>Reason</th><th>Status</th><th>Actions</th>
                            </tr></thead>
                            <tbody>
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
                            </tbody>
                        </table>
                        <?php if (empty($leaveRequests)): ?>
                        <div class="table-empty">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta">No leave requests found.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div id="pag-leave" class="reqPagination"></div>
                </div>

                <!-- OVERTIME TAB -->
                <div id="overtime" class="tab-pane fade reqTabContent" role="tabpanel">
                    <div class="table-scroll-wrapper">
                        <table class="table table-hover mb-0">
                            <thead><tr>
                                <th>Employee</th><th>Date</th><th>Time In</th>
                                <th>Time Out</th><th>Reason</th><th>Status</th><th>Actions</th>
                            </tr></thead>
                            <tbody>
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
                            </tbody>
                        </table>
                        <?php if (empty($overtimeRequests)): ?>
                        <div class="table-empty">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta">No overtime requests found.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div id="pag-overtime" class="reqPagination"></div>
                </div>

                <!-- LOG EDIT TAB -->
                <div id="log-edit" class="tab-pane fade reqTabContent" role="tabpanel">
                    <div class="table-scroll-wrapper">
                        <table class="table table-hover mb-0">
                            <thead><tr>
                                <th>Employee</th><th>Date</th><th>Type</th><th>Current Log</th>
                                <th>Correction</th><th>Reason</th><th>Requested By</th><th>Status</th><th>Actions</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($logEditRequests as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                        <td><?= date('M d, Y', strtotime($row['work_date'])) ?></td>
                                        <td>
                                            <?php
                                            $typeLabels = ['IN' => 'Time In', 'OUT' => 'Time Out', 'BREAK_IN' => 'Break In', 'BREAK_OUT' => 'Break Out'];
                                            echo htmlspecialchars($typeLabels[$row['log_type']] ?? $row['log_type']);
                                            ?>
                                        </td>
                                        <td><?= $row['original_log_time'] ? date('h:i A', strtotime($row['original_log_time'])) : '—' ?></td>
                                        <td><?= $row['proposed_log_time'] ? date('h:i A', strtotime($row['proposed_log_time'])) : '—' ?></td>
                                        <td class="reasonCol"><?= htmlspecialchars($row['reason']) ?></td>
                                        <td><?= ((int)($row['requested_by_id'] ?? 0) === (int)$_SESSION['user_id']) ? '<span class="pill"><i class="bi bi-person-fill"></i> You</span>' : getRolePill($row['requested_by_name'] ?? null, $row['requested_by_role'] ?? null) ?></td>
                                        <td><?= getStatusBadge($row['status']) ?></td>
                                        <td class="actionsCol"><?= getActionButtons('log_edit', $row['id'], $row['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (empty($logEditRequests)): ?>
                        <div class="table-empty">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta">No log edit requests found.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div id="pag-log-edit" class="reqPagination"></div>
                </div>

                <!-- OFFICIAL BUSINESS TAB -->
                <div id="ob" class="tab-pane fade reqTabContent" role="tabpanel">
                    <div class="table-scroll-wrapper">
                        <table class="table table-hover mb-0">
                            <thead><tr>
                                <th>Employee</th><th>Date</th><th>Client Name</th>
                                <th>Reason</th><th>Status</th><th>Actions</th>
                            </tr></thead>
                            <tbody>
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
                            </tbody>
                        </table>
                        <?php if (empty($obRequests)): ?>
                        <div class="table-empty">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta">No OB requests found.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div id="pag-ob" class="reqPagination"></div>
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

<!-- Confirm Action Modal -->
<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmActionTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmActionBody">
                Are you sure you want to proceed?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a id="confirmActionBtn" href="#" class="btn btn-success">Confirm</a>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        // ---- PAGINATION STATE ----
        const TAB_IDS  = ['all', 'leave', 'overtime', 'ob', 'log-edit'];
        const tabState = {};
        TAB_IDS.forEach(id => { tabState[id] = { page: 1 }; });
        let REQ_ROWS_PER_PAGE = parseInt(localStorage.getItem('reqRowsPerPage') || '10');
        const reqLastTotals   = {};

        document.addEventListener('DOMContentLoaded', () => {
            TAB_IDS.forEach(id => applyFiltersReq(id));

            document.getElementById('search-input')
                .addEventListener('input', () => {
                    const tabId = getActiveTabId();
                    tabState[tabId].page = 1;
                    applyFiltersReq(tabId);
                });

            document.querySelectorAll('#reqTab .nav-link').forEach(btn => {
                btn.addEventListener('shown.bs.tab', e => {
                    const tabId = e.target.dataset.bsTarget.replace('#', '');
                    applyFiltersReq(tabId);
                });
            });
        });

        function getActiveTabId() {
            const active = document.querySelector('#reqTab .nav-link.active');
            return active?.dataset?.bsTarget?.replace('#', '') ?? 'all';
        }

        function searchTable() {
            const tabId = getActiveTabId();
            tabState[tabId].page = 1;
            applyFiltersReq(tabId);
        }

        function applyFiltersReq(tabId) {
            const q    = document.getElementById('search-input').value.toLowerCase();
            const rows = Array.from(document.querySelectorAll('#' + tabId + ' tbody tr'));

            const filtered   = rows.filter(r => r.textContent.toLowerCase().includes(q));
            const total      = filtered.length;
            reqLastTotals[tabId] = total;
            const totalPages = Math.max(1, Math.ceil(total / REQ_ROWS_PER_PAGE));

            if (tabState[tabId].page > totalPages) tabState[tabId].page = 1;

            const start    = (tabState[tabId].page - 1) * REQ_ROWS_PER_PAGE;
            const pageRows = filtered.slice(start, start + REQ_ROWS_PER_PAGE);

            rows.forEach(r => r.style.display = 'none');
            pageRows.forEach(r => r.style.display = '');

            renderReqPagination(tabId, total, totalPages, start);
        }

        function renderReqPagination(tabId, total, totalPages, start) {
            const pag = document.getElementById('pag-' + tabId);
            if (!pag) return;
            if (total === 0) { pag.innerHTML = ''; return; }

            const cur     = tabState[tabId].page;
            const end     = Math.min(start + REQ_ROWS_PER_PAGE, total);
            const showing = `${start + 1}–${end} of ${total}`;

            let html = `
                <div class="row align-items-center g-2 w-100">
                    <div class="col-md d-flex align-items-center gap-2">
                        <span class="text-meta">Showing ${showing}</span>
                    </div>
                    <div class="col-md d-flex justify-content-center">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item${cur === 1 ? ' disabled' : ''}">
                                <button class="page-link" onclick="changeReqPage('${tabId}',${cur - 1})"><i class="bi bi-chevron-left"></i></button>
                            </li>
            `;

            getReqPageNums(cur, totalPages).forEach(p => {
                if (p === '...') {
                    html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
                } else {
                    html += `<li class="page-item${p === cur ? ' active' : ''}">
                        <button class="page-link" onclick="changeReqPage('${tabId}',${p})">${p}</button>
                    </li>`;
                }
            });

            html += `
                            <li class="page-item${cur === totalPages ? ' disabled' : ''}">
                                <button class="page-link" onclick="changeReqPage('${tabId}',${cur + 1})"><i class="bi bi-chevron-right"></i></button>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md d-flex justify-content-md-end align-items-center gap-2">
                        <span class="text-meta text-nowrap">Rows per page</span>
                        <div class="dropdown">
                            <button class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                <span>${REQ_ROWS_PER_PAGE} Rows</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button class="dropdown-item" onclick="changeReqRows(10)">10</button></li>
                                <li><button class="dropdown-item" onclick="changeReqRows(25)">25</button></li>
                                <li><button class="dropdown-item" onclick="changeReqRows(50)">50</button></li>
                                <li><button class="dropdown-item" onclick="changeReqRows(100)">100</button></li>
                            </ul>
                        </div>
                    </div>
                </div>
            `;

            pag.innerHTML = html;
        }

        function getReqPageNums(cur, tot) {
            if (tot <= 7) return Array.from({ length: tot }, (_, i) => i + 1);
            if (cur <= 4) return [1, 2, 3, 4, 5, '...', tot];
            if (cur >= tot - 3) return [1, '...', tot - 4, tot - 3, tot - 2, tot - 1, tot];
            return [1, '...', cur - 1, cur, cur + 1, '...', tot];
        }

        function changeReqPage(tabId, n) {
            const totalPages = Math.max(1, Math.ceil((reqLastTotals[tabId] || 0) / REQ_ROWS_PER_PAGE));
            if (n < 1 || n > totalPages) return;
            tabState[tabId].page = n;
            applyFiltersReq(tabId);
        }

        function changeReqRows(value) {
            REQ_ROWS_PER_PAGE = parseInt(value);
            localStorage.setItem('reqRowsPerPage', value);
            TAB_IDS.forEach(id => { tabState[id].page = 1; });
            applyFiltersReq(getActiveTabId());
        }

        // ---- CLOSE MODAL ----
        function closeModal() {
            document.getElementById('modalOverlay').style.display = 'none';
        }

        // ---- CONFIRM ACTION MODAL ----
        document.addEventListener('click', e => {
            const btn = e.target.closest('.confirm-action-btn');
            if (!btn) return;
            const label      = btn.dataset.label;
            const url        = btn.dataset.url;
            const isApprove  = label === 'Approve';

            document.getElementById('confirmActionTitle').textContent = label + ' Request';
            document.getElementById('confirmActionBody').textContent  = 'Are you sure you want to ' + label.toLowerCase() + ' this request?';

            const confirmBtn = document.getElementById('confirmActionBtn');
            confirmBtn.href      = url;
            confirmBtn.className = 'btn ' + (isApprove ? 'btn-success' : 'btn-danger');
            confirmBtn.textContent = label + ' Request';
        });

        <?php if ($success || $error): ?>
        showToast(<?= json_encode($success ?: $error) ?>, '<?= $success ? 'success' : 'danger' ?>');
        <?php endif; ?>

        // ---- HORIZONTAL MOUSE WHEEL SCROLL ----
        document.querySelectorAll('.table-scroll-wrapper').forEach(wrapper => {
            let nearHScrollbar = false;
            wrapper.addEventListener('mousemove', e => {
                nearHScrollbar = e.clientY > wrapper.getBoundingClientRect().bottom - 16;
            });
            wrapper.addEventListener('mouseleave', () => { nearHScrollbar = false; });
            wrapper.addEventListener('wheel', e => {
                if (!nearHScrollbar) return;
                e.preventDefault();
                wrapper.scrollLeft += e.deltaY + e.deltaX;
            }, { passive: false });
        });
    </script>
</body>
</html>
