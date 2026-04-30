<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'workforce') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$myId = $_SESSION['user_id'];

// Get dept info
$selfStmt = $pdo->prepare("
    SELECT e.department_id, d.department_code
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.id = ?
");
$selfStmt->execute([$myId]);
$selfData   = $selfStmt->fetch(PDO::FETCH_ASSOC);
$myDept     = $selfData['department_id'] ?? null;
$deptCode   = $selfData['department_code'] ?? null;

// Get dept employees
$deptEmployees = [];
if ($myDept) {
    $empStmt = $pdo->prepare("
        SELECT e.id, e.name, e.role, d.department_code
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.department_id = ?
        ORDER BY e.name
    ");
    $empStmt->execute([$myDept]);
    $deptEmployees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
}

$current_page = 'workforce_logs';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Logs</title>

    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="../admin_pages/admin_logs.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        /* Edit button */
        .wfLogEditBtn {
            background: rgba(13, 110, 253, 0.22);
            border: none;
            color: #6db8ff;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.68rem;
            transition: background 0.2s;
        }
        .wfLogEditBtn:hover { background: rgba(13, 110, 253, 0.45); }

        /* Edit modal */
        .wfModalOverlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 9998;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .wfModalBox {
            background: rgba(18, 18, 18, 0.97);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.65);
            width: 440px;
            max-width: 95vw;
            overflow: hidden;
        }
        .wfModalHeader {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .wfModalHeader h6 { color: #fff; font-weight: 600; margin: 0; font-size: 0.95rem; }
        .wfModalCloseBtn {
            background: none; border: none;
            color: rgba(255,255,255,0.4); font-size: 1rem;
            cursor: pointer; line-height: 1; transition: color 0.2s;
        }
        .wfModalCloseBtn:hover { color: #fff; }
        .wfModalBody { padding: 1.25rem; }
        .wfModalRow {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            font-size: 0.83rem;
        }
        .wfModalRow:last-of-type { border-bottom: none; }
        .wfModalLabel { color: rgba(255,255,255,0.45); }
        .wfModalValue { color: #fff; font-weight: 500; }
        .wfModalInput {
            font-family: 'Poppins', sans-serif;
            width: 100%;
            padding: 0.45rem 0.8rem;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            background: rgba(0,0,0,0.35);
            color: #fff;
            font-size: 0.875rem;
            outline: none;
            margin-top: 0.75rem;
            transition: border-color 0.2s;
        }
        .wfModalInput:focus { border-color: #97be41; }
        input[type="datetime-local"].wfModalInput::-webkit-calendar-picker-indicator { filter: invert(0.6); cursor: pointer; }
        .wfModalActions { display: flex; gap: 0.75rem; margin-top: 1.1rem; align-items: center; }
        .wfModalSubmitBtn {
            font-family: 'Poppins', sans-serif;
            background: #97be41; color: #fff;
            border: none; border-radius: 8px;
            padding: 0.45rem 1.25rem;
            font-size: 0.875rem; cursor: pointer;
            transition: background 0.2s;
        }
        .wfModalSubmitBtn:hover { background: #7ea832; }
        .wfModalCancelBtn {
            font-family: 'Poppins', sans-serif;
            background: none; border: none;
            color: rgba(255,255,255,0.4);
            font-size: 0.85rem; cursor: pointer;
            transition: color 0.2s;
        }
        .wfModalCancelBtn:hover { color: #dc3545; }
        .wfToast {
            position: fixed; bottom: 1.5rem; right: 1.5rem;
            padding: 0.7rem 1.2rem; border-radius: 10px;
            font-family: 'Poppins', sans-serif; font-size: 0.85rem;
            z-index: 99999; opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .wfToast.show { opacity: 1; }
        .wfToast.success { background: rgba(151,190,65,0.18); border: 1px solid rgba(151,190,65,0.35); color: #97be41; }
        .wfToast.error   { background: rgba(220,53,69,0.15);  border: 1px solid rgba(220,53,69,0.35);  color: #ff8a8a; }
    </style>
</head>
<body>

    <?php include '../sidebar.php'; ?>
    <?php include '../topbar.php'; ?>

    <div class="logsWrapper">
        <div class="logsBox">

            <!-- ===== LEFT PANEL ===== -->
            <div class="logsLeftPanel">
                <div class="logsLeftHeader">
                    <h6 class="logsLeftTitle"><?= htmlspecialchars($deptCode ?? 'Dept') ?> Employees</h6>
                    <input type="text" id="empSearch" class="logsEmpSearch"
                        placeholder="Search employee..." oninput="filterEmployees()">
                </div>

                <div class="empList" id="empList">
                    <?php if (!$myDept): ?>
                        <div class="empEmpty">No department assigned. Contact an admin.</div>
                    <?php elseif (empty($deptEmployees)): ?>
                        <div class="empEmpty">No employees in your department.</div>
                    <?php else: ?>
                        <?php foreach ($deptEmployees as $emp): ?>
                            <div class="empRow"
                                 data-id="<?= $emp['id'] ?>"
                                 data-name="<?= htmlspecialchars($emp['name']) ?>"
                                 onclick="selectEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>')">
                                <div class="empName"><?= htmlspecialchars($emp['name']) ?></div>
                                <div class="empMeta">
                                    <span class="empRoleBadge empRole-<?= $emp['role'] ?>"><?= ucfirst($emp['role']) ?></span>
                                    <?php if ($emp['department_code']): ?>
                                        <span class="empDept"><?= htmlspecialchars($emp['department_code']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== DIVIDER ===== -->
            <div class="logsPanelDivider"></div>

            <!-- ===== RIGHT PANEL ===== -->
            <div class="logsRightPanel">

                <div class="logsPlaceholder" id="logsPlaceholder">
                    <i class="bi bi-person-lines-fill logsPlaceholderIcon"></i>
                    <p>Select an employee to view their logs</p>
                </div>

                <div class="logsContent" id="logsContent" style="display:none;">

                    <div class="logsRightHeader">
                        <h6 class="logsRightTitle">
                            Logs for <span id="selectedEmpName"></span>
                        </h6>
                        <div class="logsFilterRow">
                            <select id="filterLogType" class="logsFilterInput" onchange="loadLogs()">
                                <option value="">All Types</option>
                                <option value="IN">Time In</option>
                                <option value="OUT">Time Out</option>
                                <option value="BREAK_IN">Break In</option>
                                <option value="BREAK_OUT">Break Out</option>
                            </select>
                            <input type="date" id="filterDateFrom" class="logsFilterInput" onchange="loadLogs()">
                            <span class="logsFilterSep">to</span>
                            <input type="date" id="filterDateTo" class="logsFilterInput" onchange="loadLogs()">
                            <button class="logsClearBtn" onclick="clearFilters()">
                                <i class="bi bi-x-circle"></i> Clear
                            </button>
                        </div>
                    </div>

                    <div class="logsTableWrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date &amp; Time</th>
                                    <th>Log Type</th>
                                    <th>Distance</th>
                                    <th>Accuracy</th>
                                    <th>Location</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <tr>
                                    <td colspan="7" class="logsLoadingCell">
                                        <span class="logsSpinner"></span> Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- Map Hover Popup -->
    <div class="mapPopUpContainer" id="map_pop_up_container">
        <div class="mapPopUp" id="map_pop_up"></div>
        <div class="mapPopUpInfo" id="map_pop_up_info"></div>
        <div style="padding: 10px;">
            <a class="openGoogleMapsBtn" id="open_gmaps_btn" href="#" target="_blank">
                Open in Google Maps
            </a>
        </div>
    </div>

    <!-- Log Edit Modal -->
    <div class="wfModalOverlay" id="leModalOverlay" style="display:none;" onclick="closeLeModalOverlay(event)">
        <div class="wfModalBox">
            <div class="wfModalHeader">
                <h6>Edit Log Entry</h6>
                <button class="wfModalCloseBtn" onclick="closeLeModal()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="wfModalBody">
                <div class="wfModalRow">
                    <span class="wfModalLabel">Employee</span>
                    <span class="wfModalValue" id="leEmpName">—</span>
                </div>
                <div class="wfModalRow">
                    <span class="wfModalLabel">Log Type</span>
                    <span id="leLogTypeBadge">—</span>
                </div>
                <div class="wfModalRow">
                    <span class="wfModalLabel">Current Time</span>
                    <span class="wfModalValue" id="leCurrentTime">—</span>
                </div>

                <label style="color:rgba(255,255,255,0.6);font-size:0.8rem;margin-top:0.9rem;display:block;font-weight:600;">
                    New Date &amp; Time
                </label>
                <input type="datetime-local" id="leNewTime" class="wfModalInput">

                <label style="color:rgba(255,255,255,0.6);font-size:0.8rem;margin-top:0.85rem;display:block;font-weight:600;">
                    Reason <span style="color:rgba(255,255,255,0.3);font-weight:400;">(optional)</span>
                </label>
                <textarea id="wfLeReason" class="wfModalInput" rows="2"
                    placeholder="Briefly explain the reason for this correction..."
                    style="resize:none;"></textarea>
                <p style="font-size:0.75rem;color:rgba(255,255,255,0.3);margin-top:0.4rem;">
                    <i class="bi bi-info-circle"></i> This will be submitted for admin review before taking effect.
                </p>

                <div class="wfModalActions">
                    <button class="wfModalSubmitBtn" onclick="submitLogEdit()">
                        <i class="bi bi-send-fill"></i> Submit for Approval
                    </button>
                    <button class="wfModalCancelBtn" onclick="closeLeModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="wfToast" id="wfToast"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let currentEmployeeId   = null;
    let currentEmployeeName = '';

    // ---- EMPLOYEE SEARCH ----
    function filterEmployees() {
        const q = document.getElementById('empSearch').value.toLowerCase();
        document.querySelectorAll('#empList .empRow').forEach(row => {
            row.style.display = row.dataset.name.toLowerCase().includes(q) ? '' : 'none';
        });
    }

    // ---- SELECT EMPLOYEE ----
    function selectEmployee(id, name) {
        currentEmployeeId   = id;
        currentEmployeeName = name;

        document.querySelectorAll('#empList .empRow').forEach(r => r.classList.remove('active'));
        document.querySelector(`#empList .empRow[data-id="${id}"]`).classList.add('active');

        document.getElementById('logsPlaceholder').style.display = 'none';
        document.getElementById('logsContent').style.display     = 'flex';
        document.getElementById('selectedEmpName').textContent   = name;

        loadLogs();
    }

    // ---- LOAD LOGS ----
    function loadLogs() {
        if (!currentEmployeeId) return;

        const logType  = document.getElementById('filterLogType').value;
        const dateFrom = document.getElementById('filterDateFrom').value;
        const dateTo   = document.getElementById('filterDateTo').value;

        const params = new URLSearchParams({ employee_id: currentEmployeeId });
        if (logType)  params.append('log_type', logType);
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo)   params.append('date_to', dateTo);

        document.getElementById('logsTableBody').innerHTML = `
            <tr class="logsEmptyRow">
                <td colspan="7" class="logsLoadingCell">
                    <span class="logsSpinner"></span> Loading...
                </td>
            </tr>`;

        fetch('get_workforce_logs.php?' + params.toString())
            .then(r => r.json())
            .then(renderLogs)
            .catch(() => {
                document.getElementById('logsTableBody').innerHTML =
                    '<tr class="logsEmptyRow"><td colspan="7"><div class="logsEmpty" style="color:#ff8a8a;">Failed to load logs.</div></td></tr>';
            });
    }

    // ---- RENDER LOGS ----
    function renderLogs(logs) {
        const tbody = document.getElementById('logsTableBody');

        if (!logs.length) {
            tbody.innerHTML = '<tr class="logsEmptyRow"><td colspan="7"><div class="logsEmpty">No logs found for this employee.</div></td></tr>';
            return;
        }

        const logClass = { IN: 'log-in', OUT: 'log-out', BREAK_IN: 'log-break-in', BREAK_OUT: 'log-break-out' };
        const logLabel = { IN: 'Time In', OUT: 'Time Out', BREAK_IN: 'Break In', BREAK_OUT: 'Break Out' };

        tbody.innerHTML = logs.map((log, i) => {
            const cls = logClass[log.log_type] || 'log-out';
            const lbl = logLabel[log.log_type] || log.log_type;

            const dt      = new Date(log.log_time);
            const dateStr = dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const timeStr = dt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

            const isInside    = parseInt(log.is_within_office);
            const officeClass = isInside ? 'in-office' : 'out-office';
            const officeLabel = isInside ? 'Within Office' : 'Outside Office';

            const distRaw = log.distance_meters != null ? parseFloat(log.distance_meters).toFixed(1) : null;
            const accRaw  = log.accuracy        != null ? parseFloat(log.accuracy).toFixed(0)        : null;

            const dist = distRaw !== null ? distRaw + ' m' : '—';
            const acc  = accRaw  !== null ? accRaw  + ' m' : '—';

            const lat = log.latitude;
            const lng = log.longitude;

            const locCell = (lat && lng)
                ? `<a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank"
                       class="${officeClass} loc-trigger"
                       data-lat="${lat}" data-lng="${lng}"
                       data-label="${officeLabel}" data-acc="${accRaw ?? 'N/A'}" data-dist="${distRaw ?? 'N/A'}">
                       <i class="bi bi-geo-alt-fill locationIcon"></i>${officeLabel}
                   </a>`
                : `<span class="${officeClass}">
                       <i class="bi bi-geo-alt-fill locationIcon"></i>${officeLabel}
                   </span>`;

            const canEdit = (log.log_type === 'IN' || log.log_type === 'OUT');

            const actionCell = canEdit
                ? `<button class="wfLogEditBtn" title="Edit Log"
                           onclick="openLeModal(${log.id}, '${log.log_type}', '${log.log_time}')">
                       <i class="bi bi-pencil-fill"></i>
                   </button>`
                : '<span style="color:rgba(255,255,255,0.2);">—</span>';

            return `
                <tr>
                    <td>${i + 1}</td>
                    <td>
                        <div>
                            <div>${dateStr}</div>
                            <div>${timeStr}</div>
                        </div>
                    </td>
                    <td><span class="${cls}">${lbl}</span></td>
                    <td>${dist}</td>
                    <td>${acc}</td>
                    <td>${locCell}</td>
                    <td>${actionCell}</td>
                </tr>`;
        }).join('');
    }

    // ---- CLEAR FILTERS ----
    function clearFilters() {
        document.getElementById('filterLogType').value  = '';
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value   = '';
        if (currentEmployeeId) loadLogs();
    }

    // ---- LOG EDIT MODAL ----
    let leLogId   = null;
    let leLogType = null;

    function openLeModal(logId, logType, logTime) {
        leLogId   = logId;
        leLogType = logType;

        const logLabel = { IN: 'Time In', OUT: 'Time Out' };
        const logClass = { IN: 'log-in',  OUT: 'log-out'  };

        document.getElementById('leEmpName').textContent = currentEmployeeName;
        document.getElementById('leLogTypeBadge').innerHTML =
            `<span class="${logClass[logType]}">${logLabel[logType]}</span>`;

        const dt = new Date(logTime);

        document.getElementById('leCurrentTime').textContent =
            dt.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' }) + ' ' +
            dt.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit' });

        const pad = n => String(n).padStart(2, '0');
        document.getElementById('leNewTime').value =
            `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;

        document.getElementById('wfLeReason').value = '';

        document.getElementById('leModalOverlay').style.display = 'flex';
    }

    function closeLeModal() {
        document.getElementById('leModalOverlay').style.display = 'none';
    }

    function closeLeModalOverlay(e) {
        if (e.target.id === 'leModalOverlay') closeLeModal();
    }

    function submitLogEdit() {
        const newDatetime = document.getElementById('leNewTime').value;
        if (!newDatetime) {
            showToast('Please enter a new date and time.', 'error');
            return;
        }

        const reasonVal = document.getElementById('wfLeReason').value.trim();

        const body = new URLSearchParams({
            log_id:       leLogId,
            employee_id:  currentEmployeeId,
            log_type:     leLogType,
            new_datetime: newDatetime.replace('T', ' ') + ':00',
            reason:       reasonVal
        });

        fetch('submit_workforce_log_edit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closeLeModal();
                showToast(res.message, 'success');
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(() => showToast('Something went wrong. Please try again.', 'error'));
    }

    // ---- TOAST ----
    function showToast(msg, type) {
        const toast = document.getElementById('wfToast');
        toast.textContent = msg;
        toast.className   = `wfToast ${type} show`;
        setTimeout(() => toast.classList.remove('show'), 3500);
    }
</script>
</body>
</html>
