<?php
// Expects $startDate and $endDate to be set by the including page.
// Optionally set $logsApiPath to override the default fetch URL.
// Optionally set $logsEmployeeId (int) to scope the widget to one employee (admin use).
$logsApiPath    ??= '../get_logs.php';
$logsEmployeeId ??= null;
?>
<div class="card card-neutral logs-card">

    <div class="card-header logs-header">
        <div class="dropdown">
            <button class="btn dropdown-toggle" id="datePickerBtn" type="button">
                <i class="bi bi-calendar3"></i>
                <span id="dateRangeLabel">Today</span>
            </button>
        </div>

            <div class="dropdown">
                <button class="btn dropdown-toggle" type="button" id="logTypeToggle"
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
                    <li><a class="dropdown-item" href="#" data-value="REQUEST_CHANGE_SCHEDULE">Request Schedule Edit</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-value="ADD_EMPLOYEE">Added Employee</a></li>
                    <li><a class="dropdown-item" href="#" data-value="EDIT_EMPLOYEE">Edited Employee</a></li>
                    <li><a class="dropdown-item" href="#" data-value="DELETE_EMPLOYEE">Deleted Employee</a></li>
                    <li><a class="dropdown-item" href="#" data-value="ADD_SCHEDULE">Added Schedule</a></li>
                    <li><a class="dropdown-item" href="#" data-value="EDIT_SCHEDULE">Edited Schedule</a></li>
                    <li><a class="dropdown-item" href="#" data-value="DELETE_SCHEDULE">Deleted Schedule</a></li>
                    <li><a class="dropdown-item" href="#" data-value="ADD_DEPARTMENT">Added Department</a></li>
                    <li><a class="dropdown-item" href="#" data-value="EDIT_DEPARTMENT">Edited Department</a></li>
                    <li><a class="dropdown-item" href="#" data-value="DELETE_DEPARTMENT">Deleted Department</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-value="ADD_EVENT">Added Event</a></li>
                    <li><a class="dropdown-item" href="#" data-value="EDIT_EVENT">Edited Event</a></li>
                    <li><a class="dropdown-item" href="#" data-value="DELETE_EVENT">Deleted Event</a></li>
                </ul>
            </div>

        <input type="hidden" id="logTypeFilter" value="ALL">
        <input type="hidden" id="startDate" value="<?= $startDate ?? date('Y-m-d') ?>">
        <input type="hidden" id="endDate"   value="<?= $endDate   ?? date('Y-m-d') ?>">
    </div>

    <div class="card-body logs-card-body">
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

    <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2 logs-pagination-footer"
         id="logsPagContainer" style="display:none !important;">

        <div id="logsPaginationInfo"
             class="small text-meta text-nowrap flex-sm-fill w-sm-100 text-sm-start text-center order-1">
            Showing 0 to 0 of 0 entries
        </div>

        <div class="d-flex align-items-center justify-content-center flex-wrap gap-3 flex-sm-fill w-sm-100 order-2">
            <nav aria-label="Logs Navigation">
                <ul class="pagination pagination-sm mb-0" id="logsPaginationList"></ul>
            </nav>
            <div class="d-flex align-items-center gap-1 pag-jump-wrapper" id="logsPageJumpWrapper" style="display:none !important;">
                <small class="text-meta text-nowrap">Go to:</small>
                <input type="number" id="logsPageJumpInput"
                       class="form-control form-control-sm text-center px-1 pag-jump-input"
                       min="1" style="width:45px;height:28px;" placeholder="Go">
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-sm-end justify-content-center gap-2 flex-sm-fill w-sm-100 order-3">
            <small class="text-meta text-nowrap">Rows Per Page:</small>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                        id="logsRowsPerPageBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    25 rows
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item logs-row-limit-opt" href="#" data-value="10">10 rows</a></li>
                    <li><a class="dropdown-item logs-row-limit-opt" href="#" data-value="25">25 rows</a></li>
                    <li><a class="dropdown-item logs-row-limit-opt" href="#" data-value="50">50 rows</a></li>
                    <li><a class="dropdown-item logs-row-limit-opt" href="#" data-value="100">100 rows</a></li>
                </ul>
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

let logsCurrentPage = 1;
let logsRowsPerPage = 25;

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
        { label: 'Employee',     sort: 'employee' },
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
        { label: 'Action'                               },
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
            ? `<th class="sortable" data-sort="${c.sort}">${c.label} <i class="bi bi-filter sortIcon" id="sort-${c.sort}"></i></th>`
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
    IN: 'status-approved', OUT: 'status-rejected', BREAK_IN: 'status-pending', BREAK_OUT: 'status-info',
    REQUEST_OT:              'request-overtime',
    REQUEST_LEAVE:           'request-leave',
    REQUEST_OB:              'request-official-business',
    REQUEST_LOG_EDIT:        'request-log-edit',
    REQUEST_CHANGE_SCHEDULE: 'status-info',
    ADD_EMPLOYEE:            'status-approved',
    EDIT_EMPLOYEE:           'status-info',
    DELETE_EMPLOYEE:         'status-rejected',
    ADD_SCHEDULE:            'status-info',
    EDIT_SCHEDULE:           'status-info',
    DELETE_SCHEDULE:         'status-rejected',
    ADD_DEPARTMENT:          'status-approved',
    EDIT_DEPARTMENT:         'status-info',
    DELETE_DEPARTMENT:       'status-rejected',
    ADD_EVENT:               'status-approved',
    EDIT_EVENT:              'status-info',
    DELETE_EVENT:            'status-rejected',
}; /* v2 */
const LOG_TYPE_LABEL = {
    IN: 'Time In', OUT: 'Time Out', BREAK_IN: 'Break In', BREAK_OUT: 'Break Out',
    REQUEST_OT:              'Request OT',
    REQUEST_LEAVE:           'Request Leave',
    REQUEST_OB:              'Request OB',
    REQUEST_LOG_EDIT:        'Request Log Edit',
    REQUEST_CHANGE_SCHEDULE: 'Request Schedule Edit',
    ADD_EMPLOYEE:            'Added Employee',
    EDIT_EMPLOYEE:           'Edited Employee',
    DELETE_EMPLOYEE:         'Deleted Employee',
    ADD_SCHEDULE:            'Added Schedule',
    EDIT_SCHEDULE:           'Edited Schedule',
    DELETE_SCHEDULE:         'Deleted Schedule',
    ADD_DEPARTMENT:          'Added Department',
    EDIT_DEPARTMENT:         'Edited Department',
    DELETE_DEPARTMENT:       'Deleted Department',
    ADD_EVENT:               'Added Event',
    EDIT_EVENT:              'Edited Event',
    DELETE_EVENT:            'Deleted Event',
};

/* =========================
   RENDER ROWS
========================= */
function renderLogRows({ meta, rows, total = 0 }) {
    const { user_role, scoped_to_employee } = meta;
    updateHeader(user_role, scoped_to_employee);
    applyHeaderUI();

    const pagContainer = document.getElementById('logsPagContainer');

    if (!rows.length) {
        tbody.innerHTML = '';
        document.getElementById('logsEmptyState').style.display = '';
        pagContainer.setAttribute('style', 'display:none !important');
        return;
    }
    document.getElementById('logsEmptyState').style.display = 'none';

    const startEntry = (logsCurrentPage - 1) * logsRowsPerPage + 1;
    const endEntry   = Math.min(startEntry + logsRowsPerPage - 1, total);
    document.getElementById('logsPaginationInfo').textContent =
        `Showing ${startEntry} to ${endEntry} of ${total} entries`;

    const totalPages = Math.ceil(total / logsRowsPerPage);
    renderLogsPagination(totalPages);
    pagContainer.setAttribute('style', totalPages > 0 ? 'display:flex !important' : 'display:none !important');

    tbody.innerHTML = rows.map(row => {
        const isInside = row.is_within_office;
        const locLabel = isInside ? 'Within Office' : 'Outside Office';
        const locClass = isInside ? 'status-approved' : 'status-rejected';
        const acc      = row.accuracy        != null ? row.accuracy        : 'N/A';
        const dist     = row.distance_meters != null ? row.distance_meters : 'N/A';
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
            editStatusHtml = `<span class="pill status-pending"><i class="bi bi-hourglass-split"></i> Pending</span>`;
        } else if (row.edit_status === 'approved') {
            editStatusHtml = `<span class="pill status-approved"><i class="bi bi-check-circle-fill"></i> Approved</span>`;
        } else if (row.edit_status === 'rejected') {
            editStatusHtml = `<span class="pill status-rejected"><i class="bi bi-x-circle-fill"></i> Rejected</span>`;
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

        const hasPhoto  = row.photo_path && (row.log_type === 'IN' || row.log_type === 'OUT');
        const diffAttr  = row.diff_data ? `data-diff="${esc(JSON.stringify(row.diff_data))}"` : '';
        const empAttr   = row.emp_data  ? `data-emp="${esc(JSON.stringify(row.emp_data))}"` : '';
        const otAttr    = row.ot_data    ? `data-ot="${esc(JSON.stringify(row.ot_data))}"` : '';
        const leaveAttr = row.leave_data ? `data-leave="${esc(JSON.stringify(row.leave_data))}"` : '';
        const obAttr      = row.ob_data       ? `data-ob="${esc(JSON.stringify(row.ob_data))}"` : '';
        const logEditAttr = row.log_edit_data ? `data-logedit="${esc(JSON.stringify(row.log_edit_data))}"` : '';
        const deptAttr    = row.dept_data     ? `data-dept="${esc(JSON.stringify(row.dept_data))}"` : '';
        const eventAttr   = row.event_data   ? `data-event="${esc(JSON.stringify(row.event_data))}"` : '';
        const showLogIcon = !['IN', 'OUT', 'BREAK_IN', 'BREAK_OUT'].includes(row.log_type);
        const typePill = hasPhoto
            ? `<span class="pill ${typeClass} log-detail-pill" role="button"
                     data-type-label="${esc(typeLabel)}"
                     data-type-class="${esc(typeClass)}"
                     data-details="${esc(row.details ?? '')}"
                     data-photo="${esc(row.photo_path)}"
                     ${diffAttr} ${empAttr} ${otAttr} ${leaveAttr} ${obAttr} ${logEditAttr} ${deptAttr} ${eventAttr}>
                     <i class="bi bi-camera-fill" style="font-size:0.65rem;opacity:0.8;"></i> ${typeLabel}
               </span>`
            : `<span class="pill ${typeClass} log-detail-pill" role="button"
                     data-type-label="${esc(typeLabel)}"
                     data-type-class="${esc(typeClass)}"
                     data-details="${esc(row.details ?? '')}"
                     ${diffAttr} ${empAttr} ${otAttr} ${leaveAttr} ${obAttr} ${logEditAttr} ${deptAttr} ${eventAttr}>
                     ${showLogIcon ? '<i class="bi bi-file-earmark-bar-graph-fill" style="font-size:0.65rem;opacity:0.8;"></i> ' : ''}${typeLabel}
               </span>`;

        const MGMT_TYPES = ['ADD_EMPLOYEE','EDIT_EMPLOYEE','DELETE_EMPLOYEE','ADD_SCHEDULE','EDIT_SCHEDULE','DELETE_SCHEDULE'];
        const locCell = (row.is_within_office === null || row.is_within_office === false || MGMT_TYPES.includes(row.log_type))
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
    fetch(`<?= $logsApiPath ?>?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&type=${encodeURIComponent(type)}&sort=${sortColumn}&dir=${sortDirection}&page=${logsCurrentPage}&limit=${logsRowsPerPage}${empParam}`)
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
        rowsPerPage:  logsRowsPerPage,
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
    if (_savedState.rowsPerPage)             logsRowsPerPage   = _savedState.rowsPerPage;
}
document.getElementById('logsRowsPerPageBtn').textContent = `${logsRowsPerPage} rows`;

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
        logsCurrentPage  = 1;
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
        logsCurrentPage = 1;
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
        el.className = 'sortIcon bi bi-filter';
    });

    const activeTh = document.querySelector(`.sortable[data-sort="${sortColumn}"]`);
    if (activeTh) {
        activeTh.classList.add('sorted');
        const icon = activeTh.querySelector('.sortIcon');
        if (icon) icon.className = 'sortIcon bi ' + (sortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down');
    }
}

document.getElementById('logs_thead').addEventListener('click', e => {
    const th = e.target.closest('.sortable');
    if (!th) return;

    const col = th.dataset.sort;

    if (sortColumn === col) {
        if (sortDirection === 'desc') {
            sortDirection = 'asc';
        } else {
            sortColumn    = DEFAULT_SORT_COL;
            sortDirection = DEFAULT_SORT_DIR;
        }
    } else {
        sortColumn    = col;
        sortDirection = 'desc';
    }

    applyHeaderUI();
    logsCurrentPage = 1;
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
    let   diffData  = null;
    let   empData   = null;
    let   otData    = null;
    let   leaveData = null;
    let   obData    = null;
    try { if (pill.dataset.diff)  diffData  = JSON.parse(pill.dataset.diff);  } catch (_) {}
    try { if (pill.dataset.emp)   empData   = JSON.parse(pill.dataset.emp);   } catch (_) {}
    try { if (pill.dataset.ot)    otData    = JSON.parse(pill.dataset.ot);    } catch (_) {}
    try { if (pill.dataset.leave) leaveData = JSON.parse(pill.dataset.leave); } catch (_) {}
    try { if (pill.dataset.ob)      obData      = JSON.parse(pill.dataset.ob);      } catch (_) {}
    let   logEditData = null;
    try { if (pill.dataset.logedit) logEditData = JSON.parse(pill.dataset.logedit); } catch (_) {}
    let   deptData  = null;
    try { if (pill.dataset.dept)    deptData    = JSON.parse(pill.dataset.dept);    } catch (_) {}
    let   eventData = null;
    try { if (pill.dataset.event)   eventData   = JSON.parse(pill.dataset.event);   } catch (_) {}

    document.getElementById('ldm-type-pill-container').innerHTML =
        `<span class="pill ${typeClass}">${typeLabel}</span>`;

    if (otData) {
        const schedStart = otData.scheduled_start ?? '—';
        const schedEnd   = otData.scheduled_end   ?? '—';
        const timeIn     = otData.actual_time_in  ?? '—';
        const timeOut    = otData.actual_time_out  ?? '—';
        const lateMin    = otData.late_minutes     ?? 0;
        const earlyMin   = otData.early_minutes    ?? 0;
        const otMin      = otData.overtime_minutes ?? 0;
        const otStatus   = otData.overtime_status  ?? null;
        const reason     = otData.reason           ?? null;

        const earlyLateLabel = earlyMin > 0 ? 'Early:' : lateMin > 0 ? 'Late:' : '';
        const earlyLateValue = earlyMin > 0 ? `${earlyMin} min` : lateMin > 0 ? `${lateMin} min` : '—';

        let otValue = '—';
        if (otMin > 0) {
            const statusLabel = otStatus === 'approved' ? 'Approved'
                              : otStatus === 'rejected' ? 'Rejected'
                              : 'Pending';
            const statusClass = otStatus === 'approved' ? 'status-approved'
                              : otStatus === 'rejected' ? 'status-rejected'
                              : 'status-pending';
            otValue = `${otMin} min <span class="pill ${statusClass}" style="font-size:0.7rem;padding:1px 7px;margin-left:4px;">${statusLabel}</span>`;
        }

        document.getElementById('ldm-details').innerHTML = `
            <div class="ot-detail-grid">
                <div class="ot-cell">
                    <span class="text-meta">Scheduled:</span>
                    <div>${esc(schedStart)} – ${esc(schedEnd)}</div>
                </div>
                <div class="ot-cell">
                    <span class="text-meta">${earlyLateLabel}</span>
                    <div>${earlyLateValue}</div>
                </div>
                <div class="ot-cell">
                    <span class="text-meta">Time In:</span>
                    <div>${esc(timeIn)}</div>
                </div>
                <div class="ot-cell">
                    <span class="text-meta">Time Out:</span>
                    <div>${esc(timeOut)}</div>
                </div>
                <div class="ot-cell ot-cell-full">
                    <span class="text-meta">Overtime:</span>
                    <div>${otValue}</div>
                </div>
                <div class="ot-cell ot-cell-full">
                    <span class="text-meta">Reason:</span>
                    <div style="white-space:pre-wrap;">${esc(reason ?? '—')}</div>
                </div>
            </div>`;
    } else if (leaveData) {
        const leaveType = leaveData.leave_type ?? '—';
        const dates     = Array.isArray(leaveData.dates) ? leaveData.dates : [];
        const reason    = leaveData.reason ?? null;

        const datesHtml = dates.length
            ? dates.map(d => `<div>${esc(d)}</div>`).join('')
            : '<div>—</div>';

        const reasonHtml = `
            <div class="ot-cell ot-cell-full">
                <span class="text-meta">Reason:</span>
                <div style="white-space:pre-wrap;">${esc(reason ?? '—')}</div>
            </div>`;

        document.getElementById('ldm-details').innerHTML = `
            <div class="ot-detail-grid">
                <div class="ot-cell ot-cell-full">
                    <span class="text-meta">Leave Type:</span>
                    <div>${esc(leaveType)}</div>
                </div>
                <div class="ot-cell ot-cell-full">
                    <span class="text-meta">Leave Schedule:</span>
                    <div class="leave-dates-list">${datesHtml}</div>
                </div>
                ${reasonHtml}
            </div>`;
    } else if (obData) {
        const dates      = Array.isArray(obData.dates) ? obData.dates : [];
        const clientName = obData.client_name ?? '—';
        const reason     = obData.reason      ?? '—';

        const datesHtml = dates.length
            ? dates.map(d => `<div>${esc(d)}</div>`).join('')
            : '<div>—</div>';

        document.getElementById('ldm-details').innerHTML = `
            <div class="ot-detail-grid">
                <div class="ot-cell">
                    <span class="text-meta">OB Date:</span>
                    <div class="leave-dates-list">${datesHtml}</div>
                </div>
                <div class="ot-cell">
                    <span class="text-meta">Client:</span>
                    <div>${esc(clientName)}</div>
                </div>
                <div class="ot-cell ot-cell-full">
                    <span class="text-meta">Reason:</span>
                    <div style="white-space:pre-wrap;">${esc(reason)}</div>
                </div>
            </div>`;
    } else if (logEditData) {
        const logType      = logEditData.log_type          ?? '—';
        const originalTime = logEditData.original_log_time ?? '—';
        const proposedTime = logEditData.proposed_log_time ?? '—';
        const reason       = logEditData.reason            ?? '—';

        document.getElementById('ldm-details').innerHTML = `
            <div class="ot-detail-grid">
                <div class="ot-cell">
                    <span class="text-meta">Log Type:</span>
                    <div>${esc(logType)}</div>
                </div>
                <div class="ot-cell">
                    <span class="text-meta">Original Time:</span>
                    <div>${esc(originalTime)}</div>
                </div>
                <div class="ot-cell ot-cell-full">
                    <span class="text-meta">Proposed Time:</span>
                    <div>${esc(proposedTime)}</div>
                </div>
                <div class="ot-cell ot-cell-full">
                    <span class="text-meta">Reason:</span>
                    <div style="white-space:pre-wrap;">${esc(reason)}</div>
                </div>
            </div>`;
    } else if (deptData) {
        const deptName   = deptData.department_name ?? '—';
        const deptCode   = deptData.department_code ?? '—';
        const parentName = deptData.parent_name     ?? null;
        const color      = deptData.color           ?? null;

        const parentHtml = parentName
            ? `<div class="ot-cell ot-cell-full">
                   <span class="text-meta">Parent Department:</span>
                   <div>${esc(parentName)}</div>
               </div>`
            : '';

        document.getElementById('ldm-details').innerHTML = `
            <div class="ot-detail-grid">
                <div class="ot-cell">
                    <span class="text-meta">Department:</span>
                    <div>${esc(deptName)}</div>
                </div>
                <div class="ot-cell">
                    <span class="text-meta">Code:</span>
                    <div>${esc(deptCode)}</div>
                </div>
                ${parentHtml}
            </div>`;
    } else if (eventData) {
        const evTitle = eventData.title          ?? '—';
        const evType  = eventData.event_type     ?? '—';
        const evDate  = eventData.start_datetime ?? '—';
        const evDesc  = eventData.description    ?? null;

        const descHtml = `
            <div class="ot-cell ot-cell-full">
                <span class="text-meta">Description:</span>
                <div style="white-space:pre-wrap;">${esc(evDesc ?? '—')}</div>
            </div>`;

        document.getElementById('ldm-details').innerHTML = `
            <div class="ot-detail-grid">
                <div class="ot-cell ot-cell-full">
                    <span class="text-meta">Title:</span>
                    <div>${esc(evTitle)}</div>
                </div>
                <div class="ot-cell">
                    <span class="text-meta">Type:</span>
                    <div>${esc(evType)}</div>
                </div>
                <div class="ot-cell">
                    <span class="text-meta">Date:</span>
                    <div>${esc(evDate)}</div>
                </div>
                ${descHtml}
            </div>`;
    } else if (empData && Array.isArray(empData)) {
        const cells = empData.map(([label, value]) => `
            <div class="emp-info-cell">
                <span class="text-meta">${esc(label)}:</span>
                <div>${esc(value)}</div>
            </div>`).join('');
        document.getElementById('ldm-details').innerHTML =
            `<div class="emp-info-grid">${cells}</div>`;
    } else if (diffData && typeof diffData === 'object') {
        const entries = Object.entries(diffData);
        const items = entries.map(([label, change]) => `
            <div class="edit-diff-item">
                <div class="edit-diff-field-label">${esc(label)}</div>
                <span class="text-meta">Before:</span>
                <div>${esc(change.before ?? '—')}</div>
                <span class="text-meta">After:</span>
                <div>${esc(change.after ?? '—')}</div>
            </div>`).join('');
        document.getElementById('ldm-details').innerHTML =
            `<div class="edit-diff-grid">${items}</div>`;
    } else {
        const content = details || typeLabel || '';
        document.getElementById('ldm-details').innerHTML = content
            ? content.split('\n').map(line =>
                /:\s*$/.test(line) ? `<span class="text-meta">${esc(line)}</span>` : esc(line)
              ).join('<br>')
            : '—';
    }

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
   PAGINATION
========================= */
function renderLogsPagination(totalPages) {
    const list        = document.getElementById('logsPaginationList');
    const jumpInput   = document.getElementById('logsPageJumpInput');
    const jumpWrapper = document.getElementById('logsPageJumpWrapper');

    list.innerHTML = '';

    if (totalPages <= 1) {
        jumpWrapper?.style.setProperty('display', 'none', 'important');
        return;
    }
    jumpWrapper?.setAttribute('style', 'display:flex !important');

    if (jumpInput) { jumpInput.max = totalPages; jumpInput.value = logsCurrentPage; }

    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${logsCurrentPage === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#">&laquo;</a>`;
    if (logsCurrentPage > 1) {
        prevLi.addEventListener('click', e => { e.preventDefault(); logsCurrentPage--; fetchLogs(); });
    }
    list.appendChild(prevLi);

    const maxVisible = 5;
    let startPage = Math.max(1, logsCurrentPage - 2);
    let endPage   = Math.min(totalPages, logsCurrentPage + 2);
    if (logsCurrentPage <= 3)              endPage   = Math.min(totalPages, maxVisible);
    if (logsCurrentPage > totalPages - 3)  startPage = Math.max(1, totalPages - maxVisible + 1);

    if (startPage > 1) { appendLogsPage(1); if (startPage > 2) appendLogsEllipsis(); }
    for (let i = startPage; i <= endPage; i++) appendLogsPage(i);
    if (endPage < totalPages) { if (endPage < totalPages - 1) appendLogsEllipsis(); appendLogsPage(totalPages); }

    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${logsCurrentPage === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#">&raquo;</a>`;
    if (logsCurrentPage < totalPages) {
        nextLi.addEventListener('click', e => { e.preventDefault(); logsCurrentPage++; fetchLogs(); });
    }
    list.appendChild(nextLi);

    function appendLogsPage(n) {
        const li = document.createElement('li');
        li.className = `page-item ${logsCurrentPage === n ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#">${n}</a>`;
        li.addEventListener('click', e => { e.preventDefault(); logsCurrentPage = n; fetchLogs(); });
        list.appendChild(li);
    }
    function appendLogsEllipsis() {
        const li = document.createElement('li');
        li.className = 'page-item disabled';
        li.innerHTML = `<span class="page-link text-meta">...</span>`;
        list.appendChild(li);
    }
}

document.getElementById('logsPageJumpInput').addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    let target = parseInt(this.value);
    const max  = parseInt(this.max) || 1;
    if (isNaN(target) || target < 1) target = 1;
    if (target > max) target = max;
    this.value      = target;
    logsCurrentPage = target;
    fetchLogs();
});

document.getElementById('logsPagContainer').addEventListener('click', e => {
    const opt = e.target.closest('.logs-row-limit-opt');
    if (!opt) return;
    e.preventDefault();
    logsRowsPerPage = parseInt(opt.dataset.value);
    logsCurrentPage = 1;
    document.getElementById('logsRowsPerPageBtn').textContent = `${logsRowsPerPage} rows`;
    saveState();
    fetchLogs();
});

/* =========================
   INIT
========================= */
applyHeaderUI();
fetchLogs();

document.addEventListener('attendance_tapped', () => fetchLogs());
</script>
