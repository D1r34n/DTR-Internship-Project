
// ===== GANTT JS =====

/* =========================
   RENDER HELPERS
========================= */
function fmtTime(ts) {
    return new Date(ts * 1000).toLocaleTimeString('en-US', {
        hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila'
    });
}

function ganttCursorHtml() {
    return `<div class="ganttCursor">
                <div class="ganttCursorLine"></div>
                <div class="ganttCursorLabel"></div>
            </div>`;
}

function ganttScaleHtml(rangeStart, rangeEnd) {
    const rangeSecs = rangeEnd - rangeStart;
    let step;
    if      (rangeSecs <= 4  * 3600) step = 3600;
    else if (rangeSecs <= 8  * 3600) step = 2 * 3600;
    else if (rangeSecs <= 16 * 3600) step = 3 * 3600;
    else                             step = 4 * 3600;

    const firstTick = Math.ceil(rangeStart / step) * step;
    let html = '<div class="ganttScale">';
    for (let t = firstTick; t <= rangeEnd; t += step) {
        const pos = ((t - rangeStart) / (rangeEnd - rangeStart)) * 100;
        html += `<div class="ganttScaleItem" style="left: ${pos}%">${fmtTime(t)}</div>`;
    }
    html += '</div>';
    return html;
}

/* =========================
   GANTT COMPUTE
   JS port of PHP computeGanttRow() in system_library.php.
   Takes a raw attendance row + schedule row (both as plain objects)
   and returns the same pre-computed display object the PHP version
   returns, or null if the row should be skipped.
========================= */
const _MANILA = 'Asia/Manila';

function _toTs(dtStr) {
    if (!dtStr || dtStr === '0000-00-00 00:00:00') return null;
    return new Date(dtStr.replace(' ', 'T') + '+08:00').getTime() / 1000;
}

function _nowSec() {
    return Math.floor(Date.now() / 1000);
}

function _todayManila() {
    return new Date().toLocaleDateString('en-CA', { timeZone: _MANILA });
}

function _nextDayOf(dateKey) {
    return new Date(
        new Date(dateKey + 'T00:00:00+08:00').getTime() + 86400000
    ).toLocaleDateString('en-CA', { timeZone: _MANILA });
}

function _tsDate(ts) {
    return new Date(ts * 1000).toLocaleDateString('en-CA', { timeZone: _MANILA });
}

function _fmtDayName(dateKey) {
    return new Date(dateKey + 'T00:00:00+08:00')
        .toLocaleDateString('en-US', { weekday: 'long', timeZone: _MANILA });
}

function _fmtDatePadded(dateKey) {
    return new Date(dateKey + 'T00:00:00+08:00')
        .toLocaleDateString('en-US', { month: 'short', day: '2-digit', timeZone: _MANILA });
}

function _fmtDateUnpadded(dateKey) {
    return new Date(dateKey + 'T00:00:00+08:00')
        .toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: _MANILA });
}

function computeGanttRow(row, sched) {
    const dateKey  = row.work_date;
    const today    = _todayManila();
    const nextDay  = _nextDayOf(dateKey);
    const isToday  = dateKey === today;
    const isFuture = dateKey > today;

    const isAbsent     = row.status === 'absent';
    const hasClockedIn = !!row.actual_time_in;

    let isFutureOrAbsent = false;
    let isFuturePending  = false;
    if (isFuture && !hasClockedIn) {
        isFutureOrAbsent = true;
        isFuturePending  = true;
    } else if (isAbsent) {
        isFutureOrAbsent = true;
    }

    const schedStartDt = (sched && sched.scheduled_start) || row.scheduled_start || null;
    const schedEndDt   = (sched && sched.scheduled_end)   || row.scheduled_end   || null;
    const schedStartTs = _toTs(schedStartDt);
    const schedEndTs   = _toTs(schedEndDt);

    const crossesMidnight = !!(schedEndTs && schedStartTs && (
        schedEndTs <= schedStartTs ||
        _tsDate(schedEndTs) !== _tsDate(schedStartTs)
    ));

    let dayLabel;
    if (isToday) {
        dayLabel = crossesMidnight ? 'Today – ' + _fmtDayName(nextDay) : 'Today';
    } else {
        dayLabel = crossesMidnight
            ? _fmtDayName(dateKey) + ' – ' + _fmtDayName(nextDay)
            : _fmtDayName(dateKey);
    }

    const dateNum = crossesMidnight
        ? _fmtDateUnpadded(dateKey) + ' – ' + _fmtDateUnpadded(nextDay)
        : _fmtDatePadded(dateKey);

    const lateMinutes      = row.late_minutes      | 0;
    const undertimeMinutes = row.undertime_minutes  | 0;
    const breakMinutes     = row.break_minutes      | 0;
    const overtimeMinutes  = row.overtime_minutes   | 0;
    const overtimeStatus   = row.overtime_status;

    let schedIn  = null;
    let schedOut = null;
    if (schedStartTs) {
        schedIn  = schedStartTs;
        schedOut = schedEndTs;
        if (schedOut && schedOut <= schedIn) schedOut += 86400;
    }

    if (isFutureOrAbsent) {
        if (!schedIn || !schedOut) return null;

        const rangeStart = schedIn  - 7200;
        const rangeEnd   = schedOut + 7200;
        const range      = Math.max(1, rangeEnd - rangeStart);
        const barLeft    = ((schedIn  - rangeStart) / range) * 100;
        const barWidth   = ((schedOut - schedIn)    / range) * 100;

        const isRestDay = !!(sched && sched.is_rest_day);
        let barClass, labelClass, labelText;
        if (isRestDay) {
            barClass = 'ganttBarRestDay'; labelClass = 'ganttRestDayLabel'; labelText = 'Rest Day';
        } else if (isFuturePending) {
            barClass = 'ganttBarPending'; labelClass = 'ganttPendingLabel'; labelText = 'Upcoming';
        } else {
            barClass = 'ganttBarAbsent'; labelClass = 'ganttAbsentLabel'; labelText = 'Absent';
        }

        return {
            type: 'absent_or_future',
            dayLabel, dateNum, rangeStart, rangeEnd,
            barLeft, barWidth, midLeft: barLeft + barWidth / 2,
            barClass, labelClass, labelText,
        };
    }

    // Present row
    const actualInTs  = hasClockedIn ? _toTs(row.actual_time_in) : (schedIn ?? _toTs(schedStartDt));
    let   actualOutTs = row.actual_time_out ? _toTs(row.actual_time_out) : null;
    if (actualOutTs && actualOutTs <= actualInTs) actualOutTs += 86400;

    const shiftEnd  = schedOut ?? (actualOutTs ?? _nowSec());
    const isPast    = _nowSec() > shiftEnd + 12 * 3600;
    const noTimeOut = isPast && ((actualInTs && actualOutTs === null) || row.missed_time_out == 1);

    const rangeMin = schedIn ? Math.min(schedIn, actualInTs) : actualInTs;
    const rangeMax = noTimeOut
        ? (schedOut ?? actualInTs)
        : (schedOut ? Math.max(schedOut, actualOutTs ?? _nowSec()) : (actualOutTs ?? _nowSec()));

    const rangeStart = rangeMin - 7200;
    const rangeEnd   = rangeMax + 7200;
    const range      = Math.max(1, rangeEnd - rangeStart);

    const rawActualOut = row.actual_time_out ? _toTs(row.actual_time_out) : null;
    if (actualOutTs === null) {
        actualOutTs = !isPast ? _nowSec() : _toTs(dateKey + ' 23:59:59');
    }

    const toLeft = ts => ((ts - rangeStart) / range) * 100;

    const breakIn  = row.first_break_in ? _toTs(row.first_break_in) : null;
    let   breakOut = row.last_break_out ? _toTs(row.last_break_out) : null;
    if (breakIn && !breakOut && rawActualOut) breakOut = rawActualOut;

    const isTardy     = lateMinutes > 0;
    const isEarly     = schedIn !== null && actualInTs < schedIn;
    const isOverBreak = breakMinutes > 60;
    const isUndertime = undertimeMinutes > 0;

    const onTimeStart = (isEarly && schedIn) ? schedIn : actualInTs;
    const onTimeEnd   = noTimeOut
        ? (schedOut ?? actualOutTs)
        : (overtimeMinutes > 0 && schedOut ? schedOut : actualOutTs);
    const onTimeWidth = ((onTimeEnd - onTimeStart) / range) * 100;

    const otColorClass = overtimeStatus === 'approved' ? 'ganttBarOvertimeApproved'
                       : overtimeStatus === 'rejected' ? 'ganttBarOvertimeRejected'
                       : 'ganttBarOvertimePending';

    return {
        type: 'present',
        dayLabel, dateNum, rangeStart, rangeEnd,
        isToday, hasClockedIn,

        schedIn, schedOut,
        schedLeft:  schedIn  !== null ? toLeft(schedIn)  : null,
        schedWidth: (schedIn && schedOut) ? ((schedOut - schedIn) / range) * 100 : null,

        actualLeft:   toLeft(onTimeStart),
        actualInPos:  hasClockedIn ? toLeft(actualInTs) : null,
        actualOutPos: (hasClockedIn && row.actual_time_out) ? toLeft(actualOutTs) : null,
        noTimeOut, onTimeWidth,

        onTimeSplit:      !!(breakIn && breakOut),
        onTimeLeftWidth:  (breakIn && breakOut) ? ((breakIn  - onTimeStart) / range) * 100 : 0,
        onTimeRightLeft:  (breakIn && breakOut) ? toLeft(breakOut) : null,
        onTimeRightWidth: (breakIn && breakOut) ? ((onTimeEnd - breakOut)  / range) * 100 : 0,

        isEarly,
        earlyLeft:  (isEarly && schedIn) ? toLeft(actualInTs) : null,
        earlyWidth: (isEarly && schedIn) ? ((schedIn - actualInTs) / range) * 100 : 0,

        isTardy,
        tardyLeft:  schedIn !== null ? toLeft(schedIn) : null,
        tardyWidth: (isTardy && schedIn) ? ((actualInTs - schedIn) / range) * 100 : 0,
        lateMinutes,

        breakMinutes,
        breakLeft:  breakIn ? toLeft(breakIn) : null,
        breakWidth: (breakIn && breakOut) ? ((breakOut - breakIn) / range) * 100 : 0,

        isUndertime, undertimeMinutes,
        undertimeLeft:  toLeft(actualOutTs),
        undertimeWidth: (isUndertime && schedOut) ? ((schedOut - actualOutTs) / range) * 100 : 0,

        overtimeMinutes, overtimeStatus, otColorClass,
        overtimeLeft:  schedOut !== null ? toLeft(schedOut) : null,
        overtimeWidth: (overtimeMinutes > 0 && schedOut) ? ((actualOutTs - schedOut) / range) * 100 : 0,

        schedInLabel:        schedIn  ? fmtTime(schedIn)  : '--',
        schedOutLabel:       schedOut ? fmtTime(schedOut) : '--',
        earlyLabel:          (isEarly && schedIn) ? Math.floor((schedIn - actualInTs) / 60) + ' min' : '',
        actualInLabel:       hasClockedIn ? fmtTime(actualInTs) : '--',
        actualOutLabel:      hasClockedIn
            ? (row.actual_time_out ? fmtTime(actualOutTs) : (noTimeOut ? 'No Time Out' : 'In Progress'))
            : '--',
        lateLabel:           lateMinutes     > 0 ? lateMinutes     + ' min' : '',
        overtimeLabel:       overtimeMinutes > 0 ? overtimeMinutes + ' min' : '',
        overtimeStatusLabel: overtimeMinutes > 0 ? (overtimeStatus ?? '') : '',
        undertimeLabel:      isUndertime ? undertimeMinutes + ' min' : '',
        isOverBreak,
        overbreakLabel:      isOverBreak ? breakMinutes + ' min' : '',
    };
}

function initGanttCursors() {
    const tooltip = document.getElementById('gantt_tooltip');
    // backdrop-filter on ancestor cards creates a new containing block for
    // position:fixed, breaking viewport-relative coordinates. Reparent to body.
    if (tooltip && tooltip.parentElement !== document.body) {
        document.body.appendChild(tooltip);
    }
    const gtSched     = document.getElementById('gt-sched');
    const gtActualIn  = document.getElementById('gt-actual-in');
    const gtActualOut = document.getElementById('gt-actual-out');
    const gtEarlyRow  = document.getElementById('gt-early-row');
    const gtEarly     = document.getElementById('gt-early');
    const gtLateRow   = document.getElementById('gt-late-row');
    const gtLate      = document.getElementById('gt-late');
    const gtOtRow     = document.getElementById('gt-ot-row');
    const gtOtLabel   = document.getElementById('gt-ot-label');
    const gtOt        = document.getElementById('gt-ot');
    const gtUtRow     = document.getElementById('gt-ut-row');
    const gtUt        = document.getElementById('gt-ut');
    const gtOb        = document.getElementById('gt-ob');
    const gtObRow     = document.getElementById('gt-ob-row');
    document.querySelectorAll('.ganttBarContainer').forEach(container => {
        const line  = container.querySelector('.ganttCursorLine');
        const label = container.querySelector('.ganttCursorLabel');
        if (!line || !label) return;

        const rangeStart = parseInt(container.dataset.rangeStart);
        const rangeEnd   = parseInt(container.dataset.rangeEnd);
        const range      = rangeEnd - rangeStart;

        const hasData = !!container.dataset.actualIn;

        container.addEventListener('mousemove', (e) => {
            const rect    = container.getBoundingClientRect();
            const x       = e.clientX - rect.left;
            const percent = Math.max(0, Math.min(1, x / rect.width));
            const time    = Math.floor(rangeStart + (percent * range));

            if (line)  line.style.left  = (percent * 100) + '%';
            if (label) label.style.left = (percent * 100) + '%';

            if (label) {
                label.textContent = new Date(time * 1000).toLocaleTimeString('en-US', {
                    hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila'
                });
            }

            if (hasData && tooltip) {
                gtSched.textContent     = container.dataset.schedIn + ' – ' + container.dataset.schedOut;
                gtActualIn.textContent  = container.dataset.actualIn;
                gtActualOut.textContent = container.dataset.actualOut;

                // Show if early
                if (container.dataset.early) {
                    gtEarly.textContent      = container.dataset.early;
                    gtEarlyRow.style.display = 'flex';
                } else {
                    gtEarlyRow.style.display = 'none';
                }
                
                // Show if late
                if (container.dataset.late) {
                    gtLate.textContent          = container.dataset.late;
                    gtLateRow.style.display     = 'flex';
                } else {
                    gtLateRow.style.display = 'none';
                }

                // Show overtime if it is approved or rejected
                if (container.dataset.overtime) {
                    gtOt.textContent  = container.dataset.overtime;
                    const status      = container.dataset.overtimeStatus;
                    const statusLabel = status === 'approved' ? 'Approved'
                                      : status === 'rejected' ? 'Rejected'
                                      : 'Pending';
                    if (gtOtLabel) gtOtLabel.textContent = `Overtime (${statusLabel})`;
                    gtOtRow.className = 'ganttToolTipRow ganttToolTipOverTime'
                                    + (status === 'approved' ? ' approved' : status === 'rejected' ? ' rejected' : '');
                    gtOtRow.style.display = 'flex';
                } else {
                    gtOtRow.style.display = 'none';
                }
                
                // Show undertime as soon as shift has ended
                if (container.dataset.undertime) {
                    gtUt.textContent = container.dataset.undertime;
                    gtUtRow.style.display = 'flex';
                } else {
                    gtUtRow.style.display = 'none';
                }
                
                // Show overbreak only when the employee exceeded the breaktime
                if (container.dataset.overbreak) {
                    gtOb.textContent = container.dataset.overbreak;
                    gtObRow.style.display = 'flex';
                } else {
                    gtObRow.style.display = 'none';
                }
                const tipW  = tooltip.offsetWidth;
                const tipH  = tooltip.offsetHeight;
                const flipX = (e.clientX + tipW / 2) > window.innerWidth;
                const flipY = (e.clientY - tipH - 48) < 0;

                tooltip.style.left = (e.clientX + (flipX ? -tipW / 2 : 0)) + 'px';
                tooltip.style.top  = (e.clientY + (flipY ? 20 : -10)) + 'px';
                tooltip.classList.add('visible');
            }
        });

        container.addEventListener('mouseleave', () => {
            if (tooltip) tooltip.classList.remove('visible');
        });
    });
}

function refreshGantt() {
    console.log('Gantt chart being refreshed');
    const container = document.querySelector('.ganttContainer');
    if (!container) return;

    // Fade out current rows
    container.style.transition = 'opacity 0.2s ease';
    container.style.opacity    = '0';

    // Build URL preserving the current date range
    const params = new URLSearchParams(window.location.search);
    const start  = params.get('start') ?? '';
    const end    = params.get('end')   ?? '';
    const url    = window.location.pathname + (start && end ? `?start=${start}&end=${end}` : '');

    setTimeout(() => {
        fetch(url)
            .then(r => r.text())
            .then(html => {
                const doc      = new DOMParser().parseFromString(html, 'text/html');
                const newGantt = doc.querySelector('.ganttContainer');
                if (!newGantt || !container) return;

                // Swap in the new container
                container.replaceWith(newGantt);

                // Stagger fade-in each row
                newGantt.style.opacity    = '0';
                newGantt.style.transition = 'opacity 0.3s ease';

                newGantt.querySelectorAll('.ganttRow').forEach((row, i) => {
                    row.style.opacity    = '0';
                    row.style.transform  = 'translateY(6px)';
                    row.style.transition = `opacity 0.3s ease ${i * 40}ms, transform 0.3s ease ${i * 40}ms`;

                    requestAnimationFrame(() => requestAnimationFrame(() => {
                        row.style.opacity   = '1';
                        row.style.transform = 'translateY(0)';
                    }));
                });

                newGantt.style.opacity = '1';

                // Re-initialize cursor and tooltip behavior on the new rows
                initGanttCursors();
            })
            .catch(err => console.error('Gantt refresh failed:', err));
    }, 200); // Wait for fade-out to finish before swapping
}