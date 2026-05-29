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
$currentPage = 'leave_report';

$today        = date('Y-m-d');
$defaultStart = date('Y-m-01');
$defaultEnd   = $today;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Consumed Report</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">
    <link rel="stylesheet" href="reports_page.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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

            <div class="input-group input-group-sm" style="max-width:220px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="search-input" class="form-control" placeholder="Search employee…" oninput="handleSearchInput()">
            </div>

            <button class="btn btn-sm btn-success" id="datePickerBtn" type="button">
                <i class="bi bi-calendar3 me-1"></i>
                <span id="dateRangeLabel">Loading…</span>
            </button>

            <span id="ar-range-label" class="text-tertiary small"></span>

            <div class="ms-auto d-flex gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button"
                            id="export-btn" data-bs-toggle="dropdown" aria-expanded="false" disabled>
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="z-index:1055;">
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
                <div class="text-meta mt-2">Fetching leave report…</div>
            </div>

            <div id="ar-unloaded-state" class="ar-empty">
                <i class="bi bi-bar-chart-line-fill"></i>
                <div class="text-meta">Select a date range to load the report.</div>
            </div>

            <div class="tableScroll" id="report-table-container" style="display:none;">
                <table class="table table-hover mb-0" id="reportTable">
                    <thead id="reportTableHead">
                        <tr>
                            <th class="sortable" data-sort="employee_id">Employee ID <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="name">Name <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="department">Department <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="role">Role <i class="sortIcon bi bi-filter"></i></th>
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
                        <ul class="dropdown-menu dropdown-menu-end" style="z-index:1055;">
                            <li><a class="dropdown-item row-limit-opt" href="#" data-value="5">5 rows</a></li>
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

<?php include '../toast.php'; ?>

<script>
/* ── State ────────────────────────────────────────── */
let _selStart        = <?= json_encode($defaultStart) ?>;
let _selEnd          = <?= json_encode($defaultEnd) ?>;
let _currentTypes    = [];
let currentPageIndex = 1;
let rowsPerPage      = parseInt(localStorage.getItem('lr_rows_per_page')) || 10;
let searchTimeout    = null;

// SORTING STATE
const LR_DEFAULT_SORT_COL = 'name';
const LR_DEFAULT_SORT_DIR = 'asc';
let lrSortColumn    = LR_DEFAULT_SORT_COL;
let lrSortDirection = LR_DEFAULT_SORT_DIR;

/* ── Helpers ──────────────────────────────────────── */
function toLocalStr(d) {
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function fmtDate(d) {
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function updateDateLabel(dates) {
    const label = document.getElementById('dateRangeLabel');
    if (!dates || !dates.length) { label.textContent = 'Select range'; return; }
    const isSameDay = dates.length > 1 && dates[0].toDateString() === dates[1].toDateString();
    label.textContent = (dates.length === 1 || isSameDay)
        ? fmtDate(dates[0])
        : fmtDate(dates[0]) + ' – ' + fmtDate(dates[1]);
}

function typeToColKey(name) {
    return name.toLowerCase().replace(/[^a-z0-9]+/g, '_');
}

/* ── Table header builder ─────────────────────────── */
function buildTableHeaders(types) {
    const tr = document.querySelector('#reportTableHead tr');
    
    tr.innerHTML = `
        <th class="sortable ${lrSortColumn === 'employee_id' ? 'sorted' : ''}" data-sort="employee_id">Employee ID <i class="sortIcon bi ${lrSortColumn === 'employee_id' ? (lrSortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-filter'}"></i></th>
        <th class="sortable ${lrSortColumn === 'name' ? 'sorted' : ''}" data-sort="name">Name <i class="sortIcon bi ${lrSortColumn === 'name' ? (lrSortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-filter'}"></i></th>
        <th class="sortable ${lrSortColumn === 'department' ? 'sorted' : ''}" data-sort="department">Department <i class="sortIcon bi ${lrSortColumn === 'department' ? (lrSortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-filter'}"></i></th>
        <th class="sortable ${lrSortColumn === 'role' ? 'sorted' : ''}" data-sort="role">Role <i class="sortIcon bi ${lrSortColumn === 'role' ? (lrSortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-filter'}"></i></th>
        <th class="sortable ${lrSortColumn === 'buffer' ? 'sorted' : ''}" data-sort="buffer">Buffer Leave <i class="sortIcon bi ${lrSortColumn === 'buffer' ? (lrSortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-filter'}"></i></th>
        ${types.map(t => `<th>${t.label}</th>`).join('')}
        <th class="sortable ${lrSortColumn === 'balance' ? 'sorted' : ''}" data-sort="balance">Leave Balance <i class="sortIcon bi ${lrSortColumn === 'balance' ? (lrSortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-filter'}"></i></th>
    `;
}

function applyLrHeaderUI() {
    document.querySelectorAll('#reportTableHead .sortable').forEach(el => el.classList.remove('sorted'));
    document.querySelectorAll('#reportTableHead .sortIcon').forEach(el => {
        el.className = 'sortIcon bi bi-filter';
    });

    const activeTh = document.querySelector(`#reportTableHead .sortable[data-sort="${lrSortColumn}"]`);
    if (activeTh) {
        activeTh.classList.add('sorted');
        const icon = activeTh.querySelector('.sortIcon');
        if (icon) icon.className = 'sortIcon bi ' + (lrSortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down');
    }
}

// FIXED CLICK LISTENER: Accurately switches directions when clicking the active item
document.getElementById('reportTableHead').addEventListener('click', e => {
    const th = e.target.closest('.sortable');
    if (!th) return;

    const col = th.dataset.sort;

    if (lrSortColumn === col) {
        // Toggle direction smoothly on the same header field
        lrSortDirection = (lrSortDirection === 'asc') ? 'desc' : 'asc';
    } else {
        // New column chosen: reset to that column and default to ascending sorting order
        lrSortColumn    = col;
        lrSortDirection = 'asc';
    }

    applyLrHeaderUI();
    currentPageIndex = 1;
    fetchLeaveReport();
});

/* ── Fetch ────────────────────────────────────────── */
function fetchLeaveReport() {
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

    // Synchronized API mapping keys
    const params = new URLSearchParams({
        action:         'leave',
        start:          _selStart,
        end:            _selEnd,
        page:           currentPageIndex,
        limit:          rowsPerPage,
        search:         q,
        sort_column:    lrSortColumn,
        sort_direction: lrSortDirection
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
                unloadedState.innerHTML     = `<i class="bi bi-exclamation-triangle-fill"></i><div class="text-meta">${resData.error}</div>`;
                unloadedState.style.display = 'flex';
                return;
            }

            const types        = resData.leave_types || [];
            const dataRows     = resData.data        || [];
            const totalRecords = resData.total        || 0;

            _currentTypes = types;
            buildTableHeaders(types);

            if (!dataRows.length) {
                emptyState.style.display = 'flex';
                return;
            }

            dataRows.forEach(row => {
                const typeCells = types.map(t => {
                    const key = typeToColKey(t.name);
                    const val = parseInt(row[key] ?? 0, 10);
                    return `<td>${val > 0 ? val : '<span class="text-tertiary">-</span>'}</td>`;
                }).join('');

                const bufferBal  = parseInt(row.balance_buffer_leave   ?? 0, 10);
                const vacBal     = parseInt(row.balance_vacation_leave ?? 0, 10);
                const sickBal    = parseInt(row.balance_sick_leave     ?? 0, 10);
                const leaveBalance = vacBal + sickBal;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.employee_id    || '-'}</td>
                    <td>${row.employee_name  || '—'}</td>
                    <td>${row.department_name || '—'}</td>
                    <td>${row.role_name       || '—'}</td>
                    <td>${bufferBal > 0 ? bufferBal : '<span class="text-tertiary">-</span>'}</td>
                    ${typeCells}
                    <td>${leaveBalance}</td>
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

function renderPaginationControls(totalPages) {
    _renderPaginationControls(totalPages, currentPageIndex, fetchLeaveReport, n => { currentPageIndex = n; });
}

/* ── Flatpickr range ──────────────────────────────── */
flatpickr(document.getElementById('datePickerBtn'), {
    mode: 'range',
    dateFormat: 'Y-m-d',
    defaultDate: [_selStart, _selEnd],
    disableMobile: true,
    onChange(dates) {
        updateDateLabel(dates);
        if (dates.length !== 2) return;
        _selStart        = toLocalStr(dates[0]);
        _selEnd          = toLocalStr(dates[1]);
        currentPageIndex = 1;
        fetchLeaveReport();
    }
});

updateDateLabel([new Date(_selStart + 'T00:00:00'), new Date(_selEnd + 'T00:00:00')]);

/* ── Search ───────────────────────────────────────── */
function handleSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPageIndex = 1;
        fetchLeaveReport();
    }, 400);
}

/* ── Rows per page ────────────────────────────────── */
function changeRowsPerPage(val) {
    rowsPerPage = parseInt(val);
    localStorage.setItem('lr_rows_per_page', val);
    document.getElementById('rowsPerPageBtn').textContent = `${val} rows`;
    currentPageIndex = 1;
    fetchLeaveReport();
}

/* ── Export ───────────────────────────────────────── */
function getExportData() {
    const q = (document.getElementById('search-input')?.value || '').trim();
    const params = new URLSearchParams({
        action: 'leave', start: _selStart, end: _selEnd,
        search: q, bypass_pagination: '1'
    });
    return fetch(`reports_api.php?${params.toString()}`).then(r => r.json());
}

function exportAllCSV() {
    getExportData().then(resData => {
        const dataRows = resData.data        || [];
        const types    = resData.leave_types || _currentTypes;
        if (!dataRows.length) { showToast('No records to export.', 'warning'); return; }

        const esc = v => '"' + String(v ?? '').replace(/"/g, '""').trim() + '"';
        const headers = ['Employee ID', 'Name', 'Department', 'Role',
                         'Buffer Leave', ...types.map(t => t.label), 'Leave Balance'];
        const rows = dataRows.map(r => {
            const typeCols   = types.map(t => esc(parseInt(r[typeToColKey(t.name)] ?? 0, 10)));
            const bufferBal  = parseInt(r.balance_buffer_leave   ?? 0, 10);
            const balance    = parseInt(r.balance_vacation_leave ?? 0, 10) + parseInt(r.balance_sick_leave ?? 0, 10);
            return [esc(r.employee_id), esc(r.employee_name), esc(r.department_name),
                    esc(r.role_name), esc(bufferBal), ...typeCols, esc(balance)].join(',');
        });

        const blob = new Blob(["﻿" + [headers.join(','), ...rows].join('\n')],
                              { type: 'text/csv;charset=utf-8;' });
        const a = Object.assign(document.createElement('a'), {
            href: URL.createObjectURL(blob),
            download: `leave_consumed_${_selStart}_to_${_selEnd}.csv`
        });
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });
}

async function exportAllPDF() {
    const resData  = await getExportData();
    const dataRows = resData.data        || [];
    const types    = resData.leave_types || _currentTypes;
    if (!dataRows.length) { showToast('No records to export.', 'warning'); return; }

    const logoBlob = await fetch('../assets/images/hsn_logo.png').then(r => r.blob());
    const logoB64  = await new Promise(res => { const fr = new FileReader(); fr.onloadend = () => res(fr.result); fr.readAsDataURL(logoBlob); });
    const fmtD     = s => { const [y,m,d] = s.split('-').map(Number); return new Date(y,m-1,d).toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'}); };

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('landscape');
    const pw  = doc.internal.pageSize.width;

    doc.addImage(logoB64, 'PNG', pw - 44, 4, 18, 8);
    doc.setFontSize(14);
    doc.text(`Leave Consumed Report for ${fmtD(_selStart)} to ${fmtD(_selEnd)}`, 14, 12);

    const head = [['Employee ID', 'Name', 'Department', 'Role',
                   'Buffer Leave', ...types.map(t => t.label), 'Leave Balance']];
    const body = dataRows.map(r => {
        const typeCols  = types.map(t => parseInt(r[typeToColKey(t.name)] ?? 0, 10));
        const bufferBal = parseInt(r.balance_buffer_leave   ?? 0, 10);
        const balance   = parseInt(r.balance_vacation_leave ?? 0, 10) + parseInt(r.balance_sick_leave ?? 0, 10);
        return [r.employee_id || '—', r.employee_name || '—',
                r.department_name || '—', r.role_name || '—',
                bufferBal, ...typeCols, balance];
    });
    doc.autoTable({ head, body, startY: 20, styles: { fontSize: 9, cellPadding: 3 }, headStyles: { fillColor: [151, 190, 65] } });
    doc.save(`leave_consumed_${_selStart}_to_${_selEnd}.pdf`);
}

/* ── Init ─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('rowsPerPageBtn').textContent = `${rowsPerPage} rows`;

    document.getElementById('page-jump-input')?.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        let target = parseInt(this.value);
        const max  = parseInt(this.max) || 1;
        if (isNaN(target) || target < 1) target = 1;
        if (target > max) target = max;
        this.value       = target;
        currentPageIndex = target;
        fetchLeaveReport();
    });

    document.body.addEventListener('click', e => {
        const target = e.target.closest('.row-limit-opt');
        if (!target) return;
        e.preventDefault();
        changeRowsPerPage(target.dataset.value);
    });

    applyLrHeaderUI();
    fetchLeaveReport();
});
</script>
</body>
</html>