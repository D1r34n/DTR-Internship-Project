<?php
/*
 * ================================================
 * LOGS WIDGET
 * ================================================
 * Reusable logs table widget with date filter,
 * log type filter, sorting, and map popup.
 *
 * Required before include:
 *   $logsApiUrl  — fetch endpoint e.g. '../get_logs.php'
 *   $startDate   — Y-m-d string or ''
 *   $endDate     — Y-m-d string or ''
 *
 * Optional before include:
 *   $logsTitle   — string, defaults to 'Logs'
 *   $showTypes   — array of log types to show in filter
 *                  defaults to all: IN, OUT, BREAK_IN, BREAK_OUT
 *
 * Usage:
 *   $logsApiUrl = '../get_logs.php';
 *   $startDate  = '';
 *   $endDate    = '';
 *   include '../widgets/logs_widget.php';
 * ================================================
 */

$logsTitle  = $logsTitle  ?? 'Logs';
$logsApiUrl = $logsApiUrl ?? '../get_logs.php';
$startDate  = $startDate  ?? '';
$endDate    = $endDate    ?? '';
$showTypes  = $showTypes  ?? [
    'ALL'       => 'All Types',
    'IN'        => 'Time In',
    'OUT'       => 'Time Out',
    'BREAK_IN'  => 'Break In',
    'BREAK_OUT' => 'Break Out',
];
?>

<div class="card card-glass logs-card">
    <div class="card-body d-flex flex-column logs-card-body">

        <!-- Filter Section -->
        <div class="filter-wrapper">

            <!-- Date Range Picker -->
            <div class="dropdown">
                <button class="btn dropdown-toggle" id="logs-date-picker-btn" type="button">
                    <i class="bi bi-calendar3"></i>
                    <span id="logs-date-range-label">Today</span>
                </button>
            </div>

            <!-- Log Type Filter -->
            <div class="dropdown">
                <button class="btn dropdown-toggle" type="button"
                        id="logs-type-toggle"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                    <i class="bi bi-funnel"></i>
                    <span id="logs-type-label">All Types</span>
                </button>
                <ul class="dropdown-menu" id="logs-type-menu">
                    <?php foreach ($showTypes as $value => $label): ?>
                        <li>
                            <a class="dropdown-item" href="#" data-value="<?= htmlspecialchars($value) ?>">
                                <?= htmlspecialchars($label) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <input type="hidden" id="logs-type-filter" value="ALL">
            <input type="hidden" id="logs-start-date" value="<?= htmlspecialchars($startDate) ?>">
            <input type="hidden" id="logs-end-date"   value="<?= htmlspecialchars($endDate) ?>">
        </div>

        <!-- Table Header -->
        <div class="table-header-glass">
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

        <!-- Scrollable Body -->
        <div class="table-scroll">
            <table class="table table-hover mb-0">
                <tbody id="logs-table-body">
                    <!-- populated by fetchLogs() -->
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Map Hover Popup -->
<div class="map-popup-container" id="map-popup-container">
    <div class="map-popup"      id="map-popup"></div>
    <div class="map-popup-info" id="map-popup-info"></div>
    <div style="padding: 10px;">
        <a class="open-gmaps-btn" id="open-gmaps-btn" href="#" target="_blank">
            Open in Google Maps
        </a>
    </div>
</div>

<!-- JAVASCRIPT -->
<script>
(function () {
    /* ================================================
       CONFIG — injected from PHP
       ================================================ */
    const LOGS_API_URL = <?= json_encode($logsApiUrl) ?>;

    /* ================================================
       ELEMENT REFS
       ================================================ */
    const tbody          = document.getElementById('logs-table-body');
    const startInput     = document.getElementById('logs-start-date');
    const endInput       = document.getElementById('logs-end-date');
    const datePickerBtn  = document.getElementById('logs-date-picker-btn');
    const dateRangeLabel = document.getElementById('logs-date-range-label');
    const typeHidden     = document.getElementById('logs-type-filter');
    const typeLabel      = document.getElementById('logs-type-label');
    const mapPopup       = document.getElementById('map-popup-container');

    /* ================================================
       SORTING STATE
       ================================================ */
    const DEFAULT_SORT_COL = 'date';
    const DEFAULT_SORT_DIR = 'desc';

    let sortColumn    = DEFAULT_SORT_COL;
    let sortDirection = DEFAULT_SORT_DIR;

    /* ================================================
       FETCH LOGS
       ================================================ */
    function fetchLogs() {
        const start = startInput.value;
        const end   = endInput.value;
        const type  = typeHidden.value;

        const url = `${LOGS_API_URL}?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&type=${encodeURIComponent(type)}&sort=${sortColumn}&dir=${sortDirection}`;

        fetch(url)
            .then(res => res.text())
            .then(html => { tbody.innerHTML = html; })
            .catch(err => console.error('fetchLogs error:', err));
    }

    /* ================================================
       DATE RANGE LABEL
       ================================================ */
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

    /* ================================================
       FLATPICKR
       ================================================ */
    flatpickr(datePickerBtn, {
        mode:        'range',
        dateFormat:  'Y-m-d',
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

    /* ================================================
       LOG TYPE FILTER
       ================================================ */
    document.querySelectorAll('#logs-type-menu .dropdown-item').forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            typeLabel.textContent = item.textContent.trim();
            typeHidden.value      = item.dataset.value;
            fetchLogs();
        });
    });

    /* ================================================
       SORTING
       ================================================ */
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
                    sortDirection = 'desc';
                } else {
                    sortColumn    = DEFAULT_SORT_COL;
                    sortDirection = DEFAULT_SORT_DIR;
                }
            } else {
                sortColumn    = column;
                sortDirection = 'asc';
            }

            applyHeaderUI();
            fetchLogs();
        });
    });

    /* ================================================
       MAP POPUP
       ================================================ */
    let popupMap    = null;
    let hideTimeout = null;

    document.addEventListener('mouseover', e => {
        const trigger = e.target.closest('.loc-trigger');
        if (!trigger) return;

        clearTimeout(hideTimeout);

        const lat   = parseFloat(trigger.dataset.lat);
        const lng   = parseFloat(trigger.dataset.lng);
        const label = trigger.dataset.label;
        const acc   = trigger.dataset.acc;
        const dist  = trigger.dataset.dist;

        document.getElementById('open-gmaps-btn').href = `https://www.google.com/maps?q=${lat},${lng}`;

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

        document.getElementById('map-popup-info').innerHTML = `
            <b>${label}</b><br>
            Latitude: ${lat} &nbsp;&nbsp; Longitude: ${lng}<br>
            Accuracy: ±${acc} m &nbsp; Distance: ${dist} m
        `;

        setTimeout(() => {
            if (!popupMap) {
                popupMap = L.map('map-popup', { zoomControl: false, attributionControl: false });
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

    /* ================================================
       INITIAL LOAD + REAL-TIME UPDATE
       ================================================ */
    document.addEventListener('DOMContentLoaded', () => fetchLogs());
    document.addEventListener('attendance_tapped', () => fetchLogs());

    /* Expose fetchLogs globally so topbar can call it */
    window.fetchLogs = fetchLogs;

})();
</script>