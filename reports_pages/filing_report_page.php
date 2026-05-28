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
$currentPage = 'filing_report';

$cutoffs = $pdo->query("SELECT id, start_date, end_date FROM cutoffs ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
require_once 'cutoff_helpers.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Filing Report</title>

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

            <div class="input-group input-group-sm" style="max-width:220px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="search-input" class="form-control" placeholder="Search employee…" oninput="handleSearchInput()">
            </div>

            <div class="dropdown">
                <button class="btn btn-sm btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
                    <i class="bi bi-funnel"></i>
                    <span id="status-label">Select Request Type</span>
                </button>
                <ul class="dropdown-menu" style="z-index:1055;">
                    <li><a class="dropdown-item status-opt" href="#" data-value="ALL">All Filings</a></li>
                    <li><a class="dropdown-item status-opt" href="#" data-value="overtime">OT Request</a></li>
                    <li><a class="dropdown-item status-opt" href="#" data-value="ob leave">OB Request</a></li>
                    <li><a class="dropdown-item status-opt" href="#" data-value="log_edit">Log Edit Request</a></li>
                    <li><a class="dropdown-item status-opt" href="#" data-value="schedule_edit">Schedule Edit Request</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item status-opt" href="#" data-value="sick leave">Sick Leave Request</a></li>
                    <li><a class="dropdown-item status-opt" href="#" data-value="vacation leave">Vacation Leave Request</a></li>
                    <li><a class="dropdown-item status-opt" href="#" data-value="birthday leave">Birthday Leave</a></li>
                    <li><a class="dropdown-item status-opt" href="#" data-value="solo parent leave">Solo Parent Leave</a></li>
                </ul>
            </div>

            <div class="dropdown">
                <button class="btn btn-success btn-sm dropdown-toggle" type="button"
                        id="ar-cutoff-btn" data-bs-toggle="dropdown"
                        data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bi bi-calendar3 me-1"></i>
                    <span id="ar-btn-label"><?= htmlspecialchars($defaultLabel ?? '') ?></span>
                </button>

                <ul class="dropdown-menu">
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

            <span id="ar-range-label" class="text-tertiary small">
                <?= htmlspecialchars($defaultRange ?? '') ?>
            </span>

            <input type="text" id="ar-month-fp-anchor"
                   style="position:absolute;width:0;height:0;opacity:0;pointer-events:none;">

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
                <div class="text-meta mt-2">Fetching report logs...</div>
            </div>

            <div id="ar-unloaded-state" class="ar-empty">
                <i class="bi bi-funnel-fill" style="font-size: 2.5rem;"></i>
                <div class="text-secondary mt-2">Awaiting View Criteria</div>
                <small class="text-tertiary text-center px-4">
                    Please pick a <strong class="text-success">Request type</strong> and select a<strong class="text-success"> Cut-off period </strong>
                    <br> from the options above to assemble this log summary.
                </small>
            </div>

            <div class="tableScroll" id="report-table-container" style="display:none;">
                <table class="table table-hover mb-0" id="reportTable">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Request Date</th>
                            <th>Status</th>
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
/* ── State ─────────────────────────────────────────────── */
const _allCutoffs = <?= json_encode(array_values($cutoffs)) ?>;

let _selStart  = null;
let _selEnd    = null;
let _selBtnLbl = "Select Period";
let _selRngLbl = "";

let activeStatus     = '';
let currentPageIndex = 1;
let rowsPerPage      = parseInt(localStorage.getItem('ar_rows_per_page')) || 10;
let searchTimeout    = null;

let hasSelectedStatus = false;
let hasSelectedPeriod = false;

/* ── Fetch ──────────────────────────────────────────────── */
function fetchFilingReport() {
    if (!hasSelectedStatus || !hasSelectedPeriod || !_selStart || !_selEnd) return;

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
        action: 'reports',
        start:  _selStart,
        end:    _selEnd,
        page:   currentPageIndex,
        limit:  rowsPerPage,
        status: activeStatus,
        search: q
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
                if (row.status === 'approved' || row.status === 'present') sc = 'status-approved';
                if (row.status === 'rejected' || row.status === 'absent')  sc = 'status-rejected';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.employee_id     || '—'}</td>
                    <td>${row.employee_name  || '—'}</td>
                    <td>${row.department_name|| '—'}</td>
                    <td>${row.role_name      || '—'}</td>
                    <td>${parseDateString(row.request_date)}</td>
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

/* ── Range selection ────────────────────────────────────── */
function _selectRange(start, end, btnLabel, rangeLabel) {
    _selStart  = start;
    _selEnd    = end;
    _selBtnLbl = btnLabel;
    _selRngLbl = rangeLabel;

    hasSelectedPeriod = true;
    currentPageIndex  = 1;

    document.getElementById('ar-btn-label').textContent   = btnLabel;
    document.getElementById('ar-range-label').textContent = rangeLabel;

    document.querySelectorAll('.cutoff-item').forEach(el => {
        el.classList.toggle('active', el.dataset.start === start && el.dataset.end === end);
    });

    _dropObj.hide();
    fetchFilingReport();
}

function renderPaginationControls(totalPages) {
    _renderPaginationControls(totalPages, currentPageIndex, fetchFilingReport, n => { currentPageIndex = n; });
}

/* ── Search (debounced) ─────────────────────────────────── */
function handleSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPageIndex = 1;
        fetchFilingReport();
    }, 400);
}

/* ── Rows per page ──────────────────────────────────────── */
function changeRowsPerPage(val) {
    rowsPerPage = parseInt(val);
    localStorage.setItem('ar_rows_per_page', val);
    document.getElementById('rowsPerPageBtn').textContent = `${val} rows`;
    currentPageIndex = 1;
    fetchFilingReport();
}

/* ── Export ─────────────────────────────────────────────── */
function getExportData() {
    const q = (document.getElementById('search-input')?.value || '').trim();
    const params = new URLSearchParams({
        action: 'reports', start: _selStart, end: _selEnd,
        status: activeStatus, search: q, bypass_pagination: '1'
    });
    return fetch(`reports_api.php?${params.toString()}`).then(r => r.json());
}

function exportAllCSV() {
    getExportData().then(resData => {
        const dataRows = resData.data || [];
        if (!dataRows.length) { showToast('No records to export.', 'warning'); return; }

        const headers = ['Employee ID', 'Name', 'Department', 'Role', 'Request Date', 'Filing Type', 'Status'];
        const escCSV  = v => '"' + String(v || '').replace(/"/g,'""').replace(/\n/g,' ').trim() + '"';
        const rows    = dataRows.map(r => [
            escCSV(r.employee_id),
            escCSV(r.employee_name),
            escCSV(r.department_name),
            escCSV(r.role_name),
            escCSV(parseDateString(r.request_date)),
            escCSV(r.request_type ? r.request_type.toUpperCase().replace('_', ' ') : '—'),
            escCSV(r.status ? r.status.toUpperCase() : '—')
        ].join(','));

        const blob = new Blob(["﻿" + [headers.join(','), ...rows].join('\n')], { type: 'text/csv;charset=utf-8;' });
        const a    = Object.assign(document.createElement('a'), {
            href: URL.createObjectURL(blob),
            download: `filing_report_${_selStart}_to_${_selEnd}.csv`
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
    doc.text(`Filing Summary Report for ${fmtD(_selStart)} to ${fmtD(_selEnd)}`, 14, 12);

    const head = [['Employee ID', 'Name', 'Department', 'Role', 'Date of Request', 'Filing Type', 'Status']];
    const body = dataRows.map(r => [
        r.employee_id || '—',
        r.employee_name || '—',
        r.department_name || '—',
        r.role_name || '—',
        parseDateString(r.request_date),
        r.request_type ? r.request_type.toUpperCase().replace('_', ' ') : '—',
        r.status ? r.status.toUpperCase() : '—'
    ]);
    doc.autoTable({ head, body, startY: 20, styles: { fontSize: 9, cellPadding: 3 }, headStyles: { fillColor: [151, 190, 65] } });
    doc.save(`filing_report_${_selStart}_to_${_selEnd}.pdf`);
}

/* ── Flatpickr init ─────────────────────────────────────── */
let _fpOpen = false;
const _dropBtn  = document.getElementById('ar-cutoff-btn');
const _dropObj  = bootstrap.Dropdown.getOrCreateInstance(_dropBtn);
const _panel1   = document.getElementById('ar-panel-1');
const _panel2   = document.getElementById('ar-panel-2');
const _periodLi = document.getElementById('ar-period-list');

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
                _selectRange(item.dataset.start, item.dataset.end, item.dataset.btn, item.dataset.range);
            });
        });
    }
});

const _monthFp = flatpickr('#ar-month-fp-anchor', {
    plugins: [new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'F Y' })],
    disableMobile: true,
    positionElement: _dropBtn,
    onChange(selectedDates) {
        if (!selectedDates.length) return;
        const d = selectedDates[0], y = d.getFullYear(), mo = d.getMonth();
        const start = `${y}-${String(mo + 1).padStart(2,'0')}-01`;
        const end   = new Date(y, mo + 1, 0).toISOString().slice(0, 10);
        const mLbl  = d.toLocaleString('en-US', { month: 'long', year: 'numeric' });
        _selectRange(start, end, mLbl, 'Selected Month: ' + _fmtRange(start, end));
    }
});

/* ── DOM event listeners ────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('ar-btn-label').textContent   = _selBtnLbl;
    document.getElementById('ar-range-label').textContent = _selRngLbl;

    document.getElementById('rowsPerPageBtn').textContent = `${rowsPerPage} rows`;

    document.querySelectorAll('.row-limit-opt').forEach(opt => {
        opt.addEventListener('click', e => {
            e.preventDefault();
            changeRowsPerPage(opt.dataset.value);
        });
    });

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
    _dropBtn.addEventListener('hide.bs.dropdown', e => { if (_fpOpen) e.preventDefault(); });

    document.querySelectorAll('.cutoff-item').forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            _selectRange(item.dataset.start, item.dataset.end, item.dataset.btnLabel, item.dataset.rangeLabel);
        });
    });

    document.getElementById('ar-open-month-picker').addEventListener('click', e => {
        e.preventDefault();
        _dropObj.hide();
        setTimeout(() => _monthFp.open(), 50);
    });

    document.querySelectorAll('.status-opt').forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            activeStatus = item.dataset.value;
            document.getElementById('status-label').textContent = item.textContent.trim();
            hasSelectedStatus = true;
            currentPageIndex  = 1;
            fetchFilingReport();
        });
    });

    document.getElementById('page-jump-input')?.addEventListener('keydown', e => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        let target = parseInt(e.target.value);
        const max  = parseInt(e.target.max) || 1;
        if (isNaN(target) || target < 1) target = 1;
        if (target > max) target = max;
        e.target.value   = target;
        currentPageIndex = target;
        fetchFilingReport();
    });
});
</script>
</body>
</html>
