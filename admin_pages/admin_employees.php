<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

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

$current_page = 'employees';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Management</title>

    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="admin_employees.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>body::before { background-image: url('../images/drt_bg.jpg'); }</style>
</head>
<body>

    <?php include '../sidebar.php'; ?>
    <?php include '../topbar.php'; ?>

    <div class="empWrapper">
        <div class="empBox">

            <!-- ===== LEFT PANEL ===== -->
            <div class="empLeftPanel">
                <div class="empLeftHeader">
                    <h6 class="empLeftTitle">Employees</h6>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <input type="text" id="empSearch" class="empSearch"
                               placeholder="Search..." oninput="filterEmployees()">
                        <button class="empAddBtn" onclick="openAddModal()" title="Add Employee">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="empAlert"><?= $success ?></div>
                <?php endif; ?>

                <div class="empList" id="empList">
                    <?php foreach ($employees as $emp): ?>
                        <div class="empRow"
                             data-id="<?= $emp['id'] ?>"
                             data-name="<?= htmlspecialchars($emp['name']) ?>"
                             data-email="<?= htmlspecialchars($emp['email']) ?>"
                             data-role="<?= $emp['role'] ?>"
                             data-dept="<?= htmlspecialchars($emp['department_id'] ?? '') ?>"
                             data-dept-name="<?= htmlspecialchars($emp['department_name'] ?? '') ?>"
                             onclick="selectEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>')">
                            <div class="empName"><?= htmlspecialchars($emp['name']) ?></div>
                            <div class="empMeta">
                                <span class="empRoleBadge empRole-<?= $emp['role'] ?>"><?= ucfirst($emp['role']) ?></span>
                                <?php if ($emp['department_id']): ?>
                                    <span class="empDept"><?= htmlspecialchars($emp['department_code'] ?? '') ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="empRowActions" onclick="event.stopPropagation()">
                                <button class="empActionBtn empEditBtn"
                                        onclick="openEditModal(this.closest('.empRow'))"
                                        title="Edit">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <a class="empActionBtn empDeleteBtn"
                                   href="admin_employees.php?delete=<?= $emp['id'] ?>"
                                   onclick="return confirm('Delete <?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>?')"
                                   title="Delete">
                                    <i class="bi bi-trash-fill"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($employees)): ?>
                        <div class="empEmpty">No employees found.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== DIVIDER ===== -->
            <div class="empPanelDivider"></div>

            <!-- ===== RIGHT PANEL ===== -->
            <div class="empRightPanel">

                <!-- Placeholder -->
                <div class="empPlaceholder" id="empPlaceholder">
                    <i class="bi bi-person-lines-fill empPlaceholderIcon"></i>
                    <p>Select an employee to view their records</p>
                </div>

                <!-- Records Content -->
                <div class="empRecordsContent" id="empRecordsContent" style="display:none;flex-direction:column;flex:1;overflow:hidden;">

                    <div class="empRecordsHeader">
                        <h6 class="empRecordsTitle">Records for <span id="selectedEmpName"></span></h6>
                        <div class="empDateWrapper">
                            <input type="text" id="dateRangePicker" class="empDateInput" readonly>
                            <i class="bi bi-chevron-down empDateIcon"></i>
                        </div>
                    </div>

                    <div class="ganttContainer" id="ganttContainer">
                        <!-- AJAX loaded -->
                    </div>

                </div>

            </div>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../system_functions/gantt.js"></script>
    <script>

        let currentEmployeeId = null;
        let datePicker        = null;
        let currentStart      = '<?= date('Y-m-01') ?>';
        let currentEnd        = '<?= date('Y-m-t') ?>';

        // ---- EMPLOYEE SEARCH ----
        function filterEmployees() {
            const q = document.getElementById('empSearch').value.toLowerCase();
            document.querySelectorAll('#empList .empRow').forEach(row => {
                row.style.display = row.dataset.name.toLowerCase().includes(q) ? '' : 'none';
            });
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
                    const menu = document.getElementById('deptDropdown');
                    menu.innerHTML = '<div class="customSelectItem" onclick="selectDept(\'\',\'Select Department\')">None</div>';
                    depts.forEach(d => {
                        const item = document.createElement('div');
                        item.className = 'customSelectItem';
                        item.textContent = d.department_name;
                        item.onclick = () => selectDept(d.id, d.department_name);
                        menu.appendChild(item);
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
