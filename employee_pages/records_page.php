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
$defaultMonth      = sprintf('%04d-%02d', $yr, $mn);
$defaultMonthLabel = date('F Y', strtotime($defaultMonth . '-01'));
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

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">
    <link rel="stylesheet" href="employee_records.css">
</head>
<body>
    <?php include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">
        <?php include '../topbar_revised.php'; ?>

        <!-- SUMMARY CARDS -->
        <div class="container-fluid flex-shrink-0 px-3 pt-2">
            <div class="row g-3">

                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card card-warning p-3 h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-0">
                            <div class="icon-box icon-box-warning">
                                <i class="bi bi-hourglass-split fs-4"></i>
                            </div>
                            <div class="d-flex flex-column ms-auto text-end">
                                <div class="stats-number" id="count-pending">—</div>
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
                                <div class="stats-number" id="count-present">—</div>
                                <div class="text-meta">Present</div>
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
                                <div class="stats-number" id="count-absent">—</div>
                                <div class="text-meta">Absent</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- MAIN CARD -->
        <div class="card card-neutral records-card">

            <div class="record-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <button class="btn btn-sm dropdown-toggle" id="month-picker-btn" type="button">
                            <i class="bi bi-calendar3"></i>
                            <span id="dateRangeLabel"><?= $defaultMonthLabel ?></span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body d-flex flex-column records-card-body">
                <div class="gantt-container">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>

        <!-- Hover tooltip -->
        <div id="gantt_tooltip">
            <div class="ganttToolTipRow">
                <span class="ganttToolTipLabel">Scheduled</span>
                <span class="ganttToolTipValue" id="gt-sched"></span>
            </div>
            <div class="ganttToolTipRow">
                <span class="ganttToolTipLabel">Time In</span>
                <span class="ganttToolTipValue" id="gt-actual-in"></span>
            </div>
            <div class="ganttToolTipRow">
                <span class="ganttToolTipLabel">Time Out</span>
                <span class="ganttToolTipValue" id="gt-actual-out"></span>
            </div>
            <div class="ganttToolTipRow ganttToolTipEarly" id="gt-early-row">
                <span class="ganttToolTipLabel">Early</span>
                <span class="ganttToolTipValue" id="gt-early"></span>
            </div>
            <div class="ganttToolTipRow ganttToolTipLate" id="gt-late-row">
                <span class="ganttToolTipLabel">Late</span>
                <span class="ganttToolTipValue" id="gt-late"></span>
            </div>
            <div class="ganttToolTipRow ganttToolTipOverBreak" id="gt-ob-row">
                <span class="ganttToolTipLabel">Overbreak</span>
                <span class="ganttToolTipValue" id="gt-ob"></span>
            </div>
            <div class="ganttToolTipRow ganttToolTipOverTime" id="gt-ot-row">
                <span class="ganttToolTipLabel" id="gt-ot-label">Overtime</span>
                <span class="ganttToolTipValue" id="gt-ot"></span>
            </div>
            <div class="ganttToolTipRow ganttToolTipUnderTime" id="gt-ut-row">
                <span class="ganttToolTipLabel">Undertime</span>
                <span class="ganttToolTipValue" id="gt-ut"></span>
            </div>
        </div>
    </div><!-- #main-wrapper -->

    <input type="hidden" id="currentMonth" value="<?= $defaultMonth ?>">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="../system_functions/gantt.js"></script>
<script>
/* =========================
   HELPERS
========================= */
function esc(v) {
    if (v == null) return '';
    return String(v)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function ganttCursor() {
    return `<div class="ganttCursor">
                <div class="ganttCursorLine"></div>
                <div class="ganttCursorLabel"></div>
            </div>`;
}

function ganttScale(rangeStart, rangeEnd) {
    let html = '<div class="ganttScale">';
    for (let t = rangeStart; t <= rangeEnd; t += 3600) {
        const pos   = ((t - rangeStart) / (rangeEnd - rangeStart)) * 100;
        const label = new Date(t * 1000).toLocaleTimeString('en-US', {
            hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila'
        });
        html += `<div class="ganttScaleItem" style="left: ${pos}%">${label}</div>`;
    }
    html += '</div>';
    return html;
}

/* =========================
   ROW RENDERERS
========================= */
function renderAbsentRow(row) {
    return `
    <div class="ganttRow">
        <div class="ganttLabel">
            <div>${esc(row.dayLabel)}</div>
            <div class="ganttSubLabel">${esc(row.dateNum)}</div>
        </div>
        <div class="ganttBarContainer"
            data-range-start="${row.rangeStart}"
            data-range-end="${row.rangeEnd}">
            ${ganttCursor()}
            ${ganttScale(row.rangeStart, row.rangeEnd)}
            <div class="ganttBar ${esc(row.barClass)}"
                style="left:${row.barLeft}%; width:${row.barWidth}%;">
                <span class="${esc(row.labelClass)}" style="left:${row.midLeft}%;">
                    ${esc(row.labelText)}
                </span>
            </div>
        </div>
    </div>`;
}

function renderPresentRow(row) {
    const barClass = row.noTimeOut ? 'ganttBarNoTimeOut' : 'ganttBarOnTime';
    const barLabel = row.noTimeOut ? 'No Time Out' : 'On Time';

    let schedBar = row.schedIn !== null
        ? `<div class="ganttBar ganttBarScheduled" style="left: ${row.schedLeft}%; width: ${row.schedWidth}%;"></div>`
        : '';

    let earlyBar = (row.isEarly && row.schedIn !== null)
        ? `<div class="ganttBar ganttBarEarly" style="left: ${row.earlyLeft}%; width: ${row.earlyWidth}%;"><span class="ganttBarLabel">Early</span></div>`
        : '';

    let tardyBar = row.isTardy
        ? `<div class="ganttBar ganttBarTardy" style="left: ${row.tardyLeft}%; width: ${row.tardyWidth}%;"><span class="ganttBarLabel">Late</span></div>`
        : '';

    let mainBar = '';
    if (row.hasClockedIn) {
        if (row.onTimeSplit) {
            mainBar = `
            <div class="ganttBar ${barClass}" style="left: ${row.actualLeft}%; width: ${row.onTimeLeftWidth}%;">
                <span class="ganttBarLabel">${barLabel}</span>
            </div>
            <div class="ganttBar ganttBarBreak" style="left: ${row.breakLeft}%; width: ${row.breakWidth}%;">
                <span class="ganttBarLabel">Break</span>
            </div>
            <div class="ganttBar ${barClass}" style="left: ${row.onTimeRightLeft}%; width: ${row.onTimeRightWidth}%;">
                ${row.onTimeRightWidth > 5 ? `<span class="ganttBarLabel">${barLabel}</span>` : ''}
            </div>`;
        } else {
            mainBar = `
            <div class="ganttBar ${barClass}" style="left: ${row.actualLeft}%; width: ${row.onTimeWidth}%;">
                <span class="ganttBarLabel">${barLabel}</span>
            </div>`;
        }

        if (row.isUndertime && row.schedOut !== null) {
            mainBar += `
            <div class="ganttBar ganttBarUndertime" style="left: ${row.undertimeLeft}%; width: ${row.undertimeWidth}%;">
                <span class="ganttBarLabel">Undertime</span>
            </div>`;
        }
    }

    let otBar = (row.overtimeMinutes > 0 && row.schedOut !== null)
        ? `<div class="ganttBar ${esc(row.otColorClass)}" style="left: ${row.overtimeLeft}%; width: ${row.overtimeWidth}%;"><span class="ganttBarLabel">Overtime</span></div>`
        : '';

    let markers = '';
    if (row.actualInPos  !== null) markers += `<div class="ganttMarker ganttMarkerActualStart" style="left: ${row.actualInPos}%"></div>`;
    if (row.actualOutPos !== null) markers += `<div class="ganttMarker ganttMarkerActualEnd"   style="left: ${row.actualOutPos}%"></div>`;

    return `
    <div class="ganttRow">
        <div class="ganttLabel">
            <div>${esc(row.dayLabel)}</div>
            <div class="ganttSubLabel">${esc(row.dateNum)}</div>
        </div>
        <div class="ganttBarContainer"
            data-is-today="${row.isToday ? '1' : '0'}"
            data-range-start="${row.rangeStart}"
            data-range-end="${row.rangeEnd}"
            data-sched-in="${esc(row.schedInLabel)}"
            data-sched-out="${esc(row.schedOutLabel)}"
            data-actual-in="${esc(row.actualInLabel)}"
            data-actual-out="${esc(row.actualOutLabel)}"
            data-early="${esc(row.earlyLabel)}"
            data-late="${esc(row.lateLabel)}"
            data-overtime="${esc(row.overtimeLabel)}"
            data-overtime-status="${esc(row.overtimeStatusLabel)}"
            data-undertime="${esc(row.undertimeLabel)}"
            data-overbreak="${esc(row.overbreakLabel)}">
            ${ganttCursor()}
            ${ganttScale(row.rangeStart, row.rangeEnd)}
            ${schedBar}
            ${earlyBar}
            ${tardyBar}
            ${mainBar}
            ${otBar}
            ${markers}
        </div>
    </div>`;
}

/* =========================
   RENDER
========================= */
function renderRows({ meta, rows }) {
    document.getElementById('count-pending').textContent = meta.pendingCount;
    document.getElementById('count-present').textContent = meta.presentCount;
    document.getElementById('count-absent').textContent  = meta.absentCount;
    document.getElementById('dateRangeLabel').textContent = meta.monthLabel;

    const container = document.querySelector('.gantt-container');

    if (!rows.length) {
        container.innerHTML = `
            <div class="gantt-empty">
                <i class="bi bi-calendar2-x-fill"></i>
                <div class="text-meta">No records found for this period.</div>
            </div>`;
        return;
    }

    container.innerHTML = rows.map(row =>
        row.type === 'absent_or_future' ? renderAbsentRow(row) : renderPresentRow(row)
    ).join('');

    initGanttCursors();
}

/* =========================
   FETCH
========================= */
function fetchRecords() {
    const month = document.getElementById('currentMonth').value;
    fetch(`../get_records.php?month=${encodeURIComponent(month)}`)
        .then(res => res.json())
        .then(data => renderRows(data));
}

/* =========================
   MONTH PICKER
========================= */
flatpickr('#month-picker-btn', {
    plugins: [
        new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'F Y' })
    ],
    defaultDate: document.getElementById('currentMonth').value,
    onChange(selectedDates, dateStr) {
        document.getElementById('currentMonth').value = dateStr;
        fetchRecords();
    }
});

/* =========================
   INIT
========================= */
document.addEventListener('DOMContentLoaded', () => {
    fetchRecords();
});

document.addEventListener('attendance_tapped', () => fetchRecords());
</script>
</body>
</html>
