<?php
// Expects $recordsMonth (YYYY-MM) to be set by the including page.
// Optionally set $recordsApiPath to override the default fetch URL.
$recordsApiPath ??= '../get_records.php';
$recordsMonth   ??= date('Y-m');
?>



<!-- MAIN CARD -->
<div class="records-widget">

    <!-- HEADER -->
    <div class="records-header">
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle" id="month-picker-btn" type="button">
                    <i class="bi bi-calendar3"></i>
                    <span id="dateRangeLabel">Loading…</span>
                </button>
            </div>
        </div>
    </div>

    <!-- BODY -->
    <div class="card-body d-flex flex-column records-card-body">
        <div class="gantt-container" id="gantt-container">
            <!-- filled by JS -->
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

<input type="hidden" id="current-month" 
       value="<?= htmlspecialchars($recordsMonth) ?>"
       data-employee-id="<?= isset($recordsEmployeeId) ? (int)$recordsEmployeeId : '' ?>">

<script>
const ganttContainer = document.getElementById('gantt-container');
const monthHidden    = document.getElementById('current-month');

/* =========================
   RENDER ROWS
========================= */
function renderRecordRows(data) {
    const { meta, records, schedules, leaveMap, obMap } = data;

    const statPending = document.getElementById('stat-pending');
    const statPresent = document.getElementById('stat-present');
    const statAbsent  = document.getElementById('stat-absent');

    if (statPending) statPending.textContent = meta.pendingCount;
    if (statPresent) statPresent.textContent = meta.presentCount;
    if (statAbsent)  statAbsent.textContent  = meta.absentCount;

    document.getElementById('dateRangeLabel').textContent = meta.monthLabel;

    let html    = '';
    let hasRows = false;

    for (const row of records) {
        const sched = schedules[row.work_date] ?? null;
        let g = computeGanttRow(row, sched);
        if (g === null) continue;
        hasRows = true;

        const leaveStatus = leaveMap[row.work_date] ?? null;
        const obStatus    = obMap[row.work_date]    ?? null;
        if (leaveStatus === 'approved') {
            g = { ...g, type: 'absent_or_future', barClass: 'ganttBarLeave',
                  labelClass: 'ganttAbsentLabel', labelText: 'On Leave',
                  barLeft: 0, barWidth: 100, midLeft: 50 };
        } else if (obStatus === 'approved') {
            g = { ...g, type: 'absent_or_future', barClass: 'ganttBarOB',
                  labelClass: 'ganttAbsentLabel', labelText: 'On OB',
                  barLeft: 0, barWidth: 100, midLeft: 50 };
        }

        if (g.type === 'absent_or_future') {
            html += `
            <div class="ganttRow">
                <div class="ganttLabel">
                    <div>${g.dayLabel}</div>
                    <div class="ganttSubLabel">${g.dateNum}</div>
                </div>
                <div class="ganttBarContainer"
                    data-range-start="${g.rangeStart}"
                    data-range-end="${g.rangeEnd}">
                    ${ganttCursorHtml()}
                    ${ganttScaleHtml(g.rangeStart, g.rangeEnd)}
                    <div class="ganttBar ${g.barClass}"
                        style="left:${g.barLeft}%; width:${g.barWidth}%;">
                        <span class="${g.labelClass}" style="left:${g.midLeft}%;">
                            ${g.labelText}
                        </span>
                    </div>
                </div>
            </div>`;
        } else {
            let scheduledBar = g.schedIn !== null
                ? `<div class="ganttBar ganttBarScheduled" style="left: ${g.schedLeft}%; width: ${g.schedWidth}%;"></div>`
                : '';

            let earlyBar = (g.isEarly && g.schedIn)
                ? `<div class="ganttBar ganttBarEarly" style="left: ${g.earlyLeft}%; width: ${g.earlyWidth}%;"><span class="ganttBarLabel">Early</span></div>`
                : '';

            let tardyBar = g.isTardy
                ? `<div class="ganttBar ganttBarTardy" style="left: ${g.tardyLeft}%; width: ${g.tardyWidth}%;"><span class="ganttBarLabel">Late</span></div>`
                : '';

            let mainBar = '';
            if (g.hasClockedIn) {
                const cls = g.noTimeOut ? 'ganttBarNoTimeOut' : 'ganttBarOnTime';
                const lbl = g.noTimeOut ? 'No Time Out' : 'On Time';

                if (g.onTimeSplit) {
                    mainBar = `
                    <div class="ganttBar ${cls}" style="left: ${g.actualLeft}%; width: ${g.onTimeLeftWidth}%;"><span class="ganttBarLabel">${lbl}</span></div>
                    <div class="ganttBar ganttBarBreak" style="left: ${g.breakLeft}%; width: ${g.breakWidth}%;"><span class="ganttBarLabel">Break</span></div>
                    <div class="ganttBar ${cls}" style="left: ${g.onTimeRightLeft}%; width: ${g.onTimeRightWidth}%;">
                        ${g.onTimeRightWidth > 5 ? `<span class="ganttBarLabel">${lbl}</span>` : ''}
                    </div>`;
                } else {
                    mainBar = `<div class="ganttBar ${cls}" style="left: ${g.actualLeft}%; width: ${g.onTimeWidth}%;"><span class="ganttBarLabel">${lbl}</span></div>`;
                }

                if (g.isUndertime && g.schedOut) {
                    mainBar += `<div class="ganttBar ganttBarUndertime" style="left: ${g.undertimeLeft}%; width: ${g.undertimeWidth}%;"><span class="ganttBarLabel">Undertime</span></div>`;
                }
            }

            let overtimeBar = (g.overtimeMinutes > 0 && g.schedOut)
                ? `<div class="ganttBar ${g.otColorClass}" style="left: ${g.overtimeLeft}%; width: ${g.overtimeWidth}%;"><span class="ganttBarLabel">Overtime</span></div>`
                : '';

            let markers = '';
            if (g.actualInPos  !== null) markers += `<div class="ganttMarker ganttMarkerActualStart" style="left: ${g.actualInPos}%"></div>`;
            if (g.actualOutPos !== null) markers += `<div class="ganttMarker ganttMarkerActualEnd"   style="left: ${g.actualOutPos}%"></div>`;

            html += `
            <div class="ganttRow">
                <div class="ganttLabel">
                    <div>${g.dayLabel}</div>
                    <div class="ganttSubLabel">${g.dateNum}</div>
                </div>
                <div class="ganttBarContainer"
                    data-is-today="${g.isToday ? '1' : '0'}"
                    data-range-start="${g.rangeStart}"
                    data-range-end="${g.rangeEnd}"
                    data-sched-in="${g.schedInLabel}"
                    data-sched-out="${g.schedOutLabel}"
                    data-actual-in="${g.actualInLabel}"
                    data-actual-out="${g.actualOutLabel}"
                    data-early="${g.earlyLabel}"
                    data-late="${g.lateLabel}"
                    data-overtime="${g.overtimeLabel}"
                    data-overtime-status="${g.overtimeStatusLabel}"
                    data-undertime="${g.undertimeLabel}"
                    data-overbreak="${g.overbreakLabel}">
                    ${ganttCursorHtml()}
                    ${ganttScaleHtml(g.rangeStart, g.rangeEnd)}
                    ${scheduledBar}${earlyBar}${tardyBar}${mainBar}${overtimeBar}${markers}
                </div>
            </div>`;
        }
    }

    if (!hasRows) {
        ganttContainer.innerHTML = `
            <div class="gantt-empty">
                <i class="bi bi-calendar2-x-fill"></i>
                <div class="text-meta">No records found for this period.</div>
            </div>`;
        return;
    }

    ganttContainer.innerHTML = html;
    initGanttCursors();
}

/* =========================
   FETCH
========================= */
function fetchRecords(month) {
    monthHidden.value = month;
    const empParam = monthHidden.dataset.employeeId
        ? `&employee_id=${monthHidden.dataset.employeeId}`
        : '';
    fetch(`<?= $recordsApiPath ?>?month=${encodeURIComponent(month)}${empParam}`)
        .then(res => res.json())
        .then(data => renderRecordRows(data));
}

/* =========================
   FLATPICKR
========================= */
flatpickr('#month-picker-btn', {
    plugins: [
        new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'F Y' })
    ],
    defaultDate: monthHidden.value,
    onChange(selectedDates, dateStr) {
        fetchRecords(dateStr);
    }
});

/* =========================
   INIT
========================= */
document.addEventListener('DOMContentLoaded', () => fetchRecords(monthHidden.value));
document.addEventListener('attendance_tapped', () => fetchRecords(monthHidden.value));
</script>
