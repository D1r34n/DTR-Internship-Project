<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

date_default_timezone_set('Asia/Manila');

$startDate = !empty($_GET['start'])
    ? date('Y-m-d', strtotime($_GET['start']))
    : date('Y-m-d');

$endDate = !empty($_GET['end'])
    ? date('Y-m-d', strtotime($_GET['end']))
    : date('Y-m-d');

$currentPage = 'logs';
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Logs</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <link rel="stylesheet" href="logs_page.css">
    <link rel="stylesheet" href="logs_widget.css">

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <?php include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">
        <?php include '../topbar_revised.php'; ?>

        <div class="card card-neutral logs-card">
            <div class="card-header logs-header">
                <div class="dropdown">
                    <button class="btn btn-sm dropdown-toggle" id="datePickerBtn" type="button">
                        <i class="bi bi-calendar3"></i>
                        <span id="dateRangeLabel">Today</span>
                    </button>
                </div>

                <div class="dropdown">
                    <button class="btn btn-sm dropdown-toggle" type="button" id="logTypeToggle"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-funnel"></i>
                        <span id="logTypeLabel">All Types</span>
                    </button>
                    <ul class="dropdown-menu" id="logTypeMenu" style="max-height:340px;overflow-y:auto!important;overflow-x:hidden!important;">
                        <li><a class="dropdown-item" href="#" data-value="ALL">All Types</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-value="IN">Time In</a></li>
                        <li><a class="dropdown-item" href="#" data-value="OUT">Time Out</a></li>
                        <li><a class="dropdown-item" href="#" data-value="BREAK_IN">Break In</a></li>
                        <li><a class="dropdown-item" href="#" data-value="BREAK_OUT">Break Out</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-value="REQUEST_OT">Request OT</a></li>
                        <li><a class="dropdown-item" href="#" data-value="REQUEST_LEAVE">Request Leave</a></li>
                        <li><a class="dropdown-item" href="#" data-value="REQUEST_OB">Request OB</a></li>
                        <li><a class="dropdown-item" href="#" data-value="REQUEST_LOG_EDIT">Request Log Edit</a></li>
                        <li><a class="dropdown-item" href="#" data-value="REQUEST_CHANGE_SCHEDULE">Request Change Schedule</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-value="ADD_EMPLOYEE">Added Employee</a></li>
                        <li><a class="dropdown-item" href="#" data-value="EDIT_EMPLOYEE">Edited Employee</a></li>
                        <li><a class="dropdown-item" href="#" data-value="ADD_SCHEDULE">Added Schedule</a></li>
                        <li><a class="dropdown-item" href="#" data-value="EDIT_SCHEDULE">Edited Schedule</a></li>
                    </ul>
                </div>

                <input type="hidden" id="logTypeFilter" value="ALL">
                <input type="hidden" id="startDate" value="<?= $startDate ?>">
                <input type="hidden" id="endDate"   value="<?= $endDate ?>">
            </div>
            <div class="card-body logs-card-body">
                <?php $logsInlineHeader = true; include 'logs_widget.php'; ?>
            </div>
        </div>

    </div><!-- #main-wrapper -->

</body>
</html>