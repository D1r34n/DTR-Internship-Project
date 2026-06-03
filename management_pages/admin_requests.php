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
            'log_edit' => 'log_edit_requests',
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
        $pdo->prepare("UPDATE leave_requests SET status = ?, updated_at = NOW(), approved_by = ? WHERE id = ?")
            ->execute([$status, $_SESSION['user_id'], $id]);

        if ($status === 'approved') {
            $lrRow = $pdo->prepare("
                SELECT lr.employee_id, lr.selected_dates, lt.name AS leave_type_name
                FROM leave_requests lr
                JOIN leave_types lt ON lt.id = lr.leave_type_id
                WHERE lr.id = ?
            ");
            $lrRow->execute([$id]);
            $lr = $lrRow->fetch(PDO::FETCH_ASSOC);

            if ($lr) {
                $balColMap = [
                    'sick leave'        => 'sick_leave',
                    'vacation leave'    => 'vacation_leave',
                    'birthday leave'    => 'birthday_leave',
                    'paternity leave'   => 'paternity_leave',
                    'maternity leave'   => 'maternity_leave',
                    'solo parent leave' => 'solo_parent_leave',
                    'buffer leave'      => 'buffer_leave',
                ];
                $balCol = $balColMap[strtolower($lr['leave_type_name'])] ?? null;

                if ($balCol) {
                    $days = count(json_decode($lr['selected_dates'] ?? '[]', true));
                    if ($days > 0) {
                        $pdo->prepare("
                            UPDATE employee_leave_balances
                            SET `{$balCol}` = GREATEST(0, `{$balCol}` - ?)
                            WHERE employee_id = ?
                        ")->execute([$days, $lr['employee_id']]);
                    }
                }
            }
        }

    } elseif ($type === 'overtime') {
        $pdo->prepare("UPDATE overtime_requests SET status = ?, updated_at = NOW(), approved_by = ? WHERE id = ?")
            ->execute([$status, $_SESSION['user_id'], $id]);

        $pdo->prepare("
            UPDATE attendances a
            JOIN overtime_requests o ON a.employee_id = o.employee_id AND a.work_date = o.date
            SET a.overtime_status = ?
            WHERE o.id = ?
        ")->execute([$status, $id]);

    } elseif ($type === 'log_edit') {
        $leStmt = $pdo->prepare("SELECT id, log_id, employee_id, proposed_log_time FROM log_edit_requests WHERE id = ?");
        $leStmt->execute([$id]);
        $le = $leStmt->fetch(PDO::FETCH_ASSOC);

        if ($status === 'approved' && $le && $le['proposed_log_time']) {
            $logRow = $pdo->prepare("SELECT log_time, schedule_id FROM logs WHERE id = ?");
            $logRow->execute([$le['log_id']]);
            $logData    = $logRow->fetch(PDO::FETCH_ASSOC);
            $scheduleId = $logData ? (int)$logData['schedule_id'] : null;

            $pdo->prepare("UPDATE logs SET log_time = ? WHERE id = ?")
                ->execute([$le['proposed_log_time'], $le['log_id']]);

            $pdo->prepare("UPDATE log_edit_requests SET status = 'approved', approved_by = ? WHERE id = ?")
                ->execute([$_SESSION['user_id'], $id]);

            if ($scheduleId) {
                $pdo->prepare("UPDATE attendances SET status = 'incomplete' WHERE employee_id = ? AND schedule_id = ?")
                    ->execute([$le['employee_id'], $scheduleId]);

                require_once __DIR__ . '/../system_functions/system_service.php';
                $effectiveNow = max($le['proposed_log_time'], date('Y-m-d H:i:s'));
                finalizeEmployeeAttendance($pdo, (int)$le['employee_id'], $scheduleId, $effectiveNow);
            }
        } else {
            $pdo->prepare("UPDATE log_edit_requests SET status = 'rejected', approved_by = ? WHERE id = ?")
                ->execute([$_SESSION['user_id'], $id]);
        }

    }

    $success = "Request has been " . ucfirst($status) . "!";
}
skip_action_ar:

// ---- GET SUMMARY COUNTS ----
if ($deptScoped) {
    $lrC  = $pdo->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.status = ? AND e.department_id = ?");
    $otC  = $pdo->prepare("SELECT COUNT(*) FROM overtime_requests o JOIN employees e ON o.employee_id = e.id WHERE o.status = ? AND e.department_id = ?");
    $obC  = $pdo->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.leave_type_id = (SELECT id FROM leave_types WHERE name = 'ob leave') AND lr.status = ? AND e.department_id = ?");
    $leC  = $pdo->prepare("SELECT COUNT(*) FROM log_edit_requests l JOIN employees e ON l.employee_id = e.id WHERE l.status = ? AND e.department_id = ?");

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
    $pendingOB        = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type_id = (SELECT id FROM leave_types WHERE name = 'ob leave') AND status = 'pending'")->fetchColumn();
    $approvedOB       = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type_id = (SELECT id FROM leave_types WHERE name = 'ob leave') AND status = 'approved'")->fetchColumn();
    $rejectedOB       = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type_id = (SELECT id FROM leave_types WHERE name = 'ob leave') AND status = 'rejected'")->fetchColumn();
    $pendingLogEdit   = $pdo->query("SELECT COUNT(*) FROM log_edit_requests WHERE status = 'pending'")->fetchColumn();
    $approvedLogEdit  = $pdo->query("SELECT COUNT(*) FROM log_edit_requests WHERE status = 'approved'")->fetchColumn();
    $rejectedLogEdit  = $pdo->query("SELECT COUNT(*) FROM log_edit_requests WHERE status = 'rejected'")->fetchColumn();
}

$totalPending  = $pendingLeave  + $pendingOvertime  + $pendingOB  + $pendingLogEdit;
$totalApproved = $approvedLeave + $approvedOvertime + $approvedOB + $approvedLogEdit;
$totalRejected = $rejectedLeave + $rejectedOvertime + $rejectedOB + $rejectedLogEdit;
$totalOvertime = $pendingOvertime + $approvedOvertime + $rejectedOvertime;
$totalOB       = $pendingOB + $approvedOB + $rejectedOB;
$totalLogEdit  = $pendingLogEdit + $approvedLogEdit + $rejectedLogEdit;

// Row data is now loaded client-side via admin_requests_api.php
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
                            <tbody id="tbody-all"></tbody>
                        </table>
                        <div class="table-empty" id="empty-all" style="display:none;">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta">No requests found.</div>
                        </div>
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
                            <tbody id="tbody-leave"></tbody>
                        </table>
                        <div class="table-empty" id="empty-leave" style="display:none;">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta">No leave requests found.</div>
                        </div>
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
                            <tbody id="tbody-overtime"></tbody>
                        </table>
                        <div class="table-empty" id="empty-overtime" style="display:none;">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta">No overtime requests found.</div>
                        </div>
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
                            <tbody id="tbody-log-edit"></tbody>
                        </table>
                        <div class="table-empty" id="empty-log-edit" style="display:none;">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta">No log edit requests found.</div>
                        </div>
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
                            <tbody id="tbody-ob"></tbody>
                        </table>
                        <div class="table-empty" id="empty-ob" style="display:none;">
                            <i class="bi bi-calendar2-x-fill"></i>
                            <div class="text-meta">No OB requests found.</div>
                        </div>
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

        const MY_USER_ID      = <?= (int)$_SESSION['user_id'] ?>;
        const TAB_IDS         = ['all', 'leave', 'overtime', 'ob', 'log-edit'];
        const TAB_TO_TYPE     = { all: 'all', leave: 'leave', overtime: 'overtime', ob: 'ob', 'log-edit': 'log_edit' };
        const tabPages        = {};
        TAB_IDS.forEach(id => { tabPages[id] = 1; });
        let REQ_ROWS_PER_PAGE = parseInt(localStorage.getItem('reqRowsPerPage') || '25');
        let searchTimeout     = null;

        /* ── Helpers ─────────────────────────────────────────── */
        function esc(v) {
            return v == null ? '' : String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function fmtDate(s) {
            if (!s) return '—';
            const [y,m,d] = s.slice(0,10).split('-').map(Number);
            return new Date(y,m-1,d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
        }
        function fmtTime(s) {
            if (!s) return '—';
            // TIME-only value from MySQL e.g. "08:00:00"
            if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(s.trim())) {
                const [h, m] = s.split(':').map(Number);
                return new Date(1970, 0, 1, h, m)
                    .toLocaleTimeString('en-US', {hour:'numeric', minute:'2-digit', hour12:true});
            }
            const d = new Date(s.includes('T') ? s : s.replace(' ','T'));
            return d.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true});
        }
        function statusBadge(s) {
            return s === 'pending'  ? '<span class="badge status-pending">Pending</span>'
                 : s === 'approved' ? '<span class="badge status-approved">Approved</span>'
                 : s === 'rejected' ? '<span class="badge status-rejected">Rejected</span>'
                 : '<span class="badge">Unknown</span>';
        }
        function actionBtns(type, id, status) {
            if (status !== 'pending') return '<span class="text-meta">No actions</span>';
            return `<button class="btn btn-sm btn-success confirm-action-btn"
                        data-url="admin_requests.php?action=approve&type=${type}&id=${id}"
                        data-label="Approve" data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                        <i class="bi bi-check-lg"></i> Approve</button>
                    <button class="btn btn-sm btn-danger confirm-action-btn"
                        data-url="admin_requests.php?action=reject&type=${type}&id=${id}"
                        data-label="Reject" data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                        <i class="bi bi-x-lg"></i> Reject</button>`;
        }
        function rolePill(name, role, requestedById) {
            if (parseInt(requestedById) === MY_USER_ID) return '<span class="pill"><i class="bi bi-person-fill"></i> You</span>';
            if (!name) return '<span style="color:rgba(255,255,255,0.3)">—</span>';
            const icon = role === 'superadmin' ? 'bi-shield-fill' : 'bi-person-fill';
            const cls  = role ? `empRoleBadge empRole-${esc(role)}` : '';
            return `<span class="pill ${cls}"><i class="bi ${icon}"></i> ${esc(name)}</span>`;
        }

        /* ── Row renderers ────────────────────────────────────── */
        const LOG_TYPE_LABELS = {IN:'Time In',OUT:'Time Out',BREAK_IN:'Break In',BREAK_OUT:'Break Out'};
        const TYPE_BADGE = {
            leave:    '<span class="badge request-leave">Leave</span>',
            overtime: '<span class="badge request-overtime">Overtime</span>',
            ob:       '<span class="badge request-official-business">Official Business</span>',
            log_edit: '<span class="badge request-log-edit">Log Edit</span>',
        };

        function renderRow(tabId, r) {
            switch (tabId) {
                case 'all': {
                    const at = r.req_type === 'ob' ? 'leave' : r.req_type;
                    let details = '';
                    if (r.req_type === 'leave')    details = `${fmtDate(r.start_date)} – ${fmtDate(r.end_date)}`;
                    if (r.req_type === 'overtime')  details = `${fmtDate(r.date)} | ${fmtTime(r.time_in)} – ${fmtTime(r.time_out)}`;
                    if (r.req_type === 'ob')        details = `${fmtDate(r.start_date)} | ${esc(r.client_name)}`;
                    if (r.req_type === 'log_edit')  details = `${fmtDate(r.work_date)} | ${LOG_TYPE_LABELS[r.log_type]||r.log_type}: ${fmtTime(r.original_log_time)} &rarr; ${fmtTime(r.proposed_log_time)}`;
                    return `<tr><td>${esc(r.employee_name)}</td><td>${TYPE_BADGE[r.req_type]||''}</td><td>${details}</td>
                        <td class="reasonCol">${esc(r.reason)}</td><td>${statusBadge(r.status)}</td>
                        <td class="actionsCol">${actionBtns(at, r.id, r.status)}</td></tr>`;
                }
                case 'leave':
                    return `<tr><td>${esc(r.employee_name)}</td>
                        <td><span class="badge request-leave">${esc(r.leave_type)}</span></td>
                        <td>${fmtDate(r.start_date)}</td><td>${fmtDate(r.end_date)}</td>
                        <td class="reasonCol">${esc(r.reason)}</td><td>${statusBadge(r.status)}</td>
                        <td class="actionsCol">${actionBtns('leave', r.id, r.status)}</td></tr>`;
                case 'overtime':
                    return `<tr><td>${esc(r.employee_name)}</td><td>${fmtDate(r.date)}</td>
                        <td>${fmtTime(r.time_in)}</td><td>${fmtTime(r.time_out)}</td>
                        <td class="reasonCol">${esc(r.reason)}</td><td>${statusBadge(r.status)}</td>
                        <td class="actionsCol">${actionBtns('overtime', r.id, r.status)}</td></tr>`;
                case 'ob':
                    return `<tr><td>${esc(r.employee_name)}</td><td>${fmtDate(r.start_date)}</td>
                        <td>${esc(r.client_name)}</td>
                        <td class="reasonCol">${esc(r.reason)}</td><td>${statusBadge(r.status)}</td>
                        <td class="actionsCol">${actionBtns('leave', r.id, r.status)}</td></tr>`;
                case 'log-edit':
                    return `<tr><td>${esc(r.employee_name)}</td><td>${fmtDate(r.work_date)}</td>
                        <td>${esc(LOG_TYPE_LABELS[r.log_type]||r.log_type)}</td>
                        <td>${fmtTime(r.original_log_time)}</td><td>${fmtTime(r.proposed_log_time)}</td>
                        <td class="reasonCol">${esc(r.reason)}</td>
                        <td>${rolePill(r.requested_by_name, r.requested_by_role, r.requested_by_id)}</td>
                        <td>${statusBadge(r.status)}</td>
                        <td class="actionsCol">${actionBtns('log_edit', r.id, r.status)}</td></tr>`;
            }
            return '';
        }

        /* ── Fetch ────────────────────────────────────────────── */
        function fetchRequests(tabId, page) {
            tabPages[tabId] = page || tabPages[tabId] || 1;
            const search = document.getElementById('search-input').value.trim();
            const params = new URLSearchParams({
                type: TAB_TO_TYPE[tabId], page: tabPages[tabId],
                limit: REQ_ROWS_PER_PAGE, search
            });
            const tbody   = document.getElementById('tbody-' + tabId);
            const emptyEl = document.getElementById('empty-' + tabId);
            const pagEl   = document.getElementById('pag-'   + tabId);

            tbody.innerHTML = `<tr class="emptyRow"><td colspan="10" class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-secondary"></div></td></tr>`;
            if (emptyEl) emptyEl.style.display = 'none';

            fetch('admin_requests_api.php?' + params)
                .then(r => r.json())
                .then(res => {
                    if (res.error) throw new Error(res.error);
                    const rows  = res.data  || [];
                    const total = res.total || 0;
                    tbody.innerHTML = '';
                    if (!rows.length) {
                        if (emptyEl) emptyEl.style.display = '';
                        if (pagEl)   pagEl.innerHTML = '';
                        return;
                    }
                    tbody.innerHTML = rows.map(r => renderRow(tabId, r)).join('');
                    const totalPages = Math.max(1, Math.ceil(total / REQ_ROWS_PER_PAGE));
                    renderReqPagination(tabId, total, totalPages, (tabPages[tabId] - 1) * REQ_ROWS_PER_PAGE);
                })
                .catch(err => {
                    tbody.innerHTML = `<tr class="emptyRow"><td colspan="10" class="text-center py-3 text-meta">${esc(err.message||'Failed to load.')}</td></tr>`;
                });
        }

        /* ── Pagination render ────────────────────────────────── */
        function renderReqPagination(tabId, total, totalPages, start) {
            const pag = document.getElementById('pag-' + tabId);
            if (!pag) return;
            if (total === 0) { pag.innerHTML = ''; return; }
            const cur = tabPages[tabId];
            const end = Math.min(start + REQ_ROWS_PER_PAGE, total);
            let pageLinks = `<li class="page-item${cur===1?' disabled':''}">
                <button class="page-link" onclick="changeReqPage('${tabId}',${cur-1})">&laquo;</button></li>`;
            getReqPageNums(cur, totalPages).forEach(p => {
                pageLinks += p === '...'
                    ? `<li class="page-item disabled"><span class="page-link text-meta">...</span></li>`
                    : `<li class="page-item${p===cur?' active':''}"><button class="page-link" onclick="changeReqPage('${tabId}',${p})">${p}</button></li>`;
            });
            pageLinks += `<li class="page-item${cur===totalPages?' disabled':''}">
                <button class="page-link" onclick="changeReqPage('${tabId}',${cur+1})">&raquo;</button></li>`;
            pag.innerHTML = `
                <div class="d-flex flex-sm-nowrap flex-wrap align-items-center justify-content-between gap-3 w-100">
                    <div class="small text-meta text-nowrap flex-sm-fill w-sm-100 text-sm-start text-center order-1">
                        Showing ${start+1} to ${end} of ${total} entries</div>
                    <div class="d-flex align-items-center justify-content-center flex-wrap gap-3 flex-sm-fill w-sm-100 order-2">
                        <nav><ul class="pagination pagination-sm mb-0">${pageLinks}</ul></nav>
                        ${totalPages>1?`<div class="d-flex align-items-center gap-1 pag-jump-wrapper">
                            <small class="text-meta text-nowrap">Go to:</small>
                            <input type="number" class="form-control form-control-sm text-center px-1 pag-jump-input pag-jump-req"
                                data-tab="${tabId}" min="1" max="${totalPages}" value="${cur}"
                                style="width:45px;height:28px;" placeholder="Go"></div>`:''}
                    </div>
                    <div class="d-flex align-items-center justify-content-sm-end justify-content-center gap-2 flex-sm-fill w-sm-100 order-3">
                        <small class="text-meta text-nowrap">Rows Per Page:</small>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">${REQ_ROWS_PER_PAGE} rows</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                ${[10,25,50,100].map(n=>`<li><button class="dropdown-item" onclick="changeReqRows(${n})">${n} rows</button></li>`).join('')}
                            </ul>
                        </div>
                    </div>
                </div>`;
        }

        function getReqPageNums(cur, tot) {
            if (tot <= 7) return Array.from({length:tot},(_,i)=>i+1);
            if (cur <= 4) return [1,2,3,4,5,'...',tot];
            if (cur >= tot-3) return [1,'...',tot-4,tot-3,tot-2,tot-1,tot];
            return [1,'...',cur-1,cur,cur+1,'...',tot];
        }
        function changeReqPage(tabId, n) { if (n >= 1) fetchRequests(tabId, n); }
        function changeReqRows(value) {
            REQ_ROWS_PER_PAGE = parseInt(value);
            localStorage.setItem('reqRowsPerPage', value);
            TAB_IDS.forEach(id => fetchRequests(id, 1));
        }
        document.addEventListener('keydown', e => {
            if (e.key !== 'Enter' || !e.target.classList.contains('pag-jump-req')) return;
            e.preventDefault();
            const max = parseInt(e.target.max)||1;
            let t = parseInt(e.target.value);
            if (isNaN(t)||t<1) t=1; if (t>max) t=max;
            e.target.value = t;
            changeReqPage(e.target.dataset.tab, t);
        });

        /* ── Init ─────────────────────────────────────────────── */
        document.addEventListener('DOMContentLoaded', () => {
            fetchRequests('all', 1);

            document.getElementById('search-input').addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => fetchRequests(getActiveTabId(), 1), 400);
            });

            document.querySelectorAll('#reqTab .nav-link').forEach(btn => {
                btn.addEventListener('shown.bs.tab', e => {
                    const tabId = e.target.dataset.bsTarget.replace('#','');
                    fetchRequests(tabId, tabPages[tabId] || 1);
                });
            });
        });

        function getActiveTabId() {
            return document.querySelector('#reqTab .nav-link.active')?.dataset?.bsTarget?.replace('#','') ?? 'all';
        }

        // ---- CLOSE MODAL ----
        function closeModal() { document.getElementById('modalOverlay').style.display = 'none'; }

        // ---- CONFIRM ACTION MODAL ----
        document.addEventListener('click', e => {
            const btn = e.target.closest('.confirm-action-btn');
            if (!btn) return;
            const label     = btn.dataset.label;
            const isApprove = label === 'Approve';
            document.getElementById('confirmActionTitle').textContent = label + ' Request';
            document.getElementById('confirmActionBody').textContent  = 'Are you sure you want to ' + label.toLowerCase() + ' this request?';
            const cb = document.getElementById('confirmActionBtn');
            cb.href = btn.dataset.url;
            cb.className = 'btn ' + (isApprove ? 'btn-success' : 'btn-danger');
            cb.textContent = label + ' Request';
        });

        <?php if ($success || $error): ?>
        showToast(<?= json_encode($success ?: $error) ?>, '<?= $success ? 'success' : 'danger' ?>');
        <?php endif; ?>

    </script>
</body>
</html>
