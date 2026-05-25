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

try {
    $pdo->exec("ALTER TABLE schedules ADD COLUMN pending_delete TINYINT(1) NOT NULL DEFAULT 0");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE schedules ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE schedules ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE schedules ADD COLUMN request_type ENUM('added','edit','deleted') NULL DEFAULT NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE schedules ADD COLUMN batch_id VARCHAR(32) NULL DEFAULT NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE schedules ADD COLUMN orig_is_rest_day TINYINT(1) NULL DEFAULT NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE schedules ADD COLUMN orig_scheduled_start DATETIME NULL DEFAULT NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE schedules ADD COLUMN orig_scheduled_end DATETIME NULL DEFAULT NULL");
} catch (PDOException $e) {}

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
            $q = $pdo->prepare("SELECT * FROM schedules WHERE batch_id = ? AND status = 'pending'");
            $q->execute([$batchId]);
        } else {
            $q = $pdo->prepare("SELECT * FROM schedules WHERE id = ? AND status = 'pending'");
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
            $pdo->prepare("UPDATE schedules SET status = 'approved', updated_at = NOW() WHERE id = ?")->execute([$sched['id']]);
            if (!$sched['is_rest_day']) {
                $attStmt->execute([
                    $sched['employee_id'], $sched['id'], $sched['schedule_date'],
                    $sched['scheduled_start'], $sched['scheduled_end'],
                ]);
            }
        }
        if (!empty($schedList)) $success = "Schedule approved successfully!";

    } elseif ($action === 'reject') {
        if ($batchId) {
            $q = $pdo->prepare("SELECT id, employee_id, schedule_date, request_type, orig_is_rest_day FROM schedules WHERE batch_id = ? AND status = 'pending'");
            $q->execute([$batchId]);
        } else {
            $q = $pdo->prepare("SELECT id, employee_id, schedule_date, request_type, orig_is_rest_day FROM schedules WHERE id = ? AND status = 'pending'");
            $q->execute([$id]);
        }
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);

        $restoreStmt = $pdo->prepare("
            UPDATE schedules SET
                status             = 'approved',
                is_rest_day        = orig_is_rest_day,
                scheduled_start    = orig_scheduled_start,
                scheduled_end      = orig_scheduled_end,
                orig_is_rest_day   = NULL,
                orig_scheduled_start = NULL,
                orig_scheduled_end   = NULL,
                request_type       = NULL,
                batch_id           = NULL,
                updated_at         = NOW()
            WHERE id = ?
        ");
        $rejectStmt = $pdo->prepare("UPDATE schedules SET status = 'rejected', updated_at = NOW() WHERE id = ?");
        $delAtt     = $pdo->prepare("DELETE FROM attendances WHERE employee_id = ? AND work_date = ? AND actual_time_in IS NULL");

        foreach ($rows as $row) {
            if ($row['request_type'] === 'edit' && $row['orig_is_rest_day'] !== null) {
                $restoreStmt->execute([$row['id']]);
            } else {
                $rejectStmt->execute([$row['id']]);
            }
            $delAtt->execute([$row['employee_id'], $row['schedule_date']]);
        }
        $success = "Schedule request rejected.";

    } elseif ($action === 'approve_delete') {
        if ($deptScoped) {
            $chkStmt = $pdo->prepare("SELECT s.id FROM schedules s JOIN employees e ON s.employee_id = e.id WHERE s.id = ? AND e.department_id = ? AND s.pending_delete = 1");
            $chkStmt->execute([$id, $myDeptId]);
            if (!$chkStmt->fetch()) { $error = "Unauthorized action."; goto skip_action_sr; }
        }
        $siStmt = $pdo->prepare("SELECT employee_id, schedule_date FROM schedules WHERE id = ? AND pending_delete = 1");
        $siStmt->execute([$id]);
        $si = $siStmt->fetch(PDO::FETCH_ASSOC);
        if ($si) {
            $pdo->prepare("DELETE FROM attendances WHERE employee_id = ? AND work_date = ? AND actual_time_in IS NULL")
                ->execute([$si['employee_id'], $si['schedule_date']]);
            $pdo->prepare("UPDATE schedules SET is_archived = 1, pending_delete = 0, updated_at = NOW() WHERE id = ?")
                ->execute([$id]);
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
    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM schedules s JOIN employees e ON s.employee_id = e.id WHERE s.status = ? AND s.is_rest_day = 0 AND e.department_id = ?");
    $cStmt->execute(['pending',  $myDeptId]); $pendingCount  = (int)$cStmt->fetchColumn();
    $cStmt->execute(['approved', $myDeptId]); $approvedCount = (int)$cStmt->fetchColumn();
    $cStmt->execute(['rejected', $myDeptId]); $rejectedCount = (int)$cStmt->fetchColumn();
} else {
    $pendingCount  = (int)$pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending'  AND is_rest_day = 0")->fetchColumn();
    $approvedCount = (int)$pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'approved' AND is_rest_day = 0")->fetchColumn();
    $rejectedCount = (int)$pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'rejected' AND is_rest_day = 0")->fetchColumn();
}

// ---- GET SCHEDULE REQUESTS (grouped by batch) ----
$batchSql = "
    SELECT
        g.*,
        CONCAT(r.first_name, ' ', r.last_name) AS requested_by_name,
        rr.role_key AS requested_by_role
    FROM (
        SELECT
            COALESCE(s.batch_id, CONCAT('solo_', s.id)) AS group_key,
            MIN(s.batch_id)                              AS batch_id,
            MIN(s.id)                                    AS id,
            MIN(s.employee_id)                           AS employee_id,
            MIN(CONCAT(e.first_name, ' ', e.last_name)) AS employee_name,
            MIN(d.department_code)                       AS department_code,
            GROUP_CONCAT(s.schedule_date ORDER BY s.schedule_date SEPARATOR ',') AS all_dates,
            MIN(s.scheduled_start) AS scheduled_start,
            MIN(s.scheduled_end)   AS scheduled_end,
            MIN(s.is_rest_day)     AS is_rest_day,
            MIN(s.status)          AS status,
            MAX(s.pending_delete)  AS pending_delete,
            MAX(COALESCE(s.is_archived, 0)) AS is_archived,
            MIN(s.request_type)    AS request_type,
            MIN(s.requested_by)    AS requested_by,
            MAX(s.updated_at)      AS updated_at,
            COUNT(*)               AS date_count
        FROM schedules s
        JOIN employees e ON s.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE (s.is_rest_day = 0 OR s.pending_delete = 1 OR COALESCE(s.is_archived, 0) = 1)
        %%DEPT%%
        GROUP BY COALESCE(s.batch_id, CONCAT('solo_', s.id))
    ) g
    LEFT JOIN employees r  ON g.requested_by = r.id
    LEFT JOIN roles     rr ON r.role_id = rr.id
    ORDER BY g.pending_delete DESC,
             CASE WHEN g.status = 'pending' THEN 0 ELSE 1 END ASC,
             g.updated_at DESC, g.id DESC
    LIMIT 300
";

if ($deptScoped) {
    $srStmt = $pdo->prepare(str_replace('%%DEPT%%', 'AND e.department_id = ?', $batchSql));
    $srStmt->execute([$myDeptId]);
    $scheduleRequests = $srStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $scheduleRequests = $pdo->query(str_replace('%%DEPT%%', '', $batchSql))->fetchAll(PDO::FETCH_ASSOC);
}

function formatScheduleDates(string $allDates): string {
    $dates = array_values(array_filter(explode(',', $allDates)));
    sort($dates);
    $n = count($dates);
    if ($n === 0) return '—';
    if ($n === 1) return date('M d, Y', strtotime($dates[0]));

    $consecutive = true;
    for ($i = 1; $i < $n; $i++) {
        if ((strtotime($dates[$i]) - strtotime($dates[$i - 1])) !== 86400) {
            $consecutive = false; break;
        }
    }

    if ($consecutive) {
        $first = new DateTime($dates[0]);
        $last  = new DateTime($dates[$n - 1]);
        if ($first->format('M Y') === $last->format('M Y')) {
            return $first->format('M d') . ' – ' . $last->format('d, Y');
        }
        return $first->format('M d') . ' – ' . $last->format('M d, Y');
    }

    $parts = array_map(fn($d) => date('M d', strtotime($d)), $dates);
    return implode(', ', $parts) . ', ' . date('Y', strtotime($dates[0]));
}

function getRolePill(?string $name, ?string $role): string {
    if (!$name) return '<span style="color:rgba(255,255,255,0.3)">—</span>';
    $icon  = $role === 'superadmin' ? 'bi-shield-fill' : 'bi-person-fill';
    $class = $role ? 'empRoleBadge empRole-' . htmlspecialchars($role) : '';
    return '<span class="pill ' . $class . '"><i class="bi ' . $icon . '"></i> ' . htmlspecialchars($name) . '</span>';
}

function getTypeBadge(?string $type): string {
    $badges = [
        'added'   => '<span class="badge status-approved">Added</span>',
        'edit'    => '<span class="badge status-info">Edit</span>',
        'deleted' => '<span class="badge status-rejected">Deleted</span>',
    ];
    return $badges[$type] ?? '<span style="color:rgba(255,255,255,0.3)">—</span>';
}

function getStatusBadge(string $status): string {
    $badges = [
        'pending'  => '<span class="badge status-pending">Pending</span>',
        'approved' => '<span class="badge status-approved">Approved</span>',
        'rejected' => '<span class="badge status-rejected">Rejected</span>',
        'deleted'  => '<span class="badge status-rejected">Deleted</span>',
    ];
    return $badges[$status] ?? '<span class="badge">Unknown</span>';
}
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
                        <tbody>
                            <?php if (count($scheduleRequests) > 0): ?>
                                <?php foreach ($scheduleRequests as $row): ?>
                                    <?php
                                        $startHour       = $row['scheduled_start'] ? (int)date('H', strtotime($row['scheduled_start'])) : 6;
                                        $isNightShift    = $startHour >= 18 || $startHour < 6;
                                        $isPendingDelete = !empty($row['pending_delete']);
                                        $isArchived      = !empty($row['is_archived']);
                                        $rowStatus       = $isArchived ? 'deleted' : $row['status'];
                                        $actionParam     = $row['batch_id']
                                            ? 'batch_id=' . urlencode($row['batch_id'])
                                            : 'id=' . $row['id'];
                                        $dateLabel       = formatScheduleDates($row['all_dates']);
                                    ?>
                                    <tr data-status="<?= $rowStatus ?>">
                                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                        <td><?= $row['department_code'] ? htmlspecialchars($row['department_code']) : '—' ?></td>
                                        <td><?= htmlspecialchars($dateLabel) ?></td>
                                        <td><?= $row['scheduled_start'] ? date('h:i A', strtotime($row['scheduled_start'])) : '—' ?></td>
                                        <td><?= $row['scheduled_end']   ? date('h:i A', strtotime($row['scheduled_end']))   : '—' ?></td>
                                        <td>
                                            <?php if ($isNightShift): ?>
                                                <span class="badge request-overtime">Night</span>
                                            <?php else: ?>
                                                <span class="badge status-approved">Day</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= getTypeBadge($row['request_type'] ?? null) ?></td>
                                        <td><?= getRolePill($row['requested_by_name'] ?? null, $row['requested_by_role'] ?? null) ?></td>
                                        <td>
                                            <?php if ($isArchived): ?>
                                                <?= getStatusBadge('deleted') ?>
                                            <?php elseif ($isPendingDelete): ?>
                                                <span class="badge status-pending">Pending Delete</span>
                                            <?php else: ?>
                                                <?= getStatusBadge($row['status']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actionsCol">
                                            <?php if ($isArchived): ?>
                                                <span class="no-action-text">No actions</span>
                                            <?php elseif ($isPendingDelete): ?>
                                                <button type="button"
                                                    class="btn btn-sm btn-danger confirm-action-btn"
                                                    data-url="admin_schedule_requests.php?action=approve_delete&id=<?= $row['id'] ?>"
                                                    data-label="Approve Delete"
                                                    data-body="Are you sure you want to approve this deletion? The schedule will be permanently removed."
                                                    data-btn-class="btn-danger"
                                                    data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                                                    <i class="bi bi-trash"></i> Approve Delete
                                                </button>
                                                <button type="button"
                                                    class="btn btn-sm btn-secondary confirm-action-btn"
                                                    data-url="admin_schedule_requests.php?action=reject_delete&id=<?= $row['id'] ?>"
                                                    data-label="Reject Delete"
                                                    data-body="Are you sure you want to reject this delete request?"
                                                    data-btn-class="btn-secondary"
                                                    data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                                                    <i class="bi bi-x-lg"></i> Reject
                                                </button>
                                            <?php elseif ($row['status'] === 'pending'): ?>
                                                <button type="button"
                                                    class="btn btn-sm btn-success confirm-action-btn"
                                                    data-url="admin_schedule_requests.php?action=approve&<?= $actionParam ?>"
                                                    data-label="Approve"
                                                    data-body="Are you sure you want to approve this schedule request?"
                                                    data-btn-class="btn-success"
                                                    data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                                <button type="button"
                                                    class="btn btn-sm btn-danger confirm-action-btn"
                                                    data-url="admin_schedule_requests.php?action=reject&<?= $actionParam ?>"
                                                    data-label="Reject"
                                                    data-body="Are you sure you want to reject this schedule request?"
                                                    data-btn-class="btn-danger"
                                                    data-bs-toggle="modal" data-bs-target="#confirmActionModal">
                                                    <i class="bi bi-x-lg"></i> Reject
                                                </button>
                                            <?php else: ?>
                                                <span class="no-action-text">No actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center">No schedule requests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

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

    <?php include '../toast.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStatus = 'ALL';

        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.tableScroll tbody tr').forEach(row => {
                const matchSearch = row.textContent.toLowerCase().includes(search);
                const matchStatus = currentStatus === 'ALL' || row.dataset.status === currentStatus;
                row.style.display = (matchSearch && matchStatus) ? '' : 'none';
            });
        }

        document.querySelectorAll('#statusMenu .dropdown-item').forEach(item => {
            item.addEventListener('click', e => {
                e.preventDefault();
                document.getElementById('statusLabel').textContent = item.textContent.trim();
                currentStatus = item.dataset.value;
                filterTable();
            });
        });

        // ---- CONFIRM ACTION MODAL ----
        document.addEventListener('click', e => {
            const btn = e.target.closest('.confirm-action-btn');
            if (!btn) return;
            const label    = btn.dataset.label;
            const url      = btn.dataset.url;
            const body     = btn.dataset.body || 'Are you sure you want to ' + label.toLowerCase() + '?';
            const btnClass = btn.dataset.btnClass || 'btn-primary';

            document.getElementById('confirmActionTitle').textContent = label;
            document.getElementById('confirmActionBody').textContent  = body;

            const confirmBtn = document.getElementById('confirmActionBtn');
            confirmBtn.href      = url;
            confirmBtn.className = 'btn ' + btnClass;
            confirmBtn.textContent = label;
        });

        <?php if ($success || $error): ?>
        showToast(<?= json_encode($success ?: $error) ?>, '<?= $success ? 'success' : 'danger' ?>');
        <?php endif; ?>

        // ---- HORIZONTAL MOUSE WHEEL SCROLL ----
        document.querySelectorAll('.table-scroll-wrapper, .tableScroll').forEach(wrapper => {
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
