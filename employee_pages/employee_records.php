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

/**
 * Format a real timestamp (ms since epoch) as a clock time string.
 */
function fmtTime(ts) {
    const d    = new Date(ts);
    let h      = d.getHours();
    const m    = String(d.getMinutes()).padStart(2, '0');
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${m} ${ampm}`;
}

/**
 * Format an offset (ms from shift anchor) as a clock time string,
 * given the anchor timestamp so we can recover the real wall-clock time.
 */
function fmtOffset(offsetMs, anchorTs) {
    return fmtTime(anchorTs + offsetMs);
}

function fmtLabel(dateStr) {
    const d       = new Date(dateStr + 'T00:00:00');
    const now     = new Date();
    const isToday = d.toDateString() === now.toDateString();
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

    // Track all relative offsets across all rows to compute a shared x-axis window.
    // Each row is normalised to its own anchor (schedStart ?? actualIn),
    // so offset 0 always means "shift start" and the x-axis represents duration.
    let globalMinOffset = Infinity;
    let globalMaxOffset = -Infinity;

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

        // Anchor: prefer schedStart so offset 0 = scheduled shift start.
        // Fall back to actualIn if no schedule exists.
        const anchor = schedStart ?? actualIn ?? 0;

        /**
         * Convert an absolute timestamp to a relative offset from this row's anchor.
         * This eliminates date differences so night-shift rows (e.g. 6 PM – 1 AM)
         * and day-shift rows (e.g. 9 AM – 5 PM) are both plotted correctly:
         * a timestamp that is 2 hours after anchor becomes offset = 2 * 3600000
         * regardless of which calendar date the shift falls on.
         */
        function rel(ts) {
            return ts != null ? ts - anchor : null;
        }

        // Helper to track x-axis extents
        function trackOffset(offset) {
            if (offset == null) return;
            if (offset < globalMinOffset) globalMinOffset = offset;
            if (offset > globalMaxOffset) globalMaxOffset = offset;
        }

        const relSchedStart = rel(schedStart);   // always 0 when anchor = schedStart
        const relSchedEnd   = rel(schedEnd);
        const relActualIn   = rel(actualIn);
        const relActualOut  = rel(actualOut);

        trackOffset(relSchedStart);
        trackOffset(relSchedEnd);
        trackOffset(relActualIn);
        trackOffset(relActualOut);

        // ── Schedule bar ──────────────────────────────────────────────────────
        if (relSchedStart != null && relSchedEnd != null) {
            barData.scheduled.push({
                value:  [yi, relSchedStart, relSchedEnd],
                anchor: anchor
            });
        }

        // ── No Timeout bar ────────────────────────────────────────────────────
        // Employee clocked in but never out, and the shift has fully ended.
        if (status === 'incomplete' && relActualIn != null && relSchedEnd != null && now > schedEnd) {
            trackOffset(relSchedEnd);
            barData.notimeout.push({
                value:  [yi, relActualIn, relSchedEnd],
                anchor: anchor
            });
        }

        // ── Work bar ──────────────────────────────────────────────────────────
        // Clamped to schedEnd so OT doesn't bleed into the work bar.
        if (relActualIn != null && relActualOut != null && status !== 'incomplete') {
            const workEndTs  = (schedEnd && actualOut > schedEnd) ? schedEnd : actualOut;
            const relWorkEnd = rel(workEndTs);
            if (relWorkEnd > relActualIn) {
                trackOffset(relWorkEnd);
                barData.work.push({
                    value:  [yi, relActualIn, relWorkEnd],
                    anchor: anchor
                });
            }
        }

        // ── Late bar ──────────────────────────────────────────────────────────
        // Gap between scheduled start (offset 0) and actual clock-in.
        if (row.late_minutes > 0 && relSchedStart != null) {
            const lateEnd = relSchedStart + row.late_minutes * 60000;
            trackOffset(lateEnd);
            barData.late.push({
                value:  [yi, relSchedStart, lateEnd],
                anchor: anchor
            });
        }

        // ── Overtime bar ──────────────────────────────────────────────────────
        // Employee clocked out after scheduled end.
        if (relActualOut != null && relSchedEnd != null && actualOut > schedEnd && status !== 'incomplete') {
            trackOffset(relActualOut);
            barData.ot.push({
                value:  [yi, relSchedEnd, relActualOut],
                anchor: anchor
            });
        }

        // ── Undertime bar ─────────────────────────────────────────────────────
        // Employee left before scheduled end and the shift has fully ended.
        if (relActualOut != null && relSchedEnd != null && actualOut < schedEnd && now > schedEnd && status !== 'incomplete') {
            barData.undertime.push({
                value:  [yi, relActualOut, relSchedEnd],
                anchor: anchor
            });
        }
    });

    // ── x-axis window ────────────────────────────────────────────────────────
    // Add 30-minute padding on each side. If no data at all, default to a
    // 12-hour window (0 to 12 h in ms).
    const PADDING = 30 * 60000;
    const xMin = isFinite(globalMinOffset) ? globalMinOffset - PADDING : 0;
    const xMax = isFinite(globalMaxOffset) ? globalMaxOffset + PADDING : 12 * 3600000;

    // ── x-axis label formatter ────────────────────────────────────────────────
    // The axis now carries relative offsets (ms from shift start), so we
    // display them as "+ H h M m" durations rather than clock times.
    // This makes it immediately obvious that the axis is shift-relative.
    function fmtAxisOffset(ms) {
        const sign    = ms < 0 ? '-' : '+';
        const abs     = Math.abs(ms);
        const h       = Math.floor(abs / 3600000);
        const m       = Math.floor((abs % 3600000) / 60000);
        if (h === 0 && m === 0) return 'Start';
        return m === 0 ? `${sign}${h}h` : `${sign}${h}h${m}m`;
    }

    return {
        backgroundColor: 'transparent',
        tooltip: {
            trigger: 'item',
            formatter(params) {
                const { seriesName, data } = params;
                const anchor = data.anchor ?? 0;
                const start  = fmtOffset(data.value[1], anchor);
                const end    = fmtOffset(data.value[2], anchor);
                const dur    = Math.round((data.value[2] - data.value[1]) / 60000);
                return `<b>${seriesName}</b><br>${start} – ${end}<br>${dur} min`;
            }
        },
        legend: {
            top: 8, right: 16,
            data: ['Schedule', 'Work', 'Late', 'Overtime', 'Undertime', 'No Timeout'],
            textStyle: { color: '#ccc' }
        },
        grid: { left: 110, right: 24, top: 48, bottom: 40 },
        xAxis: {
            type:  'value',   // ← value axis, not time axis, carries ms offsets
            min:   xMin,
            max:   xMax,
            axisLabel: {
                formatter: val => fmtAxisOffset(val),
                color: '#aaa'
            },
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