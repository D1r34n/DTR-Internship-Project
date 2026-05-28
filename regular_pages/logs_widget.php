<?php
// Expects $startDate and $endDate to be set by the including page.
// Optionally set $logsApiPath to override the default fetch URL.
// Optionally set $logsEmployeeId (int) to scope the widget to one employee (admin use).
$logsApiPath      ??= '../get_logs.php';
$logsEmployeeId   ??= null;
$logsInlineHeader ??= false;
?>
<div class="logs-widget-wrapper">
    <div class="logs-widget">

        <?php if (!$logsInlineHeader): ?>
        <!-- Filter Section -->
        <div class="logs-header">
            <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle" id="datePickerBtn" type="button">
                    <i class="bi bi-calendar3"></i>
                    <span id="dateRangeLabel">Today</span>
                </button>
            </div>

            <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle" type="button" id="logTypeToggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-funnel"></i>
                    <span id="logTypeLabel">All Types</span>
                </button>
                <ul class="dropdown-menu" id="logTypeMenu" style="max-height:340px;overflow-y:auto!important;overflow-x:hidden!important;">
                    <li><a class="dropdown-item" href="#" data-value="ALL">All Types</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-value="IN">Time In</a></li>
                    <li><a class="dropdown-item" href="#" data-value="OUT">Time Out</a></li>
                    <li><a class="dropdown-item" href="#" data-value="BREAK_IN">Break In</a></li>
                    <li><a class="dropdown-item" href="#" data-value="BREAK_OUT">Break Out</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-value="REQUEST_OT">Request OT</a></li>
                    <li><a class="dropdown-item" href="#" data-value="REQUEST_LEAVE">Request Leave</a></li>
                    <li><a class="dropdown-item" href="#" data-value="REQUEST_OB">Request OB</a></li>
                    <li><a class="dropdown-item" href="#" data-value="REQUEST_LOG_EDIT">Request Log Edit</a></li>
                    <li><a class="dropdown-item" href="#" data-value="REQUEST_CHANGE_SCHEDULE">Request Change Schedule</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-value="ADD_EMPLOYEE">Added Employee</a></li>
                    <li><a class="dropdown-item" href="#" data-value="EDIT_EMPLOYEE">Edited Employee</a></li>
                    <li><a class="dropdown-item" href="#" data-value="ADD_SCHEDULE">Added Schedule</a></li>
                    <li><a class="dropdown-item" href="#" data-value="EDIT_SCHEDULE">Edited Schedule</a></li>
                </ul>
            </div>

            <input type="hidden" id="logTypeFilter" value="ALL">
            <input type="hidden" id="startDate" value="<?= $startDate ?? date('Y-m-d') ?>">
            <input type="hidden" id="endDate"   value="<?= $endDate   ?? date('Y-m-d') ?>">
        </div>
        <?php endif; ?>

        <div class="table-scroll-wrapper">
            <table class="table table-hover mb-0">
                <colgroup id="logs_colgroup"></colgroup>
                <thead id="logs_thead">
                    <tr id="logs_header_row"></tr>
                </thead>
                <tbody id="logs_table_body"></tbody>
            </table>
            <div class="logs-empty" id="logsEmptyState" style="display:none">
                <i class="bi bi-calendar2-x-fill"></i>
                <div class="text-meta">No logs found for this period.</div>
            </div>

        </div>

    </div>
</div>

<!-- Map Hover Popup -->
<div class="mapPopUpContainer" id="map_pop_up_container">
    <div class="mapPopUp" id="map_pop_up"></div>
    <div class="mapPopUpInfo" id="map_pop_up_info"></div>
    <div style="padding:10px;">
        <a class="openGoogleMapsBtn" id="open_gmaps_btn" href="#" target="_blank">
            Open in Google Maps
        </a>
    </div>
</div>

<!-- Log Detail Modal -->
<div class="modal fade" id="logDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="ldm-type-pill-container"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="ldm-details" class="mb-0"></p>
                <div id="ldm-photo-container" style="display:none; margin-top:14px; text-align:center;">
                    <img id="ldm-photo" src="" alt="Attendance capture" class="cap-preview-img" style="max-width:100%; border-radius:8px;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
const LOGS_EMPLOYEE_ID = <?= $logsEmployeeId ? (int)$logsEmployeeId : 'null' ?>;

const tbody = document.getElementById('logs_table_body');

/* =========================
   SORTING
========================= */
const DEFAULT_SORT_COL = 'date';
const DEFAULT_SORT_DIR = 'desc';

let sortColumn    = DEFAULT_SORT_COL;
let sortDirection = DEFAULT_SORT_DIR;

/* =========================
   COLUMN DEFINITIONS
========================= */
const COLS = {
    employee: [
        { label: 'Date',         sort: 'date'     },
        { label: 'Time',         sort: 'time'     },
        { label: 'Log Type',     sort: 'type'     },
        { label: 'Location',     sort: 'location' },
        { label: 'Requested By'                   },
        { label: 'Edit Status'                    },
    ],
    superadmin: [
        { label: 'Date',         sort: 'date'     },
        { label: 'Time',         sort: 'time'     },
        { label: 'Employee'                       },
        { label: 'Role'                           },
        { label: 'Log Type',     sort: 'type'     },
        { label: 'Location',     sort: 'location' },
        { label: 'Requested By'                   },
        { label: 'Edit Status'                    },
    ],
    admin_scoped: [
        { label: 'Date',         sort: 'date'     },
        { label: 'Time',         sort: 'time'     },
        { label: 'Log Type',     sort: 'type'     },
        { label: 'Location',     sort: 'location' },
        { label: 'Requested By'                   },
        { label: 'Edit Status'                    },
        { label: ''                               },
    ],
};

function updateHeader(user_role, scoped_to_employee) {
    const deptScoped = user_role === 'manager' || user_role === 'workforce';
    const key = scoped_to_employee ? 'admin_scoped'
              : (user_role === 'superadmin' || user_role === 'admin' || deptScoped) ? 'superadmin'
              : 'employee';
    const cols = COLS[key];
    document.getElementById('logs_colgroup').innerHTML = '';
    document.getElementById('logs_header_row').innerHTML = cols.map(c =>
        c.sort
            ? `<th class="sortable" data-sort="${c.sort}">${c.label} <i class="bi bi-arrow-down-up sortIcon" id="sort-${c.sort}"></i></th>`
            : `<th>${c.label}</th>`
    ).join('');
}

/* =========================
   RENDER HELPERS
========================= */
function esc(v) {
    if (v == null) return '';
    return String(v)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

const LOG_TYPE_CLASS = {
    IN: 'btn-success', OUT: 'btn-danger', BREAK_IN: 'status-pending', BREAK_OUT: 'btn-info',
    REQUEST_OT:              'request-overtime',
    REQUEST_LEAVE:           'request-leave',
    REQUEST_OB:              'request-official-business',
    REQUEST_LOG_EDIT:        'request-log-edit',
    REQUEST_CHANGE_SCHEDULE: 'status-info',
    ADD_EMPLOYEE:            'status-approved',
    EDIT_EMPLOYEE:           'status-info',
    ADD_SCHEDULE:            'status-info',
    EDIT_SCHEDULE:           'status-info', 
};
const LOG_TYPE_LABEL = {
    IN: 'Time In', OUT: 'Time Out', BREAK_IN: 'Break In', BREAK_OUT: 'Break Out',
    REQUEST_OT:              'Request OT',
    REQUEST_LEAVE:           'Request Leave',
    REQUEST_OB:              'Request OB',
    REQUEST_LOG_EDIT:        'Request Log Edit',
    REQUEST_CHANGE_SCHEDULE: 'Request Change Schedule',
    ADD_EMPLOYEE:            'Added Employee',
    EDIT_EMPLOYEE:           'Edited Employee',
    ADD_SCHEDULE:            'Added Schedule',
    EDIT_SCHEDULE:           'Edited Schedule',
};

/* =========================
   RENDER ROWS
========================= */
function renderLogRows({ meta, rows }) {
    const { user_role, scoped_to_employee } = meta;
    updateHeader(user_role, scoped_to_employee);

    if (!rows.length) {
        tbody.innerHTML = '';
        document.getElementById('logsEmptyState').style.display = '';
        return;
    }
    document.getElementById('logsEmptyState').style.display = 'none';

    tbody.innerHTML = rows.map(row => {
        const isInside = row.is_within_office;
        const locLabel = isInside ? 'Within Office' : 'Outside Office';
        const locClass = isInside ? 'btn-success' : 'btn-danger';
        const acc      = row.accuracy        != null ? row.accuracy        : 'N/A';
        const dist     = row.distance_meters != null ? row.distance_meters : 'N/A';
        const mapUrl   = `https://www.google.com/maps?q=${row.latitude},${row.longitude}`;
        const typeClass = LOG_TYPE_CLASS[row.log_type] ?? '';
        const typeLabel = LOG_TYPE_LABEL[row.log_type] ?? row.log_type;

        let editRoleHtml = `<span style="color:rgba(255,255,255,0.15);font-size:0.75rem;">—</span>`;
        if (row.edit_role === 'self') {
            editRoleHtml = `<span class="pill"><i class="bi bi-person-fill"></i> You</span>`;
        } else if (row.edit_role) {
            const icon = row.edit_role === 'superadmin' ? 'bi-shield-fill' : 'bi-person-fill';
            editRoleHtml = `<span class="pill empRoleBadge empRole-${esc(row.edit_role)}"><i class="bi ${icon}"></i> ${esc(row.initiator_name ?? row.edit_role)}</span>`;
        }

        let editStatusHtml = `<span style="color:rgba(255,255,255,0.2);font-size:0.75rem;">—</span>`;
        if (row.edit_status === 'pending') {
            editStatusHtml = `<span class="pill btn-info"><i class="bi bi-hourglass-split"></i> Pending</span>`;
        } else if (row.edit_status === 'approved') {
            editStatusHtml = `<span class="pill btn-success"><i class="bi bi-check-circle-fill"></i> Approved</span>`;
        } else if (row.edit_status === 'rejected') {
            editStatusHtml = `<span class="pill btn-danger"><i class="bi bi-x-circle-fill"></i> Rejected</span>`;
        }

        let adminCols = '';
        if ((user_role === 'superadmin' || user_role === 'admin' || user_role === 'manager' || user_role === 'workforce') && !scoped_to_employee) {
            const roleLabel = row.employee_role
                ? row.employee_role.charAt(0).toUpperCase() + row.employee_role.slice(1)
                : '';
            adminCols = `
            <td><span class="empIdBadge"> ${esc(row.employee_name)}</td>
            <td><span class="empRoleBadge empRole-${esc(row.employee_role)}">${esc(roleLabel)}</span></td>`;
        }

        const BASE_LOG_TYPES = ['IN', 'OUT', 'BREAK_IN', 'BREAK_OUT'];

        let editBtnCol = '';
        if (scoped_to_employee) {
            const canEdit = ['superadmin', 'admin'].includes(user_role);
            const editBtn = BASE_LOG_TYPES.includes(row.log_type) && canEdit
                ? `<button class="leEditRowBtn" title="Edit log entry"
                        data-log-id="${row.log_id}"
                        data-log-type="${esc(row.log_type)}"
                        data-log-datetime="${esc(row.log_datetime)}"
                        data-log-date-label="${esc(row.date)}"
                        data-log-time-label="${esc(row.time)}"
                        onclick="openAdminLogEditModal(this)">
                        <i class="bi bi-pencil-fill"></i>
                    </button>`
                : '';
            editBtnCol = `<td>${editBtn}</td>`;
        }

        const hasPhoto = row.photo_path && (row.log_type === 'IN' || row.log_type === 'OUT');
        const typePill = hasPhoto
            ? `<span class="pill ${typeClass} log-detail-pill" role="button"
                     data-type-label="${esc(typeLabel)}"
                     data-type-class="${esc(typeClass)}"
                     data-details="${esc(row.details ?? '')}"
                     data-photo="${esc(row.photo_path)}">
                     <i class="bi bi-camera-fill" style="font-size:0.65rem;opacity:0.8;"></i> ${typeLabel}
               </span>`
            : `<span class="pill ${typeClass} log-detail-pill" role="button"
                     data-type-label="${esc(typeLabel)}"
                     data-type-class="${esc(typeClass)}"
                     data-details="${esc(row.details ?? '')}">
                     ${typeLabel}
               </span>`;

        const locCell = row.is_within_office === null
            ? `<span style="color:rgba(255,255,255,0.2);font-size:0.75rem;">—</span>`
            : `<span role="button" tabindex="0"
                    class="pill ${locClass} loc-trigger"
                    data-lat="${esc(row.latitude)}"
                    data-lng="${esc(row.longitude)}"
                    data-label="${esc(locLabel)}"
                    data-acc="${esc(acc)}"
                    data-dist="${esc(dist)}">
                    <i class="bi bi-geo-alt-fill"></i>
                    ${locLabel}
               </span>`;

        return `<tr>
            <td>${esc(row.date)}</td>
            <td>${esc(row.time)}</td>
            ${adminCols}
            <td>${typePill}</td>
            <td>${locCell}</td>
            <td>${editRoleHtml}</td>
            <td>${editStatusHtml}</td>
            ${editBtnCol}
        </tr>`;
    }).join('');

}

/* =========================
   FETCH LOGS
========================= */
function fetchLogs() {
    const start = document.getElementById('startDate').value;
    const end   = document.getElementById('endDate').value;
    const type  = document.getElementById('logTypeFilter').value;

    const empParam = LOGS_EMPLOYEE_ID ? `&employee_id=${LOGS_EMPLOYEE_ID}` : '';
    fetch(`<?= $logsApiPath ?>?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&type=${encodeURIComponent(type)}&sort=${sortColumn}&dir=${sortDirection}${empParam}`)
        .then(res => res.json())
        .then(data => renderLogRows(data));
}

/* =========================
   DATE LABEL HELPER
========================= */
const startInput     = document.getElementById('startDate');
const endInput       = document.getElementById('endDate');
const dateRangeLabel = document.getElementById('dateRangeLabel');

/* =========================
   LOCAL STORAGE STATE
========================= */
const LS_KEY = 'logs_state_v2_' + (LOGS_EMPLOYEE_ID !== null ? LOGS_EMPLOYEE_ID : 'global');

function saveState() {
    localStorage.setItem(LS_KEY, JSON.stringify({
        startDate:    startInput.value,
        endDate:      endInput.value,
        logType:      document.getElementById('logTypeFilter').value,
        logTypeLabel: document.getElementById('logTypeLabel').textContent,
        sortColumn,
        sortDirection,
    }));
}

function loadState() {
    try { return JSON.parse(localStorage.getItem(LS_KEY)); }
    catch { return null; }
}

const _savedState = loadState();
if (_savedState) {
    if (_savedState.sortColumn)              sortColumn        = _savedState.sortColumn;
    if (_savedState.sortDirection)           sortDirection     = _savedState.sortDirection;
    if (_savedState.startDate !== undefined) startInput.value  = _savedState.startDate;
    if (_savedState.endDate   !== undefined) endInput.value    = _savedState.endDate;
}

function fmtDate(d) {
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function toLocalStr(d) {
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function updateDateLabel(dates) {
    if (!dates.length) {
        dateRangeLabel.textContent = 'All Logs';
        return;
    }

    const today     = toLocalStr(new Date());
    const isSameDay = dates.length > 1 && dates[0].toDateString() === dates[1].toDateString();
    const isSingle  = dates.length === 1 || isSameDay;

    if (isSingle && toLocalStr(dates[0]) === today) {
        dateRangeLabel.textContent = 'Today';
        return;
    }

    dateRangeLabel.textContent = isSingle
        ? fmtDate(dates[0])
        : fmtDate(dates[0]) + ' – ' + fmtDate(dates[1]);
}

/* =========================
   FLATPICKR
========================= */
const fp = flatpickr(document.getElementById('datePickerBtn'), {
    mode: 'range',
    dateFormat: 'Y-m-d',
    defaultDate: (startInput.value && endInput.value) ? [startInput.value, endInput.value] : [],

    onChange(dates) {
        updateDateLabel(dates);
        if (dates.length !== 2) return;
        startInput.value = toLocalStr(dates[0]);
        endInput.value   = toLocalStr(dates[1]);
        fetchLogs();
        saveState();
    }
});

if (startInput.value && endInput.value) {
    updateDateLabel([new Date(startInput.value + 'T00:00'), new Date(endInput.value + 'T00:00')]);
} else {
    dateRangeLabel.textContent = 'All Logs';
}

/* =========================
   LOG TYPE FILTER
========================= */
const logTypeHidden = document.getElementById('logTypeFilter');
const logTypeLabel  = document.getElementById('logTypeLabel');

if (_savedState && _savedState.logType) {
    logTypeHidden.value      = _savedState.logType;
    logTypeLabel.textContent = _savedState.logTypeLabel || _savedState.logType;
}

document.querySelectorAll('#logTypeMenu .dropdown-item').forEach(item => {
    item.addEventListener('click', e => {
        e.preventDefault();
        logTypeLabel.textContent = item.textContent.trim();
        logTypeHidden.value = item.dataset.value;
        fetchLogs();
        saveState();
    });
});

/* =========================
   SORT HEADERS
========================= */
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

document.getElementById('logs_thead').addEventListener('click', e => {
    const th = e.target.closest('.sortable');
    if (!th) return;

    const col = th.dataset.sort;

    if (sortColumn === col) {
        if (sortDirection === 'asc') {
            sortDirection = 'desc';
        } else {
            sortColumn    = DEFAULT_SORT_COL;
            sortDirection = DEFAULT_SORT_DIR;
        }
    } else {
        sortColumn    = col;
        sortDirection = 'asc';
    }

    applyHeaderUI();
    fetchLogs();
    saveState();
});

/* =========================
   MAP POPUP
========================= */
let popupMap      = null;
let activeTrigger = null;
const mapPopup    = document.getElementById('map_pop_up_container');

function openMapPopup(trigger) {
    activeTrigger = trigger;

    const lat  = parseFloat(trigger.dataset.lat);
    const lng  = parseFloat(trigger.dataset.lng);
    const acc  = trigger.dataset.acc;
    const dist = trigger.dataset.dist;

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

    document.getElementById('map_pop_up_info').innerHTML =
        `<b>${trigger.dataset.label}</b><br>
         Lat: ${lat} &nbsp; Lng: ${lng}<br>
         Accuracy: ±${acc} m &nbsp; Distance: ${dist} m`;

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
}

function closeMapPopup() {
    mapPopup.style.display = 'none';
    if (popupMap) { popupMap.remove(); popupMap = null; }
    activeTrigger = null;
}

document.addEventListener('click', e => {
    const trigger = e.target.closest('.loc-trigger');

    if (!trigger && !e.target.closest('#map_pop_up_container')) {
        closeMapPopup();
        return;
    }

    if (!trigger) return;

    if (trigger === activeTrigger) {
        closeMapPopup();
    } else {
        openMapPopup(trigger);
    }
});

/* =========================
   MOUSE WHEEL SCROLL
   If cursor is over the horizontal scrollbar strip (bottom ~16 px),
   redirect wheel deltaY to horizontal scroll.
   Everywhere else, leave vertical scroll to the browser.
========================= */
(function () {
    const wrapper = tbody.closest('.table-scroll-wrapper');
    if (!wrapper) return;

    let nearHScrollbar = false;

    wrapper.addEventListener('mousemove', e => {
        const rect = wrapper.getBoundingClientRect();
        nearHScrollbar = e.clientY > rect.bottom - 16;
    });

    wrapper.addEventListener('mouseleave', () => { nearHScrollbar = false; });

    wrapper.addEventListener('wheel', e => {
        if (!nearHScrollbar) return;
        e.preventDefault();
        wrapper.scrollLeft += e.deltaY + e.deltaX;
    }, { passive: false });
})();

/* =========================
   LOG DETAIL PILL — CLICK TO MODAL
========================= */
(function () {
    const m = document.getElementById('logDetailModal');
    if (m) document.body.appendChild(m);
})();

document.addEventListener('click', e => {
    const pill = e.target.closest('.log-detail-pill');
    if (!pill) return;

    const typeLabel = pill.dataset.typeLabel;
    const typeClass = pill.dataset.typeClass;
    const details   = pill.dataset.details;
    const photo     = pill.dataset.photo;

    document.getElementById('ldm-type-pill-container').innerHTML =
        `<span class="pill ${typeClass}">${typeLabel}</span>`;
    document.getElementById('ldm-details').innerHTML = details
        ? details.split('\n').map(line =>
            /:\s*$/.test(line) ? `<span class="text-meta">${esc(line)}</span>` : esc(line)
          ).join('<br>')
        : '—';

    const photoContainer = document.getElementById('ldm-photo-container');
    const photoImg       = document.getElementById('ldm-photo');
    if (photo) {
        photoImg.src = `../assets/attendance_captures/${photo}`;
        photoContainer.style.display = '';
    } else {
        photoContainer.style.display = 'none';
        photoImg.src = '';
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('logDetailModal')).show();
});

/* =========================
   INIT
========================= */
applyHeaderUI();
fetchLogs();

document.addEventListener('attendance_tapped', () => fetchLogs());
</script>
