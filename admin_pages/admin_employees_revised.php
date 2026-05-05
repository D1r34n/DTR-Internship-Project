<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$success = "";

// ---- HANDLE DELETE ----
if (isset($_GET['delete'])) {
    $employeeId = $_GET['delete'];
    $pdo->prepare("DELETE FROM logs WHERE employee_id = ?")->execute([$employeeId]);
    $pdo->prepare("DELETE FROM schedules WHERE employee_id = ?")->execute([$employeeId]);
    $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$employeeId]);
    $success = "Employee deleted successfully!";
}

// ---- HANDLE ADD / EDIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']);
    $email      = trim($_POST['email']);
    $password   = trim($_POST['password']);
    $role       = $_POST['role'];
    $department = !empty($_POST['department_id']) ? $_POST['department_id'] : null;

    if (!empty($_POST['employee_id'])) {
        if (!empty($password)) {
            $pdo->prepare("UPDATE employees SET name=?, email=?, password=?, role=?, department_id=? WHERE id=?")
                ->execute([$name, $email, $password, $role, $department, $_POST['employee_id']]);
        } else {
            $pdo->prepare("UPDATE employees SET name=?, email=?, role=?, department_id=? WHERE id=?")
                ->execute([$name, $email, $role, $department, $_POST['employee_id']]);
        }
        $success = "Employee updated successfully!";
    } else {
        $pdo->prepare("INSERT INTO employees (name, email, password, role, department_id) VALUES (?, ?, ?, ?, ?)")
            ->execute([$name, $email, $password, $role, $department]);
        $success = "Employee added successfully!";
    }
}

// ---- GET ALL EMPLOYEES ----
$employees = $pdo->query("
    SELECT e.*, d.department_name, d.department_code
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY e.name
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Management</title>

    <!-- 1. Bootstrap FIRST -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- 2. Your global CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <!-- 3. Page-specific CSS -->
    <link rel="stylesheet" href="admin_employees_revised.css">

    <!-- 4. Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
</head>
<body>

    <?php $currentPage = 'employees'; include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">

        <?php include '../topbar_revised.php'; ?>

        <div class="card card-glass logs-card">
            <div class="card-body d-flex flex-column logs-card-body">

                <!-- Filter Section -->
                <div class="filter-wrapper">
                    <span class="employee-title text-secondary">
                        <i class="bi bi-people-fill"></i>
                        Total Employees: <span id="empCount"><?= count($employees) ?></span>
                    </span>
                    <div class="d-flex gap-2 align-items-center ms-auto flex-wrap">

                        <!-- Role filter -->
                        <div class="dropdown">
                            <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span id="roleBtnLabel">All Roles</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button class="dropdown-item" type="button" onclick="selectFilter('role','','All Roles')">All Roles</button></li>
                                <li><button class="dropdown-item" type="button" onclick="selectFilter('role','employee','Employee')">Employee</button></li>
                                <li><button class="dropdown-item" type="button" onclick="selectFilter('role','workforce','Workforce')">Workforce</button></li>
                                <li><button class="dropdown-item" type="button" onclick="selectFilter('role','admin','Admin')">Admin</button></li>
                            </ul>
                        </div>
                        <input type="hidden" id="roleFilter" value="">

                        <!-- Dept filter -->
                        <div class="dropdown">
                            <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span id="deptBtnLabel">All Depts</span>
                            </button>
                            <ul class="dropdown-menu" id="filterDeptMenu">
                                <li><button class="dropdown-item" type="button" onclick="selectFilter('dept','','All Depts')">All Depts</button></li>
                            </ul>
                        </div>
                        <input type="hidden" id="deptFilter" value="">

                        <!-- Search -->
                        <div class="input-group input-group-sm" style="max-width: 220px;">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>
                            <input 
                                type="text" 
                                id="empSearch" 
                                class="form-control" 
                                placeholder="Search..."
                                oninput="applyFilters()">
                        </div>

                        <button class="empAddBtn" onclick="openAddModal()" title="Add Employee">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="empAlert"><?= $success ?></div>
                <?php endif; ?>

                <!-- Table Header -->
                <div class="tableHeaderGlass">
                    <table class="table table-borderless mb-0">
                        <colgroup>
                            <col style="width:8%">
                            <col style="width:22%">
                            <col style="width:26%">
                            <col style="width:12%">
                            <col style="width:20%">
                            <col style="width:12%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="sortable" onclick="sortBy('id')">ID <i class="bi bi-arrow-down-up sortIcon" id="sort-id"></i></th>
                                <th class="sortable" onclick="sortBy('name')">Name <i class="bi bi-arrow-down-up sortIcon" id="sort-name"></i></th>
                                <th class="sortable" onclick="sortBy('email')">Email <i class="bi bi-arrow-down-up sortIcon" id="sort-email"></i></th>
                                <th class="sortable" onclick="sortBy('role')">Role <i class="bi bi-arrow-down-up sortIcon" id="sort-role"></i></th>
                                <th class="sortable" onclick="sortBy('deptName')">Department <i class="bi bi-arrow-down-up sortIcon" id="sort-deptName"></i></th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>

                <!-- Scrollable Body -->
                <div class="tableScroll">
                    <table class="table table-hover mb-0">
                        <colgroup>
                            <col style="width:8%">
                            <col style="width:22%">
                            <col style="width:26%">
                            <col style="width:12%">
                            <col style="width:20%">
                            <col style="width:12%">
                        </colgroup>
                        <tbody id="empList">
                            <?php foreach ($employees as $emp): ?>
                                <tr class="empRow"
                                    data-id="<?= $emp['id'] ?>"
                                    data-name="<?= htmlspecialchars($emp['name']) ?>"
                                    data-email="<?= htmlspecialchars($emp['email']) ?>"
                                    data-role="<?= $emp['role'] ?>"
                                    data-dept="<?= htmlspecialchars($emp['department_id'] ?? '') ?>"
                                    data-dept-name="<?= htmlspecialchars($emp['department_name'] ?? '') ?>">

                                    <td><?= $emp['id'] ?></td>
                                    <td><?= htmlspecialchars($emp['name']) ?></td>
                                    <td><?= htmlspecialchars($emp['email']) ?></td>
                                    <td>
                                        <span class="empRoleBadge empRole-<?= $emp['role'] ?>">
                                            <?= ucfirst($emp['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($emp['department_name'] ?? 'No Department') ?></td>
                                    <td class="text-end" onclick="event.stopPropagation()">
                                        <button class="empActionBtn empEditBtn"
                                                onclick="openEditModal(this.closest('tr'))"
                                                title="Edit">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <a class="empActionBtn empDeleteBtn"
                                           href="admin_employees.php?delete=<?= $emp['id'] ?>"
                                           onclick="return confirm('Delete <?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>?')"
                                           title="Delete">
                                            <i class="bi bi-trash-fill"></i>
                                        </a>
                                    </td>

                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($employees)): ?>
                                <tr class="emptyRow">
                                    <td colspan="6">
                                        <div class="logsEmpty">
                                            <i class="bi bi-people logsEmptyIcon"></i>
                                            No employees found.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="empPagination" class="empPagination"></div>

            </div>
        </div>

        <!-- Gantt Tooltip -->
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
                <span class="ganttToolTipLabel">Overtime</span>
                <span class="ganttToolTipValue" id="gt-ot"></span>
            </div>
            <div class="ganttToolTipRow ganttToolTipUnderTime" id="gt-ut-row">
                <span class="ganttToolTipLabel">Undertime</span>
                <span class="ganttToolTipValue" id="gt-ut"></span>
            </div>
        </div>

        <!-- Add / Edit Modal -->
        <div class="empModalOverlay" id="empModalOverlay" style="display:none;" onclick="closeModal(event)">
            <div class="empModal">
                <div class="empModalHeader">
                    <h6 id="empModalTitle">Add Employee</h6>
                    <button onclick="closeModalBtn()"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="empModalBody">
                    <form method="POST" action="admin_employees.php">
                        <input type="hidden" name="employee_id" id="modalEmpId">

                        <div class="formGrid">
                            <div class="formGroup">
                                <label>Name</label>
                                <input type="text" name="name" id="modalName" class="formControl" required>
                            </div>
                            <div class="formGroup">
                                <label>Email</label>
                                <input type="email" name="email" id="modalEmail" class="formControl" required>
                            </div>
                            <div class="formGroup">
                                <label id="modalPwdLabel">Password</label>
                                <input type="password" name="password" id="modalPassword" class="formControl">
                            </div>
                            <div class="formGroup">
                                <label>Role</label>
                                <div class="customSelectWrapper">
                                    <div class="customSelectToggle" onclick="toggleModalDropdown('roleDropdown')">
                                        <span id="roleLabel">Employee</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </div>
                                    <div class="customSelectMenu" id="roleDropdown">
                                        <div class="customSelectItem" onclick="selectRole('employee','Employee')">Employee</div>
                                        <div class="customSelectItem" onclick="selectRole('workforce','Workforce')">Workforce</div>
                                        <div class="customSelectItem" onclick="selectRole('admin','Admin')">Admin</div>
                                    </div>
                                </div>
                                <input type="hidden" name="role" id="roleInput" value="employee">
                            </div>
                            <div class="formGroup">
                                <label>Department</label>
                                <div class="customSelectWrapper">
                                    <div class="customSelectToggle" onclick="toggleModalDropdown('deptDropdown')">
                                        <span id="deptLabel">Select Department</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </div>
                                    <div class="customSelectMenu" id="deptDropdown">
                                        <div class="customSelectItem" onclick="selectDept('','Select Department')">None</div>

                                    </div>
                                </div>
                                <input type="hidden" name="department_id" id="deptInput" value="">
                            </div>
                        </div>

                        <div class="formActions">
                            <button type="submit" class="btnSave" id="modalSubmitBtn">
                                <i class="bi bi-check-circle-fill"></i> Save Employee
                            </button>
                            <button type="button" class="btnCancel" onclick="closeModalBtn()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- #main-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../system_functions/gantt.js"></script>
    <script>

        let currentEmployeeId = null;
        let datePicker        = null;
        let currentStart      = '<?= date('Y-m-01') ?>';
        let currentEnd        = '<?= date('Y-m-t') ?>';

        // ---- TABLE STATE ----
        const ROWS_PER_PAGE = 10;
        let allRows     = [];
        let sortCol     = null;
        let sortDir     = 1;
        let currentPage = 1;
        let lastTotal   = 0;

        document.addEventListener('DOMContentLoaded', () => {
            allRows = Array.from(document.querySelectorAll('#empList .empRow'));
            applyFilters();
        });

        // ---- FILTER DROPDOWN SELECTION ----
        function selectFilter(type, value, label) {
            if (type === 'role') {
                document.getElementById('roleFilter').value  = value;
                document.getElementById('roleBtnLabel').textContent = label;
            } else {
                document.getElementById('deptFilter').value  = value;
                document.getElementById('deptBtnLabel').textContent = label;
            }
            currentPage = 1;
            applyFilters();
        }

        // ---- FILTER + SORT + PAGINATE ----
        function applyFilters() {
            const q    = document.getElementById('empSearch').value.toLowerCase().trim();
            const role = document.getElementById('roleFilter').value;
            const dept = document.getElementById('deptFilter').value;

            let filtered = allRows.filter(row => {
                const matchSearch = !q
                    || row.dataset.name.toLowerCase().includes(q)
                    || row.dataset.email.toLowerCase().includes(q);
                const matchRole = !role || row.dataset.role === role;
                const matchDept = !dept || row.dataset.dept === dept;
                return matchSearch && matchRole && matchDept;
            });

            if (sortCol) {
                filtered.sort((a, b) => {
                    if (sortCol === 'id') {
                        return sortDir * (parseInt(a.dataset.id) - parseInt(b.dataset.id));
                    }
                    const av = (a.dataset[sortCol] || '').toLowerCase();
                    const bv = (b.dataset[sortCol] || '').toLowerCase();
                    return sortDir * av.localeCompare(bv);
                });
            }

            const total = filtered.length;
            lastTotal = total;
            const totalPages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
            if (currentPage > totalPages) currentPage = 1;

            const start = (currentPage - 1) * ROWS_PER_PAGE;
            const pageSet = new Set(filtered.slice(start, start + ROWS_PER_PAGE));

            allRows.forEach(r => r.style.display = pageSet.has(r) ? '' : 'none');

            const emptyRow = document.querySelector('#empList .emptyRow');
            if (emptyRow) emptyRow.style.display = total === 0 ? '' : 'none';

            document.getElementById('empCount').textContent = total;
            renderPagination(total, totalPages, start);
        }

        // ---- SORT ----
        function sortBy(col) {
            if (sortCol === col) sortDir *= -1;
            else { sortCol = col; sortDir = 1; }
            updateSortIcons();
            currentPage = 1;
            applyFilters();
        }

        function updateSortIcons() {
            document.querySelectorAll('.sortIcon').forEach(el => {
                el.className = 'sortIcon bi bi-arrow-down-up';
            });
            if (!sortCol) return;
            const icon = document.getElementById('sort-' + sortCol);
            if (icon) icon.className = 'sortIcon bi ' + (sortDir === 1 ? 'bi-arrow-up' : 'bi-arrow-down');
        }

        // ---- PAGINATION ----
        function renderPagination(total, totalPages, start) {
            const pag = document.getElementById('empPagination');
            if (!pag) return;
            if (total === 0) { pag.innerHTML = ''; return; }

            const end     = Math.min(start + ROWS_PER_PAGE, total);
            const showing = `${start + 1}–${end} of ${total}`;

            let html = `<span class="pagInfo">Showing ${showing}</span><div class="pagBtns">`;

            html += `<button class="pagBtn" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                        <i class="bi bi-chevron-left"></i></button>`;

            getPageNums(currentPage, totalPages).forEach(p => {
                if (p === '...') {
                    html += `<span class="pagEllipsis">…</span>`;
                } else {
                    html += `<button class="pagBtn${p === currentPage ? ' active' : ''}" onclick="changePage(${p})">${p}</button>`;
                }
            });

            html += `<button class="pagBtn" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                        <i class="bi bi-chevron-right"></i></button>`;
            html += '</div>';
            pag.innerHTML = html;
        }

        function getPageNums(cur, tot) {
            if (tot <= 7) return Array.from({length: tot}, (_, i) => i + 1);
            if (cur <= 4)      return [1,2,3,4,5,'...',tot];
            if (cur >= tot-3)  return [1,'...',tot-4,tot-3,tot-2,tot-1,tot];
            return [1,'...',cur-1,cur,cur+1,'...',tot];
        }

        function changePage(n) {
            const totalPages = Math.max(1, Math.ceil(lastTotal / ROWS_PER_PAGE));
            if (n < 1 || n > totalPages) return;
            currentPage = n;
            applyFilters();
        }

        // ---- SELECT EMPLOYEE ----
        function selectEmployee(id, name) {
            currentEmployeeId = id;
            document.querySelectorAll('#empList .empRow').forEach(r => r.classList.remove('active'));
            document.querySelector(`#empList .empRow[data-id="${id}"]`).classList.add('active');
            document.getElementById('empPlaceholder').style.display    = 'none';
            document.getElementById('empRecordsContent').style.display = 'flex';
            document.getElementById('selectedEmpName').textContent     = name;
            initDatePicker();
            loadRecords();
        }

        // ---- DATE PICKER ----
        function initDatePicker() {
            if (datePicker) { datePicker.destroy(); datePicker = null; }

            datePicker = flatpickr('#dateRangePicker', {
                mode:          'range',
                dateFormat:    'Y-m-d',
                altInput:      true,
                altInputClass: 'empDateInput',
                altFormat:     'M j, Y',
                defaultDate:   [currentStart, currentEnd],
                onChange(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        currentStart = instance.formatDate(selectedDates[0], 'Y-m-d');
                        currentEnd   = instance.formatDate(selectedDates[1], 'Y-m-d');
                        loadRecords();
                    }
                }
            });
        }

        // ---- LOAD RECORDS ----
        function loadRecords() {
            if (!currentEmployeeId) return;

            const container = document.getElementById('ganttContainer');
            container.innerHTML = '<div class="ganttEmpty"><div class="empSpinner"></div> Loading...</div>';

            const params = new URLSearchParams({
                employee_id: currentEmployeeId,
                start:       currentStart,
                end:         currentEnd
            });

            fetch('get_admin_employee_records.php?' + params.toString())
                .then(r => r.text())
                .then(html => {
                    container.innerHTML = html;
                    initGanttCursors();
                })
                .catch(() => {
                    container.innerHTML = '<div class="ganttEmpty" style="color:#ff8a8a;">Failed to load records.</div>';
                });
        }

        // ---- MODAL ----
        function openAddModal() {
            document.getElementById('empModalTitle').textContent    = 'Add Employee';
            document.getElementById('modalEmpId').value            = '';
            document.getElementById('modalName').value             = '';
            document.getElementById('modalEmail').value            = '';
            document.getElementById('modalPassword').value         = '';
            document.getElementById('modalPwdLabel').textContent   = 'Password';
            document.getElementById('modalPassword').required      = true;
            document.getElementById('modalSubmitBtn').innerHTML    = '<i class="bi bi-check-circle-fill"></i> Save Employee';
            selectRole('employee', 'Employee');
            selectDept('', 'Select Department');
            document.getElementById('empModalOverlay').style.display = 'flex';
        }

        function openEditModal(row) {
            document.getElementById('empModalTitle').textContent    = 'Edit Employee';
            document.getElementById('modalEmpId').value            = row.dataset.id;
            document.getElementById('modalName').value             = row.dataset.name;
            document.getElementById('modalEmail').value            = row.dataset.email;
            document.getElementById('modalPassword').value         = '';
            document.getElementById('modalPwdLabel').textContent   = 'New Password (leave blank to keep)';
            document.getElementById('modalPassword').required      = false;
            document.getElementById('modalSubmitBtn').innerHTML    = '<i class="bi bi-check-circle-fill"></i> Update Employee';
            selectRole(row.dataset.role, row.dataset.role.charAt(0).toUpperCase() + row.dataset.role.slice(1));
            selectDept(row.dataset.dept, row.dataset.deptName || 'Select Department');
            document.getElementById('empModalOverlay').style.display = 'flex';
        }

        function closeModal(e) {
            if (e.target === document.getElementById('empModalOverlay')) closeModalBtn();
        }

        function closeModalBtn() {
            document.getElementById('empModalOverlay').style.display = 'none';
        }

        // ---- ROLE / DEPT SELECTS ----
        function toggleModalDropdown(id) {
            const all = ['roleDropdown', 'deptDropdown'];
            all.forEach(d => {
                if (d !== id) document.getElementById(d).classList.remove('show');
            });
            document.getElementById(id).classList.toggle('show');
        }

        function selectRole(value, label) {
            document.getElementById('roleInput').value       = value;
            document.getElementById('roleLabel').textContent = label;
            document.getElementById('roleDropdown').classList.remove('show');
        }

        function selectDept(value, label) {
            document.getElementById('deptInput').value       = value;
            document.getElementById('deptLabel').textContent = label || 'Select Department';
            document.getElementById('deptDropdown').classList.remove('show');
        }

        document.addEventListener('click', e => {
            if (!e.target.closest('.customSelectWrapper')) {
                document.querySelectorAll('.customSelectMenu').forEach(m => m.classList.remove('show'));
            }
        });

        // ---- DEPARTMENTS ----
        function loadDepartments() {
            fetch('/DTR-Internship-Project/admin_pages/department_api.php?action=list')
                .then(r => r.json())
                .then(depts => {
                    // Populate modal dropdown
                    const menu = document.getElementById('deptDropdown');
                    menu.innerHTML = '<div class="customSelectItem" onclick="selectDept(\'\',\'Select Department\')">None</div>';
                    depts.forEach(d => {
                        const item = document.createElement('div');
                        item.className = 'customSelectItem';
                        item.textContent = d.department_name;
                        item.onclick = () => selectDept(d.id, d.department_name);
                        menu.appendChild(item);
                    });

                    // Populate filter dropdown
                    const filterMenu = document.getElementById('filterDeptMenu');
                    depts.forEach(d => {
                        const li = document.createElement('li');
                        const btn = document.createElement('button');
                        btn.type      = 'button';
                        btn.className = 'dropdown-item';
                        btn.textContent = d.department_name;
                        btn.onclick = () => selectFilter('dept', String(d.id), d.department_name);
                        li.appendChild(btn);
                        filterMenu.appendChild(li);
                    });
                })
                .catch(() => {});
        }

        loadDepartments();

        // Auto-dismiss success alert
        setTimeout(() => {
            document.querySelectorAll('.empAlert').forEach(a => {
                a.style.transition = 'opacity 0.5s';
                a.style.opacity    = '0';
                setTimeout(() => a.remove(), 500);
            });
        }, 3000);

    </script>
</body>
</html>
