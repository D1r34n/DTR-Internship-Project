<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

date_default_timezone_set('Asia/Manila');

$startDate = !empty($_GET['start']) ? date('Y-m-d', strtotime($_GET['start'])) : '';
$endDate   = !empty($_GET['end'])   ? date('Y-m-d', strtotime($_GET['end']))   : '';

$current_page = 'logs';
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Logs</title>

    <!-- 1. Third-party CSS FIRST -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <!-- 2. Your global CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <!-- 3. Page-specific CSS -->
    <link rel="stylesheet" href="employee_logs.css">

    <!-- 4. Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body>
    <?php $currentPage = 'logs'; include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">
        <?php include '../topbar_revised.php'; ?>

        <div class="card card-glass logs-card">
            <div class="card-body d-flex flex-column logs-card-body">

            <!-- Filter Section -->
            <div class="filterWrapper">

                <!-- Date Range Picker -->
                <div class="dropdown">
                    <button class="btn dropdown-toggle" id="datePickerBtn" type="button">
                        <i class="bi bi-calendar3"></i>
                        <span id="dateRangeLabel">Today</span>
                    </button>
                </div>

                <!-- Log Type Filter -->
                <div class="dropdown">
                    <button class="btn dropdown-toggle" type="button" id="logTypeToggle"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-funnel"></i>
                        <span id="logTypeLabel">All Types</span>
                    </button>
                    <ul class="dropdown-menu" id="logTypeMenu">
                        <li><a class="dropdown-item" href="#" data-value="ALL">All Types</a></li>
                        <li><a class="dropdown-item" href="#" data-value="IN">Time In</a></li>
                        <li><a class="dropdown-item" href="#" data-value="OUT">Time Out</a></li>
                        <li><a class="dropdown-item" href="#" data-value="BREAK_IN">Break In</a></li>
                        <li><a class="dropdown-item" href="#" data-value="BREAK_OUT">Break Out</a></li>
                    </ul>
                </div>

                <input type="hidden" id="logTypeFilter" value="ALL">
                <input type="hidden" id="startDate" value="<?= $startDate ?>">
                <input type="hidden" id="endDate" value="<?= $endDate ?>">
            </div>

            <!-- Table Header -->
            <div class="tableHeaderGlass">
                <table class="table table-borderless mb-0">
                    <colgroup>
                        <col style="width:18%">
                        <col style="width:12%">
                        <col style="width:15%">
                        <col style="width:18%">
                        <col style="width:20%">
                        <col style="width:17%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="date">
                                Date <i class="bi bi-arrow-down-up sortIcon" id="sort-date"></i>
                            </th>
                            <th class="sortable" data-sort="time">
                                Time <i class="bi bi-arrow-down-up sortIcon" id="sort-time"></i>
                            </th>
                            <th class="sortable" data-sort="type">
                                Log Type <i class="bi bi-arrow-down-up sortIcon" id="sort-type"></i>
                            </th>
                            <th class="sortable" data-sort="location">
                                Location <i class="bi bi-arrow-down-up sortIcon" id="sort-location"></i>
                            </th>
                            <th>Requested By</th>
                            <th>Edit Status</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <!-- Body with scrolling -->
            <div class="tableScroll">
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col style="width:18%">
                        <col style="width:12%">
                        <col style="width:15%">
                        <col style="width:18%">
                        <col style="width:20%">
                        <col style="width:17%">
                    </colgroup>
                    <tbody id="logs_table_body">
                        <!-- populated by fetchLogs() -->
                    </tbody>
                </table>
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
</div><!-- #main-wrapper -->

 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
const tbody = document.getElementById('logs_table_body');

/* =========================
   FETCH LOGS (AJAX)
========================= */
function fetchLogs() {
    const start = document.getElementById('startDate').value;
    const end   = document.getElementById('endDate').value;
    const type  = document.getElementById('logTypeFilter').value;

    fetch(`../get_logs.php?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&type=${encodeURIComponent(type)}&sort=${sortColumn}&dir=${sortDirection}`)
        .then(res => res.text())
        .then(html => { tbody.innerHTML = html; });
}

/* =========================
   FLATPICKR INIT
========================= */
const startInput     = document.getElementById('startDate');
const endInput       = document.getElementById('endDate');
const datePickerBtn  = document.getElementById('datePickerBtn');
const dateRangeLabel = document.getElementById('dateRangeLabel');

function fmtDate(d) {
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function updateDateLabel(dates) {
    if (!dates.length) { dateRangeLabel.textContent = 'All Logs'; return; }
    const isSameDay = dates.length > 1 && dates[0].toDateString() === dates[1].toDateString();
    dateRangeLabel.textContent = (dates.length === 1 || isSameDay)
        ? fmtDate(dates[0])
        : fmtDate(dates[0]) + ' – ' + fmtDate(dates[1]);
}

flatpickr(datePickerBtn, {
    mode: 'range',
    dateFormat: 'Y-m-d',
    defaultDate: startInput.value ? [startInput.value, endInput.value] : [],

    onReady(dates) { updateDateLabel(dates); },

    onChange(dates) {
        updateDateLabel(dates);
        if (dates.length !== 2) return;

        const pad     = n => String(n).padStart(2, '0');
        const toLocal = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

        startInput.value = toLocal(dates[0]);
        endInput.value   = toLocal(dates[1]);
        fetchLogs();
    }
});

/* =========================
   LOG TYPE FILTER
========================= */
const logTypeHidden = document.getElementById('logTypeFilter');
const logTypeLabel  = document.getElementById('logTypeLabel');

document.querySelectorAll('#logTypeMenu .dropdown-item').forEach(item => {
    item.addEventListener('click', e => {
        e.preventDefault();
        logTypeLabel.textContent = item.textContent.trim();
        logTypeHidden.value = item.dataset.value;
        fetchLogs();
    });
});

/* =========================
   SORTING — 3-STATE PER COLUMN
   1st click on column  → ASC
   2nd click same column → DESC
   3rd click same column → reset to default (date DESC)
========================= */
const DEFAULT_SORT_COL = 'date';
const DEFAULT_SORT_DIR = 'desc';

let sortColumn    = DEFAULT_SORT_COL;
let sortDirection = DEFAULT_SORT_DIR;

function applyHeaderUI() {
    document.querySelectorAll('.sortable').forEach(el => el.classList.remove('sorted'));
    document.querySelectorAll('.sortIcon').forEach(el => {
        el.className = 'sortIcon bi bi-arrow-down-up';
    });

    const activeTh = document.querySelector(`.sortable[data-sort="${sortColumn}"]`);
    if (activeTh) {
        activeTh.classList.add('sorted');
        const icon = activeTh.querySelector('.sortIcon');
        if (icon) icon.className = 'sortIcon bi ' + (sortDirection === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down');
    }
}

document.querySelectorAll('.sortable').forEach(th => {
    th.addEventListener('click', () => {
        const column = th.dataset.sort;

        if (sortColumn === column) {
            if (sortDirection === 'asc') {
                // 2nd click → flip to DESC
                sortDirection = 'desc';
            } else {
                // 3rd click → reset to default
                sortColumn    = DEFAULT_SORT_COL;
                sortDirection = DEFAULT_SORT_DIR;
            }
        } else {
            // New column → start ASC
            sortColumn    = column;
            sortDirection = 'asc';
        }

        applyHeaderUI();
        fetchLogs();
    });
});

/* =========================
   MAP POPUP
========================= */
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
    const LEFT_OFFSET = 310;

    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;
    const spaceRight = window.innerWidth - rect.left;

    const topPos  = (spaceBelow < popupHeight && spaceAbove > spaceBelow)
        ? rect.top    + window.scrollY - popupHeight - 3
        : rect.bottom + window.scrollY + 3;

    const leftPos = (spaceRight < popupWidth)
        ? rect.right + window.scrollX - popupWidth - LEFT_OFFSET
        : rect.left  + window.scrollX - LEFT_OFFSET;

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
mapPopup.addEventListener('mouseout', () => {
    hideTimeout = setTimeout(() => {
        mapPopup.style.display = 'none';
        if (popupMap) { popupMap.remove(); popupMap = null; }
    }, 200);
});

/* =========================
   INITIAL LOAD
========================= */
document.addEventListener('DOMContentLoaded', () => {
    applyHeaderUI();
    fetchLogs();
});

/* =========================
   REAL-TIME UPDATE
   Re-fetch when topbar fires a tap (same tab)
========================= */
document.addEventListener('attendance_tapped', () => fetchLogs());
</script>
</body>
</html>