<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$current_page = 'employee_logs';

$employees = $pdo->query("SELECT id, name, role, department_id FROM employees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Logs</title>

    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="admin_logs.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>

    <?php include '../sidebar.php'; ?>
    <?php include '../topbar.php'; ?>

    <div class="logsWrapper">
        <div class="logsBox">

            <!-- ===== LEFT PANEL: EMPLOYEE LIST ===== -->
            <div class="logsLeftPanel">

                <div class="logsLeftHeader">
                    <h6 class="logsLeftTitle">Employees</h6>
                    <input type="text" id="empSearch" class="logsEmpSearch"
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
                                <?php if ($emp['department_id']): ?>
                                    <span class="empDept"><?= htmlspecialchars($emp['department_id']) ?></span>
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
            <div class="logsPanelDivider"></div>

            <!-- ===== RIGHT PANEL: LOGS VIEW ===== -->
            <div class="logsRightPanel">

                <!-- Placeholder -->
                <div class="logsPlaceholder" id="logsPlaceholder">
                    <i class="bi bi-person-lines-fill logsPlaceholderIcon"></i>
                    <p>Select an employee to view their logs</p>
                </div>

                <!-- Log Content -->
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
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <tr>
                                    <td colspan="6" class="logsLoadingCell">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        let currentEmployeeId = null;

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
            if (logType)  params.append('log_type',  logType);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo)   params.append('date_to',   dateTo);

            document.getElementById('logsTableBody').innerHTML = `
                <tr class="logsEmptyRow"><td colspan="6" class="logsLoadingCell">
                    <span class="logsSpinner"></span> Loading...
                </td></tr>
            `;

            fetch('get_employee_logs.php?' + params.toString())
                .then(r => r.json())
                .then(renderLogs)
                .catch(() => {
                    document.getElementById('logsTableBody').innerHTML =
                        '<tr class="logsEmptyRow"><td colspan="6"><div class="logsEmpty" style="color:#ff8a8a;">Failed to load logs.</div></td></tr>';
                });
        }

        // ---- RENDER LOGS ----
        function renderLogs(logs) {
            const tbody = document.getElementById('logsTableBody');

            if (!logs.length) {
                tbody.innerHTML = '<tr class="logsEmptyRow"><td colspan="6"><div class="logsEmpty">No logs found for this employee.</div></td></tr>';
                return;
            }

            const logClass = { IN: 'log-in', OUT: 'log-out', BREAK_IN: 'log-break-in', BREAK_OUT: 'log-break-out' };
            const logLabel = { IN: 'Time In', OUT: 'Time Out', BREAK_IN: 'Break In', BREAK_OUT: 'Break Out' };

            tbody.innerHTML = logs.map((log, i) => {
                const cls     = logClass[log.log_type] || 'log-out';
                const lbl     = logLabel[log.log_type] || log.log_type;
                const dt      = new Date(log.log_time);
                const dateStr = dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const timeStr = dt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                const isInside    = parseInt(log.is_within_office);
                const officeClass = isInside ? 'in-office' : 'out-office';
                const officeLabel = isInside ? 'Within Office' : 'Outside Office';

                const distRaw = log.distance_meters != null ? parseFloat(log.distance_meters).toFixed(1) : null;
                const accRaw  = log.accuracy        != null ? parseFloat(log.accuracy).toFixed(0)        : null;
                const dist    = distRaw !== null ? distRaw + ' m' : '—';
                const acc     = accRaw  !== null ? accRaw  + ' m' : '—';
                const lat     = log.latitude;
                const lng     = log.longitude;

                const locCell = (lat && lng)
                    ? `<a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank"
                           class="${officeClass} loc-trigger"
                           style="text-decoration:none;"
                           data-lat="${lat}" data-lng="${lng}"
                           data-label="${officeLabel}"
                           data-acc="${accRaw ?? 'N/A'}"
                           data-dist="${distRaw ?? 'N/A'}">
                           <i class="bi bi-geo-alt-fill locationIcon"></i>${officeLabel}
                       </a>`
                    : `<span class="${officeClass}">
                           <i class="bi bi-geo-alt-fill locationIcon"></i>${officeLabel}
                       </span>`;

                return `
                    <tr>
                        <td class="logNum">${i + 1}</td>
                        <td>
                            <div class="logDateTime">
                                <span class="logDate">${dateStr}</span>
                                <span class="logTime">${timeStr}</span>
                            </div>
                        </td>
                        <td><span class="${cls}">${lbl}</span></td>
                        <td>${dist}</td>
                        <td>${acc}</td>
                        <td>${locCell}</td>
                    </tr>
                `;
            }).join('');
        }

        // ---- CLEAR FILTERS ----
        function clearFilters() {
            document.getElementById('filterLogType').value  = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value   = '';
            if (currentEmployeeId) loadLogs();
        }

        // ---- MAP HOVER POPUP ----
        let popupMap    = null;
        let hideTimeout = null;
        const mapPopup  = document.getElementById('map_pop_up_container');

        document.addEventListener('mouseover', e => {
            const trigger = e.target.closest('.loc-trigger');
            if (!trigger) return;

            clearTimeout(hideTimeout);

            const lat   = parseFloat(trigger.dataset.lat);
            const lng   = parseFloat(trigger.dataset.lng);
            const label = trigger.dataset.label;
            const acc   = trigger.dataset.acc;
            const dist  = trigger.dataset.dist;

            document.getElementById('open_gmaps_btn').href = `https://www.google.com/maps?q=${lat},${lng}`;

            const rect        = trigger.getBoundingClientRect();
            const popupHeight = 320;
            const popupWidth  = 300;
            const spaceBelow  = window.innerHeight - rect.bottom;
            const spaceAbove  = rect.top;
            const spaceRight  = window.innerWidth  - rect.left;

            const topPos  = (spaceBelow < popupHeight && spaceAbove > spaceBelow)
                ? rect.top    + window.scrollY - popupHeight - 3
                : rect.bottom + window.scrollY + 3;

            const leftPos = spaceRight < popupWidth
                ? rect.right + window.scrollX - popupWidth - 310
                : rect.left  + window.scrollX - 310;

            mapPopup.style.top     = `${topPos}px`;
            mapPopup.style.left    = `${leftPos}px`;
            mapPopup.style.display = 'block';

            document.getElementById('map_pop_up_info').innerHTML = `
                <b>${label}</b><br>
                Latitude: ${lat} &nbsp;&nbsp; Longitude: ${lng}<br>
                Accuracy: ±${acc} m &nbsp; Distance: ${dist} m
            `;

            setTimeout(() => {
                if (!popupMap) {
                    popupMap = L.map('map_pop_up', { zoomControl: false, attributionControl: false });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(popupMap);
                    popupMap._marker = null;
                }
                popupMap.invalidateSize();
                popupMap.setView([lat, lng], 17);
                if (popupMap._marker) popupMap.removeLayer(popupMap._marker);
                popupMap._marker = L.marker([lat, lng]).addTo(popupMap);
            }, 50);
        });

        document.addEventListener('mouseout', e => {
            const trigger = e.target.closest('.loc-trigger');
            if (!trigger) return;
            hideTimeout = setTimeout(() => {
                mapPopup.style.display = 'none';
                if (popupMap) { popupMap.remove(); popupMap = null; }
            }, 200);
        });

        mapPopup.addEventListener('mouseover', () => clearTimeout(hideTimeout));
        mapPopup.addEventListener('mouseout',  () => {
            hideTimeout = setTimeout(() => {
                mapPopup.style.display = 'none';
                if (popupMap) { popupMap.remove(); popupMap = null; }
            }, 200);
        });

    </script>
</body>
</html>
