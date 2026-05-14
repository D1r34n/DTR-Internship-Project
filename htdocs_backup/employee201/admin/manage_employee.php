<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require '../includes/log.php';
require_once(__DIR__ . '/../auth/session_check.php');
include '../includes/header.php';
include '../includes/sidebar.php';

// Fetch ALL employees (filtering/sorting/paging done client-side)
$stmt = $conn->prepare("
    SELECT id, employee_code, first_name, middle_name, last_name, department, employment_status, date_of_separation
    FROM employees 
    ORDER BY id DESC
");
$stmt->execute();
$result = $stmt->get_result();

// Log admin viewing employee list
logAction($conn, $_SESSION['admin_id'], "Viewed employee list");

function esc($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Build JS data array
$employees = [];
while ($row = $result->fetch_assoc()) {
    $status = strtolower($row['employment_status'] ?? '');
    $sepDate = in_array($status, ['resigned', 'retired', 'fired'])
        ? ($row['date_of_separation'] ?? '-/-/-')
        : '-/-/-';
    $employees[] = [
        'id'         => (int)$row['id'],
        'code'       => $row['employee_code'] ?? '',
        'name'       => trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
        'department' => $row['department'] ?? '',
        'status'     => $row['employment_status'] ?? '',
        'sepDate'    => $sepDate,
    ];
}
$stmt->close();
?>

<style>
<?php include '../assets/css/style.css'; ?>

.main-content {
    margin-left: 220px;
    padding: 20px;
    width: calc(100% - 260px);
    transition: margin-left 0.3s ease, width 0.3s ease;
}
.sidebar.collapsed + .main-content {
    margin-left: 80px;
    width: calc(100% - 80px);
}
.main-content .container {
    max-width: 100%;
    width: 100%;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-header h1 {
    font-weight: bold;
    font-size: 1.8rem;
    color: #fff;
}

/* ── Search bar ── */
.search-bar {
    display: flex;
    gap: 10px;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.search-bar input[type="text"] {
    padding: 10px 14px;
    width: 320px;
    border: 1px solid #3b3b4a;
    border-radius: 8px;
    background-color: #232330;
    color: #e5e7eb;
    outline: none;
    font-size: 0.95rem;
    transition: border-color 0.25s;
}
.search-bar input[type="text"]:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 6px rgba(59,130,246,0.4);
}
.search-label {
    color: #9ca3af;
    font-size: 0.88rem;
    white-space: nowrap;
}

/* ── Per-page selector ── */
.per-page-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #9ca3af;
    font-size: 0.88rem;
}
.per-page-wrap select {
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #3b3b4a;
    background: #232330;
    color: #e5e7eb;
    cursor: pointer;
}

/* ── Table ── */
.employee-table {
    width: 100%;
    border-collapse: collapse;
    background-color: #232330;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    animation: fadeIn 0.4s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-16px); }
    to   { opacity: 1; transform: translateY(0); }
}
.employee-table th,
.employee-table td {
    padding: 13px 16px;
    text-align: left;
    border-bottom: 1px solid #3b3b4a;
    font-size: 0.92rem;
}
.employee-table th {
    background-color: #3b3b4a;
    color: #f9fafb;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
}
.employee-table th:hover {
    background-color: #4b4b5a;
}
.employee-table th .sort-icon {
    margin-left: 6px;
    opacity: 0.5;
    font-size: 0.75rem;
}
.employee-table th.sorted-asc .sort-icon::after  { content: '▲'; opacity: 1; }
.employee-table th.sorted-desc .sort-icon::after { content: '▼'; opacity: 1; }
.employee-table th:not(.sorted-asc):not(.sorted-desc) .sort-icon::after { content: '⇅'; }

.employee-table tbody tr:hover td {
    background-color: rgba(50, 127, 252, 0.22);
    color: #ffffff;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

/* ── Pagination ── */
.pagination-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 16px;
    color: #9ca3af;
    font-size: 0.88rem;
    flex-wrap: wrap;
    gap: 10px;
}
.pagination-btns {
    display: flex;
    gap: 6px;
}
.pagination-btns button {
    padding: 6px 14px;
    border-radius: 6px;
    border: 1px solid #3b3b4a;
    background: #2d2d3d;
    color: #e5e7eb;
    cursor: pointer;
    font-size: 0.85rem;
    transition: background 0.2s;
}
.pagination-btns button:hover:not(:disabled) {
    background: #3b82f6;
    border-color: #3b82f6;
    color: #fff;
}
.pagination-btns button:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
.pagination-btns button.active {
    background: linear-gradient(90deg,#7c3aed,#3b82f6);
    border-color: transparent;
    color: #fff;
    font-weight: 600;
}

/* ── No results ── */
.no-results {
    padding: 24px;
    text-align: center;
    background: #3b3b4a;
    color: #f9fafb;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 15px;
}
</style>

<div class="main-content">
    <div class="header-container">
        <h1><i class="fas fa-users"></i> Manage Employees</h1>
    </div>

    <div class="content-container">

        <!-- Controls row -->
        <div class="search-bar">
            <div style="display:flex;align-items:center;gap:10px;">
                <input type="text" id="liveSearch" placeholder="&#xf002;  Search employees…" autocomplete="off">
                <span class="search-label" id="matchCount"></span>
            </div>
            <div class="per-page-wrap">
                Show
                <select id="perPageSelect">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                per page
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <table class="employee-table" id="empTable">
                <thead>
                    <tr>
                        <th data-col="0">Employee ID<span class="sort-icon"></span></th>
                        <th data-col="1">Name<span class="sort-icon"></span></th>
                        <th data-col="2">Department<span class="sort-icon"></span></th>
                        <th data-col="3">Employment Status<span class="sort-icon"></span></th>
                        <th data-col="4">Separation Date<span class="sort-icon"></span></th>
                    </tr>
                </thead>
                <tbody id="empBody">
                    <?php foreach ($employees as $emp): ?>
                    <tr onclick="location.href='view_employee.php?id=<?= $emp['id'] ?>'"
                        data-search="<?= esc(strtolower($emp['code'] . ' ' . $emp['name'] . ' ' . $emp['department'] . ' ' . $emp['status'])) ?>">
                        <td><?= esc($emp['code']) ?></td>
                        <td><?= esc($emp['name']) ?></td>
                        <td><?= esc($emp['department']) ?></td>
                        <td><?= esc($emp['status']) ?></td>
                        <td><?= esc($emp['sepDate']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="no-results" id="noResults" style="display:none;">No employees found.</div>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrap">
            <span id="pageInfo">Showing 0 – 0 of 0 employees</span>
            <div class="pagination-btns" id="paginationBtns"></div>
        </div>

    </div><!-- /.content-container -->
</div><!-- /.main-content -->

<script>
(function () {

    const searchInput  = document.getElementById('liveSearch');
    const perPageSel   = document.getElementById('perPageSelect');
    const tbody        = document.getElementById('empBody');
    const noResults    = document.getElementById('noResults');
    const pageInfo     = document.getElementById('pageInfo');
    const paginBtns    = document.getElementById('paginationBtns');
    const matchCount   = document.getElementById('matchCount');
    const headers      = document.querySelectorAll('#empTable thead th');

    const allRows = Array.from(tbody.querySelectorAll('tr'));

    let filteredRows = [...allRows];
    let currentPage  = 1;
    let perPage      = parseInt(perPageSel.value);
    let sortCol      = -1;
    let sortAsc      = true;

    /* ───────────── HELPERS ───────────── */

    function cellText(row, col) {
        return row.children[col]?.textContent.trim() ?? '';
    }

    function parseDate(value) {
        if (!value || value === '-/-/-') return null;
        const d = new Date(value);
        return isNaN(d.getTime()) ? null : d.getTime();
    }

    /* ───────────── FILTER ───────────── */

    function applyFilter() {
        const q = searchInput.value.trim().toLowerCase();

        filteredRows = allRows.filter(r =>
            !q || r.dataset.search.includes(q)
        );

        currentPage = 1;
        render();
    }

    /* ───────────── SORT ───────────── */

    headers.forEach(th => {
        th.addEventListener('click', () => {

            const col = parseInt(th.dataset.col);

            if (sortCol === col) {
                sortAsc = !sortAsc;
            } else {
                sortCol = col;
                sortAsc = true;
            }

            headers.forEach(h => h.classList.remove('sorted-asc','sorted-desc'));
            th.classList.add(sortAsc ? 'sorted-asc' : 'sorted-desc');

            filteredRows.sort((a, b) => {

                const va = cellText(a, col);
                const vb = cellText(b, col);

                // Employee ID (natural sort)
                if (col === 0) {
                    return sortAsc
                        ? va.localeCompare(vb, undefined, { numeric:true })
                        : vb.localeCompare(va, undefined, { numeric:true });
                }

                // Separation Date (date sort)
                if (col === 4) {
                    const da = parseDate(va);
                    const db = parseDate(vb);

                    if (da === null && db === null) return 0;
                    if (da === null) return 1;
                    if (db === null) return -1;

                    return sortAsc ? da - db : db - da;
                }

                // Default text sort
                return sortAsc
                    ? va.localeCompare(vb, undefined, { sensitivity:'base' })
                    : vb.localeCompare(va, undefined, { sensitivity:'base' });
            });

            currentPage = 1;
            render();
        });
    });

    /* ───────────── RENDER ───────────── */

    function render() {

        perPage = parseInt(perPageSel.value);

        const total = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(total / perPage));

        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * perPage;
        const end   = Math.min(start + perPage, total);

        // 🔥 IMPORTANT FIX: Clear tbody and re-append rows in sorted order
        tbody.innerHTML = '';

        filteredRows.slice(start, end).forEach(row => {
            tbody.appendChild(row);
        });

        noResults.style.display = total === 0 ? 'block' : 'none';
        document.getElementById('empTable').style.display = total === 0 ? 'none' : '';

        matchCount.textContent = total < allRows.length
            ? `(${total} match${total !== 1 ? 'es' : ''})`
            : '';

        pageInfo.textContent = total === 0
            ? 'No employees found'
            : `Showing ${start + 1}–${end} of ${total} employee${total !== 1 ? 's' : ''}`;

        buildPagination(totalPages);
    }

    function buildPagination(totalPages) {

        paginBtns.innerHTML = '';

        const btn = (label, page, disabled=false, active=false) => {
            const b = document.createElement('button');
            b.textContent = label;
            if (disabled) b.disabled = true;
            if (active) b.classList.add('active');
            b.onclick = () => {
                currentPage = page;
                render();
            };
            return b;
        };

        paginBtns.appendChild(btn('‹ Prev', currentPage - 1, currentPage === 1));

        for (let i = 1; i <= totalPages; i++) {
            paginBtns.appendChild(btn(i, i, false, i === currentPage));
        }

        paginBtns.appendChild(btn('Next ›', currentPage + 1, currentPage === totalPages));
    }

    /* ───────────── EVENTS ───────────── */

    let debounce;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(applyFilter, 200);
    });

    perPageSel.addEventListener('change', () => {
        currentPage = 1;
        render();
    });

    render();

})();
</script>

<?php include '../includes/footer.php'; ?> 