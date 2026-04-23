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

$records   = getAttendanceRecords($pdo, $employeeId, $startDate, $endDate);
$schedules = getSchedulesByDateRange($pdo, $employeeId, $startDate, $endDate);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Employee Records</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- Apache ECharts -->
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
</style>
</head>

<body>

<?php include '../sidebar.php'; ?>
<?php include '../topbar.php'; ?>

<div class="recordBoxWrapper">
<div class="recordBox">

<!-- DATE PICKER -->
<div class="recordHeader">
    <div class="dateWrapper">
        <input type="text" id="dateRangePicker"
            value="<?= date('F j', strtotime($startDate)) ?> – <?= date('F j, Y', strtotime($endDate)) ?>"
            class="recordTitle" readonly>
        <i class="bi bi-chevron-down dateIcon"></i>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    flatpickr('#dateRangePicker', {
        mode: 'range',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'F j, Y',
        defaultDate: ['<?= $startDate ?>', '<?= $endDate ?>'],
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

<!-- TIMELINE -->
<div id="attendanceTimeline"></div>

</div>
</div>

<script>
const records   = <?= json_encode(array_values($records)) ?>;
const schedules = <?= json_encode($schedules) ?>;

const startDate = "<?= $startDate ?>";
const endDate   = "<?= $endDate ?>";

// ── Formatters ────────────────────────────────────────────────────────────────

function toTs(datetimeStr) {
    if (!datetimeStr) return null;
    return new Date(datetimeStr.replace(' ', 'T')).getTime();
}

function fmtTime(ts) {
    const d    = new Date(ts);
    let h      = d.getHours();
    const m    = String(d.getMinutes()).padStart(2, '0');
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${m} ${ampm}`;
}

function fmtLabel(dateStr) {
    const d        = new Date(dateStr + 'T00:00:00');
    const now      = new Date();
    const isToday  = d.toDateString() === now.toDateString();
    const monthDay = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    const weekday  = isToday ? 'Today' : d.toLocaleDateString('en-US', { weekday: 'short' });
    return `${weekday}\n${monthDay}`;
}

// ── renderItem (shared by all series) ────────────────────────────────────────

function renderBar(params, api) {
    const yIdx      = api.value(0);
    const start     = api.coord([api.value(1), yIdx]);
    const end       = api.coord([api.value(2), yIdx]);
    const barHeight = api.size([0, 1])[1] * 0.45;

    return {
        type: 'rect',
        shape: {
            x:      start[0],
            y:      start[1] - barHeight / 2,
            width:  Math.max(end[0] - start[0], 2),
            height: barHeight,
            r:      4
        },
        style:    api.style(),
        emphasis: api.styleEmphasis()
    };
}

// ── buildOption ───────────────────────────────────────────────────────────────

function buildOption(records, schedules) {
    const yCategories = [];
    const barData     = {
        scheduled:  [],
        work:       [],
        late:       [],
        ot:         [],
        undertime:  [],
        notimeout:  []
    };
    const allTs = [];

    const sorted = [...records].sort((a, b) => b.work_date.localeCompare(a.work_date));

    sorted.forEach((row, yi) => {
        const sched      = schedules[row.work_date] || null;
        const schedStart = toTs(sched?.scheduled_start_datetime);
        const schedEnd   = toTs(sched?.scheduled_end_datetime);
        const actualIn   = toTs(row.actual_time_in);
        const actualOut  = toTs(row.actual_time_out);
        const status     = row.status;
        const now        = Date.now();

        yCategories.push(fmtLabel(row.work_date));

        // collect timestamps for x-axis auto-fit
        if (schedStart) allTs.push(schedStart);
        if (schedEnd)   allTs.push(schedEnd);
        if (actualIn)   allTs.push(actualIn);
        if (actualOut)  allTs.push(actualOut);

        // Schedule bar
        if (schedStart && schedEnd) {
            barData.scheduled.push({ value: [yi, schedStart, schedEnd] });
        }

        // No Timeout bar — status is incomplete (tapped IN, never OUT)
        // and the shift has fully expired. Draw from actualIn to schedEnd.
        if (status === 'incomplete' && actualIn && schedEnd && now > schedEnd) {
            const noTimeoutEnd = schedEnd;
            allTs.push(noTimeoutEnd);
            barData.notimeout.push({ value: [yi, actualIn, noTimeoutEnd] });
        }

        // Work bar — only if both times exist, clamped to schedEnd
        if (actualIn && actualOut && status !== 'incomplete') {
            const workEnd = (schedEnd && actualOut > schedEnd) ? schedEnd : actualOut;
            if (workEnd > actualIn) {
                barData.work.push({ value: [yi, actualIn, workEnd] });
            }
        }

        // Late bar — gap between schedStart and actualIn
        if (row.late_minutes > 0 && schedStart) {
            barData.late.push({
                value: [yi, schedStart, schedStart + row.late_minutes * 60000]
            });
        }

        // OT bar — actualOut goes past schedEnd
        if (actualOut && schedEnd && actualOut > schedEnd && status !== 'incomplete') {
            barData.ot.push({ value: [yi, schedEnd, actualOut] });
        }

        // Undertime bar — left early, shift has fully ended
        if (actualOut && schedEnd && actualOut < schedEnd && now > schedEnd && status !== 'incomplete') {
            barData.undertime.push({ value: [yi, actualOut, schedEnd] });
        }
    });

    // x-axis window
    const refDate = sorted.length
        ? sorted[sorted.length - 1].work_date
        : new Date().toISOString().slice(0, 10);
    const xMin = allTs.length ? Math.min(...allTs) - 30 * 60000 : new Date(`${refDate}T06:00:00`).getTime();
    const xMax = allTs.length ? Math.max(...allTs) + 30 * 60000 : new Date(`${refDate}T20:00:00`).getTime();

    return {
        backgroundColor: 'transparent',
        tooltip: {
            trigger: 'item',
            formatter(params) {
                const { seriesName, data } = params;
                const dur = Math.round((data.value[2] - data.value[1]) / 60000);
                return `<b>${seriesName}</b><br>${fmtTime(data.value[1])} – ${fmtTime(data.value[2])}<br>${dur} min`;
            }
        },
        legend: {
            top: 8, right: 16,
            data: ['Schedule', 'Work', 'Late', 'Overtime', 'Undertime', 'No Timeout'],
            textStyle: { color: '#ccc' }
        },
        grid: { left: 110, right: 24, top: 48, bottom: 40 },
        xAxis: {
            type: 'time',
            min: xMin,
            max: xMax,
            axisLabel: { formatter: val => fmtTime(val), color: '#aaa' },
            splitLine: { lineStyle: { color: 'rgba(255,255,255,0.06)' } },
            axisLine:  { lineStyle: { color: 'rgba(255,255,255,0.15)' } }
        },
        yAxis: {
            type: 'category',
            data: yCategories,
            axisLabel: { color: '#ccc' },
            axisLine:  { lineStyle: { color: 'rgba(255,255,255,0.15)' } },
            splitLine: { show: false }
        },
        series: [
            {
                name: 'Schedule',
                type: 'custom',
                renderItem: renderBar,
                encode: { x: [1, 2], y: 0 },
                data: barData.scheduled,
                itemStyle: {
                    color: 'rgba(150,150,255,0.18)',
                    borderColor: '#7b7bff',
                    borderWidth: 1,
                    borderType: 'dashed'
                },
                z: 1
            },
            {
                name: 'Work',
                type: 'custom',
                renderItem: renderBar,
                encode: { x: [1, 2], y: 0 },
                data: barData.work,
                itemStyle: { color: 'rgba(25,135,84,0.80)' },
                z: 2
            },
            {
                name: 'No Timeout',
                type: 'custom',
                renderItem: renderBar,
                encode: { x: [1, 2], y: 0 },
                data: barData.notimeout,
                itemStyle: {
                    color: 'rgba(180,100,255,0.80)',
                    borderColor: '#c084fc',
                    borderWidth: 1,
                    borderType: 'dashed'
                },
                z: 2
            },
            {
                name: 'Late',
                type: 'custom',
                renderItem: renderBar,
                encode: { x: [1, 2], y: 0 },
                data: barData.late,
                itemStyle: { color: 'rgba(255,170,0,0.85)' },
                z: 3
            },
            {
                name: 'Overtime',
                type: 'custom',
                renderItem: renderBar,
                encode: { x: [1, 2], y: 0 },
                data: barData.ot,
                itemStyle: { color: 'rgba(77,163,255,0.85)' },
                z: 3
            },
            {
                name: 'Undertime',
                type: 'custom',
                renderItem: renderBar,
                encode: { x: [1, 2], y: 0 },
                data: barData.undertime,
                itemStyle: { color: 'rgba(255,80,80,0.80)' },
                z: 3
            }
        ]
    };
}

// ── Chart init ────────────────────────────────────────────────────────────────

const chart = echarts.init(document.getElementById('attendanceTimeline'));
chart.setOption(buildOption(records, schedules));
window.addEventListener('resize', () => chart.resize());

// ── Update / Refresh ──────────────────────────────────────────────────────────

function updateChart(records, schedules) {
    chart.setOption(buildOption(records, schedules), true);
}

function refreshChart() {
    fetch(`../system_functions/employee_records_data.php?start=${startDate}&end=${endDate}`)
        .then(res => res.json())
        .then(data => updateChart(data.records, data.schedules))
        .catch(err => console.error('refreshChart error:', err));
}

refreshChart();
</script>

</body>
</html>