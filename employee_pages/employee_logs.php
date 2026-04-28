<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

date_default_timezone_set('Asia/Manila');

$today = date('Y-m-d');

$startDate = !empty($_GET['start']) ? date('Y-m-d', strtotime($_GET['start'])) : $today;
$endDate   = !empty($_GET['end'])   ? date('Y-m-d', strtotime($_GET['end']))   : $today;

$current_page = 'logs';
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Logs</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- CSS -->
    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link rel="stylesheet" href="employee_logs.css">
</head>

<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../topbar.php'; ?>

    <div class="recordBoxWrapper">
        <div class="recordBox">

            <!-- Filter Section -->
            <div class="filterWrapper">

                <div class="dateWrapper">
                    <input type="text" id="dateRangePicker" class="recordTitle" readonly>
                    <i class="bi bi-calendar3 dateIcon"></i>
                </div>

                <!-- Log Type Filter -->
                <div class="userDropdownWrapper logTypeDropdown">
                    <span class="userEmail dropdown-toggle" id="logTypeToggle">
                        All Types
                        <i class="bi bi-chevron-down logArrow"></i>
                    </span>
                    <div class="userDropdownMenu" id="logTypeMenu">
                        <div class="dropdownSection">
                            <a href="#" class="userDropdownItem" data-value="ALL">All Types</a>
                            <a href="#" class="userDropdownItem" data-value="IN">Time in</a>
                            <a href="#" class="userDropdownItem" data-value="OUT">Time out</a>
                            <a href="#" class="userDropdownItem" data-value="BREAK_IN">Break In</a>
                            <a href="#" class="userDropdownItem" data-value="BREAK_OUT">Break Out</a>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="logTypeFilter" value="ALL">
                <input type="hidden" id="startDate" value="<?= $startDate ?>">
                <input type="hidden" id="endDate" value="<?= $endDate ?>">
            </div>

            <!-- Table Header -->
            <div class="tableHeaderGlass">
                <table class="table table-borderless mb-0">
                    <thead>
                        <tr>
                            <th class="sortable active desc" data-sort="date">
                                Date <i class="bi bi-chevron-down sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="time">
                                Time <i class="bi bi-chevron-down sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="type">
                                Log Type <i class="bi bi-chevron-down sort-icon"></i>
                            </th>
                            <th class="sortable" data-sort="location">
                                Location <i class="bi bi-chevron-down sort-icon"></i>
                            </th>
                        </tr>
                    </thead>
                </table>
            </div>

            <!-- Body with scrolling -->
            <div class="tableScroll">
                <table class="table table-hover mb-0">
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
   DATE RESIZE FIX
========================= */
function resizeDateInput() {
    const input =
        document.querySelector(".flatpickr-input.active") ||
        document.querySelector(".flatpickr-input");
    if (!input) return;

    const wrapper = input.closest('.dateWrapper');
    if (!wrapper) return;

    const icon  = wrapper.querySelector('.dateIcon');
    const style = window.getComputedStyle(input);

    const mirror = document.createElement('span');
    document.body.appendChild(mirror);
    mirror.style.position   = 'absolute';
    mirror.style.visibility = 'hidden';
    mirror.style.whiteSpace = 'pre';
    mirror.style.font       = style.font;
    mirror.textContent      = input.value || '';

    const iconWidth     = icon ? icon.offsetWidth : 20;
    const computedWidth = mirror.offsetWidth + iconWidth + 40;
    const maxWidth      = wrapper.parentElement.offsetWidth * 0.6;

    wrapper.style.width = Math.min(computedWidth, maxWidth) + 'px';
    document.body.removeChild(mirror);
}

/* =========================
   FLATPICKR INIT
========================= */
const startInput = document.getElementById('startDate');
const endInput   = document.getElementById('endDate');

flatpickr("#dateRangePicker", {
    mode: "range",
    dateFormat: "Y-m-d",
    altInput: true,
    altFormat: "F j, Y",
    defaultDate: [startInput.value, endInput.value],

    onReady(selectedDates) {
        if (selectedDates.length === 0) {
            startInput.value = endInput.value = startInput.value;
        }
    },

    onChange(selectedDates) {
        if (selectedDates.length !== 2) return;

        const pad     = n => String(n).padStart(2, '0');
        const toLocal = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

        startInput.value = toLocal(selectedDates[0]);
        endInput.value   = toLocal(selectedDates[1]);

        fetchLogs();
        setTimeout(resizeDateInput, 0);
    }
});

/* =========================
   LOG TYPE FILTER
========================= */
const logTypeWrapper = document.querySelector('.logTypeDropdown');
const logTypeToggle  = document.getElementById('logTypeToggle');
const logTypeMenu    = document.getElementById('logTypeMenu');
const logTypeHidden  = document.getElementById('logTypeFilter');

let logTypeOpen = false;

logTypeToggle.addEventListener('mouseenter', () => logTypeMenu.classList.add('show'));
logTypeToggle.addEventListener('mouseleave', () => { if (!logTypeOpen) logTypeMenu.classList.remove('show'); });
logTypeMenu.addEventListener('mouseenter',   () => logTypeMenu.classList.add('show'));
logTypeMenu.addEventListener('mouseleave',   () => { if (!logTypeOpen) logTypeMenu.classList.remove('show'); });

logTypeToggle.addEventListener('click', e => {
    e.stopPropagation();
    logTypeOpen = !logTypeOpen;
    logTypeMenu.classList.toggle('show', logTypeOpen);
});

document.addEventListener('click', e => {
    if (!logTypeWrapper.contains(e.target)) {
        logTypeMenu.classList.remove('show');
        logTypeOpen = false;
    }
});

document.querySelectorAll('#logTypeMenu .userDropdownItem').forEach(item => {
    item.addEventListener('click', e => {
        e.preventDefault();
        logTypeToggle.textContent = item.textContent;
        logTypeHidden.value = item.dataset.value;
        logTypeMenu.classList.remove('show');
        logTypeOpen = false;
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
    document.querySelectorAll('.sortable').forEach(el => {
        el.classList.remove('active', 'asc', 'desc');
    });

    const activeTh = document.querySelector(`.sortable[data-sort="${sortColumn}"]`);
    if (activeTh) activeTh.classList.add('active', sortDirection);
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
    fetchLogs();
    resizeDateInput();
});
</script>
</body>
</html>