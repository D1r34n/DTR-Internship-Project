<?php
// Start the session to access session variables
session_start();

// Redirect unauthenticated users to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Load database connection and helper libraries
require_once '../db.php';
require_once '../system_functions/system_library.php';
require_once '../system_functions/system_service.php';

// Set timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// Set the active sidebar item and get the logged-in employee's ID
$current_page = 'records';
$employeeId   = $_SESSION['user_id'];

// Read optional date range from GET params (used when user picks a range)
$startDate = $_GET['start'] ?? null;
$endDate   = $_GET['end'] ?? null;

// Default to the current calendar month if no date range is provided
if (!$startDate && !$endDate) {
    $startDate = date('Y-m-01'); // First day of the current month
    $endDate   = date('Y-m-t');  // Last day of the current month
}

// Fetch attendance records and schedules for the employee within the selected range
$records   = getAttendanceRecords($pdo, $employeeId, $startDate, $endDate);
$schedules = getSchedulesByDateRange($pdo, $employeeId, $startDate, $endDate);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Schedule</title>

    <!-- Bootstrap CSS framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <!-- Flatpickr date picker CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Flatpickr JS (loaded early since it has no dependencies) -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
    
    <!-- Project-specific stylesheets -->
    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link rel="stylesheet" href="employee_records.css">
</head>
<body>
    <!-- Shared sidebar navigation -->
    <?php include '../sidebar.php'; ?>

    <!-- Shared top navigation bar -->
    <?php include '../topbar.php'; ?>

<div class="recordBoxWrapper">
    <div class="recordBox">

        <!-- Header: shows a clickable date range that opens the date picker -->
        <div class="recordHeader">
            <div class="dateWrapper">
                <!-- Display the currently selected date range as human-readable text -->
                <input type="text" id="dateRangePicker"
                    value="<?= date('F j', strtotime($startDate)) ?> – <?= date('F j, Y', strtotime($endDate)) ?>"
                    class="recordTitle"
                    readonly>

                <!-- Chevron icon acting as a visual cue to open the date picker -->
                <i class="bi bi-chevron-down dateIcon"></i>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize Flatpickr as a date range picker on the header input
            flatpickr('#dateRangePicker', {
                mode: 'range',          // Allow selecting a start and end date
                dateFormat: 'Y-m-d',    // Internal format sent to the server
                altInput: true,         // Show a human-friendly display input
                altFormat: 'F j, Y',    // e.g. "April 1, 2025"
                defaultDate: ['<?= $startDate ?>', '<?= $endDate ?>'], // Pre-select the active range

                // Reload the page with the new date range once both dates are chosen
                onChange(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        const start = instance.formatDate(selectedDates[0], "Y-m-d");
                        const end   = instance.formatDate(selectedDates[1], "Y-m-d");
                        window.location.href = `?start=${start}&end=${end}`;
                    }
                }
            });
        });
        </script>

        <!-- Attendance chart: one row per attendance record -->
        <canvas class="attendanceChartContainer" id="attendanceChart"></canvas>

        <script>
        const attendanceData = <?= json_encode($records) ?>;

        const labels = attendanceData.map(r => r.work_date);

        // convert hours → minutes for stacking consistency
        const late = attendanceData.map(r => parseInt(r.late_minutes ?? 0));
        const overtime = attendanceData.map(r => parseInt(r.overtime_minutes ?? 0));
        const undertime = attendanceData.map(r => parseInt(r.undertime_minutes ?? 0));

        // convert hours to minutes (important fix)
        const work = attendanceData.map(r => {
            const hours = parseFloat(r.total_work_hours ?? 0);
            return Math.max(0, Math.round(hours * 60));
        });

        Chart.register(ChartDataLabels);

        new Chart(document.getElementById('attendanceChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'LATE',
                        data: late,
                        backgroundColor: 'rgba(255, 170, 0, 0.75)',
                        barThickness: 20
                    },
                    {
                        label: 'ONTIME',
                        data: work,
                        backgroundColor: 'rgba(25, 135, 84, 0.75)',
                        barThickness: 20
                    },
                    {
                        label: 'OVERTIME',
                        data: overtime,
                        backgroundColor: '#4da3ff',
                        barThickness: 20
                    },
                    {
                        label: 'UNDERTIME',
                        data: undertime,
                        backgroundColor: '#7c3aed',
                        barThickness: 20
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                scales: {
                    x: { stacked: true },
                    y: { stacked: true }
                },

                plugins: {
                    legend: {
                        display: false
                    },

                    datalabels: {
                        color: '#fff',
                        font: {
                            weight: 'bold',
                            size: 10
                        },

                        formatter: function(value, context) {
                            return value > 0 ? context.dataset.label : '';
                        },

                        display: function(context) {
                            return context.active === true;
                        },

                        anchor: 'center',
                        align: 'center',
                        clamp: true
                    },

                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + ' min';
                            }
                        }
                    }
                }
            }
        });
        </script>
    </div>
</div>

<!-- Hover tooltip — populated dynamically by initGanttCursors() -->
<div id="gantt_tooltip">
    <div class="ganttToolTipRow"><span class="ganttToolTipLabel">Scheduled</span><span class="ganttToolTipValue" id="gt-sched"></span></div>
    <div class="ganttToolTipRow"><span class="ganttToolTipLabel">Time In</span><span class="ganttToolTipValue" id="gt-actual-in"></span></div>
    <div class="ganttToolTipRow"><span class="ganttToolTipLabel">Time Out</span><span class="ganttToolTipValue" id="gt-actual-out"></span></div>
    <!-- Late row — hidden by default, shown only when the employee was tardy -->
    <div class="ganttToolTipRow ganttToolTipLate" id="gt-late-row"><span class="ganttToolTipLabel">Late</span><span class="ganttToolTipValue" id="gt-late"></span></div>
    <!-- Overtime row — hidden by default, shown only when overtime exists -->
    <div class="ganttToolTipRow ganttToolTipOverTime" id="gt-ot-row"><span class="ganttToolTipLabel">Overtime</span><span class="ganttToolTipValue" id="gt-ot"></span></div>
    <!-- Undertime row — hidden by default, shown only when undertime exists -->
    <div class="ganttToolTipRow ganttToolTipUnderTime" id="gt-ut-row"><span class="ganttToolTipLabel">Undertime</span><span class="ganttToolTipValue" id="gt-ut"></span></div>
</div>

<!-- Bootstrap JS bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Wire up cursor tracking and tooltip behavior for all Gantt rows
        initGanttCursors();
    });
</script>
</body>
</html>