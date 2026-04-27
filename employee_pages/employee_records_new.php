<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
require_once '../system_functions/system_library.php';
require_once '../system_functions/system_service.php';

date_default_timezone_set('Asia/Manila');

$current_page = 'records';
$employeeId   = $_SESSION['user_id'];

$startDate = $_GET['start'] ?? null;
$endDate   = $_GET['end'] ?? null;

if (!$startDate && !$endDate) {
    $startDate = date('Y-m-01');
    $endDate   = date('Y-m-t');
}

/**
 * RAW DATA
 */
$recordsRaw   = getAttendanceRecords($pdo, $employeeId, $startDate, $endDate);
$schedulesRaw = getSchedulesByDateRange($pdo, $employeeId, $startDate, $endDate);

/**
 * INDEX FOR FAST LOOKUP
 */
$records = [];
foreach ($recordsRaw as $r) {
    $records[$r['work_date']] = $r;
}

/**
 * FILTERED FINAL DATASET
 * RULE: show all attendance rows; skip only if the schedule explicitly marks the day as a rest day
 */
$finalRecords = [];

foreach ($records as $date => $record) {
    // skip if schedule says it's a rest day
    if (isset($schedulesRaw[$date]) && !empty($schedulesRaw[$date]['is_rest_day'])) {
        continue;
    }
    $finalRecords[] = $record;
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Employee Records</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

<link rel="stylesheet" href="../root.css">
<link rel="stylesheet" href="../side_and_top_bar.css">
<link rel="stylesheet" href="employee_records.css">

<style>
#attendanceTimeline {
    width: 100%;
    height: 600px;
    border-radius: 10px;
}
.absentRow {
    color: #ff4d4d;
    font-weight: 600;
}
</style>
</head>

<body>

<?php include '../sidebar.php'; ?>
<?php include '../topbar.php'; ?>

<div class="recordBoxWrapper">
<div class="recordBox">

<div class="recordHeader">
    <div class="dateWrapper">
        <input type="text" id="dateRangePicker"
            value="<?= date('F j', strtotime($startDate)) ?> – <?= date('F j, Y', strtotime($endDate)) ?>"
            class="recordTitle" readonly>
        <i class="bi bi-chevron-down dateIcon"></i>
    </div>
</div>

<div id="attendanceTimeline"></div>

</div>
</div>

<script>
    
const records   = <?= json_encode(array_values($finalRecords)) ?>;
const schedules = <?= json_encode($schedulesRaw) ?>;

console.log('records:', records);
console.log('schedules:', schedules);
const startDate = "<?= $startDate ?>";
const endDate   = "<?= $endDate ?>";

function toTs(v) {
    if (!v) return null;
    return new Date(v.replace(' ', 'T')).getTime();
}

function fmtLabel(dateStr, status) {
    const d = new Date(dateStr + 'T00:00:00');
    const monthDay = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    const weekday = d.toLocaleDateString('en-US', { weekday: 'short' });

    if (status === 'absent') {
        return `❌ ${weekday}\n${monthDay}`;
    }
    return `${weekday}\n${monthDay}`;
}

function renderBar(params, api) {
    const yIdx = api.value(0);
    const start = api.coord([api.value(1), yIdx]);
    const end   = api.coord([api.value(2), yIdx]);
    const h     = api.size([0, 1])[1] * 0.45;

    return {
        type: 'rect',
        shape: {
            x: start[0],
            y: start[1] - h / 2,
            width: Math.max(end[0] - start[0], 2),
            height: h,
            r: 4
        },
        style: api.style()
    };
}

function buildOption(records, schedules) {

    const y = [];
    const bars = {
        scheduled: [],
        work: [],
        late: [],
        ot: [],
        undertime: [],
        absent: []
    };

    records.forEach((row, i) => {

        const sched = schedules[row.work_date] || null;

        const schedStart = toTs(sched?.scheduled_start ?? row.scheduled_start);
        const schedEnd   = toTs(sched?.scheduled_end   ?? row.scheduled_end);
        const inTs       = toTs(row.actual_time_in);
        const outTs      = toTs(row.actual_time_out);

        y.push(fmtLabel(row.work_date, row.status));

        // ABSENT
        if (row.status === 'absent') {
            if (schedStart && schedEnd) {
                bars.absent.push({ value: [i, schedStart, schedEnd] });
            }
            return;
        }

        // SCHEDULE
        if (schedStart && schedEnd) {
            bars.scheduled.push({
                value: [i, schedStart, schedEnd]
            });
        }

        // WORK
        if (inTs && outTs) {
            bars.work.push({
                value: [i, inTs, outTs]
            });
        }

        // LATE
        if (row.late_minutes > 0 && inTs) {
            bars.late.push({
                value: [i, schedStart, inTs]
            });
        }

        // OT
        if (row.overtime_minutes > 0 && outTs) {
            bars.ot.push({
                value: [i, schedEnd, outTs]
            });
        }

        // UNDERTIME
        if (row.undertime_minutes > 0 && outTs) {
            bars.undertime.push({
                value: [i, outTs, schedEnd]
            });
        }
    });

    return {
        grid: { left: 120, right: 20, top: 40, bottom: 40 },
        xAxis: { type: 'time' },
        yAxis: { type: 'category', data: y },
        tooltip: { trigger: 'item' },
        series: [
            {
                name: 'Absent',
                type: 'custom',
                renderItem: renderBar,
                data: bars.absent,
                itemStyle: { color: '#ff4d4d' }
            },
            {
                name: 'Schedule',
                type: 'custom',
                renderItem: renderBar,
                data: bars.scheduled,
                itemStyle: { color: 'rgba(150,150,255,0.2)' }
            },
            {
                name: 'Work',
                type: 'custom',
                renderItem: renderBar,
                data: bars.work,
                itemStyle: { color: '#198754' }
            },
            {
                name: 'Late',
                type: 'custom',
                renderItem: renderBar,
                data: bars.late,
                itemStyle: { color: '#ffb020' }
            },
            {
                name: 'OT',
                type: 'custom',
                renderItem: renderBar,
                data: bars.ot,
                itemStyle: { color: '#4da3ff' }
            },
            {
                name: 'Undertime',
                type: 'custom',
                renderItem: renderBar,
                data: bars.undertime,
                itemStyle: { color: '#ff5a5a' }
            }
        ]
    };
}

const chart = echarts.init(document.getElementById('attendanceTimeline'));
chart.setOption(buildOption(records, schedules));
window.addEventListener('resize', () => chart.resize());
</script>

</body>
</html>