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
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $id     = (int)$_GET['id'];

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

// ---- COUNTS ----
$pendingCount  = $pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending'  AND is_rest_day = 0")->fetchColumn();
$approvedCount = $pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'approved' AND is_rest_day = 0")->fetchColumn();
$rejectedCount = $pdo->query("SELECT COUNT(*) FROM schedules WHERE status = 'rejected' AND is_rest_day = 0")->fetchColumn();

// ---- GET SCHEDULE REQUESTS ----
$scheduleRequests = $pdo->query("
    SELECT s.*, e.name AS employee_name, d.department_code
    FROM schedules s
    JOIN employees e ON s.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE s.is_rest_day = 0
    ORDER BY FIELD(s.status, 'pending', 'approved', 'rejected'), s.schedule_date DESC
    LIMIT 300
")->fetchAll(PDO::FETCH_ASSOC);

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

    <?php $currentPage = 'schedule_requests'; include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">

        <?php include '../topbar_revised.php'; ?>

        <div class="card card-glass requests-card">
            <div class="card-body d-flex flex-column requests-card-body">

                <!-- SUMMARY CARDS -->
                <div class="row g-2 mb-4">
                    <!-- Pending -->
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card card-glass card-pending h-100">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div class="icon-wrap">
                                    <i class="bi bi-hourglass-split fs-4"></i>
                                </div>
                                <div class="d-flex flex-column text-end">
                                    <p class="mb-0 text-meta">Pending</p>
                                    <h5 class="mb-0 stats-number"><?= $pendingCount ?></h5>
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
                                    <h5 class="mb-0 stats-number"><?= $approvedCount ?></h5>
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
                                    <h5 class="mb-0 stats-number"><?= $rejectedCount ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TITLE ROW -->
                <div class="adminTitleRow">
                    <span class="text-primary">Schedule Requests</span>
                    <input type="text" id="searchInput" class="searchInput" placeholder="Search employee..." onkeyup="searchTable()">
                </div>

                <!-- ALERTS -->
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <!-- TABLE -->
                <div class="tableScrollWrapper">
                    <table class="table table-bordered table-hover mt-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Shift</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($scheduleRequests) > 0): ?>
                                <?php foreach ($scheduleRequests as $row): ?>
                                    <?php
                                        $startHour    = $row['scheduled_start'] ? (int)date('H', strtotime($row['scheduled_start'])) : 6;
                                        $isNightShift = ($startHour >= 18 || $startHour < 6);
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                        <td><?= $row['department_code'] ? htmlspecialchars($row['department_code']) : '—' ?></td>
                                        <td><?= date('M d, Y', strtotime($row['schedule_date'])) ?></td>
                                        <td><?= $row['scheduled_start'] ? date('h:i A', strtotime($row['scheduled_start'])) : '—' ?></td>
                                        <td><?= $row['scheduled_end']   ? date('h:i A', strtotime($row['scheduled_end']))   : '—' ?></td>
                                        <td>
                                            <?php if ($isNightShift): ?>
                                                <span class="badge scheduleBadgeNight">Night Shift</span>
                                            <?php else: ?>
                                                <span class="badge scheduleBadgeDay">Day Shift</span>
                                            <?php endif; ?>
                                        </td>
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
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center">No schedule requests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div><!-- #main-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function searchTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.tableScrollWrapper tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity    = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 3000);
    </script>
</body>
</html>
