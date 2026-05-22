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
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $id     = (int)$_GET['id'];

    // Manager: verify the schedule belongs to an employee in their department
    if ($deptScoped) {
        $chkStmt = $pdo->prepare("SELECT s.id FROM schedules s JOIN employees e ON s.employee_id = e.id WHERE s.id = ? AND e.department_id = ?");
        $chkStmt->execute([$id, $myDeptId]);
        if (!$chkStmt->fetch()) {
            $error = "Unauthorized action.";
            goto skip_action_sr;
        }
    }

    if ($action === 'approve') {
        $schedRow = $pdo->prepare("SELECT * FROM schedules WHERE id = ? AND status = 'pending'");
        $schedRow->execute([$id]);
        $sched = $schedRow->fetch(PDO::FETCH_ASSOC);

        if ($sched) {
            $pdo->prepare("UPDATE schedules SET status = 'approved' WHERE id = ?")->execute([$id]);

            $pdo->prepare("
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
            ")->execute([
                $sched['employee_id'],
                $sched['id'],
                $sched['schedule_date'],
                $sched['scheduled_start'],
                $sched['scheduled_end'],
            ]);

            $success = "Schedule approved successfully!";
        }

    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE schedules SET status = 'rejected' WHERE id = ? AND status = 'pending'")->execute([$id]);
        $success = "Schedule request rejected.";
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
    $pendingCount  = $pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending'  AND is_rest_day = 0")->fetchColumn();
    $approvedCount = $pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'approved' AND is_rest_day = 0")->fetchColumn();
    $rejectedCount = $pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'rejected' AND is_rest_day = 0")->fetchColumn();
}

// ---- GET SCHEDULE REQUESTS ----
if ($deptScoped) {
    $srStmt = $pdo->prepare("
        SELECT s.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, d.department_code,
               CONCAT(r.first_name, ' ', r.last_name) AS requested_by_name,
               rr.role_key AS requested_by_role
        FROM schedules s
        JOIN employees e ON s.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees r ON s.requested_by = r.id
        LEFT JOIN roles rr ON r.role_id = rr.id
        WHERE s.is_rest_day = 0 AND e.department_id = ?
        ORDER BY FIELD(s.status, 'pending', 'approved', 'rejected'), s.schedule_date DESC
        LIMIT 300
    ");
    $srStmt->execute([$myDeptId]);
    $scheduleRequests = $srStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $scheduleRequests = $pdo->query("
        SELECT s.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, d.department_code,
               CONCAT(r.first_name, ' ', r.last_name) AS requested_by_name,
               rr.role_key AS requested_by_role
        FROM schedules s
        JOIN employees e ON s.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN employees r ON s.requested_by = r.id
        LEFT JOIN roles rr ON r.role_id = rr.id
        WHERE s.is_rest_day = 0
        ORDER BY FIELD(s.status, 'pending', 'approved', 'rejected'), s.schedule_date DESC
        LIMIT 300
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function getRolePill(?string $name, ?string $role): string {
    if (!$name) return '<span style="color:rgba(255,255,255,0.3)">—</span>';
    $icon  = $role === 'superadmin' ? 'bi-shield-fill' : 'bi-person-fill';
    $class = $role ? 'empRoleBadge empRole-' . htmlspecialchars($role) : '';
    return '<span class="pill ' . $class . '"><i class="bi ' . $icon . '"></i> ' . htmlspecialchars($name) . '</span>';
}

function getStatusBadge(string $status): string {
    $badges = [
        'pending'  => '<span class="badge status-pending">Pending</span>',
        'approved' => '<span class="badge status-approved">Approved</span>',
        'rejected' => '<span class="badge status-rejected">Rejected</span>',
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

                <!-- Toast -->
                <?php if ($success || $error): ?>
                    <div class="custom-toast <?= $success ? 'toast-success' : 'toast-error' ?>" id="customToast">
                        <div class="toast-content">
                            <i class="bi <?= $success ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> toast-icon"></i>
                            <span><?= htmlspecialchars($success ?: $error) ?></span>
                        </div>
                        <button class="toast-close" onclick="closeToast()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                <?php endif; ?>

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
                            <th>Requested By</th><th>Status</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($scheduleRequests as $row): ?>
                                <?php
                                    $startHour    = $row['scheduled_start'] ? (int)date('H', strtotime($row['scheduled_start'])) : 6;
                                    $isNightShift = ($startHour >= 18 || $startHour < 6);
                                ?>
                                <tr data-status="<?= $row['status'] ?>">
                                    <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                    <td><?= $row['department_code'] ? htmlspecialchars($row['department_code']) : '—' ?></td>
                                    <td><?= date('M d, Y', strtotime($row['schedule_date'])) ?></td>
                                    <td><?= $row['scheduled_start'] ? date('h:i A', strtotime($row['scheduled_start'])) : '—' ?></td>
                                    <td><?= $row['scheduled_end']   ? date('h:i A', strtotime($row['scheduled_end']))   : '—' ?></td>
                                    <td>
                                        <?php if ($isNightShift): ?>
                                            <span class="badge request-overtime">Night</span>
                                        <?php else: ?>
                                            <span class="badge status-info">Day</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= getRolePill($row['requested_by_name'] ?? null, $row['requested_by_role'] ?? null) ?></td>
                                    <td><?= getStatusBadge($row['status']) ?></td>
                                    <td class="actionsCol">
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <a href="admin_schedule_requests.php?action=approve&id=<?= $row['id'] ?>"
                                               class="btn btn-sm btn-success"
                                               onclick="return confirm('Approve this schedule?')">
                                                <i class="bi bi-check-lg"></i> Approve
                                            </a>
                                            <a href="admin_schedule_requests.php?action=reject&id=<?= $row['id'] ?>"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Reject this schedule?')">
                                                <i class="bi bi-x-lg"></i> Reject
                                            </a>
                                        <?php else: ?>
                                            <span class="no-action-text">No actions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($scheduleRequests)): ?>
                    <div class="table-empty">
                        <i class="bi bi-calendar2-x-fill"></i>
                        <div class="text-meta">No schedule requests found.</div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div><!-- #main-wrapper -->

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

        function closeToast() {
            const toast = document.getElementById('customToast');
            if (toast) {
                toast.classList.add('toast-hide');
                setTimeout(() => toast.remove(), 400);
            }
        }
        setTimeout(() => closeToast(), 3000);

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
