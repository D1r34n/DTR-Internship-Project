<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['superadmin', 'admin', 'manager', 'workforce'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

date_default_timezone_set('Asia/Manila');
$currentPage = 'attendance_report';

$cutoffs = $pdo->query("SELECT id, start_date, end_date FROM cutoffs ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
require_once 'cutoff_helpers.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance Report</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">
    <link rel="stylesheet" href="reports_page.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
    <script src="reports_shared.js"></script>
</head>
<body>

<?php include '../sidebar_revised.php'; ?>

<div id="main-wrapper">
    <?php include '../topbar_revised.php'; ?>

    <div class="card card-neutral requests-card">
        <div class="card-header d-flex align-items-center gap-2 flex-wrap">

            <div class="dropdown">
                <button class="btn btn-success dropdown-toggle" type="button"
                        id="ar-cutoff-btn" data-bs-toggle="dropdown"
                        data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bi bi-calendar3 me-1"></i>
                    <span id="ar-btn-label"><?= htmlspecialchars($defaultLabel ?? '') ?></span>
                </button>

                <ul class="dropdown-menu" style="min-width:280px;">
                    <div id="ar-panel-1">
                        <?php include 'cutoff_dropdown_items.php'; ?>

                        <li>
                            <a class="dropdown-item d-flex align-items-center justify-content-between"
                                href="#" id="ar-open-period-panel">
                                Select Cut-Off Period
                                <i class="bi bi-chevron-right small ms-3"></i>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2"
                                href="#" id="ar-open-month-picker">
                                <i class="bi bi-calendar3"></i> Select Full Month
                            </a>
                        </li>
                    </div>

                    <div id="ar-panel-2" style="display:none;">
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-1 text-muted"
                                href="#" id="ar-back-btn">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </li>
                        <li><hr class="dropdown-divider mt-0"></li>
                        <li>
                            <label for="ar-period-fp" class="form-label text-meta ms-2 mb-2">Selected Month</label>
                            <div class="input-group px-2">
                                <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                                <input type="text" id="ar-period-fp" class="form-control"
                                        placeholder="Pick a month…" readonly>
                            </div>
                            <hr class="dropdown-divider my-3">
                        </li>
                        <div id="ar-period-list">
                            <p class="text-meta text-center px-3 py-2 mb-0">
                                Pick a month above to see its cut-off periods.
                            </p>
                        </div>
                    </div>
                </ul>
            </div>

            <span id="ar-range-label" class="text-secondary">
                <?= htmlspecialchars($defaultRange ?? '') ?>
            </span>
        
            <input type="text" id="ar-month-fp-anchor"
                   style="position:absolute;width:0;height:0;opacity:0;pointer-events:none;">

            <div class="ms-auto d-flex gap-2">
                
                <div class="input-group style="max-width:220px;">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="search-input" class="form-control" placeholder="Search employee…" oninput="handleSearchInput()">
                </div>

                <div class="dropdown">
                    <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-funnel"></i>
                        <span id="status-label">All Status</span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item status-opt" href="#" data-value="ALL">All Status</a></li>
                        <li><a class="dropdown-item status-opt" href="#" data-value="present">Present</a></li>
                        <li><a class="dropdown-item status-opt" href="#" data-value="absent">Absent</a></li>
                        <li><a class="dropdown-item status-opt" href="#" data-value="incomplete">Incomplete</a></li>
                    </ul>
                </div>


                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button"
                            id="export-btn" data-bs-toggle="dropdown" aria-expanded="false" disabled>
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="#" onclick="exportAllCSV(); return false;">
                                <i class="bi bi-filetype-csv me-2"></i> Export CSV
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="exportAllPDF(); return false;">
                                <i class="bi bi-filetype-pdf me-2"></i> Export PDF
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card-body d-flex flex-column requests-card-body">
            <div id="ar-loading-state" class="ar-empty" style="display:none;">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="text-meta mt-2">Fetching report logs...</div>
            </div>

            <div id="ar-unloaded-state" class="ar-empty">
                <i class="bi bi-bar-chart-line-fill"></i>
                <div class="text-meta">No report loaded. Select an alternate period view up top.</div>
            </div>

            <div class="tableScroll" id="report-table-container" style="display:none;">
                <table class="table table-hover mb-0" id="reportTable">
                    <thead id="report-thead">
                        <tr>
                            <th class="sortable" data-sort="employee_id">Employee ID <i class="bi bi-filter sortIcon"></i></th>
                            <th class="sortable" data-sort="name">Name <i class="bi bi-filter sortIcon" id="sort-name"></i><span class="col-group-toggle ms-3" id="dept-role-toggle" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Toggle Dept &amp; Role columns"><i class="bi bi-chevron-right"></i></span></th>
                            <th>Department</th>
                            <th>Role</th>
                            <th class="sortable" data-sort="date">Date <i class="bi bi-filter sortIcon" id="sort-date"></i></th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th class="sortable" data-sort="regular">Regular Hours <i class="bi bi-filter sortIcon" id="sort-regular"></i></th>
                            <th class="sortable" data-sort="late">Tardiness <i class="bi bi-filter sortIcon" id="sort-late"></i></th>
                            <th>Leave</th>
                            <th class="sortable" data-sort="undertime">Undertime <i class="bi bi-filter sortIcon" id="sort-undertime"></i></th>
                            <th class="sortable" data-sort="overtime">Overtime <i class="bi bi-filter sortIcon" id="sort-overtime"></i></th>
                            <th class="sortable" data-sort="status">Status <i class="bi bi-filter sortIcon" id="sort-status"></i></th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody"></tbody>
                </table>
            </div>

            <div id="filter-empty-state" class="ar-empty" style="display:none;">
                <i class="bi bi-funnel-fill"></i>
                <div class="text-meta">No records match your filters.</div>
            </div>
        </div>

        <div class="card-footer py-2">
            <div id="pagination-container"
                 class="d-flex flex-sm-nowrap flex-wrap align-items-center justify-content-between gap-3 w-100"
                 style="display:none !important;">

                <div id="paginationInfo"
                     class="small text-meta text-nowrap flex-sm-fill w-sm-100 text-sm-start text-center order-1">
                    Showing 0 to 0 of 0 entries
                </div>

                <div class="d-flex align-items-center justify-content-center flex-wrap gap-3 flex-sm-fill w-sm-100 order-2">
                    <nav aria-label="Table Navigation">
                        <ul class="pagination pagination-sm mb-0" id="paginationList"></ul>
                    </nav>
                    <div class="d-flex align-items-center gap-1" id="page-jump-wrapper">
                        <small class="text-meta text-nowrap">Go to:</small>
                        <input type="number" id="page-jump-input"
                               class="form-control form-control-sm text-center px-1"
                               min="1" style="width:45px;height:28px;" placeholder="Go">
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-sm-end justify-content-center gap-2 flex-sm-fill w-sm-100 order-3">
                    <small class="text-meta text-nowrap">Rows Per Page:</small>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                type="button" id="rowsPerPageBtn"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            10 rows
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item row-limit-opt" href="#" data-value="10">10 rows</a></li>
                            <li><a class="dropdown-item row-limit-opt" href="#" data-value="25">25 rows</a></li>
                            <li><a class="dropdown-item row-limit-opt" href="#" data-value="50">50 rows</a></li>
                            <li><a class="dropdown-item row-limit-opt" href="#" data-value="100">100 rows</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../system_functions/show_toast.php'; ?>

<script>
/* ── Shared State ───────────────────────────────────────── */
const _allCutoffs = <?= json_encode(array_values($cutoffs)) ?>;

let _selStart  = <?= json_encode($defaultStart) ?>;
let _selEnd    = <?= json_encode($defaultEnd) ?>;
let _selBtnLbl = <?= json_encode($defaultLabel) ?>;
let _selRngLbl = <?= json_encode($defaultRange) ?>;

let activeStatus     = 'ALL';
let currentPageIndex = 1;
let rowsPerPage      = parseInt(localStorage.getItem('ar_rows_per_page')) || 10;
let searchTimeout    = null;

const _dropBtn  = document.getElementById('ar-cutoff-btn');
const _dropObj  = bootstrap.Dropdown.getOrCreateInstance(_dropBtn);
const _panel1   = document.getElementById('ar-panel-1');
const _panel2   = document.getElementById('ar-panel-2');
const _periodLi = document.getElementById('ar-period-list');

// SORTING
const AR_DEFAULT_SORT_COL = 'date';
const AR_DEFAULT_SORT_DIR = 'desc';
let arSortColumn    = AR_DEFAULT_SORT_COL;
let arSortDirection = AR_DEFAULT_SORT_DIR;

/* ── Fetch ──────────────────────────────────────────────── */
function fetchAttendanceReport() {
    if (!_selStart || !_selEnd) return;

    const unloadedState  = document.getElementById('ar-unloaded-state');
    const loadingState   = document.getElementById('ar-loading-state');
    const tableContainer = document.getElementById('report-table-container');
    const emptyState     = document.getElementById('filter-empty-state');
    const pagContainer   = document.getElementById('pagination-container');
    const q              = (document.getElementById('search-input')?.value || '').trim();

    tableContainer.style.display = 'none';
    unloadedState.style.display  = 'none';
    emptyState.style.display     = 'none';
    pagContainer.setAttribute('style', 'display:none !important');
    loadingState.style.display   = 'flex';
    document.getElementById('export-btn').disabled = true;

    const params = new URLSearchParams({
        action: 'attendance',
        start:  _selStart,
        end:    _selEnd,
        page:   currentPageIndex,
        limit:  rowsPerPage,
        status: activeStatus,
        search: q,
        sort:   arSortColumn,
        dir:    arSortDirection
    });

    fetch(`reports_api.php?${params.toString()}`)
        .then(res => {
            if (res.status === 401) throw new Error('Unauthorized access. Please log back in.');
            return res.json();
        })
        .then(resData => {
            loadingState.style.display = 'none';
            const tbody = document.getElementById('reportTableBody');
            tbody.innerHTML = '';

            if (resData.error) {
                unloadedState.innerHTML    = `<i class="bi bi-exclamation-triangle-fill"></i><div class="text-meta">${resData.error}</div>`;
                unloadedState.style.display = 'flex';
                return;
            }

            const dataRows     = resData.data  || [];
            const totalRecords = resData.total || 0;

            if (!dataRows.length) {
                emptyState.style.display = 'flex';
                return;
            }

            dataRows.forEach(row => {
                let sc = 'status-pending';
                if (row.status === 'present') sc = 'status-approved';
                if (row.status === 'absent')  sc = 'status-rejected';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.employee_id     || '<span class="text-muted">-</span>'}</td>
                    <td>${row.employee_name  || '<span class="text-muted">-</span>'}</td>
                    <td>${row.department_name|| '<span class="text-muted">-</span>'}</td>
                    <td>${row.role_name      || '<span class="text-muted">-</span>'}</td>
                    <td>${parseDateString(row.work_date)}</td>
                    <td>${parseTimeString(row.actual_time_in)}</td>
                    <td>${parseTimeString(row.actual_time_out)}</td>
                    <td>${formatMinutes(row.total_work_minutes)}</td>
                    <td>${formatMinutes(row.late_minutes)}</td>
                    <td><span class="text-muted">-</span></td>
                    <td>${formatMinutes(row.undertime_minutes)}</td>
                    <td>${formatMinutes(row.overtime_minutes)}</td>
                    <td><span class="pill ${sc}">${row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—'}</span></td>
                `;
                tbody.appendChild(tr);
            });

            tableContainer.style.display = 'block';
            document.getElementById('export-btn').disabled = false;

            const startEntry = (currentPageIndex - 1) * rowsPerPage + 1;
            const endEntry   = Math.min(startEntry + rowsPerPage - 1, totalRecords);
            document.getElementById('paginationInfo').textContent =
                `Showing ${startEntry} to ${endEntry} of ${totalRecords} entries`;

            const totalPages = Math.ceil(totalRecords / rowsPerPage);
            renderPaginationControls(totalPages);
            pagContainer.setAttribute('style', 'display:flex !important');
        })
        .catch(err => {
            loadingState.style.display  = 'none';
            unloadedState.innerHTML     = `<i class="bi bi-exclamation-triangle-fill"></i><div class="text-meta">${err.message || 'An error occurred.'}</div>`;
            unloadedState.style.display = 'flex';
        });
}

/* ── Range selection — saves to localStorage ────────────── */
function _selectRange(start, end, btnLabel, rangeLabel) {
    _selStart  = start;
    _selEnd    = end;
    _selBtnLbl = btnLabel;
    _selRngLbl = rangeLabel;
    currentPageIndex = 1;

    localStorage.setItem('ar_cutoff', JSON.stringify({ start, end, btnLabel, rangeLabel }));

    document.getElementById('ar-btn-label').textContent   = btnLabel;
    document.getElementById('ar-range-label').textContent = rangeLabel;

    document.querySelectorAll('.cutoff-item').forEach(el => {
        el.classList.toggle('active', el.dataset.start === start && el.dataset.end === end);
    });

    _dropObj.hide();
    fetchAttendanceReport();
}

function formatMinutes(mins) {
    if (mins === null || mins === undefined) return '<span class="text-muted">-</span>';
    mins = parseInt(mins, 10);
    if (isNaN(mins)) return '<span class="text-muted">-</span>';
    if (mins === 0)  return '0m';
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0 && m > 0) return `${h}h ${m}m`;
    if (h > 0)           return `${h}h`;
    return `${m}m`;
}

function renderPaginationControls(totalPages) {
    _renderPaginationControls(totalPages, currentPageIndex, fetchAttendanceReport, n => { currentPageIndex = n; });
}

// Add Sorting to Header
function applyArHeaderUI() {
    document.querySelectorAll('#report-thead .sortable').forEach(el => el.classList.remove('sorted'));
    document.querySelectorAll('#report-thead .sortIcon').forEach(el => {
        el.className = 'sortIcon bi bi-filter';
    });

    const activeTh = document.querySelector(`#report-thead .sortable[data-sort="${arSortColumn}"]`);
    if (activeTh) {
        activeTh.classList.add('sorted');
        const icon = activeTh.querySelector('.sortIcon');
        if (icon) icon.className = 'sortIcon bi ' + (arSortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down');
    }
}

document.getElementById('report-thead').addEventListener('click', e => {
    const th = e.target.closest('.sortable');
    if (!th) return;

    const col = th.dataset.sort;

    if (arSortColumn === col) {
        if (arSortDirection === 'desc') {
            arSortDirection = 'asc';
        } else {
            arSortColumn    = AR_DEFAULT_SORT_COL;
            arSortDirection = AR_DEFAULT_SORT_DIR;
        }
    } else {
        arSortColumn    = col;
        arSortDirection = 'desc';
    }

    applyArHeaderUI();
    currentPageIndex = 1;
    fetchAttendanceReport();
});

/* ── Panel navigation ───────────────────────────────────── */
function _goPanel1() {
    _periodLi.innerHTML = '<p class="text-meta small text-center px-3 py-2 mb-0">Pick a month above to see its cut-off periods.</p>';
    if (typeof _periodFp !== 'undefined') _periodFp.clear();
    _panel2.style.display = 'none';
    _panel1.style.display = 'block';
}

document.getElementById('ar-open-period-panel').addEventListener('click', e => {
    e.preventDefault(); e.stopPropagation();
    _panel1.style.display = 'none';
    _panel2.style.display = 'block';
    setTimeout(() => _periodFp.open(), 30);
});

document.getElementById('ar-back-btn').addEventListener('click', e => {
    e.preventDefault(); e.stopPropagation();
    _goPanel1();
});

_dropBtn.closest('.dropdown').addEventListener('hidden.bs.dropdown', _goPanel1);

/* ── Cutoff items ───────────────────────────────────────── */
document.querySelectorAll('.cutoff-item').forEach(item => {
    item.addEventListener('click', e => {
        e.preventDefault();
        _selectRange(item.dataset.start, item.dataset.end,
                     item.dataset.btnLabel, item.dataset.rangeLabel);
    });
});

/* ── Flatpickr: period panel ────────────────────────────── */
let _fpOpen = false;

const _periodFp = flatpickr('#ar-period-fp', {
    plugins: [new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'F Y' })],
    disableMobile: true,
    onOpen()  { _fpOpen = true;  },
    onClose() { _fpOpen = false; },
    onChange(selectedDates) {
        if (!selectedDates.length) return;
        const d = selectedDates[0], y = d.getFullYear(), mo = d.getMonth();
        const mStart = new Date(y, mo, 1), mEnd = new Date(y, mo + 1, 0);

        const matches = _allCutoffs.filter(c => {
            const cs = new Date(c.start_date + 'T00:00:00');
            const ce = new Date(c.end_date   + 'T00:00:00');
            return cs <= mEnd && ce >= mStart;
        });

        if (!matches.length) {
            _periodLi.innerHTML = '<p class="text-tertiary small text-center px-3 py-2 mb-0">No cut-off periods found for this month.</p>';
            return;
        }

        _periodLi.innerHTML = matches.map(c => {
            const rLabel = _fmtRange(c.start_date, c.end_date);
            return `<a href="#" class="dropdown-item rw-period-item"
                        data-start="${c.start_date}" data-end="${c.end_date}"
                        data-btn="Selected Cut-Off"
                        data-range="Selected Cut-Off: ${rLabel}">${rLabel}</a>`;
        }).join('');

        _periodLi.querySelectorAll('.rw-period-item').forEach(item => {
            item.addEventListener('click', e => {
                e.preventDefault();
                _selectRange(item.dataset.start, item.dataset.end,
                             item.dataset.btn, item.dataset.range);
            });
        });
    }
});

_dropBtn.addEventListener('hide.bs.dropdown', e => { if (_fpOpen) e.preventDefault(); });

/* ── Flatpickr: full month ──────────────────────────────── */
const _monthFp = flatpickr('#ar-month-fp-anchor', {
    plugins: [new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'F Y' })],
    disableMobile: true,
    positionElement: document.getElementById('ar-cutoff-btn'),
    onChange(selectedDates) {
        if (!selectedDates.length) return;
        const d = selectedDates[0], y = d.getFullYear(), mo = d.getMonth();
        const start = `${y}-${String(mo + 1).padStart(2,'0')}-01`;
        const end   = new Date(y, mo + 1, 0).toISOString().slice(0, 10);
        const mLbl  = d.toLocaleString('en-US', { month: 'long', year: 'numeric' });
        _selectRange(start, end, mLbl, 'Selected Month: ' + _fmtRange(start, end));
    }
});

document.getElementById('ar-open-month-picker').addEventListener('click', e => {
    e.preventDefault();
    _dropObj.hide();
    setTimeout(() => _monthFp.open(), 50);
});

/* ── Status filter ──────────────────────────────────────── */
document.querySelectorAll('.status-opt').forEach(item => {
    item.addEventListener('click', e => {
        e.preventDefault();
        activeStatus = item.dataset.value;
        document.getElementById('status-label').textContent = item.textContent.trim();
        currentPageIndex = 1;
        fetchAttendanceReport();
    });
});

/* ── Search (debounced) ─────────────────────────────────── */
function handleSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPageIndex = 1;
        fetchAttendanceReport();
    }, 400);
}

/* ── Rows per page ──────────────────────────────────────── */
function changeRowsPerPage(val) {
    rowsPerPage = parseInt(val);
    localStorage.setItem('ar_rows_per_page', val);
    document.getElementById('rowsPerPageBtn').textContent = `${val} rows`;
    currentPageIndex = 1;
    fetchAttendanceReport();
}

/* ── Export ─────────────────────────────────────────────── */
function getExportData() {
    const q = (document.getElementById('search-input')?.value || '').trim();
    const params = new URLSearchParams({
        action: 'attendance', start: _selStart, end: _selEnd,
        status: activeStatus, search: q, bypass_pagination: '1'
    });
    return fetch(`reports_api.php?${params.toString()}`).then(r => r.json());
}

function _fmtMins(mins) {
    if (mins == null) return '—';
    mins = parseInt(mins, 10);
    if (isNaN(mins)) return '—';
    if (mins === 0) return '0m';
    const h = Math.floor(mins / 60), m = mins % 60;
    if (h > 0 && m > 0) return `${h}h ${m}m`;
    return h > 0 ? `${h}h` : `${m}m`;
}

function exportAllCSV() {
    getExportData().then(resData => {
        const dataRows = resData.data || [];
        if (!dataRows.length) { showToast('No records to export.', 'warning'); return; }

        const headers = ['Employee ID','Name','Department','Role','Date','Time In','Time Out','Regular Hours','Tardiness','Leave','Undertime','Overtime','Status'];
        const escCSV  = v => '"' + String(v ?? '—').replace(/"/g,'""').replace(/\n/g,' ').trim() + '"';
        const rows    = dataRows.map(r => [
            escCSV(r.employee_id),              escCSV(r.employee_name),
            escCSV(r.department_name),          escCSV(r.role_name),
            escCSV(parseDateString(r.work_date)),
            escCSV(parseTimeString(r.actual_time_in)), escCSV(parseTimeString(r.actual_time_out)),
            escCSV(_fmtMins(r.total_work_minutes)), escCSV(_fmtMins(r.late_minutes)),
            escCSV('—'),
            escCSV(_fmtMins(r.undertime_minutes)), escCSV(_fmtMins(r.overtime_minutes)),
            escCSV(r.status)
        ].join(','));

        const blob = new Blob(["﻿" + [headers.join(','), ...rows].join('\n')], { type: 'text/csv;charset=utf-8;' });
        const a    = Object.assign(document.createElement('a'), {
            href: URL.createObjectURL(blob),
            download: `attendance_report_${_selStart}_to_${_selEnd}.csv`
        });
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });
}

async function exportAllPDF() {
    const resData  = await getExportData();
    const dataRows = resData.data || [];
    if (!dataRows.length) { showToast('No records to export.', 'warning'); return; }

    const logoBlob = await fetch('../assets/images/hsn_logo.png').then(r => r.blob());
    const logoB64  = await new Promise(res => { const fr = new FileReader(); fr.onloadend = () => res(fr.result); fr.readAsDataURL(logoBlob); });
    const fmtD     = s => { const [y,m,d] = s.split('-').map(Number); return new Date(y,m-1,d).toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'}); };

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('landscape');
    const pw  = doc.internal.pageSize.width;

    doc.addImage(logoB64, 'PNG', pw - 44, 4, 18, 8);
    doc.setFontSize(14);
    doc.text(`Attendance Report for ${fmtD(_selStart)} to ${fmtD(_selEnd)}`, 14, 12);

    const head = [['Employee ID','Name','Department','Role','Date','Time In','Time Out','Reg. Hours','Tardiness','Leave','Undertime','Overtime','Status']];
    const body = dataRows.map(r => [
        r.employee_id || '—',       r.employee_name || '—',
        r.department_name || '—',   r.role_name || '—',
        parseDateString(r.work_date),
        parseTimeString(r.actual_time_in), parseTimeString(r.actual_time_out),
        _fmtMins(r.total_work_minutes),   _fmtMins(r.late_minutes),
        '—',
        _fmtMins(r.undertime_minutes),    _fmtMins(r.overtime_minutes),
        r.status ? r.status.toUpperCase() : '—'
    ]);
    doc.autoTable({ head, body, startY: 20, styles: { fontSize: 7, cellPadding: 2 }, headStyles: { fillColor: [151, 190, 65] } });
    doc.save(`attendance_report_${_selStart}_to_${_selEnd}.pdf`);
}

/* ── Collapsible columns ────────────────────────────────── */
const AR_COL_LS_KEY = 'ar_col_collapsed';

function loadCollapsedCols() {
    try {
        if (JSON.parse(localStorage.getItem(AR_COL_LS_KEY) || 'false'))
            document.getElementById('reportTable').classList.add('cols-dept-role-collapsed');
    } catch (_) {}
}

document.getElementById('dept-role-toggle').addEventListener('click', e => {
    e.stopPropagation();
    bootstrap.Tooltip.getInstance(e.currentTarget)?.hide();
    document.getElementById('reportTable').classList.toggle('cols-dept-role-collapsed');
    localStorage.setItem(AR_COL_LS_KEY,
        document.getElementById('reportTable').classList.contains('cols-dept-role-collapsed'));
});

/* ── Init ───────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {

    document.getElementById('rowsPerPageBtn').textContent = `${rowsPerPage} rows`;
    loadCollapsedCols();
    bootstrap.Tooltip.getOrCreateInstance(document.getElementById('dept-role-toggle'), { trigger: 'hover' });

    try {
        const saved = localStorage.getItem('ar_cutoff');
        if (saved) {
            const { start, end, btnLabel, rangeLabel } = JSON.parse(saved);
            _selStart  = start;
            _selEnd    = end;
            _selBtnLbl = btnLabel;
            _selRngLbl = rangeLabel;
            document.getElementById('ar-btn-label').textContent   = btnLabel;
            document.getElementById('ar-range-label').textContent = rangeLabel;
            document.querySelectorAll('.cutoff-item').forEach(el => {
                el.classList.toggle('active', el.dataset.start === start && el.dataset.end === end);
            });
        }
    } catch (e) { /* corrupt storage — fall back to PHP defaults */ }

    applyArHeaderUI();
    
    const jumpInput = document.getElementById('page-jump-input');
    if (jumpInput) {
        jumpInput.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            let target = parseInt(this.value);
            const max  = parseInt(this.max) || 1;
            if (isNaN(target) || target < 1) target = 1;
            if (target > max) target = max;
            this.value       = target;
            currentPageIndex = target;
            fetchAttendanceReport();
        });
    }

    document.body.addEventListener('click', e => {
        const target = e.target.closest('.row-limit-opt');
        if (!target) return;
        e.preventDefault();
        changeRowsPerPage(target.dataset.value);
    });

    fetchAttendanceReport();
});
</script>
</body>
</html>
