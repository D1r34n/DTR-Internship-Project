<?php
// Expects $recordsMonth (YYYY-MM) from the including page (month-based mode).
// When $cutoffs and $activeCutoffId are provided, renders a cutoff selector instead.
$recordsApiPath ??= '../get_records.php';
$recordsMonth   ??= date('Y-m');
$cutoffs        ??= [];
$activeCutoffId ??= null;

$useCutoffMode = !empty($cutoffs);
$loadCutoff    = null;
?>

<?php if (!defined('TOAST_INCLUDED')): define('TOAST_INCLUDED', true); include __DIR__ . '/../system_functions/show_toast.php'; endif; ?>

<!-- MAIN CARD -->
<div class="card card-neutral records-card">

    <!-- HEADER -->
    <div class="card-header records-header">
        <div class="d-flex align-items-center gap-2">
    <?php if ($useCutoffMode):

        $today = date('Y-m-d');

        // Current cutoff (today falls within its range)
        $currentCutoff = null;
        foreach ($cutoffs as $c) {
            if ($today >= $c['start_date'] && $today <= $c['end_date']) {
                $currentCutoff = $c;
                break;
            }
        }

        // Previous cutoff (most recent that ended before current started)
        $previousCutoff = null;
        foreach ($cutoffs as $c) {
            if ($currentCutoff) {
                if ($c['end_date'] < $currentCutoff['start_date']) {
                    if (!$previousCutoff || $c['end_date'] > $previousCutoff['end_date']) {
                        $previousCutoff = $c;
                    }
                }
            } else {
                if ($c['end_date'] < $today) {
                    if (!$previousCutoff || $c['end_date'] > $previousCutoff['end_date']) {
                        $previousCutoff = $c;
                    }
                }
            }
        }

        // Active cutoff to initially load
        $loadCutoff = $currentCutoff ?? $previousCutoff ?? ($cutoffs[0] ?? null);
        if ($activeCutoffId) {
            foreach ($cutoffs as $c) {
                if ((int)$c['id'] === (int)$activeCutoffId) {
                    $loadCutoff = $c;
                    break;
                }
            }
        }

        function co_label(array $c) {
            return date('M j, Y', strtotime($c['start_date'])) .
                   ' – ' .
                   date('M j, Y', strtotime($c['end_date']));
        }

        // Initial button label and secondary range label
        if ($loadCutoff) {
            if ($currentCutoff && (int)$loadCutoff['id'] === (int)$currentCutoff['id']) {
                $activeBtnLabel   = 'Current Cut-Off';
                $activeRangeLabel = 'Current Cut-Off: ' . co_label($loadCutoff);
            } elseif ($previousCutoff && (int)$loadCutoff['id'] === (int)$previousCutoff['id']) {
                $activeBtnLabel   = 'Previous Cut-Off';
                $activeRangeLabel = 'Previous Cut-Off: ' . co_label($loadCutoff);
            } else {
                $activeBtnLabel   = 'Selected Cut-Off';
                $activeRangeLabel = 'Selected Cut-Off: ' . co_label($loadCutoff);
            }
        } else {
            $activeBtnLabel   = 'Select Period';
            $activeRangeLabel = '';
        }

    ?>
            <div class="dropdown">
                <button class="btn dropdown-toggle" type="button"
                        id="cutoff-dropdown-btn" data-bs-toggle="dropdown"
                        data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bi bi-calendar3 me-1"></i>
                    <span id="cutoff-btn-label"><?= htmlspecialchars($activeBtnLabel) ?></span>
                </button>

                <ul class="dropdown-menu" style="min-width:280px;">

                    <!-- ── Panel 1: main list ─────────────────────── -->
                    <div id="rw-panel-1">

                        <?php if ($currentCutoff): ?>
                        <li>
                            <a class="dropdown-item cutoff-item <?= ($loadCutoff && (int)$loadCutoff['id'] === (int)$currentCutoff['id']) ? 'active' : '' ?>"
                               href="#"
                               data-start="<?= $currentCutoff['start_date'] ?>"
                               data-end="<?= $currentCutoff['end_date'] ?>"
                               data-btn-label="Current Cut-Off"
                               data-range-label="Current Cut-Off: <?= htmlspecialchars(co_label($currentCutoff)) ?>">
                                
                            <div class="vstack">
                               Current Cut-Off
                                <small class="text-tertiary"><?= co_label($currentCutoff) ?></small>
                            </div>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($previousCutoff): ?>
                        <li>
                            <a class="dropdown-item cutoff-item <?= ($loadCutoff && (int)$loadCutoff['id'] === (int)$previousCutoff['id']) ? 'active' : '' ?>"
                               href="#"
                               data-start="<?= $previousCutoff['start_date'] ?>"
                               data-end="<?= $previousCutoff['end_date'] ?>"
                               data-btn-label="Previous Cut-Off"
                               data-range-label="Previous Cut-Off: <?= htmlspecialchars(co_label($previousCutoff)) ?>">
                               <div class="vstack">
                               Previous Cut-Off
                                <small class="text-tertiary"><?= co_label($previousCutoff) ?></small>
                            </div>
                            </a>
                        </li>
                        <?php endif; ?>

                        <li>
                            <a class="dropdown-item d-flex justify-content-between align-items-center"
                               href="#" id="rw-open-period-panel">
                               <div class="hstack">
                                    Select Cut-Off Period
                                    <i class="bi bi-chevron-right small ms-3"></i>
                                </div>
                            </a>
                        </li>
                        
                        
                        <li><hr class="dropdown-divider"></li>

                        <li>
                            <a class="dropdown-item" href="#" id="rw-open-month-picker">
                                <div class="hstack">
                                    <i class="bi bi-calendar3"></i>Select Month
                                </div>
                            </a>
                        </li>

                    </div><!-- #rw-panel-1 -->

                    <!-- ── Panel 2: pick month → cut-off list ─────── -->
                    <div id="rw-panel-2" style="display:none;">

                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-1 text-muted"
                               href="#" id="rw-back-btn">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </li>
                        <li><hr class="dropdown-divider mt-0"></li>

<li>
                            <label for="rw-period-fp" class="form-label text-meta ms-2 mb-2">
                                Selected Month
                            </label>

                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-calendar3"></i>
                                </span>

                                <input type="text"
                                    id="rw-period-fp"
                                    class="form-control"
                                    placeholder="Pick a month…"
                                    readonly>
                            </div>

                            <hr class="dropdown-divider my-3">
                        </li>

                        <div id="rw-period-list">
                            <p class="text-meta small text-center px-3 py-2 mb-0">
                                Pick a month above to see its cut-off periods.
                            </p>
                        </div>

                    </div><!-- #rw-panel-2 -->

                </ul>
            </div>

            <!-- Secondary range label to the right of the dropdown -->
            <span id="cutoff-range-label" class="text-secondary">
                <?= htmlspecialchars($activeRangeLabel) ?>
            </span>

            <!-- Hidden flatpickr anchor for "Select Month" (full month) -->
            <input type="text" id="rw-month-fp-anchor"
                   style="position:absolute;width:0;height:0;opacity:0;pointer-events:none;">

    <?php else: ?>
            <div class="dropdown">
                <button class="btn dropdown-toggle" id="month-picker-btn" type="button">
                    <i class="bi bi-calendar3"></i>
                    <span id="dateRangeLabel">Loading…</span>
                </button>
            </div>
    <?php endif; ?>
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
       data-employee-id="<?= isset($recordsEmployeeId) ? (int)$recordsEmployeeId : '' ?>"
       data-cutoff-mode="<?= $useCutoffMode ? '1' : '0' ?>"
       data-cutoff-start="<?= ($useCutoffMode && $loadCutoff) ? htmlspecialchars($loadCutoff['start_date']) : '' ?>"
       data-cutoff-end="<?= ($useCutoffMode && $loadCutoff)   ? htmlspecialchars($loadCutoff['end_date'])   : '' ?>">

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

    if (monthHidden.dataset.cutoffMode !== '1') {
        const dateRangeLabel = document.getElementById('dateRangeLabel');
        if (dateRangeLabel) dateRangeLabel.textContent = meta.monthLabel;
    }

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
                  labelClass: 'ganttOBLabel', labelText: 'On OB',
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
const _apiBase    = '<?= $recordsApiPath ?>';
const _empParam   = monthHidden.dataset.employeeId
    ? `&employee_id=${monthHidden.dataset.employeeId}`
    : '';
const _cutoffMode = monthHidden.dataset.cutoffMode === '1';

function fetchRecords(monthOrStart, end) {
    let url;
    if (_cutoffMode && end !== undefined) {
        url = `${_apiBase}?start=${encodeURIComponent(monthOrStart)}&end=${encodeURIComponent(end)}${_empParam}`;
    } else {
        monthHidden.value = monthOrStart;
        url = `${_apiBase}?month=${encodeURIComponent(monthOrStart)}${_empParam}`;
    }
    fetch(url)
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then(data => renderRecordRows(data))
        .catch(err => {
            console.error('Failed to load records:', err, 'URL:', url);
            showToast('Failed to load records. Please try again.', 'danger');
        });
}

function fetchActiveCutoff() {
    const start = monthHidden.dataset.cutoffStart;
    const end   = monthHidden.dataset.cutoffEnd;
    if (start && end) fetchRecords(start, end);
}

/* =========================
   CONTROLS
========================= */
<?php if ($useCutoffMode): ?>

const _allCutoffs  = <?= json_encode(array_values($cutoffs)) ?>;
const _dropdownBtn = document.getElementById('cutoff-dropdown-btn');
const _dropdownObj = bootstrap.Dropdown.getOrCreateInstance(_dropdownBtn);

// Collect every overflow-clipping ancestor up to <body> so we can
// temporarily clear them when the menu opens (letting it escape the
// overflow:hidden / backdrop-filter boundary).
const _overflowAncestors = [];
(function () {
    let el = _dropdownBtn.parentElement;
    while (el && el.tagName !== 'BODY') {
        _overflowAncestors.push(el);
        el = el.parentElement;
    }
}());
_dropdownBtn.addEventListener('show.bs.dropdown', function () {
    _overflowAncestors.forEach(function (el) {
        el.dataset.prevOverflow = el.style.overflow;
        el.style.overflow = 'visible';
    });
});
_dropdownBtn.addEventListener('hidden.bs.dropdown', function () {
    _overflowAncestors.forEach(function (el) {
        el.style.overflow = el.dataset.prevOverflow || '';
        delete el.dataset.prevOverflow;
    });
});
const _panel1      = document.getElementById('rw-panel-1');
const _panel2      = document.getElementById('rw-panel-2');
const _periodList  = document.getElementById('rw-period-list');

function _fmtRange(start, end) {
    const s = new Date(start + 'T00:00:00');
    const e = new Date(end   + 'T00:00:00');
    return s.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) +
           ' – ' +
           e.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function _applySelection(start, end, btnLabel, rangeLabel) {
    monthHidden.dataset.cutoffStart = start;
    monthHidden.dataset.cutoffEnd   = end;
    document.getElementById('cutoff-btn-label').textContent   = btnLabel;
    document.getElementById('cutoff-range-label').textContent = rangeLabel;
    document.querySelectorAll('.cutoff-item').forEach(el => el.classList.remove('active'));
    sessionStorage.setItem('recordsCutoffSel', JSON.stringify({ start, end, btnLabel, rangeLabel }));
    fetchRecords(start, end);
    showToast(rangeLabel, 'info');
    _dropdownObj.hide();
}

function _resetPanel2() {
    _periodList.innerHTML =
        '<p class="text-muted small text-center px-3 py-2 mb-0">Pick a month above to see its cut-off periods.</p>';
    if (typeof _periodFp !== 'undefined') _periodFp.clear();
}

function _goToPanel1() {
    _resetPanel2();
    _panel2.style.display = 'none';
    _panel1.style.display = 'block';
}

// ── Panel 2: flatpickr month picker ───────────────────────
let _fpOpen = false;

const _periodFp = flatpickr('#rw-period-fp', {
    plugins: [new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'F Y' })],
    disableMobile: true,
    onOpen()  { _fpOpen = true;  },
    onClose() { _fpOpen = false; },
    onChange(selectedDates) {
        if (!selectedDates.length) return;
        const d      = selectedDates[0];
        const year   = d.getFullYear();
        const month  = d.getMonth();
        const mStart = new Date(year, month,     1);
        const mEnd   = new Date(year, month + 1, 0);

        const matches = _allCutoffs.filter(c => {
            const cs = new Date(c.start_date + 'T00:00:00');
            const ce = new Date(c.end_date   + 'T00:00:00');
            return cs <= mEnd && ce >= mStart;
        });

        if (!matches.length) {
            _periodList.innerHTML =
                '<p class="text-tertiary small text-center px-3 py-2 mb-0">No cut-off periods found for this month.</p>';
            return;
        }

        _periodList.innerHTML = matches.map(c => `
            <a href="#" class="dropdown-item rw-period-item"
               data-start="${c.start_date}"
               data-end="${c.end_date}">
                ${_fmtRange(c.start_date, c.end_date)}
            </a>
        `).join('');

        _periodList.querySelectorAll('.rw-period-item').forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                const start    = this.dataset.start;
                const end      = this.dataset.end;
                const rangeStr = _fmtRange(start, end);
                _applySelection(start, end, 'Selected Cut-Off', 'Selected Cut-Off: ' + rangeStr);
            });
        });
    }
});

// Keep dropdown open while the flatpickr calendar is visible
_dropdownBtn.addEventListener('hide.bs.dropdown', function (e) {
    if (_fpOpen) e.preventDefault();
});

// Reset panel 2 when dropdown closes
_dropdownBtn.closest('.dropdown').addEventListener('hidden.bs.dropdown', _goToPanel1);

// ── Panel navigation ───────────────────────────────────────
document.getElementById('rw-open-period-panel').addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    _panel1.style.display = 'none';
    _panel2.style.display = 'block';
    setTimeout(() => _periodFp.open(), 30);
});

document.getElementById('rw-back-btn').addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    _goToPanel1();
});

// ── Current / Previous dropdown items ─────────────────────
document.querySelectorAll('.cutoff-item').forEach(function (item) {
    item.addEventListener('click', function (e) {
        e.preventDefault();
        this.classList.add('active');
        _applySelection(
            this.dataset.start,
            this.dataset.end,
            this.dataset.btnLabel,
            this.dataset.rangeLabel
        );
    });
});

// ── Select Month (full month) ──────────────────────────────
const _monthFp = flatpickr('#rw-month-fp-anchor', {
    plugins: [new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'F Y' })],
    disableMobile: true,
    onChange(selectedDates) {
        if (!selectedDates.length) return;
        const d     = selectedDates[0];
        const year  = d.getFullYear();
        const month = d.getMonth();
        const start = `${year}-${String(month + 1).padStart(2, '0')}-01`;
        const end   = new Date(year, month + 1, 0).toISOString().slice(0, 10);
        const mLbl  = d.toLocaleString('en-US', { month: 'long', year: 'numeric' });
        document.querySelectorAll('.cutoff-item').forEach(el => el.classList.remove('active'));
        _applySelection(start, end, mLbl, 'Selected Month: ' + _fmtRange(start, end));
    }
});

document.getElementById('rw-open-month-picker').addEventListener('click', function (e) {
    e.preventDefault();
    _dropdownObj.hide();
    setTimeout(() => _monthFp.open(), 50);
});

<?php else: ?>
flatpickr('#month-picker-btn', {
    plugins: [
        new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'F Y' })
    ],
    defaultDate: monthHidden.value,
    onChange(selectedDates, dateStr) {
        fetchRecords(dateStr);
    }
});
<?php endif; ?>

/* =========================
   INIT
========================= */
document.addEventListener('DOMContentLoaded', () => {
    if (_cutoffMode) {
        try {
            const saved = sessionStorage.getItem('recordsCutoffSel');
            if (saved) {
                const sel = JSON.parse(saved);
                monthHidden.dataset.cutoffStart = sel.start;
                monthHidden.dataset.cutoffEnd   = sel.end;
                const btnLbl = document.getElementById('cutoff-btn-label');
                const rngLbl = document.getElementById('cutoff-range-label');
                if (btnLbl) btnLbl.textContent = sel.btnLabel;
                if (rngLbl) rngLbl.textContent = sel.rangeLabel;
                document.querySelectorAll('.cutoff-item').forEach(el => {
                    el.classList.toggle('active',
                        el.dataset.start === sel.start && el.dataset.end === sel.end);
                });
            }
        } catch (e) {}
        fetchActiveCutoff();
    } else {
        fetchRecords(monthHidden.value);
    }
});
document.addEventListener('attendance_tapped', () => {
    if (_cutoffMode) {
        fetchActiveCutoff();
    } else {
        fetchRecords(monthHidden.value);
    }
});
</script>
