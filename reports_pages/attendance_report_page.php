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

// Ensure tables exist
$pdo->exec("CREATE TABLE IF NOT EXISTS `cutoffs` (
    `id`         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `start_date` date NOT NULL,
    `end_date`   date NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Pull cutoffs metadata for the menu panels
$cutoffs = $pdo->query("SELECT id, start_date, end_date FROM cutoffs ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

function co_label(array $c): string {
    return date('M j, Y', strtotime($c['start_date'])) . ' – ' . date('M j, Y', strtotime($c['end_date']));
}

// Find default landing range settings natively on load
$today = date('Y-m-d');
$currentCutoff = null;
foreach ($cutoffs as $c) {
    if ($today >= $c['start_date'] && $today <= $c['end_date']) {
        $currentCutoff = $c;
        break;
    }
}

$defaultStart = $currentCutoff['start_date'] ?? ($cutoffs[0]['start_date'] ?? null);
$defaultEnd   = $currentCutoff['end_date']   ?? ($cutoffs[0]['end_date']   ?? null);
$defaultLabel = $currentCutoff ? 'Current Cut-Off' : (!empty($cutoffs) ? 'Selected Cut-Off' : 'Select Period');
$defaultRange = $currentCutoff ? 'Current Cut-Off: '.co_label($currentCutoff) : (!empty($cutoffs) ? 'Selected Cut-Off: '.co_label($cutoffs[0]) : '');
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
    <link rel="stylesheet" href="attendance_report_page.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
</head>
<body>

<?php include '../sidebar_revised.php'; ?>

<div id="main-wrapper">
    <?php include '../topbar_revised.php'; ?>

    <div class="card card-neutral requests-card">
        <div class="card-header d-flex align-items-center gap-2 flex-wrap">

            <div class="input-group input-group-sm" style="max-width:220px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Search employee…" oninput="handleSearchInput()">
            </div>

            <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-funnel"></i>
                    <span id="statusLabel">All Status</span>
                </button>
                <ul class="dropdown-menu" style="z-index:1055;">
                    <li><a class="dropdown-item status-opt" href="#" data-value="ALL">All Status</a></li>
                    <li><a class="dropdown-item status-opt" href="#" data-value="present">Present</a></li>
                    <li><a class="dropdown-item status-opt" href="#" data-value="absent">Absent</a></li>
                    <li><a class="dropdown-item status-opt" href="#" data-value="incomplete">Incomplete</a></li>
                </ul>
            </div>

            <div class="dropdown">
                <button class="btn btn-success btn-sm dropdown-toggle" type="button" id="ar-cutoff-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bi bi-calendar3 me-1"></i>
                    <span id="ar-btn-label"><?= htmlspecialchars($defaultLabel) ?></span>
                </button>

                <ul class="dropdown-menu" style="min-width:280px; z-index:1055;">
                    <div id="ar-panel-1">
                        <?php 
                        $current_index = null;
                        $previous_index = null;

                        // 1. Identify which cut-off matches today
                        foreach ($cutoffs as $idx => $c) {
                            if ($today >= $c['start_date'] && $today <= $c['end_date']) {
                                $current_index = $idx;
                                break;
                            }
                        }

                        // 2. Determine assignments cleanly
                        if ($current_index !== null) {
                            $previous_index = isset($cutoffs[$current_index + 1]) ? $current_index + 1 : null;
                        } else {
                            $current_index = 0;
                            $previous_index = isset($cutoffs[1]) ? 1 : null;
                        }

                        // 3. Render Current Cut-Off Panel Link
                        if (isset($cutoffs[$current_index])): 
                            $cc = $cutoffs[$current_index];
                            $cc_label = co_label($cc);
                        ?>
                        <li>
                            <a class="dropdown-item cutoff-item active" href="#" 
                            data-start="<?= $cc['start_date'] ?>" 
                            data-end="<?= $cc['end_date'] ?>"
                            data-btn-label="Current Cut-Off"
                            data-range-label="Current Cut-Off: <?= htmlspecialchars($cc_label) ?>">
                                <div class="vstack gap-0">
                                    <span>Current Cut-Off</span>
                                    <small class="text-meta"><?= htmlspecialchars($cc_label) ?></small>
                                </div>
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- 4. Render Previous Cut-Off Panel Link -->
                        <?php if ($previous_index !== null && isset($cutoffs[$previous_index])): 
                            $pc = $cutoffs[$previous_index];
                            $pc_label = co_label($pc);
                        ?>
                        <li>
                            <a class="dropdown-item cutoff-item" href="#" 
                            data-start="<?= $pc['start_date'] ?>" 
                            data-end="<?= $pc['end_date'] ?>"
                            data-btn-label="Previous Cut-Off"
                            data-range-label="Previous Cut-Off: <?= htmlspecialchars($pc_label) ?>">
                                <div class="vstack gap-0">
                                    <span>Previous Cut-Off</span>
                                    <small class="text-meta"><?= htmlspecialchars($pc_label) ?></small>
                                </div>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- 5. Advanced Period Navigation Controls -->
                        <li>
                            <a class="dropdown-item d-flex align-items-center justify-content-between" href="#" id="ar-open-period-panel">
                                Select Cut-Off Period
                                <i class="bi bi-chevron-right small ms-3"></i>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="#" id="ar-open-month-picker">
                                <i class="bi bi-calendar3"></i> Select Full Month
                            </a>
                        </li>
                    </div>
                    <div id="ar-panel-2" style="display:none;">
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-1 text-muted" href="#" id="ar-back-btn">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </li>
                        <li><hr class="dropdown-divider mt-0"></li>
                        <li>
                            <label for="ar-period-fp" class="form-label text-meta ms-2 mb-2">Selected Month</label>
                            <div class="input-group px-2">
                                <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                                <input type="text" id="ar-period-fp" class="form-control" placeholder="Pick a month…" readonly>
                            </div>
                            <hr class="dropdown-divider my-3">
                        </li>
                        <div id="ar-period-list">
                            <p class="text-meta text-center px-3 py-2 mb-0">Pick a month above to see its cut-off periods.</p>
                        </div>
                    </div>
                </ul>
            </div>

            <span id="ar-range-label" class="text-tertiary small"><?= htmlspecialchars($defaultRange) ?></span>

            <input type="text" id="ar-month-fp-anchor" style="position:absolute;width:0;height:0;opacity:0;pointer-events:none;">

            <div class="ms-auto d-flex gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="z-index:1055;">
                        <li><a class="dropdown-item" href="#" onclick="exportAllCSV(); return false;"><i class="bi bi-filetype-csv me-2"></i> Export CSV</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportAllPDF(); return false;"><i class="bi bi-filetype-pdf me-2"></i> Export PDF</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card-body d-flex flex-column requests-card-body">
            <div id="ar-loading-state" class="ar-empty" style="display: none;">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="text-meta mt-2">Fetching report logs...</div>
            </div>

            <div id="ar-unloaded-state" class="ar-empty">
                <i class="bi bi-bar-chart-line-fill"></i>
                <div class="text-meta">No report loaded. Select an alternate period view up top.</div>
            </div>

            <div class="tableScroll" id="reportTableContainer" style="display:none">
                <table class="table table-hover mb-0" id="reportTable">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody"></tbody>
                </table>
            </div>


            <div id="filterEmptyState" class="ar-empty" style="display:none">
                <i class="bi bi-funnel-fill"></i>
                <div class="text-meta">No records match your filters.</div>
            </div>
        </div>

        <div class="card-footer py-2">
            <div id="paginationContainer" class="d-flex flex-sm-nowrap flex-wrap align-items-center justify-content-between gap-3 w-100" style="display: none !important;">
                
                <div id="paginationInfo" class="small text-meta text-nowrap flex-sm-fill w-sm-100 text-sm-start text-center order-1">
                    Showing 0 to 0 of 0 entries
                </div>

                <div class="d-flex align-items-center justify-content-center flex-wrap gap-3 flex-sm-fill w-sm-100 order-2">
                    <nav aria-label="Table Navigation">
                        <ul class="pagination pagination-sm mb-0" id="paginationList"></ul>
                    </nav>
                    
                    <div class="d-flex align-items-center gap-1" id="pageJumpWrapper">
                        <small class="text-meta text-nowrap">Go to:</small>
                        <input type="number" 
                            id="pageJumpInput" 
                            class="form-control form-control-sm text-center px-1" 
                            min="1" 
                            style="width: 45px; height: 28px;"
                            placeholder="Go">
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-sm-end justify-content-center gap-2 flex-sm-fill w-sm-100 order-3">
                    <small class="text-meta text-nowrap">Rows Per Page:</small>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                type="button"
                                id="rowsPerPageBtn"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">
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
/* ── Shared State Variables ────────────────────────────── */
const _allCutoffs = <?= json_encode(array_values($cutoffs)) ?>;
let _selStart     = <?= json_encode($defaultStart) ?>;
let _selEnd       = <?= json_encode($defaultEnd) ?>;
let _selBtnLbl    = <?= json_encode($defaultLabel) ?>;
let _selRngLbl    = <?= json_encode($defaultRange) ?>;
let activeStatus  = 'ALL';

// Server Pagination Rules
let currentPageIndex = 1;
let rowsPerPage = parseInt(localStorage.getItem('ar_rows_per_page')) || 10;
let searchTimeout = null;

const _dropBtn    = document.getElementById('ar-cutoff-btn');
const _dropObj    = bootstrap.Dropdown.getOrCreateInstance(_dropBtn);
const _panel1     = document.getElementById('ar-panel-1');
const _panel2     = document.getElementById('ar-panel-2');
const _periodLi   = document.getElementById('ar-period-list');

function _fmtRange(start, end) {
    const s = new Date(start + 'T00:00:00');
    const e = new Date(end   + 'T00:00:00');
    return s.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ' – ' +
           e.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/* ── UI Parsing Formatters ─────────────────────────────── */
function parseDateString(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function parseTimeString(timeStr) {
    if (!timeStr) return '—';
    const parts = timeStr.split(':');
    if(parts.length < 2) return '—';
    let hrs = parseInt(parts[0], 10);
    const mins = parts[1];
    const ampm = hrs >= 12 ? 'PM' : 'AM';
    hrs = hrs % 12;
    hrs = hrs ? hrs : 12;
    return `${hrs}:${mins} ${ampm}`;
}

/* ── Remote API Fetch Controller Logic ─────────────────── */
function fetchAttendanceReport() {
    if (!_selStart || !_selEnd) return;

    const unloadedState = document.getElementById('ar-unloaded-state');
    const loadingState  = document.getElementById('ar-loading-state');
    const tableContainer = document.getElementById('reportTableContainer');
    const emptyState    = document.getElementById('filterEmptyState');
    const pagContainer  = document.getElementById('paginationContainer');
    const q             = (document.getElementById('searchInput')?.value || '').trim();

    tableContainer.style.display = 'none';
    unloadedState.style.display  = 'none';
    emptyState.style.display     = 'none';
    pagContainer.setAttribute('style', 'display: none !important');
    loadingState.style.display   = 'flex';

    // Build Server Queries incorporating filtering parameters natively
    const params = new URLSearchParams({
        action: 'attendance',
        start: _selStart,
        end: _selEnd,
        page: currentPageIndex,
        limit: rowsPerPage,
        status: activeStatus,
        search: q
    });

    fetch(`reports_api.php?${params.toString()}`)
        .then(res => {
            if (res.status === 401) throw new Error("Unauthorized access. Please log back in.");
            return res.json();
        })
        .then(resData => {
            loadingState.style.display = 'none';
            const tbody = document.getElementById('reportTableBody');
            tbody.innerHTML = '';

            if (resData.error) {
                unloadedState.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i><div class="text-meta">${resData.error}</div>`;
                unloadedState.style.display = 'flex';
                return;
            }

            const dataRows = resData.data || [];
            const totalRecords = resData.total || 0;

            if (dataRows.length === 0) {
                emptyState.style.display = 'flex';
                return;
            }

            dataRows.forEach(row => {
                let statusClass = 'status-pending';
                if (row.status === 'present') statusClass = 'status-approved';
                if (row.status === 'absent')  statusClass = 'status-rejected';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.employee_id || '—'}</td>
                    <td>${row.employee_name || '—'}</td>
                    <td>${row.department_name || '—'}</td>
                    <td>${row.role_name || '—'}</td>
                    <td>${parseDateString(row.work_date)}</td>
                    <td>${parseTimeString(row.actual_time_in)}</td>
                    <td>${parseTimeString(row.actual_time_out)}</td>
                    <td><span class="pill ${statusClass}">${row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—'}</span></td>
                `;
                tbody.appendChild(tr);
            });

            tableContainer.style.display = 'block';
            
            // Handle Pagination Info Calculations Safely
            const startEntry = (currentPageIndex - 1) * rowsPerPage + 1;
            const endEntry = Math.min(startEntry + rowsPerPage - 1, totalRecords);
            document.getElementById('paginationInfo').textContent = `Showing ${startEntry} to ${endEntry} of ${totalRecords} entries`;
            
            const totalPages = Math.ceil(totalRecords / rowsPerPage);
            renderPaginationControls(totalPages);
            pagContainer.setAttribute('style', 'display: flex !important');
        })
        .catch(err => {
            console.error(err);
            loadingState.style.display = 'none';
            unloadedState.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i><div class="text-meta">${err.message || 'An error occurred while tracking server tables data.'}</div>`;
            unloadedState.style.display = 'flex';
        });
}

function _selectRange(start, end, btnLabel, rangeLabel) {
    _selStart  = start;
    _selEnd    = end;
    _selBtnLbl = btnLabel;
    _selRngLbl = rangeLabel;
    currentPageIndex = 1; 

    document.getElementById('ar-btn-label').textContent   = btnLabel;
    document.getElementById('ar-range-label').textContent = rangeLabel;

    document.querySelectorAll('.cutoff-item').forEach(el => el.classList.remove('active'));
    _dropObj.hide();

    fetchAttendanceReport();
}

/* ── UI Panel Component Handlers ───────────────────────── */
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

// Monitor list options tracking for current vs previous switches
document.querySelectorAll('.cutoff-item').forEach(item => {
    item.addEventListener('click', e => {
        e.preventDefault();
        
        // Remove highlighting from both option links
        document.querySelectorAll('.cutoff-item').forEach(el => el.classList.remove('active'));
        
        // Push dates into query parameters
        _selectRange(
            item.dataset.start, 
            item.dataset.end, 
            item.dataset.btnLabel, 
            item.dataset.rangeLabel
        );
        
        // Set highlighting on clicked element
        item.classList.add('active');
    });
});

/* ── Flatpickr Initialization Setup ───────────────────── */
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
            return `<a href="#" class="dropdown-item rw-period-item" data-start="${c.start_date}" data-end="${c.end_date}" data-btn="Selected Cut-Off" data-range="Selected Cut-Off: ${rLabel}">${rLabel}</a>`;
        }).join('');

        _periodLi.querySelectorAll('.rw-period-item').forEach(item => {
            item.addEventListener('click', e => {
                e.preventDefault();
                _selectRange(item.dataset.start, item.dataset.end, item.dataset.btn, item.dataset.range);
            });
        });
    }
});

_dropBtn.addEventListener('hide.bs.dropdown', e => { if (_fpOpen) e.preventDefault(); });

/* ── Flatpickr Initialization Setup ───────────────────── */

const _monthFp = flatpickr('#ar-month-fp-anchor', {
    plugins: [new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'F Y' })],
    disableMobile: true,
    // Crucial fix: Forces the calendar popover to position itself relative to your visible dropdown button
    positionElement: document.getElementById('ar-cutoff-btn'), 
    onChange(selectedDates) {
        if (!selectedDates.length) return;
        const d = selectedDates[0], y = d.getFullYear(), mo = d.getMonth();
        const start = `${y}-${String(mo + 1).padStart(2,'0')}-01`;
        const end   = new Date(y, mo + 1, 0).toISOString().slice(0, 10);
        _selectRange(start, end, d.toLocaleString('en-US', { month: 'long', year: 'numeric' }), 'Selected Month: ' + _fmtRange(start, end));
    }
});

document.getElementById('ar-open-month-picker').addEventListener('click', e => {
    e.preventDefault();
    _dropObj.hide();
    setTimeout(() => _monthFp.open(), 50);
});

/* ── Live Server Filtering Engine Event Listeners ─────────── */
document.querySelectorAll('.status-opt').forEach(item => {
    item.addEventListener('click', e => {
        e.preventDefault();
        activeStatus = item.dataset.value;
        document.getElementById('statusLabel').textContent = item.textContent.trim();
        currentPageIndex = 1; 
        fetchAttendanceReport();
    });
});

// Debounced live string monitoring pipeline 
function handleSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPageIndex = 1;
        fetchAttendanceReport();
    }, 400);
}

// Update the row display value and trigger a fresh database fetch
function changeRowsPerPage(val) {
    rowsPerPage = parseInt(val);
    localStorage.setItem('ar_rows_per_page', val);
    
    const btn = document.getElementById('rowsPerPageBtn');
    if (btn) {
        btn.textContent = `${val} rows`;
    }
    
    currentPageIndex = 1;
    fetchAttendanceReport();
}

function renderPaginationControls(totalPages) {
    const list = document.getElementById('paginationList');
    const jumpInput = document.getElementById('pageJumpInput');
    const jumpWrapper = document.getElementById('pageJumpWrapper');
    
    list.innerHTML = '';

    if (totalPages <= 1) {
        if (jumpWrapper) jumpWrapper.style.setProperty('display', 'none', 'important');
        return;
    }
    
    // Ensure the jump control element layout block is visible if there are multiple pages
    if (jumpWrapper) jumpWrapper.setAttribute('style', 'display: flex !important;');

    // Configure input boundaries dynamically based on server returns
    if (jumpInput) {
        jumpInput.max = totalPages;
        jumpInput.value = currentPageIndex;
    }

    // ── PREVIOUS BUTTON ──────────────────────────────────────────────
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${currentPageIndex === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#" aria-label="Previous">&laquo;</a>`;
    if (currentPageIndex > 1) {
        prevLi.addEventListener('click', e => {
            e.preventDefault();
            currentPageIndex--;
            fetchAttendanceReport();
        });
    }
    list.appendChild(prevLi);

    // ── SLIDING WINDOW COMPUTATION ───────────────────────────────────
    const maxVisibleButtons = 5;
    let startPage = Math.max(1, currentPageIndex - 2);
    let endPage = Math.min(totalPages, currentPageIndex + 2);

    if (currentPageIndex <= 3) {
        endPage = Math.min(totalPages, maxVisibleButtons);
    }
    if (currentPageIndex > totalPages - 3) {
        startPage = Math.max(1, totalPages - maxVisibleButtons + 1);
    }

    if (startPage > 1) {
        appendPageItem(1);
        if (startPage > 2) appendEllipsis();
    }

    for (let i = startPage; i <= endPage; i++) {
        appendPageItem(i);
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) appendEllipsis();
        appendPageItem(totalPages);
    }

    // ── NEXT BUTTON ──────────────────────────────────────────────────
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${currentPageIndex === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#" aria-label="Next">&raquo;</a>`;
    if (currentPageIndex < totalPages) {
        nextLi.addEventListener('click', e => {
            e.preventDefault();
            currentPageIndex++;
            fetchAttendanceReport();
        });
    }
    list.appendChild(nextLi);

    // Inner loop append utility structures
    function appendPageItem(pageNo) {
        const li = document.createElement('li');
        li.className = `page-item ${currentPageIndex === pageNo ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#">${pageNo}</a>`;
        li.addEventListener('click', e => {
            e.preventDefault();
            currentPageIndex = pageNo;
            fetchAttendanceReport();
        });
        list.appendChild(li);
    }

    function appendEllipsis() {
        const li = document.createElement('li');
        li.className = 'page-item disabled';
        li.innerHTML = `<span class="page-link text-meta">...</span>`;
        list.appendChild(li);
    }
}

// ── GLOBAL REGISTRATION EVENT ON INITIAL LOAD ────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Listen for the 'Enter' key inside the page jump field input frame
    const jumpInput = document.getElementById('pageJumpInput');
    if (jumpInput) {
        jumpInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let targetPage = parseInt(this.value);
                const maxPage = parseInt(this.max) || 1;

                // Handle out-of-bounds inputs cleanly
                if (isNaN(targetPage) || targetPage < 1) targetPage = 1;
                if (targetPage > maxPage) targetPage = maxPage;

                this.value = targetPage;
                currentPageIndex = targetPage;
                fetchAttendanceReport();
            }
        });
    }
});

/* ── Global Server Export Infrastructure Pipeline ───────── */
function getExportData() {
    const q = (document.getElementById('searchInput')?.value || '').trim();
    const params = new URLSearchParams({
        action: 'attendance',
        start: _selStart,
        end: _selEnd,
        status: activeStatus,
        search: q,
        bypass_pagination: '1'
    });
    return fetch(`reports_api.php?${params.toString()}`).then(res => res.json());
}

function exportAllCSV() {
    getExportData().then(resData => {
        const dataRows = resData.data || [];
        if (dataRows.length === 0) {
            if (typeof showToast === 'function') showToast('No matching records found to export.', 'warning');
            return;
        }

        const headers = ['Employee ID','Name','Department','Role','Date','Time In','Time Out','Status'];
        const escapeCSV = v => '"' + String(v || '').replace(/"/g,'""').replace(/\n/g,' ').trim() + '"';
        
        const rows = dataRows.map(row => [
            escapeCSV(row.employee_id),
            escapeCSV(row.employee_name),
            escapeCSV(row.department_name),
            escapeCSV(row.role_name),
            escapeCSV(parseDateString(row.work_date)),
            escapeCSV(parseTimeString(row.actual_time_in)),
            escapeCSV(parseTimeString(row.actual_time_out)),
            escapeCSV(row.status)
        ].join(','));

        const csv  = [headers.join(','), ...rows].join('\n');
        const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
        const a    = document.createElement('a');
        a.href     = URL.createObjectURL(blob);
        a.download = `attendance_report_${_selStart}_to_${_selEnd}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });
}

function exportAllPDF() {
    getExportData().then(resData => {
        const dataRows = resData.data || [];
        if (dataRows.length === 0) {
            if (typeof showToast === 'function') showToast('No records found to export.', 'warning');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');

        const headers = [['Employee ID','Name','Department','Role','Date','Time In','Time Out','Status']];
        const rows = dataRows.map(row => [
            row.employee_id || '—',
            row.employee_name || '—',
            row.department_name || '—',
            row.role_name || '—',
            parseDateString(row.work_date),
            parseTimeString(row.actual_time_in),
            parseTimeString(row.actual_time_out),
            row.status ? row.status.toUpperCase() : '—'
        ]);

        doc.setFontSize(14);
        doc.text(`Attendance Report (${_selStart} – ${_selEnd})`, 14, 15);
        doc.autoTable({
            head: headers, body: rows, startY: 22,
            styles: { fontSize: 9, cellPadding: 3 },
            headStyles: { fillColor: [30, 144, 255] }
        });
        doc.save(`attendance_report_${_selStart}_to_${_selEnd}.pdf`);
    });
}

// Bind click events to the new Bootstrap dropdown items
document.addEventListener('DOMContentLoaded', () => {
    // Sync the initial button label with the cached value from localStorage
    const btn = document.getElementById('rowsPerPageBtn');
    if (btn) {
        btn.textContent = `${rowsPerPage} rows`;
    }

    // Attach click events to the dropdown items dynamically
    document.body.addEventListener('click', function(e) {
        const target = e.target.closest('.row-limit-opt');
        if (target) {
            e.preventDefault();
            const val = target.dataset.value;
            changeRowsPerPage(val);
        }
    });

    fetchAttendanceReport();
});
</script>
</body>
</html>