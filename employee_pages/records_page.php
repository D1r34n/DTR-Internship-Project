<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

date_default_timezone_set('Asia/Manila');

$currentPage = 'records';

$rawMonth = $_GET['month'] ?? date('Y-m');
[$yr, $mn] = array_pad(array_map('intval', explode('-', $rawMonth)), 2, 0);
if ($yr < 2000 || $mn < 1 || $mn > 12) { $yr = (int)date('Y'); $mn = (int)date('n'); }
$recordsMonth = sprintf('%04d-%02d', $yr, $mn);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Records</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
    <script src="../system_functions/gantt.js"></script>

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">
    <link rel="stylesheet" href="records_page.css">
    <link rel="stylesheet" href="records_widget.css">
</head>
<body>
    <?php include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">
        <?php include '../topbar_revised.php'; ?>

        <!-- SUMMARY CARDS -->
        <div class="container-fluid flex-shrink-0 px-3 pt-2">
            <div class="row g-3">

                <!-- PENDING -->
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card card-neutral p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-neutral">
                                <i class="bi bi-hourglass-split fs-4"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number" id="stat-pending">—</div>
                                <div class="text-meta">Upcoming</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PRESENT -->
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card card-success p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-success">
                                <i class="bi bi-check-circle-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number" id="stat-present">—</div>
                                <div class="text-meta">Present</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ABSENT -->
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card card-danger p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-danger">
                                <i class="bi bi-x-circle-fill fs-3"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number" id="stat-absent">—</div>
                                <div class="text-meta">Absent</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="card card-neutral records-card">
            <div class="card-body records-card-body">
                <?php include 'records_widget.php'; ?>
            </div>
        </div>

    </div><!-- #main-wrapper -->

</body>
</html>
