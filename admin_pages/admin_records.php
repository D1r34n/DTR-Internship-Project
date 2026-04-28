<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$current_page = 'admin_records';

$employees = $pdo->query("SELECT id, name, role, department FROM employees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Records</title>

    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="admin_records.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>

    <?php include '../sidebar.php'; ?>
    <?php include '../topbar.php'; ?>

    <div class="recsWrapper">
        <div class="recsBox">

            <!-- ===== LEFT PANEL: EMPLOYEE LIST ===== -->
            <div class="recsLeftPanel">
                <div class="recsLeftHeader">
                    <h6 class="recsLeftTitle">Employees</h6>
                    <input type="text" id="empSearch" class="recsEmpSearch"
                        placeholder="Search employee..." oninput="filterEmployees()">
                </div>

                <div class="empList" id="empList">
                    <?php foreach ($employees as $emp): ?>
                        <div class="empRow"
                             data-id="<?= $emp['id'] ?>"
                             data-name="<?= htmlspecialchars($emp['name']) ?>"
                             onclick="selectEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>')">
                            <div class="empName"><?= htmlspecialchars($emp['name']) ?></div>
                            <div class="empMeta">
                                <span class="empRoleBadge empRole-<?= $emp['role'] ?>"><?= ucfirst($emp['role']) ?></span>
                                <?php if ($emp['department']): ?>
                                    <span class="empDept"><?= htmlspecialchars($emp['department']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($employees)): ?>
                        <div class="empEmpty">No employees found.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== DIVIDER ===== -->
            <div class="recsPanelDivider"></div>

            <!-- ===== RIGHT PANEL: RECORDS ===== -->
            <div class="recsRightPanel">

                <!-- Placeholder -->
                <div class="recsPlaceholder" id="recsPlaceholder">
                    <i class="bi bi-bar-chart-steps recsPlaceholderIcon"></i>
                    <p>Select an employee to view their records</p>
                </div>

                <!-- Content -->
                <div class="recsContent" id="recsContent" style="display:none;">

                    <div class="recsRightHeader">
                        <h6 class="recsRightTitle">
                            Records for <span id="selectedEmpName"></span>
                        </h6>
                        <div class="recsDateWrapper">
                            <input type="text" id="dateRangePicker" class="recsDateInput" readonly>
                            <i class="bi bi-chevron-down recsDateIcon"></i>
                            <input type="hidden" id="startDate">
                            <input type="hidden" id="endDate">
                        </div>
                    </div>

                    <div class="ganttContainer" id="ganttContent">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../system_functions/gantt.js"></script>
    <script>

        let currentEmployeeId = null;
        let fp = null;

        // ---- INIT DATE DEFAULTS ----
        (function () {
            const now     = new Date();
            const y       = now.getFullYear();
            const m       = String(now.getMonth() + 1).padStart(2, '0');
            const lastDay = new Date(y, now.getMonth() + 1, 0).getDate();
            document.getElementById('startDate').value = `${y}-${m}-01`;
            document.getElementById('endDate').value   = `${y}-${m}-${String(lastDay).padStart(2, '0')}`;
        })();

        // ---- INIT FLATPICKR ----
        function initDatePicker() {
            if (fp) { fp.destroy(); fp = null; }
            fp = flatpickr('#dateRangePicker', {
                mode:        'range',
                dateFormat:  'Y-m-d',
                altInput:    true,
                altFormat:   'M j, Y',
                defaultDate: [document.getElementById('startDate').value, document.getElementById('endDate').value],
                onChange(selectedDates) {
                    if (selectedDates.length !== 2) return;
                    const pad = n => String(n).padStart(2, '0');
                    const fmt = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
                    document.getElementById('startDate').value = fmt(selectedDates[0]);
                    document.getElementById('endDate').value   = fmt(selectedDates[1]);
                    loadRecords();
                }
            });
        }

        // ---- FILTER EMPLOYEE LIST ----
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
            document.getElementById('recsPlaceholder').style.display = 'none';
            document.getElementById('recsContent').style.display     = 'flex';
            document.getElementById('selectedEmpName').textContent   = name;
            initDatePicker();
            loadRecords();
        }

        // ---- LOAD RECORDS ----
        function loadRecords() {
            if (!currentEmployeeId) return;
            const start = document.getElementById('startDate').value;
            const end   = document.getElementById('endDate').value;

            document.getElementById('ganttContent').innerHTML = `
                <div class="ganttEmpty">
                    <span class="recsSpinner"></span>
                    Loading...
                </div>
            `;

            fetch(`get_admin_records.php?employee_id=${currentEmployeeId}&start=${start}&end=${end}`)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('ganttContent').innerHTML = html;
                    initGanttCursors();
                })
                .catch(() => {
                    document.getElementById('ganttContent').innerHTML =
                        '<div class="ganttEmpty" style="color:#ff8a8a;">Failed to load records.</div>';
                });
        }

    </script>
</body>
</html>
