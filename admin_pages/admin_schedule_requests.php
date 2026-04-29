<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
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

$current_page = 'schedule_requests';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Schedule Requests</title>

    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="admin_requests.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body::before { background-image: url('../images/drt_bg.jpg'); }
    </style>
</head>
<body>

    <?php include '../sidebar.php'; ?>
    <?php include '../topbar.php'; ?>

    <div class="requestsWrapper">
        <div class="requestsBox">

            <!-- TITLE ROW -->
            <div class="adminTitleRow">
                <h5 class="adminTitle">Schedule Requests</h5>
                <input type="text" id="searchInput" class="searchInput" placeholder="Search employee..." onkeyup="searchTable()">
            </div>

            <!-- ALERTS -->
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <!-- SUMMARY CARDS -->
            <div class="summaryCards">
                <div class="reqCard pending">
                    <i class="bi bi-hourglass-split"></i>
                    <div>
                        <p>Pending</p>
                        <h5><?= $pendingCount ?></h5>
                    </div>
                </div>
                <div class="reqCard approved">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <p>Approved</p>
                        <h5><?= $approvedCount ?></h5>
                    </div>
                </div>
                <div class="reqCard rejected">
                    <i class="bi bi-x-circle-fill"></i>
                    <div>
                        <p>Rejected</p>
                        <h5><?= $rejectedCount ?></h5>
                    </div>
                </div>
            </div>

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
                                               class="btn btn-sm approveBtn"
                                               onclick="return confirm('Approve this schedule?')">
                                                <i class="bi bi-check-lg"></i> Approve
                                            </a>
                                            <a href="admin_schedule_requests.php?action=reject&id=<?= $row['id'] ?>"
                                               class="btn btn-sm rejectBtn"
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
                            <tr><td colspan="8" class="text-center" style="color:rgba(255,255,255,0.4);padding:2rem;">
                                No schedule requests found.
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <?php
    function getStatusBadge($status) {
        $badges = [
            'pending'  => '<span class="badge statusPending">Pending</span>',
            'approved' => '<span class="badge statusApproved">Approved</span>',
            'rejected' => '<span class="badge statusRejected">Rejected</span>',
        ];
        return $badges[$status] ?? '<span class="badge">Unknown</span>';
    }
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
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
