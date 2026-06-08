<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['superadmin', 'admin', 'manager', 'workforce'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$myRole     = $_SESSION['user_role'];
$myDeptId   = $_SESSION['department_id'] ?? null;
$deptScoped = in_array($myRole, ['manager', 'workforce']) && $myDeptId;

$success = "";
$error   = "";

// ---- HANDLE APPROVE / REJECT ----
if (isset($_GET['action']) && (isset($_GET['id']) || isset($_GET['batch_id']))) {
    $action  = $_GET['action'];
    $id      = (int)($_GET['id'] ?? 0);
    $batchId = $_GET['batch_id'] ?? null;

    // Dept scope check
    if ($deptScoped) {
        if ($batchId) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM schedules s JOIN employees e ON s.employee_id = e.id WHERE s.batch_id = ? AND e.department_id = ?");
            $chk->execute([$batchId, $myDeptId]);
            if ((int)$chk->fetchColumn() === 0) { $error = "Unauthorized action."; goto skip_action_sr; }
        } else {
            $chk = $pdo->prepare("SELECT s.id FROM schedules s JOIN employees e ON s.employee_id = e.id WHERE s.id = ? AND e.department_id = ?");
            $chk->execute([$id, $myDeptId]);
            if (!$chk->fetch()) { $error = "Unauthorized action."; goto skip_action_sr; }
        }
    }

    if ($action === 'approve') {
        if ($batchId) {
            $serStmt = $pdo->prepare("SELECT id FROM schedule_edit_requests WHERE batch_id = ? AND status = 'pending'");
            $serStmt->execute([$batchId]);
            $ser = $serStmt->fetch(PDO::FETCH_ASSOC);
            $q = $pdo->prepare("SELECT * FROM schedules WHERE batch_id = ?");
            $q->execute([$batchId]);
        } else {
            $serRow = $pdo->prepare("SELECT ser.id FROM schedule_edit_requests ser JOIN schedules s ON ser.batch_id = s.batch_id WHERE s.id = ? AND ser.status = 'pending' LIMIT 1");
            $serRow->execute([$id]);
            $ser = $serRow->fetch(PDO::FETCH_ASSOC);
            $q = $pdo->prepare("SELECT * FROM schedules WHERE id = ?");
            $q->execute([$id]);
        }
        $schedList = $q->fetchAll(PDO::FETCH_ASSOC);

        $attStmt = $pdo->prepare("
            INSERT INTO attendances (
                employee_id, schedule_id, work_date,
                scheduled_start, scheduled_end,
                actual_time_in, actual_time_out,
                total_work_minutes, late_minutes,
                undertime_minutes, overtime_minutes,
                status, missed_time_out
            )
            VALUES (?, ?, ?, ?, ?, NULL, NULL, 0, 0, 0, 0, 'incomplete', 0)
            ON DUPLICATE KEY UPDATE
                scheduled_start = VALUES(scheduled_start),
                scheduled_end   = VALUES(scheduled_end)
        ");
        foreach ($schedList as $sched) {
            $pdo->prepare("UPDATE schedules SET updated_at = NOW() WHERE id = ?")->execute([$sched['id']]);
            if (!$sched['is_rest_day']) {
                $attStmt->execute([
                    $sched['employee_id'], $sched['id'], $sched['schedule_date'],
                    $sched['scheduled_start'], $sched['scheduled_end'],
                ]);
            }
        }

        // Request state lives in schedule_edit_requests now (logs.edit_status was dropped).
        if ($ser) {
            $pdo->prepare("UPDATE schedule_edit_requests SET status = 'approved' WHERE id = ?")
                ->execute([$ser['id']]);
        }

        if (!empty($schedList)) $success = "Schedule approved successfully!";

    } elseif ($action === 'reject') {
        if ($batchId) {
            $serStmt = $pdo->prepare("SELECT id FROM schedule_edit_requests WHERE batch_id = ? AND status = 'pending'");
            $serStmt->execute([$batchId]);
            $ser = $serStmt->fetch(PDO::FETCH_ASSOC);
            $q = $pdo->prepare("SELECT id, employee_id, schedule_date, request_type, orig_is_rest_day FROM schedules WHERE batch_id = ?");
            $q->execute([$batchId]);
        } else {
            $serRow = $pdo->prepare("SELECT ser.id FROM schedule_edit_requests ser JOIN schedules s ON ser.batch_id = s.batch_id WHERE s.id = ? AND ser.status = 'pending' LIMIT 1");
            $serRow->execute([$id]);
            $ser = $serRow->fetch(PDO::FETCH_ASSOC);
            $q = $pdo->prepare("SELECT id, employee_id, schedule_date, request_type, orig_is_rest_day FROM schedules WHERE id = ?");
            $q->execute([$id]);
        }
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);

        $restoreStmt = $pdo->prepare("
            UPDATE schedules SET
                is_rest_day          = orig_is_rest_day,
                scheduled_start      = orig_scheduled_start,
                scheduled_end        = orig_scheduled_end,
                orig_is_rest_day     = NULL,
                orig_scheduled_start = NULL,
                orig_scheduled_end   = NULL,
                request_type         = NULL,
                batch_id             = NULL,
                updated_at           = NOW()
            WHERE id = ?
        ");
        $delAtt = $pdo->prepare("DELETE FROM attendances WHERE employee_id = ? AND work_date = ? AND actual_time_in IS NULL");

        foreach ($rows as $row) {
            if ($row['request_type'] === 'edit' && $row['orig_is_rest_day'] !== null) {
                $restoreStmt->execute([$row['id']]);
            }
            $delAtt->execute([$row['employee_id'], $row['schedule_date']]);
        }
        // Request state lives in schedule_edit_requests now (logs.edit_status was dropped).
        if ($ser) {
            $pdo->prepare("UPDATE schedule_edit_requests SET status = 'rejected' WHERE id = ?")
                ->execute([$ser['id']]);
        }
        $success = "Schedule request rejected.";

    } elseif ($action === 'approve_delete') {
        if ($deptScoped) {
            $chkStmt = $pdo->prepare("SELECT s.id FROM schedules s JOIN employees e ON s.employee_id = e.id WHERE s.id = ? AND e.department_id = ? AND s.pending_delete = 1");
            $chkStmt->execute([$id, $myDeptId]);
            if (!$chkStmt->fetch()) { $error = "Unauthorized action."; goto skip_action_sr; }
        }
        $siStmt = $pdo->prepare("
            SELECT s.employee_id, s.schedule_date, s.scheduled_start, s.scheduled_end, s.is_rest_day,
                   CONCAT(e.first_name, ' ', e.last_name) AS employee_name
            FROM schedules s
            LEFT JOIN employees e ON s.employee_id = e.id
            WHERE s.id = ? AND s.pending_delete = 1
        ");
        $siStmt->execute([$id]);
        $si = $siStmt->fetch(PDO::FETCH_ASSOC);
        if ($si) {
            $pdo->prepare("DELETE FROM attendances WHERE employee_id = ? AND work_date = ? AND actual_time_in IS NULL")
                ->execute([$si['employee_id'], $si['schedule_date']]);
            $pdo->prepare("UPDATE schedules SET is_archived = 1, pending_delete = 0, updated_at = NOW() WHERE id = ?")
                ->execute([$id]);

            $timeStr = ($si['scheduled_start'] && $si['scheduled_end'])
                ? date('g:i A', strtotime($si['scheduled_start'])) . ' - ' . date('g:i A', strtotime($si['scheduled_end']))
                : ($si['is_rest_day'] ? 'Rest Day' : '—');
            $initStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM employees WHERE id = ?");
            $initStmt->execute([$_SESSION['user_id']]);
            $initRow  = $initStmt->fetch(PDO::FETCH_ASSOC);
            // schedules.requested_by was dropped; fall back to the schedule owner as the requester.
            $logRequestedBy = $si['employee_id'];
            $pdo->prepare("
                INSERT INTO logs (employee_id, log_type, log_time, longitude, latitude, is_within_office, edit_requested_by, edit_reason)
                VALUES (?, ?, NOW(), 0, 0, 0, ?, ?)
            ")->execute([$si['employee_id'], 'DELETE_SCHEDULE', $logRequestedBy, json_encode([
                'employee_name' => $si['employee_name'] ?? '—',
                'schedule_date' => date('F j, Y', strtotime($si['schedule_date'])),
                'time'          => $timeStr,
                'deleted_by'    => $initRow['name'] ?? '—',
            ])]);

            $success = "Schedule deleted successfully!";
        }

    } elseif ($action === 'reject_delete') {
        if ($deptScoped) {
            $chkStmt = $pdo->prepare("SELECT s.id FROM schedules s JOIN employees e ON s.employee_id = e.id WHERE s.id = ? AND e.department_id = ? AND s.pending_delete = 1");
            $chkStmt->execute([$id, $myDeptId]);
            if (!$chkStmt->fetch()) { $error = "Unauthorized action."; goto skip_action_sr; }
        }
        $pdo->prepare("UPDATE schedules SET pending_delete = 0 WHERE id = ? AND pending_delete = 1")->execute([$id]);
        $success = "Delete request rejected.";
    }
}
skip_action_sr:

// ---- COUNTS ----
if ($deptScoped) {
    $cStmt = $pdo->prepare("SELECT COUNT(DISTINCT ser.id) FROM schedule_edit_requests ser JOIN schedules s ON ser.batch_id = s.batch_id JOIN employees e ON s.employee_id = e.id WHERE ser.status = ? AND e.department_id = ?");
    $cStmt->execute(['pending',  $myDeptId]); $pendingCount  = (int)$cStmt->fetchColumn();
    $cStmt->execute(['approved', $myDeptId]); $approvedCount = (int)$cStmt->fetchColumn();
    $cStmt->execute(['rejected', $myDeptId]); $rejectedCount = (int)$cStmt->fetchColumn();
} else {
    $pendingCount  = (int)$pdo->query("SELECT COUNT(*) FROM schedule_edit_requests WHERE status = 'pending'")->fetchColumn();
    $approvedCount = (int)$pdo->query("SELECT COUNT(*) FROM schedule_edit_requests WHERE status = 'approved'")->fetchColumn();
    $rejectedCount = (int)$pdo->query("SELECT COUNT(*) FROM schedule_edit_requests WHERE status = 'rejected'")->fetchColumn();
}

// Row data is now loaded client-side via admin_schedule_api.php
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Schedule Requests</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <link rel="stylesheet" href="admin_requests.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Bootstrap JS — must be in <head> so the topbar session-timeout IIFE can call bootstrap.Modal on parse -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

    <?php $currentPage = 'schedule_requests'; include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">

        <?php include '../topbar_revised.php'; ?>

        <!-- Summary Cards -->
        <div class="container-fluid flex-shrink-0 px-3 pt-2">
            <div class="row g-3">

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

                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card card-success p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-success">
                                <i class="bi bi-check-circle-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number"><?= $approvedCount ?></div>
                                <div class="text-meta">Approved</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card card-danger p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-danger">
                                <i class="bi bi-x-circle-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number"><?= $rejectedCount ?></div>
                                <div class="text-meta">Rejected</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Main Card -->
        <div class="card card-neutral requests-card">

            <div class="card-body d-flex flex-column requests-card-body">

                <!-- Filter row -->
                <div class="filterWrapper">

                    <div class="dropdown">
                        <button class="btn btn-sm dropdown-toggle" type="button" id="statusToggle"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-funnel"></i>
                            <span id="statusLabel">All Status</span>
                        </button>
                        <ul class="dropdown-menu" id="statusMenu">
                            <li><a class="dropdown-item" href="#" data-value="ALL">All Status</a></li>
                            <li><a class="dropdown-item" href="#" data-value="pending">Pending</a></li>
                            <li><a class="dropdown-item" href="#" data-value="approved">Approved</a></li>
                            <li><a class="dropdown-item" href="#" data-value="rejected">Rejected</a></li>
                            <li><a class="dropdown-item" href="#" data-value="deleted">Deleted</a></li>
                        </ul>
                    </div>

                    <div class="dropdown">
                        <button class="btn btn-sm dropdown-toggle" id="srDatePickerBtn" type="button">
                            <i class="bi bi-calendar3"></i>
                            <span id="srDateRangeLabel">Today</span>
                        </button>
                    </div>

                    <div class="input-group input-group-sm ms-auto" style="max-width:200px;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchInput" class="form-control"
                               placeholder="Search..." onkeyup="filterTable()">
                    </div>

                </div>

                <!-- Table -->
                <div class="tableScroll">
                    <table class="table table-hover mb-0">
                        <thead><tr>
                            <th>Employee</th><th>Department</th><th>Date</th>
                            <th>Time In</th><th>Time Out</th><th>Shift</th>
                            <th>Type</th><th>Requested By</th><th>Status</th><th>Actions</th>
                        </tr></thead>
                        <tbody id="srTbody"></tbody>
                    </table>
                    <div id="srEmptyState" class="table-empty" style="display:none">
                        <i class="bi bi-calendar2-x-fill"></i>
                        <div class="text-meta">No schedule requests found.</div>
                    </div>
                </div>
                <div id="srPagination" class="reqPagination"></div>

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

    <?php include '../system_functions/show_toast.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        const MY_USER_ID_SR  = <?= (int)$_SESSION['user_id'] ?>;
        const fmtISO         = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        const todayISO       = fmtISO(new Date());
        let currentStatus    = 'ALL';
        let srDateFrom       = localStorage.getItem('srDateFrom') || todayISO;
        let srDateTo         = localStorage.getItem('srDateTo')   || todayISO;
        let SR_ROWS_PER_PAGE = parseInt(localStorage.getItem('srRowsPerPage') || '25');
        let srCurrentPage    = 1;
        let srSearchTimeout  = null;

        /* ── Helpers ─────────────────────────────────────────── */
        function escSR(v) {
            return v == null ? '' : String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function fmtTimeSR(s) {
            if (!s) return '—';
            // TIME-only value e.g. "08:00:00"
            if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(s.trim())) {
                const [h, m] = s.split(':').map(Number);
                return new Date(1970, 0, 1, h, m)
                    .toLocaleTimeString('en-US', {hour:'numeric', minute:'2-digit', hour12:true});
            }
            const d = new Date(s.includes('T') ? s : s.replace(' ','T'));
            return d.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true});
        }
        function getStartHour(s) {
            if (!s) return 6;
            const timePart = s.includes(' ') ? s.split(' ')[1] : s;
            return parseInt(timePart.split(':')[0]);
        }
        function fmtDatesSR(allDates) {
            const parts = allDates.split(',').filter(Boolean).sort();
            if (!parts.length) return '—';
            const parse = s => { const [y,m,d] = s.split('-').map(Number); return new Date(y,m-1,d); };
            const short = d => d.toLocaleDateString('en-US',{month:'short',day:'numeric'});
            if (parts.length === 1) {
                const d = parse(parts[0]);
                return d.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
            }
            const first = parse(parts[0]), last = parse(parts[parts.length-1]);
            const sameMonth = first.getMonth()===last.getMonth() && first.getFullYear()===last.getFullYear();
            return sameMonth
                ? `${short(first)} – ${last.getDate()}, ${last.getFullYear()}`
                : `${short(first)} – ${last.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}`;
        }
        function typeBadgeSR(t) {
            return t==='added' ? '<span class="badge status-approved">Added</span>'
                 : t==='edit'  ? '<span class="badge status-info">Edit</span>'
                 : t==='deleted' ? '<span class="badge status-rejected">Deleted</span>'
                 : '<span style="color:rgba(255,255,255,0.3)">—</span>';
        }
        function statusBadgeSR(s) {
            return s==='pending'  ? '<span class="badge status-pending">Pending</span>'
                 : s==='approved' ? '<span class="badge status-approved">Approved</span>'
                 : s==='rejected' ? '<span class="badge status-rejected">Rejected</span>'
                 : s==='deleted'  ? '<span class="badge status-rejected">Deleted</span>'
                 : '<span class="badge">Unknown</span>';
        }
        function rolePillSR(name, role, requestedById) {
            if (parseInt(requestedById) === MY_USER_ID_SR) return '<span class="pill"><i class="bi bi-person-fill"></i> You</span>';
            if (!name) return '<span style="color:rgba(255,255,255,0.3)">—</span>';
            const icon = role==='superadmin' ? 'bi-shield-fill' : 'bi-person-fill';
            const cls  = role ? `empRoleBadge empRole-${escSR(role)}` : '';
            return `<span class="pill ${cls}"><i class="bi ${icon}"></i> ${escSR(name)}</span>`;
        }

        /* ── Row renderer ─────────────────────────────────────── */
        function renderSRRow(r) {
            const isArchived     = parseInt(r.is_archived);
            const isPendingDel   = parseInt(r.pending_delete);
            const isNight        = (() => { const h = getStartHour(r.scheduled_start); return h >= 18 || h < 6; })();
            const rowStatus      = isArchived ? 'deleted' : r.status;
            const actionParam    = r.batch_id ? `batch_id=${encodeURIComponent(r.batch_id)}` : `id=${r.id}`;
            const dateLabel      = fmtDatesSR(r.all_dates || '');

            let statusCell = statusBadgeSR(rowStatus);
            if (!isArchived && isPendingDel) statusCell = '<span class="badge status-pending">Pending Delete</span>';

            let actionsCell = '<span class="text-meta">No actions</span>';
            if (!isArchived && isPendingDel) {
                actionsCell = `
                    <button class="btn btn-sm btn-danger confirm-action-btn"
                        data-url="admin_schedule_requests.php?action=approve_delete&id=${r.id}"
                        data-label="Approve Delete"
                        data-body="Are you sure you want to approve this deletion? The schedule will be permanently removed."
                        data-btn-class="btn-danger" data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                        <i class="bi bi-trash"></i> Approve Delete</button>
                    <button class="btn btn-sm btn-secondary confirm-action-btn"
                        data-url="admin_schedule_requests.php?action=reject_delete&id=${r.id}"
                        data-label="Reject Delete"
                        data-body="Are you sure you want to reject this delete request?"
                        data-btn-class="btn-secondary" data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                        <i class="bi bi-x-lg"></i> Reject</button>`;
            } else if (!isArchived && r.status === 'pending') {
                actionsCell = `
                    <button class="btn btn-sm btn-success confirm-action-btn"
                        data-url="admin_schedule_requests.php?action=approve&${actionParam}"
                        data-label="Approve"
                        data-body="Are you sure you want to approve this schedule request?"
                        data-btn-class="btn-success" data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                        <i class="bi bi-check-lg"></i> Approve</button>
                    <button class="btn btn-sm btn-danger confirm-action-btn"
                        data-url="admin_schedule_requests.php?action=reject&${actionParam}"
                        data-label="Reject"
                        data-body="Are you sure you want to reject this schedule request?"
                        data-btn-class="btn-danger" data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                        <i class="bi bi-x-lg"></i> Reject</button>`;
            }

            return `<tr>
                <td>${escSR(r.employee_name)}</td>
                <td>${r.department_code ? escSR(r.department_code) : '—'}</td>
                <td>${escSR(dateLabel)}</td>
                <td>${fmtTimeSR(r.scheduled_start)}</td>
                <td>${fmtTimeSR(r.scheduled_end)}</td>
                <td>${isNight ? '<span class="badge request-overtime">Night</span>' : '<span class="badge status-approved">Day</span>'}</td>
                <td>${typeBadgeSR(r.request_type)}</td>
                <td>${rolePillSR(r.requested_by_name, r.requested_by_role, r.requested_by)}</td>
                <td>${statusCell}</td>
                <td class="actionsCol">${actionsCell}</td>
            </tr>`;
        }

        /* ── Fetch ────────────────────────────────────────────── */
        function fetchSR(page) {
            srCurrentPage = page || srCurrentPage;
            const search  = document.getElementById('searchInput').value.trim();
            const params  = new URLSearchParams({
                page: srCurrentPage, limit: SR_ROWS_PER_PAGE,
                search, status: currentStatus,
                date_from: srDateFrom || '', date_to: srDateTo || ''
            });
            const tbody   = document.getElementById('srTbody');
            const emptyEl = document.getElementById('srEmptyState');

            tbody.innerHTML = `<tr class="emptyRow"><td colspan="10" class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-secondary"></div></td></tr>`;
            if (emptyEl) emptyEl.style.display = 'none';

            fetch('admin_schedule_api.php?' + params)
                .then(r => r.json())
                .then(res => {
                    if (res.error) throw new Error(res.error);
                    const rows  = res.data  || [];
                    const total = res.total || 0;
                    tbody.innerHTML = '';
                    if (!rows.length) {
                        if (emptyEl) emptyEl.style.display = '';
                        document.getElementById('srPagination').innerHTML = '';
                        return;
                    }
                    tbody.innerHTML = rows.map(renderSRRow).join('');
                    const totalPages = Math.max(1, Math.ceil(total / SR_ROWS_PER_PAGE));
                    renderSRPagination(total, totalPages, (srCurrentPage - 1) * SR_ROWS_PER_PAGE);
                })
                .catch(err => {
                    tbody.innerHTML = `<tr class="emptyRow"><td colspan="10" class="text-center py-3 text-meta">${escSR(err.message||'Failed to load.')}</td></tr>`;
                });
        }

        /* ── Pagination render ────────────────────────────────── */
        function renderSRPagination(total, totalPages, start) {
            const pag = document.getElementById('srPagination');
            if (!pag) return;
            if (total === 0) { pag.innerHTML = ''; return; }
            const end = Math.min(start + SR_ROWS_PER_PAGE, total);
            let pageLinks = `<li class="page-item${srCurrentPage===1?' disabled':''}">
                <button class="page-link" onclick="changeSRPage(${srCurrentPage-1})">&laquo;</button></li>`;
            getSRPageNums(srCurrentPage, totalPages).forEach(p => {
                pageLinks += p==='...'
                    ? `<li class="page-item disabled"><span class="page-link text-meta">...</span></li>`
                    : `<li class="page-item${p===srCurrentPage?' active':''}"><button class="page-link" onclick="changeSRPage(${p})">${p}</button></li>`;
            });
            pageLinks += `<li class="page-item${srCurrentPage===totalPages?' disabled':''}">
                <button class="page-link" onclick="changeSRPage(${srCurrentPage+1})">&raquo;</button></li>`;
            pag.innerHTML = `
                <div class="d-flex flex-sm-nowrap flex-wrap align-items-center justify-content-between gap-3 w-100">
                    <div class="small text-meta text-nowrap flex-sm-fill w-sm-100 text-sm-start text-center order-1">
                        Showing ${start+1} to ${end} of ${total} entries</div>
                    <div class="d-flex align-items-center justify-content-center flex-wrap gap-3 flex-sm-fill w-sm-100 order-2">
                        <nav><ul class="pagination pagination-sm mb-0">${pageLinks}</ul></nav>
                        ${totalPages>1?`<div class="d-flex align-items-center gap-1 pag-jump-wrapper">
                            <small class="text-meta text-nowrap">Go to:</small>
                            <input type="number" id="srPageJumpInput" class="text-center pag-jump-input"
                                min="1" max="${totalPages}" value="${srCurrentPage}"
                                style="width:45px;height:28px;" placeholder="Go"></div>`:''}
                    </div>
                    <div class="d-flex align-items-center justify-content-sm-end justify-content-center gap-2 flex-sm-fill w-sm-100 order-3">
                        <small class="text-meta text-nowrap">Rows Per Page:</small>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">${SR_ROWS_PER_PAGE} rows</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                ${[10,25,50,100].map(n=>`<li><button class="dropdown-item" onclick="changeSRRows(${n})">${n} rows</button></li>`).join('')}
                            </ul>
                        </div>
                    </div>
                </div>`;
            document.getElementById('srPageJumpInput')?.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                let t = parseInt(this.value);
                if (isNaN(t)||t<1) t=1; if (t>totalPages) t=totalPages;
                this.value = t; changeSRPage(t);
            });
        }

        function getSRPageNums(cur, tot) {
            if (tot<=7) return Array.from({length:tot},(_,i)=>i+1);
            if (cur<=4) return [1,2,3,4,5,'...',tot];
            if (cur>=tot-3) return [1,'...',tot-4,tot-3,tot-2,tot-1,tot];
            return [1,'...',cur-1,cur,cur+1,'...',tot];
        }
        function changeSRPage(n) { if (n >= 1) fetchSR(n); }
        function changeSRRows(value) {
            SR_ROWS_PER_PAGE = parseInt(value);
            localStorage.setItem('srRowsPerPage', value);
            fetchSR(1);
        }
        function filterTable() { fetchSR(1); }

        /* ── Date label ───────────────────────────────────────── */
        function updateSRDateLabel(dates) {
            if (!dates.length) { document.getElementById('srDateRangeLabel').textContent = 'Today'; return; }
            const today = fmtISO(new Date());
            const fmt = d => d.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
            const isSame = dates.length > 1 && dates[0].toDateString() === dates[1].toDateString();
            const single = dates.length === 1 || isSame;
            document.getElementById('srDateRangeLabel').textContent =
                (single && fmtISO(dates[0]) === today) ? 'Today'
                : single ? fmt(dates[0]) : fmt(dates[0]) + ' – ' + fmt(dates[1]);
        }

        /* ── Status filter ────────────────────────────────────── */
        document.querySelectorAll('#statusMenu .dropdown-item').forEach(item => {
            item.addEventListener('click', e => {
                e.preventDefault();
                document.getElementById('statusLabel').textContent = item.textContent.trim();
                currentStatus = item.dataset.value;
                fetchSR(1);
            });
        });

        /* ── Init ─────────────────────────────────────────────── */
        document.addEventListener('DOMContentLoaded', () => {
            updateSRDateLabel(srDateFrom && srDateTo
                ? [new Date(srDateFrom+'T00:00'), new Date(srDateTo+'T00:00')] : []);

            flatpickr(document.getElementById('srDatePickerBtn'), {
                mode: 'range', dateFormat: 'Y-m-d',
                defaultDate: srDateFrom && srDateTo ? [srDateFrom, srDateTo] : 'today',
                onChange(dates) {
                    srDateFrom = dates.length >= 1 ? fmtISO(dates[0]) : null;
                    srDateTo   = dates.length === 2 ? fmtISO(dates[1]) : srDateFrom;
                    localStorage.setItem('srDateFrom', srDateFrom || '');
                    localStorage.setItem('srDateTo',   srDateTo   || '');
                    updateSRDateLabel(dates);
                    fetchSR(1);
                }
            });

            document.getElementById('searchInput').addEventListener('input', () => {
                clearTimeout(srSearchTimeout);
                srSearchTimeout = setTimeout(() => fetchSR(1), 400);
            });

            fetchSR(1);
        });

        // ---- CONFIRM ACTION MODAL ----
        document.addEventListener('click', e => {
            const btn = e.target.closest('.confirm-action-btn');
            if (!btn) return;
            const label    = btn.dataset.label;
            const body     = btn.dataset.body || 'Are you sure you want to ' + label.toLowerCase() + '?';
            const btnClass = btn.dataset.btnClass || 'btn-primary';
            document.getElementById('confirmActionTitle').textContent = label;
            document.getElementById('confirmActionBody').textContent  = body;
            const cb = document.getElementById('confirmActionBtn');
            cb.href = btn.dataset.url; cb.className = 'btn ' + btnClass; cb.textContent = label;
        });

        <?php if ($success || $error): ?>
        showToast(<?= json_encode($success ?: $error) ?>, '<?= $success ? 'success' : 'danger' ?>');
        <?php endif; ?>

    </script>
</body>
</html>
