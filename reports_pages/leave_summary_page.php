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
$currentPage = 'leave_summary';
$currentYear = (int)date('Y');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Summary</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">
    <link rel="stylesheet" href="reports_page.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
                <button class="btn btn-success dropdown-toggle" type="button" id="yearSelectBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-calendar-event me-1"></i> <span id="selectedYearLabel">Selected Year: <?= $currentYear ?></span>
                </button>
                <ul class="dropdown-menu" aria-labelledby="yearSelectBtn" style="z-index: 1055;">
                    <?php for($y = $currentYear; $y >= $currentYear - 4; $y--): ?>
                        <li><a class="dropdown-item year-opt" href="#" data-value="<?= $y ?>"><?= $y ?></a></li>
                    <?php endfor; ?>
                </ul>
            </div>

            <div class="ms-auto d-flex gap-2">
                <div class="input-group" style="max-width: 220px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="search-input" class="form-control" placeholder="Search employee…" oninput="handleSearchInput()">
                </div>


                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" id="export-btn" data-bs-toggle="dropdown" aria-expanded="false" disabled>
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="z-index: 1055;">
                        <li><a class="dropdown-item" href="#" onclick="exportAllCSV(); return false;"><i class="bi bi-filetype-csv me-2"></i> Export CSV</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportAllPDF(); return false;"><i class="bi bi-filetype-pdf me-2"></i> Export PDF</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card-body d-flex flex-column requests-card-body">
            <div id="ar-loading-state" class="ar-empty" style="display: none;">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="text-meta mt-2">Fetching leave summary report…</div>
            </div>

            <div id="ar-unloaded-state" class="ar-empty" style="display: none;">
                <i class="bi bi-bar-chart-line-fill"></i>
                <div class="text-meta">Select a criteria option context to parse parameters.</div>
            </div>

            <div class="tableScroll" id="report-table-container" style="display: none;">
                <table class="table table-hover mb-0" id="reportTable">
                    <thead id="reportTableHead">
                        <tr>
                            <th class="sortable" data-sort="employee_id">Employee ID <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="name">Name <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="department">Department <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="role">Role <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="entitled_vl">Entitled VL <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="carry_over">Carry Over <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="vl_taken">VL Taken <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="remaining_vl">Remaining VL <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="entitled_sl">Entitled SL <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="sl_taken">SL Taken <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="remaining_sl">Remaining SL <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="total_entitled">Total Entitled <i class="sortIcon bi bi-filter"></i></th>
                            <th class="sortable" data-sort="total_taken">Total Taken <i class="sortIcon bi bi-filter"></i></th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody"></tbody>
                </table>
            </div>

            <div id="filter-empty-state" class="ar-empty" style="display: none;">
                <i class="bi bi-funnel-fill"></i>
                <div class="text-meta">No records match your filters.</div>
            </div>
        </div>

        <div class="card-footer py-2">
            <div id="pagination-container" class="d-flex flex-sm-nowrap flex-wrap align-items-center justify-content-between gap-3 w-100" style="display: none !important;">
                <div id="paginationInfo" class="small text-meta text-nowrap flex-sm-fill w-sm-100 text-sm-start text-center order-1">
                    Showing 0 to 0 of 0 entries
                </div>

                <div class="d-flex align-items-center justify-content-center flex-wrap gap-3 flex-sm-fill w-sm-100 order-2">
                    <nav aria-label="Table Navigation">
                        <ul class="pagination pagination-sm mb-0" id="paginationList"></ul>
                    </nav>
                    <div class="d-flex align-items-center gap-1" id="page-jump-wrapper">
                        <small class="text-meta text-nowrap">Go to:</small>
                        <input type="number" id="page-jump-input" class="form-control form-control-sm text-center px-1" min="1" style="width: 45px; height: 28px;" placeholder="Go">
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-sm-end justify-content-center gap-2 flex-sm-fill w-sm-100 order-3">
                    <small class="text-meta text-nowrap">Rows Per Page:</small>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="rowsPerPageBtn" data-bs-toggle="dropdown" aria-expanded="false">
                            25 rows
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="z-index: 1055;">
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
let _selYear         = <?= $currentYear ?>;
let currentPageIndex = 1;
let rowsPerPage      = parseInt(localStorage.getItem('ls_rows_per_page')) || 25;
let searchTimeout    = null;

// SORTING STATE
const LS_DEFAULT_SORT_COL = 'name';
const LS_DEFAULT_SORT_DIR = 'asc';
let lsSortColumn    = LS_DEFAULT_SORT_COL;
let lsSortDirection = LS_DEFAULT_SORT_DIR;

/* ── Helpers ──────────────────────────────────────── */
function applyLsHeaderUI() {
    document.querySelectorAll('#reportTableHead .sortable').forEach(el => el.classList.remove('sorted'));
    document.querySelectorAll('#reportTableHead .sortIcon').forEach(el => {
        el.className = 'sortIcon bi bi-filter';
    });

    const activeTh = document.querySelector(`#reportTableHead .sortable[data-sort="${lsSortColumn}"]`);
    if (activeTh) {
        activeTh.classList.add('sorted');
        const icon = activeTh.querySelector('.sortIcon');
        if (icon) icon.className = 'sortIcon bi ' + (lsSortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down');
    }
}

document.getElementById('reportTableHead').addEventListener('click', e => {
    const th = e.target.closest('.sortable');
    if (!th) return;

    const col = th.dataset.sort;

    if (lsSortColumn === col) {
        lsSortDirection = (lsSortDirection === 'asc') ? 'desc' : 'asc';
    } else {
        lsSortColumn    = col;
        lsSortDirection = 'asc';
    }

    applyLsHeaderUI();
    currentPageIndex = 1;
    fetchLeaveSummary();
});

/* ── Fetch ────────────────────────────────────────── */
function fetchLeaveSummary() {
    const loadingState   = document.getElementById('ar-loading-state');
    const tableContainer = document.getElementById('report-table-container');
    const emptyState     = document.getElementById('filter-empty-state');
    const pagContainer   = document.getElementById('pagination-container');
    const q              = (document.getElementById('search-input')?.value || '').trim();

    tableContainer.style.display = 'none';
    emptyState.style.display     = 'none';
    pagContainer.setAttribute('style', 'display:none !important');
    loadingState.style.display   = 'flex';
    document.getElementById('export-btn').disabled = true;

    const params = new URLSearchParams({
        action:         'leave_summary',
        year:           _selYear,
        page:           currentPageIndex,
        limit:          rowsPerPage,
        search:         q,
        sort_column:    lsSortColumn,
        sort_direction: lsSortDirection
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
                const unloadedState = document.getElementById('ar-unloaded-state');
                unloadedState.innerHTML     = `<i class="bi bi-exclamation-triangle-fill"></i><div class="text-meta">${resData.error}</div>`;
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
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.employee_id      || '<span class="text-meta">-</span>'}</td>
                    <td><strong>${row.employee_name || '<span class="text-meta">-</span>'}</strong></td>
                    <td>${row.department_name   || '<span class="text-meta">-</span>'}</td>
                    <td>${row.role_name         || '<span class="text-meta">-</span>'}</td>
                    <td>${parseInt(row.entitled_vacation_leave,  10) || '<span class="text-meta">-</span>'}</td>
                    <td>${parseInt(row.carry_over_vacation,      10) || '<span class="text-meta">-</span>'}</td>
                    <td>${parseInt(row.vacation_leave_taken,     10) || '<span class="text-meta">-</span>'}</td>
                    <td>${parseInt(row.remaining_vacation_leave, 10) || '<span class="text-meta">-</span>'}</td>
                    <td>${parseInt(row.entitled_sick_leave,      10) || '<span class="text-meta">-</span>'}</td>
                    <td>${parseInt(row.sick_leave_taken,         10) || '<span class="text-meta">-</span>'}</td>
                    <td>${parseInt(row.remaining_sick_leave,     10) || '<span class="text-meta">-</span>'}</td>
                    <td>${parseInt(row.total_entitled,           10) || '<span class="text-meta">-</span>'}</td>
                    <td>${parseInt(row.total_taken,              10) || '<span class="text-meta">-</span>'}</td>
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
            loadingState.style.display = 'none';
            const unloadedState = document.getElementById('ar-unloaded-state');
            unloadedState.innerHTML     = `<i class="bi bi-exclamation-triangle-fill"></i><div class="text-meta">${err.message || 'An error occurred.'}</div>`;
            unloadedState.style.display = 'flex';
        });
}

function renderPaginationControls(totalPages) {
    _renderPaginationControls(totalPages, currentPageIndex, fetchLeaveSummary, n => { currentPageIndex = n; });
}

/* ── Search ───────────────────────────────────────── */
function handleSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPageIndex = 1;
        fetchLeaveSummary();
    }, 400);
}

/* ── Rows per page ────────────────────────────────── */
function changeRowsPerPage(val) {
    rowsPerPage = parseInt(val);
    localStorage.setItem('ls_rows_per_page', val);
    document.getElementById('rowsPerPageBtn').textContent = `${val} rows`;
    currentPageIndex = 1;
    fetchLeaveSummary();
}

/* ── Export ───────────────────────────────────────── */
function getExportData() {
    const q = (document.getElementById('search-input')?.value || '').trim();
    const params = new URLSearchParams({
        action: 'leave_summary', year: _selYear, search: q, bypass_pagination: '1',
        sort_column: lsSortColumn, sort_direction: lsSortDirection
    });
    return fetch(`reports_api.php?${params.toString()}`).then(r => r.json());
}

function getDynamicHeaders(year) {
    return [
        'Employee ID', 'Name', 'Department', 'Role',
        'Entitled VL', 'Carry Over', 'VL Taken', 'Remaining VL',
        'Entitled SL', 'SL Taken', 'Remaining SL',
        'Total Entitled', 'Total Taken'
    ];
}

function exportAllCSV() {
    getExportData().then(resData => {
        const rowsData = resData.data || [];
        if (!rowsData.length) { showToast('No records to export.', 'warning'); return; }

        const esc = v => '"' + String(v ?? '').replace(/"/g, '""').trim() + '"';
        const headers = getDynamicHeaders(_selYear).join(',');
        const rows    = rowsData.map(r => [
            esc(r.employee_id), esc(r.employee_name), esc(r.department_name), esc(r.role_name),
            esc(r.entitled_vacation_leave), esc(r.carry_over_vacation), esc(r.vacation_leave_taken), esc(r.remaining_vacation_leave),
            esc(r.entitled_sick_leave), esc(r.sick_leave_taken), esc(r.remaining_sick_leave),
            esc(r.total_entitled), esc(r.total_taken)
        ].join(','));

        const blob = new Blob(["﻿" + [headers, ...rows].join('\n')], { type: 'text/csv;charset=utf-8;' });
        const a = Object.assign(document.createElement('a'), {
            href: URL.createObjectURL(blob),
            download: `leave_summary_${_selYear}.csv`
        });
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });
}

function rowToArr(r) {
    return [
        r.employee_id || '—', r.employee_name || '—', r.department_name || '—', r.role_name || '—',
        r.entitled_vacation_leave, r.carry_over_vacation, r.vacation_leave_taken, r.remaining_vacation_leave,
        r.entitled_sick_leave, r.sick_leave_taken, r.remaining_sick_leave,
        r.total_entitled, r.total_taken
    ];
}

async function exportAllPDF() {
    const resData = await getExportData();
    const rows    = resData.data || [];
    if (!rows.length) { showToast('No records to export.', 'warning'); return; }

    const logoBlob = await fetch('../assets/images/hsn_logo.png').then(r => r.blob());
    const logoB64  = await new Promise(res => { const fr = new FileReader(); fr.onloadend = () => res(fr.result); fr.readAsDataURL(logoBlob); });

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('landscape');
    const pw  = doc.internal.pageSize.width;

    doc.addImage(logoB64, 'PNG', pw - 44, 4, 18, 8);
    doc.setFontSize(14);
    doc.text(`Leave Summary — ${_selYear}`, 14, 12);

    doc.autoTable({
        head: [getDynamicHeaders(_selYear)],
        body: rows.map(rowToArr),
        startY: 20,
        styles:     { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [151, 190, 65] }
    });

    doc.save(`leave_summary_${_selYear}.pdf`);
}

/* ── Init ───────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('rowsPerPageBtn').textContent = `${rowsPerPage} rows`;

    document.getElementById('page-jump-input')?.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        let t = parseInt(this.value), max = parseInt(this.max) || 1;
        if (isNaN(t) || t < 1) t = 1;
        if (t > max) t = max;
        this.value       = t;
        currentPageIndex = t;
        fetchLeaveSummary();
    });

    document.body.addEventListener('click', e => {
        const target = e.target.closest('.row-limit-opt');
        if (!target) return;
        e.preventDefault();
        changeRowsPerPage(target.dataset.value);
    });

    document.body.addEventListener('click', e => {
        const target = e.target.closest('.year-opt');
        if (!target) return;
        e.preventDefault();
        _selYear = parseInt(target.dataset.value);
        document.getElementById('selectedYearLabel').textContent = _selYear;
        currentPageIndex = 1;
        fetchLeaveSummary();
    });

    applyLsHeaderUI();
    fetchLeaveSummary();
});
</script>
</body>
</html>