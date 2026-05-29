<?php
/*
 * Schedules Widget
 *
 * Variables set by the including page:
 *   $schedApiPath        — URL for get_schedule.php  (employee / admin-global source)
 *   $schedCalEvents      — array of pre-fetched calendar events (holidays etc.)
 *   $schedBirthdayEvents — array of pre-fetched birthday events (admin-global only)
 *   $schedEventApiPath   — POST endpoint for event CRUD (admin-global view)
 *   $schedCurrentMonth   — current month as YYYY-MM
 *   $schedInitialDate    — calendar initial date as YYYY-MM-DD
 *
 *   $schedEmployeeId     — int|null  (null = global view, int = scoped to one employee)
 *   $schedEmpUrlId       — string employee_id for URLs (admin-scoped only)
 *   $schedCalApiPath     — URL for get_schedule.php with employee_id (admin-scoped only)
 *   $schedSaveApiPath    — POST endpoint for schedule save (admin-scoped only)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
$isAdmin     = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin';
$isWorkforce = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'workforce';

$schedApiPath        ??= '../get_schedule.php';
$schedCalEvents      ??= [];
$schedBirthdayEvents ??= [];
$schedEventApiPath   ??= 'schedules_page.php';
$schedCurrentMonth   ??= date('Y-m');
$schedInitialDate    ??= date('Y-m-01');

$schedEmployeeId     ??= null;
$schedEmpUrlId       ??= null;
$schedCalApiPath     ??= '../get_schedule.php';
$schedSaveApiPath    ??= '';

$isScoped = $schedEmployeeId !== null;
?>

<div class="schedules-widget">

    <?php if ($isScoped): ?>
    <!-- ── Admin scoped header: month nav + chip + manage btn ── -->
    <div class="sw-header">
        <div class="d-flex align-items-center gap-2">
            <button class="sched-nav-btn" onclick="swPrev()" title="Previous month">
                <i class="bi bi-chevron-left"></i>
            </button>
            <span class="sched-month-label" id="sw-month-label">
                <?= date('F Y', strtotime($schedInitialDate)) ?>
            </span>
            <button class="sched-nav-btn" onclick="swNext()" title="Next month">
                <i class="bi bi-chevron-right"></i>
            </button>
            <button class="sched-nav-btn" onclick="swToday()" title="Go to today"
                    style="font-size:0.65rem;width:auto;padding:0 8px;letter-spacing:0.03em;">
                Today
            </button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span id="sw-chip-sched-count" class="tab-summary-chip" style="color:var(--text-muted);">
                — scheduled days
            </span>
            <button class="btn btn-success sched-add-btn" type="button" id="sw-btn-manage-schedule">
                <i class="bi bi-plus-lg"></i> Manage Schedule
            </button>
        </div>
    </div>

    <?php elseif ($isAdmin): ?>
    <!-- ── Admin global header: btn moved into FC toolbar by JS ── -->
    <div class="shiftLegend" style="display:none">
        <button id="sw-add-event-btn" class="btn btn-sm btn-add-event"
                data-bs-toggle="modal"
                data-bs-target="#swAddEventModal"
                style="display:none">
            <i class="bi bi-plus-lg"></i> Add Event
        </button>
    </div>

    <!-- filter select; moved into FC toolbar by JS after render -->
    <select id="sw-schedule-filter" class="schedule-filter-select" style="display:none">
        <option value="all">All</option>
        <option value="events">All Events</option>
        <option value="leave">On Leave</option>
        <option value="ob">On OB</option>
        <option value="birthday">Birthday</option>
        <option value="holiday">Holiday</option>
        <option value="meeting">Meeting</option>
        <option value="announcement">Announcement</option>
        <option value="party">Party</option>
        <option value="other">Other</option>
    </select>

    <?php else: ?>
    <!-- ── Employee header: shift legend ── -->
    <div class="shiftLegend">
        <div class="shiftLegendItem"><div class="shiftLegendDot day"></div> Day Shift</div>
        <div class="shiftLegendItem"><div class="shiftLegendDot night"></div> Night Shift</div>
        <div class="shiftLegendItem"><div class="shiftLegendDot night-cont"></div> Night Shift (cont.)</div>
        <div class="shiftLegendItem"><div class="shiftLegendDot rest"></div> Rest Day</div>
        <div class="shiftLegendItem"><div class="shiftLegendDot leave-approved"></div> On Leave</div>
        <div class="shiftLegendItem"><div class="shiftLegendDot ob-approved"></div> On OB</div>
        <div class="shiftLegendItem"><div class="shiftLegendDot leave-pending"></div> Leave/OB Pending</div>
        <div class="shiftLegendItem"><div class="shiftLegendDot leave-rejected"></div> Leave/OB Rejected</div>
    </div>

    <!-- filter select; moved into FC toolbar by JS after render -->
    <select id="sw-schedule-filter" class="schedule-filter-select" style="display:none">
        <option value="all">All</option>
        <option value="events">All Events</option>
        <option value="leave">On Leave</option>
        <option value="ob">On OB</option>
        <option value="birthday">Birthday</option>
        <option value="holiday">Holiday</option>
        <option value="meeting">Meeting</option>
        <option value="announcement">Announcement</option>
        <option value="party">Party</option>
        <option value="other">Other</option>
    </select>
    <?php endif; ?>

    <!-- ── Calendar ── -->
    <div id="sw-cal-wrapper">
        <div id="sw-cal-loading">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading…</span>
            </div>
        </div>
        <div id="sw-calendar"></div>
    </div>

</div><!-- .schedules-widget -->


<?php if ($isScoped): ?>
<!-- ═══════════════════════════════════════════════════
     MANAGE SCHEDULE MODAL  (admin scoped view)
════════════════════════════════════════════════════ -->
<div class="modal fade" id="swManageScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-modal">

            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-week me-2"></i>Manage Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="<?= htmlspecialchars($schedSaveApiPath) ?>" id="swAddSchedForm">
                <input type="hidden" name="action"           value="save_combined">
                <input type="hidden" name="employee_id"      value="<?= (int)$schedEmployeeId ?>">
                <input type="hidden" name="selected_dates"   id="swAddSelectedDatesInput">
                <input type="hidden" name="rest_days"        id="swRestDaysInput" value="[]">
                <input type="hidden" name="rest_days_dirty"  id="swRestDaysDirty" value="0">
                <input type="hidden" name="single_rest_dates" id="swSingleRestDatesInput" value="[]">

                <div class="modal-body">

                    <!-- Preset Schedule -->
                    <div class="preset-sched-dropdown-wrap mb-3">
                        <label class="form-label">Preset Schedule</label>
                        <button type="button" class="preset-sched-trigger" id="swPresetSchedTrigger">
                            <span id="swPresetSchedDisplay">Select a preset schedule…</span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="preset-sched-menu" id="swPresetSchedMenu"></div>
                        <script>
                        (function () {
                            const menu = document.getElementById('swPresetSchedMenu');
                            const startMin = 6 * 60, endMin = 24 * 60 + 5 * 60 + 30;
                            function fmt(h, m) {
                                const p = h >= 12 ? 'PM' : 'AM';
                                const dh = h % 12 || 12;
                                return `${dh}:${String(m).padStart(2,'0')} ${p}`;
                            }
                            function to24(h, m) { return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`; }
                            for (let t = startMin; t <= endMin; t += 30) {
                                const cm = t % (24 * 60);
                                const ih = Math.floor(cm / 60), im = cm % 60;
                                const ot = (cm + 9 * 60) % (24 * 60);
                                const oh = Math.floor(ot / 60), om = ot % 60;
                                const el = document.createElement('div');
                                el.className = 'preset-sched-item';
                                el.dataset.in  = to24(ih, im);
                                el.dataset.out = to24(oh, om);
                                el.textContent = `${fmt(ih,im)} – ${fmt(oh,om)}`;
                                menu.appendChild(el);
                            }
                        })();
                        </script>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Time In</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                <input type="time" name="time_in" id="swAddModalTimeIn" class="form-control">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Time Out <small class="text-muted">(next day if night)</small></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                <input type="time" name="time_out" id="swAddModalTimeOut" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Select Dates</label>
                        <input type="text" id="swAddSchedDatePicker" class="form-control"
                               placeholder="Click to select dates…" readonly>
                        <div id="swAddSelectedDatesList" class="mt-2"></div>
                    </div>

                    <div id="swRestDaySection">
                        <label class="form-label">Set Rest Days</label>
                        <div class="rest-day-grid" id="swRestDayToggles">
                            <button type="button" class="btn rest-day-toggle" data-dow="0">Sun</button>
                            <button type="button" class="btn rest-day-toggle" data-dow="1">Mon</button>
                            <button type="button" class="btn rest-day-toggle" data-dow="2">Tue</button>
                            <button type="button" class="btn rest-day-toggle" data-dow="3">Wed</button>
                            <button type="button" class="btn rest-day-toggle" data-dow="4">Thu</button>
                            <button type="button" class="btn rest-day-toggle" data-dow="5">Fri</button>
                            <button type="button" class="btn rest-day-toggle" data-dow="6">Sat</button>
                        </div>
                    </div>

                    <div id="swSingleDateRestDaySection" style="display:none;">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="swIsSingleRestDay">
                            <label class="form-check-label" for="swIsSingleRestDay">Is Rest Day</label>
                        </div>
                    </div>

                </div><!-- .modal-body -->

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check-circle-fill me-1"></i>
                        <?= $isWorkforce ? 'Submit for Approval' : 'Save Schedule' ?>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     EDIT SCHEDULE MODAL  (admin scoped view)
════════════════════════════════════════════════════ -->
<div class="modal fade" id="swEditSchedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-modal">

            <div class="modal-header">
                <h5 class="modal-title" id="swSchedModalTitle">Edit Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="<?= htmlspecialchars($schedSaveApiPath) ?>" id="swEditSchedForm">
                <input type="hidden" name="action"         value="save_schedule">
                <input type="hidden" name="employee_id"    id="swModalEmpId" value="<?= (int)$schedEmployeeId ?>">
                <input type="hidden" name="selected_dates" id="swSelectedDatesInput">
                <input type="hidden" name="is_edit"        id="swIsEditMode" value="0">
                <input type="hidden" name="is_rest_day"    id="swModalIsRestDay" value="0">

                <div class="modal-body">

                    <div class="form-check mb-3" id="swRestDayCheckRow">
                        <input class="form-check-input" type="checkbox" id="swModalRestDayCheck">
                        <label class="form-check-label" for="swModalRestDayCheck">Mark as Rest Day</label>
                    </div>

                    <div id="swModalTimeFields" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Time In</label>
                            <input type="time" name="time_in" id="swModalTimeIn" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time Out <small class="text-muted">(next day if night)</small></label>
                            <input type="time" name="time_out" id="swModalTimeOut" class="form-control" required>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Selected Date</label>
                        <p class="text-muted small mb-2">Click to select a date.</p>
                        <input type="text" id="swEditSchedDatePicker" class="form-control" readonly>
                        <div id="swSelectedDatesList" class="mt-2"></div>
                    </div>

                </div><!-- .modal-body -->

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle-fill"></i>
                        <span id="swSchedSubmitLabel">Update Schedule</span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<?php elseif ($isAdmin): ?>
<!-- ═══════════════════════════════════════════════════
     ADD / EDIT EVENT MODAL  (admin global view)
════════════════════════════════════════════════════ -->
<div class="modal fade" id="swAddEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="swAddEventModalLabel">
                    <i class="bi bi-calendar-plus me-2"></i>
                    <span id="swAddEventModalTitleText">Add Event</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="swEvtId">
                <div id="swAddEventError" class="alert alert-danger d-none"></div>

                <div class="mb-3">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" id="swEvtTitle" class="form-control" placeholder="Event title">
                </div>
                <div class="mb-3">
                    <label class="form-label">Event Type</label>
                    <select id="swEvtType" class="form-select">
                        <option value="holiday">Holiday</option>
                        <option value="party">Party</option>
                        <option value="meeting">Meeting</option>
                        <option value="announcement">Announcement</option>
                        <option value="other" selected>Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Select Date <span class="text-danger">*</span></label>
                    <input type="text" id="swEvtDate" class="form-control" placeholder="Select date" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description <small class="text-muted">(optional)</small></label>
                    <textarea id="swEvtDescription" class="form-control" rows="2"
                              placeholder="Short description…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="swSaveEventBtn">
                    <i class="bi bi-check-lg me-1"></i>Save Event
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     VIEW EVENT MODAL  (admin global view)
════════════════════════════════════════════════════ -->
<div class="modal fade" id="swViewEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="swViewEvtTitle">Event Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <small class="text-meta">Type</small>
                    <div id="swViewEvtTypeBadge" class="fw-semibold mt-1"></div>
                </div>
                <div class="mb-2">
                    <small class="text-meta">Date</small>
                    <div id="swViewEvtDate" class="fw-semibold mt-1"></div>
                </div>
                <div>
                    <small class="text-meta">Description</small>
                    <div id="swViewEvtDesc" class="mt-1"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="swViewEvtEditBtn">
                    <i class="bi bi-pencil me-1"></i>Edit
                </button>
                <button type="button" class="btn btn-danger" id="swViewEvtDeleteBtn">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- ═══════════════════════════════════════════════════
     VIEW EVENT MODAL  (employee view — read-only)
════════════════════════════════════════════════════ -->
<div class="modal fade" id="swEmpViewEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="swEmpViewEvtTitle">Event Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <small class="text-meta">Type</small>
                    <div id="swEmpViewEvtTypeBadge" class="fw-semibold mt-1"></div>
                </div>
                <div class="mb-2">
                    <small class="text-meta">Date</small>
                    <div id="swEmpViewEvtDate" class="fw-semibold mt-1"></div>
                </div>
                <div>
                    <small class="text-meta">Description</small>
                    <div id="swEmpViewEvtDesc" class="mt-1"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════
     DELETE SCHEDULE CONFIRM MODAL  (admin scoped only)
════════════════════════════════════════════════════ -->
<?php if ($isScoped): ?>
<div class="modal fade" id="swDeleteSchedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-trash me-2"></i>Delete Schedule
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete this schedule? This action cannot be undone.</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="swDoDeleteConfirm()">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
            </div>

        </div>
    </div>
</div>
<?php endif; ?>

<script>
/* ================================================================
   SCHEDULES WIDGET  —  JavaScript
================================================================ */
const SW_IS_ADMIN     = <?= $isAdmin ? 'true' : 'false' ?>;
const SW_IS_SCOPED    = <?= $isScoped ? 'true' : 'false' ?>;
const SW_IS_WORKFORCE = <?= $isWorkforce ? 'true' : 'false' ?>;
const SW_EMP_ID    = <?= $isScoped ? (int)$schedEmployeeId : 'null' ?>;
const SW_EMP_URL_ID = <?= $isScoped ? json_encode($schedEmpUrlId) : 'null' ?>;
const SW_CAL_API   = <?= json_encode($schedCalApiPath) ?>;
const SW_SAVE_API  = <?= json_encode($schedSaveApiPath) ?>;
const SW_EVENT_API = <?= json_encode($schedEventApiPath) ?>;
const SW_CAL_EVENTS = <?= json_encode($schedCalEvents) ?>;
const SW_BIRTHDAY_EVENTS = <?= json_encode($schedBirthdayEvents) ?>;
const SW_SCHED_API = <?= json_encode($schedApiPath) ?>;

let swCalendar   = null;
let _swSuppressDatesSet = false;

function swPad(n) { return String(n).padStart(2, '0'); }
function swParseYM(ym) { const [y, m] = ym.split('-').map(Number); return { year: y, month: m }; }
function swMonthLabel(ym) {
    const { year, month } = swParseYM(ym);
    return new Date(year, month - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

/* Exposed for external callers (admin_employee_view.php tab sync) */
window.swUpdateSize = function () { if (swCalendar) swCalendar.updateSize(); };
window.swGotoMonth  = function (ym) {
    if (!swCalendar) return;
    const { year, month } = swParseYM(ym);
    _swSuppressDatesSet = true;
    swCalendar.gotoDate(new Date(year, month - 1, 1));
    const lbl = document.getElementById('sw-month-label');
    if (lbl) lbl.textContent = swMonthLabel(ym);
};

document.addEventListener('DOMContentLoaded', function () {
    /* Move modals to <body> so backdrop-filter on ancestor cards
       doesn't create a stacking context that buries them behind .modal-backdrop */
    ['swManageScheduleModal','swEditSchedModal','swAddEventModal','swViewEventModal','swDeleteSchedModal'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) document.body.appendChild(el);
    });

    const calEl = document.getElementById('sw-calendar');

<?php if ($isScoped): ?>
{
/* ================================================================
   MODE: ADMIN SCOPED  (employee-specific schedule calendar)
================================================================ */
let swSelectedDates    = [];
let swSelectedDatesAdd = [];
let swSelectedRestDays = [];
let swFpEdit  = null;
let swFpAdd   = null;
let swScheduledDates = new Set();
let _swSkipDateClick = false;

swCalendar = new FullCalendar.Calendar(calEl, {
    initialView:  'dayGridMonth',
    firstDay:     0,
    headerToolbar: false,
    height:       'auto',
    initialDate:  <?= json_encode($schedInitialDate) ?>,
    dayMaxEvents: false,
    eventDisplay: 'block',

    eventOrder: function (a, b) {
        const aIsCont = a.extendedProps.type === 'night-cont';
        const bIsCont = b.extendedProps.type === 'night-cont';
        if (aIsCont && !bIsCont) return -1;
        if (!aIsCont && bIsCont) return  1;
        return 0;
    },

    events: {
        url:         SW_CAL_API,
        method:      'GET',
        extraParams: { employee_id: SW_EMP_ID },
        failure:     function () { console.error('Failed to fetch schedule events.'); }
    },

    datesSet: function (info) {
        if (_swSuppressDatesSet) { _swSuppressDatesSet = false; return; }
        const d   = info.view.currentStart;
        const ym  = `${d.getFullYear()}-${swPad(d.getMonth() + 1)}`;
        const lbl = document.getElementById('sw-month-label');
        if (lbl) lbl.textContent = swMonthLabel(ym);
        document.dispatchEvent(new CustomEvent('scheduleMonthChanged', { detail: { month: ym } }));
    },

    eventsSet: function (events) {
        swScheduledDates = new Set(
            events.filter(e => ['day', 'night', 'rest'].includes(e.extendedProps.type))
                  .map(e => e.startStr)
        );
        const count = swScheduledDates.size;
        const chip  = document.getElementById('sw-chip-sched-count');
        if (chip) chip.textContent = count + ' scheduled day' + (count !== 1 ? 's' : '');

        const eventDates = new Set(events.map(e => e.startStr));
        document.querySelectorAll('#sw-calendar .fc-daygrid-day').forEach(cell => {
            cell.classList.toggle('fc-day-has-events', eventDates.has(cell.dataset.date));
        });
    },

    dayCellDidMount: function (info) {
        const frame = info.el.querySelector('.fc-daygrid-day-frame');
        if (!frame) return;
        const ov = document.createElement('div');
        ov.className = 'sw-day-add-overlay';
        ov.innerHTML = '<i class="bi bi-plus-circle"></i>';
        frame.appendChild(ov);
    },

    eventContent: function (arg) {
        const props = arg.event.extendedProps;
        let html = '<div class="fc-admin-inner">';
        html += `<span class="fc-admin-label">${arg.event.title}</span>`;
        if (props.timeInStr && props.timeOutStr && props.type !== 'pending-schedule') {
            html += `<span class="fc-admin-time">${props.timeInStr} – ${props.timeOutStr}</span>`;
        }
        html += '</div>';
        return { html };
    },

    eventDidMount: function (info) {
        const props   = info.event.extendedProps;
        const type    = props.type;
        const canEdit = !props.hasActiveLeaveOrOB && props.hasSchedule &&
                        ['day', 'night', 'rest', 'leave-rejected', 'pending-schedule'].includes(type);
        const canDel  = ['day', 'night', 'rest', 'pending-schedule'].includes(type);
        if (!canEdit && !canDel) return;

        const cell  = info.el.closest('.fc-daygrid-day');
        const frame = cell ? cell.querySelector('.fc-daygrid-day-frame') : null;
        if (!frame || frame.querySelector('.sw-ev-actions')) return;

        const wrap = document.createElement('div');
        wrap.className = 'sw-ev-actions';

        if (canEdit) {
            const btn = document.createElement('button');
            btn.className = 'sched-cal-action-btn edit';
            btn.title = 'Edit';
            btn.innerHTML = '<i class="bi bi-pencil"></i>';
            btn.addEventListener('click', e => {
                e.stopPropagation();
                _swSkipDateClick = true;
                setTimeout(() => { _swSkipDateClick = false; }, 100);
                if (props.type === 'rest' || props.isRestDay) swOpenRestDayEditModal(props.dateStr);
                else swOpenEditModal(props.dateStr, props.schedInVal || '', props.schedOutVal || '');
            });
            wrap.appendChild(btn);
        }
        if (canDel) {
            const btn = document.createElement('button');
            btn.className = 'sched-cal-action-btn delete';
            btn.title = 'Delete';
            btn.innerHTML = '<i class="bi bi-trash"></i>';
            btn.addEventListener('click', e => {
                e.stopPropagation();
                _swSkipDateClick = true;
                setTimeout(() => { _swSkipDateClick = false; }, 100);
                swDeleteSchedule(props.dateStr);
            });
            wrap.appendChild(btn);
        }
        frame.appendChild(wrap);
    },

    dateClick: function (info) {
        if (_swSkipDateClick) return;
        swOpenManageModalWithDate(info.dateStr);
    },
});

swCalendar.render();

/* ---- Resize for sidebar / window (keep calendar correctly sized) ---- */
const swScopedSidebar = document.getElementById('sidebar');
if (swScopedSidebar) new ResizeObserver(() => { swCalendar.updateSize(); }).observe(swScopedSidebar);
window.addEventListener('resize', () => { swCalendar.updateSize(); });

/* ---- Flatpickr ---- */
swFpEdit = flatpickr('#swEditSchedDatePicker', {
    mode: 'range',
    dateFormat: 'Y-m-d',
    appendTo: document.body,
    onChange(dates) {
        if (dates.length < 2) {
            swSelectedDates = dates.length === 1
                ? [`${dates[0].getFullYear()}-${swPad(dates[0].getMonth()+1)}-${swPad(dates[0].getDate())}`]
                : [];
        } else {
            swSelectedDates = [];
            const cur = new Date(dates[0].getTime());
            const end = new Date(dates[1].getTime());
            while (cur <= end) {
                swSelectedDates.push(`${cur.getFullYear()}-${swPad(cur.getMonth()+1)}-${swPad(cur.getDate())}`);
                cur.setDate(cur.getDate() + 1);
            }
        }
        swRenderDateTags();
    }
});

swFpAdd = flatpickr('#swAddSchedDatePicker', {
    mode: 'range',
    dateFormat: 'Y-m-d',
    appendTo: document.body,
    onChange(dates) {
        if (dates.length < 2) {
            swSelectedDatesAdd = dates.length === 1
                ? [`${dates[0].getFullYear()}-${swPad(dates[0].getMonth()+1)}-${swPad(dates[0].getDate())}`]
                : [];
        } else {
            swSelectedDatesAdd = [];
            const cur = new Date(dates[0].getTime());
            const end = new Date(dates[1].getTime());
            while (cur <= end) {
                swSelectedDatesAdd.push(`${cur.getFullYear()}-${swPad(cur.getMonth()+1)}-${swPad(cur.getDate())}`);
                cur.setDate(cur.getDate() + 1);
            }
        }
        swRenderDateTagsAdd();
    }
});

/* ---- Manage Schedule button ---- */
document.getElementById('sw-btn-manage-schedule').addEventListener('click', swOpenManageModal);

/* ---- Rest day toggles ---- */
document.querySelectorAll('#swRestDayToggles .rest-day-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const dow = parseInt(btn.dataset.dow);
        if (btn.classList.contains('active')) {
            btn.classList.remove('active');
            swSelectedRestDays = swSelectedRestDays.filter(d => d !== dow);
        } else {
            if (swSelectedRestDays.length >= 2) return;
            btn.classList.add('active');
            swSelectedRestDays.push(dow);
        }
        document.getElementById('swRestDaysDirty').value = '1';
    });
});

/* ---- Manage Schedule form submit ---- */
document.getElementById('swAddSchedForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const isSingleRest    = swSelectedDatesAdd.length === 1 && document.getElementById('swIsSingleRestDay').checked;
    const datesToSchedule = isSingleRest ? [] : [...swSelectedDatesAdd];
    const singleRestDates = isSingleRest ? [...swSelectedDatesAdd] : [];

    const hasDates      = datesToSchedule.length > 0;
    const hasRestDays   = document.getElementById('swRestDaysDirty').value === '1';
    const hasSingleRest = singleRestDates.length > 0;

    if (!hasDates && !hasRestDays && !hasSingleRest) {
        if (typeof showToast === 'function') showToast('Please select dates or set rest days.', 'warning');
        return;
    }

    if (hasDates) {
        const conflicts = datesToSchedule.filter(d => swScheduledDates.has(d));
        if (conflicts.length > 0) {
            const msg = conflicts.length === 1
                ? `A schedule for ${conflicts[0]} already exists. Replace it?`
                : `Schedules for ${conflicts.length} selected dates already exist. Replace them?`;
            if (!confirm(msg)) return;
        }
    }

    document.getElementById('swAddSelectedDatesInput').value = JSON.stringify(datesToSchedule);
    document.getElementById('swRestDaysInput').value         = JSON.stringify(swSelectedRestDays);
    document.getElementById('swSingleRestDatesInput').value  = JSON.stringify(singleRestDates);

    fetch(this.getAttribute('action'), { method: 'POST', body: new FormData(this) })
        .then(r => {
            if (!r.ok && r.status !== 200) throw new Error('save failed');
            bootstrap.Modal.getInstance(document.getElementById('swManageScheduleModal'))?.hide();
            const msg = SW_IS_WORKFORCE ? 'Schedule submitted for approval.' : 'Schedule saved successfully.';
            if (typeof showToast === 'function') showToast(msg, 'success');
            if (swCalendar) swCalendar.refetchEvents();
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('Failed to save schedule. Please try again.', 'danger');
        });
});

/* ---- Edit Schedule form submit ---- */
document.getElementById('swEditSchedForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!swPrepareEditSubmit()) return;
    fetch(this.getAttribute('action'), { method: 'POST', body: new FormData(this) })
        .then(r => r.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.error === 'already_pending') {
                    if (typeof showToast === 'function') showToast('Edit Already Pending', 'warning');
                    return;
                }
            } catch(e) {}
            swCloseEditModal();
            const msg = SW_IS_WORKFORCE ? 'Schedule edit submitted for approval.' : 'Schedule saved successfully.';
            if (typeof showToast === 'function') showToast(msg, 'success');
            if (swCalendar) swCalendar.refetchEvents();
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('Failed to save schedule. Please try again.', 'danger');
        });
});

/* ---- Rest day checkbox in edit modal ---- */
document.getElementById('swModalRestDayCheck').addEventListener('change', function () {
    const isRest = this.checked;
    document.getElementById('swModalIsRestDay').value           = isRest ? '1' : '0';
    document.getElementById('swModalTimeFields').style.display  = isRest ? 'none' : '';
    document.getElementById('swModalTimeIn').required           = !isRest;
    document.getElementById('swModalTimeOut').required          = !isRest;
});

/* ---- Preset schedule ---- */
(function () {
    const trigger = document.getElementById('swPresetSchedTrigger');
    const menu    = document.getElementById('swPresetSchedMenu');
    trigger.addEventListener('click', e => { e.stopPropagation(); menu.classList.toggle('open'); });
    document.querySelectorAll('#swPresetSchedMenu .preset-sched-item').forEach(item => {
        item.addEventListener('click', () => {
            document.getElementById('swAddModalTimeIn').value  = item.dataset.in;
            document.getElementById('swAddModalTimeOut').value = item.dataset.out;
            document.querySelectorAll('#swPresetSchedMenu .preset-sched-item').forEach(el => el.classList.remove('active'));
            item.classList.add('active');
            document.getElementById('swPresetSchedDisplay').textContent = item.textContent;
            menu.classList.remove('open');
        });
    });
    document.addEventListener('click', () => menu.classList.remove('open'));
})();

/* ---- Helper functions (scoped mode) ---- */
function swRenderDateTagsAdd() {
    const list = document.getElementById('swAddSelectedDatesList');
    if (swSelectedDatesAdd.length === 0) {
        list.innerHTML = '';
    } else if (swSelectedDatesAdd.length === 1) {
        list.innerHTML = `<span class="selected-date-tag">${swSelectedDatesAdd[0]}
            <span class="selected-date-remove" onclick="swClearDateSelectionAdd()">&times;</span></span>`;
    } else {
        const first = swSelectedDatesAdd[0], last = swSelectedDatesAdd[swSelectedDatesAdd.length - 1];
        list.innerHTML = `<span class="selected-date-tag">${first} &rarr; ${last} &nbsp;(${swSelectedDatesAdd.length} days)
            <span class="selected-date-remove" onclick="swClearDateSelectionAdd()">&times;</span></span>`;
    }
    swUpdateRestDaySection();
}

function swUpdateRestDaySection() {
    const single = swSelectedDatesAdd.length === 1;
    document.getElementById('swRestDaySection').style.display           = single ? 'none' : '';
    document.getElementById('swSingleDateRestDaySection').style.display = single ? ''     : 'none';
    if (!single) document.getElementById('swIsSingleRestDay').checked   = false;
}

function swClearDateSelectionAdd() {
    swSelectedDatesAdd = [];
    if (swFpAdd) swFpAdd.clear();
    swRenderDateTagsAdd();
}

function swRenderDateTags() {
    const list = document.getElementById('swSelectedDatesList');
    if (swSelectedDates.length === 0) { list.innerHTML = ''; return; }
    if (swSelectedDates.length === 1) {
        list.innerHTML = `<span class="selected-date-tag">${swSelectedDates[0]}
            <span class="selected-date-remove" onclick="swClearDateSelection()">&times;</span></span>`;
    } else {
        const first = swSelectedDates[0], last = swSelectedDates[swSelectedDates.length - 1];
        list.innerHTML = `<span class="selected-date-tag">${first} &rarr; ${last} &nbsp;(${swSelectedDates.length} days)
            <span class="selected-date-remove" onclick="swClearDateSelection()">&times;</span></span>`;
    }
}

function swClearDateSelection() {
    swSelectedDates = [];
    if (swFpEdit) swFpEdit.clear();
    swRenderDateTags();
}

function swOpenManageModal() {
    document.getElementById('swAddModalTimeIn').value  = '';
    document.getElementById('swAddModalTimeOut').value = '';
    document.querySelectorAll('#swPresetSchedMenu .preset-sched-item').forEach(el => el.classList.remove('active'));
    document.getElementById('swPresetSchedDisplay').textContent = 'Select a preset schedule…';
    document.getElementById('swPresetSchedMenu').classList.remove('open');
    swSelectedDatesAdd = [];
    swRenderDateTagsAdd();
    if (swFpAdd) swFpAdd.clear();
    swSelectedRestDays = [];
    document.querySelectorAll('#swRestDayToggles .rest-day-toggle').forEach(btn => btn.classList.remove('active'));
    document.getElementById('swRestDaysDirty').value             = '0';
    document.getElementById('swIsSingleRestDay').checked         = false;
    document.getElementById('swSingleDateRestDaySection').style.display = 'none';
    document.getElementById('swRestDaySection').style.display           = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('swManageScheduleModal')).show();
}

function swOpenManageModalWithDate(dateStr) {
    swOpenManageModal();
    swSelectedDatesAdd = [dateStr];
    if (swFpAdd) swFpAdd.setDate([dateStr, dateStr], false);
    swRenderDateTagsAdd();
}

function swOpenRestDayEditModal(dateStr) {
    swOpenManageModalWithDate(dateStr);
    document.getElementById('swIsSingleRestDay').checked = true;
}

function swOpenEditModal(date, timeIn, timeOut) {
    document.getElementById('swSchedModalTitle').textContent  = 'Edit Schedule';
    document.getElementById('swSchedSubmitLabel').textContent = SW_IS_WORKFORCE ? 'Submit for Approval' : 'Update Schedule';
    document.getElementById('swIsEditMode').value             = '1';
    document.getElementById('swModalIsRestDay').value         = '0';
    document.getElementById('swModalRestDayCheck').checked    = false;
    document.getElementById('swModalTimeIn').value            = timeIn;
    document.getElementById('swModalTimeOut').value           = timeOut;
    document.getElementById('swModalTimeFields').style.display = '';
    document.getElementById('swModalTimeIn').required          = true;
    document.getElementById('swModalTimeOut').required         = true;
    swSelectedDates = [date];
    swRenderDateTags();
    if (swFpEdit) swFpEdit.setDate([date, date], false);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('swEditSchedModal')).show();
}

function swCloseEditModal() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('swEditSchedModal')).hide();
}

function swPrepareEditSubmit() {
    document.getElementById('swSelectedDatesInput').value = JSON.stringify(swSelectedDates);
    if (swSelectedDates.length === 0) {
        if (typeof showToast === 'function') showToast('Please select at least one date.', 'warning');
        return false;
    }
    return true;
}

let _swDeleteInProgress = false;
let _swPendingDeleteDate = null;

function swDeleteSchedule(date) {
    if (_swDeleteInProgress) return;
    _swPendingDeleteDate = date;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('swDeleteSchedModal')).show();
}

window.swDoDeleteConfirm = function () {
    const date = _swPendingDeleteDate;
    if (!date) return;

    bootstrap.Modal.getInstance(document.getElementById('swDeleteSchedModal'))?.hide();
    _swDeleteInProgress = true;

    const base = SW_SAVE_API.replace(/\?.*$/, '');
    fetch(`${base}?employee_id=${SW_EMP_URL_ID}&ajax_delete=1&emp=${SW_EMP_ID}&date=${date}`)
        .then(r => r.text())
        .then(text => {
            let data;
            try { data = JSON.parse(text); } catch (e) {
                if (typeof showToast === 'function') showToast('Something went wrong. Please try again.', 'danger');
                return;
            }
            if (data.error === 'already_pending_delete') {
                if (typeof showToast === 'function') showToast('Delete request already pending.', 'warning');
                return;
            }
            if (data.status === 'pending') {
                if (typeof showToast === 'function') showToast('Delete request submitted for approval.', 'success');
                if (swCalendar) swCalendar.refetchEvents();
                return;
            }
            if (typeof showToast === 'function') showToast('Schedule deleted successfully.', 'success');
            if (swCalendar) swCalendar.refetchEvents();
            document.dispatchEvent(new CustomEvent('scheduleDeleted', { detail: { date } }));
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('Something went wrong. Please try again.', 'danger');
        })
        .finally(() => {
            _swDeleteInProgress = false;
            _swPendingDeleteDate = null;
        });
};

window.swPrev  = function () { if (swCalendar) swCalendar.prev(); };
window.swNext  = function () { if (swCalendar) swCalendar.next(); };
window.swToday = function () { if (swCalendar) swCalendar.today(); };

} /* end admin scoped block */

<?php elseif ($isAdmin): ?>
{
/* ================================================================
   MODE: ADMIN GLOBAL  (full calendar with events + birthdays)
================================================================ */
let swCurrentFilter = 'all';
let swIsRefreshing  = false;
let swPendingDate   = null;
let swEditPayload   = null;
let swFpEvtDate     = null;

function swMatchesFilter(el) {
    if (swCurrentFilter === 'all') return true;
    const st = el.dataset.st;
    const et = el.dataset.et;
    if (swCurrentFilter === 'leave')    return st && st.startsWith('leave_');
    if (swCurrentFilter === 'ob')       return st && st.startsWith('ob_');
    if (swCurrentFilter === 'birthday') return st === 'birthday';
    if (swCurrentFilter === 'events')   return st === 'cal_event';
    return st === 'cal_event' && et === swCurrentFilter;
}

function swApplyFilter(el) { el.style.display = swMatchesFilter(el) ? '' : 'none'; }

const swFilterEl = document.getElementById('sw-schedule-filter');
if (swFilterEl) {
    const urlFilter = new URLSearchParams(window.location.search).get('filter');
    if (urlFilter && swFilterEl.querySelector(`option[value="${urlFilter}"]`)) {
        swFilterEl.value = urlFilter;
        swCurrentFilter  = urlFilter;
    }
    swFilterEl.addEventListener('change', function () {
        swCurrentFilter = this.value;
        document.querySelectorAll('#sw-calendar .fc-event').forEach(swApplyFilter);
    });
}

swCalendar = new FullCalendar.Calendar(calEl, {
    initialView:  'dayGridMonth',
    initialDate:  <?= json_encode($schedInitialDate) ?>,
    eventDisplay: 'block',
    dayMaxEvents: false,
    height:       '100%',

    customButtons: {
        refresh: {
            text:  '',
            click: function () {
                swIsRefreshing = true;
                swCalendar.refetchEvents();
            }
        },
    },

    headerToolbar: { left: 'refresh today', right: 'prev title next' },

    loading: function (isLoading) {
        if (!swIsRefreshing) return;
        const ov = document.getElementById('sw-cal-loading');
        if (isLoading) { if (ov) ov.classList.add('show'); }
        else           { if (ov) ov.classList.remove('show'); swIsRefreshing = false; }
    },

    eventSources: [
        { url: SW_SCHED_API, method: 'GET', failure: function () { console.error('Failed to fetch schedule.'); } },
        { events: function(info, successCallback) { successCallback(SW_CAL_EVENTS); } },
        <?php if ($isAdmin): ?>{ events: function(info, successCallback) { successCallback(SW_BIRTHDAY_EVENTS); } },<?php endif; ?>
    ],

    eventContent: function (arg) {
        const st    = arg.event.extendedProps.shift_type;
        const props = arg.event.extendedProps;
        if ((st === 'day' || st === 'night') && props.timeInStr && props.timeOutStr) {
            return { html: '<div class="fc-admin-inner"><span class="fc-admin-label">' + arg.event.title + '</span><span class="fc-admin-time">' + props.timeInStr + ' – ' + props.timeOutStr + '</span></div>' };
        }
        return true;
    },

    eventDidMount: function (info) {
        const shiftType = info.event.extendedProps.shift_type;
        info.el.dataset.st = shiftType || '';
        info.el.dataset.et = info.event.extendedProps.event_type || '';
        swApplyFilter(info.el);

        if (shiftType === 'birthday') {
            const tEl = info.el.querySelector('.fc-event-title');
            if (tEl) tEl.innerHTML = '<i class="bi bi-cake"></i> ' + info.event.title;
        }
        if (shiftType === 'cal_event') {
            const iconMap = {
                holiday: 'bi-umbrella-fill', party: 'bi-balloon-fill',
                meeting: 'bi-people-fill', announcement: 'bi-megaphone-fill', other: 'bi-pin-fill',
            };
            const tEl = info.el.querySelector('.fc-event-title');
            if (tEl) {
                const icon = iconMap[info.event.extendedProps.event_type] || 'bi-pin-fill';
                tEl.innerHTML = `<i class="bi ${icon}"></i> ` + info.event.title;
            }
        }
    },

    eventClick: function (info) {
        const props = info.event.extendedProps;
        if (props.shift_type !== 'cal_event') return;
        info.jsEvent.preventDefault();
        const iconMap  = { holiday:'bi-umbrella-fill', party:'bi-balloon-fill', meeting:'bi-people-fill', announcement:'bi-megaphone-fill', other:'bi-pin-fill' };
        const colorMap = { holiday:'#ef4444', party:'#ec4899', meeting:'#3b82f6', announcement:'#f59e0b', other:'#6b7280' };
        const et    = props.event_type || 'other';
        const icon  = iconMap[et]  || 'bi-pin-fill';
        const color = colorMap[et] || '#6b7280';
        const dateStr = info.event.start ? info.event.start.toLocaleDateString('en-CA') : '—';

        document.getElementById('swViewEvtTitle').textContent    = info.event.title;
        document.getElementById('swViewEvtTypeBadge').innerHTML  = `<i class="bi ${icon} me-1"></i>${et.charAt(0).toUpperCase()+et.slice(1)}`;
        document.getElementById('swViewEvtTypeBadge').style.color = color;
        document.getElementById('swViewEvtDate').textContent     = dateStr;
        document.getElementById('swViewEvtDesc').textContent     = props.description || '—';
        document.getElementById('swViewEvtEditBtn').dataset.id   = info.event.id;
        document.getElementById('swViewEvtDeleteBtn').dataset.id = info.event.id;

        swEditPayload = { id: info.event.id, title: info.event.title, event_type: et, description: props.description || '', date: dateStr };
        bootstrap.Modal.getOrCreateInstance(document.getElementById('swViewEventModal')).show();
    },

    dayCellDidMount: function (info) {
        const ov = document.createElement('div');
        ov.className = 'es-day-add-overlay';
        ov.innerHTML = '<i class="bi bi-plus-circle"></i>';
        info.el.appendChild(ov);
        const tip = new bootstrap.Tooltip(info.el, { title:'Add Event', trigger:'hover', placement:'top', container:'body' });
        tip.disable();
    },

    dayCellWillUnmount: function (info) { bootstrap.Tooltip.getInstance(info.el)?.dispose(); },

    eventsSet: function (events) {
        const eventDates = new Set(events.map(e => e.startStr));
        document.querySelectorAll('#sw-calendar .fc-daygrid-day').forEach(cell => {
            const hasEvent = eventDates.has(cell.dataset.date);
            cell.classList.toggle('fc-day-has-events', hasEvent);
            const tip = bootstrap.Tooltip.getInstance(cell);
            if (tip) { if (hasEvent) tip.disable(); else tip.enable(); }
        });
    },

    dateClick: function (info) {
        const cell = document.querySelector(`#sw-calendar .fc-daygrid-day[data-date="${info.dateStr}"]`);
        if (cell && cell.classList.contains('fc-day-has-events')) return;
        swPendingDate = info.dateStr;
        swEditPayload = null;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('swAddEventModal')).show();
    },
});

swCalendar.render();

/* ---- Move filter select into FC toolbar beside Today ---- */
const swFilterSelect = document.getElementById('sw-schedule-filter');
const swLeftChunk    = calEl.querySelector('.fc-toolbar-chunk:first-child');
if (swFilterSelect && swLeftChunk) {
    swLeftChunk.appendChild(swFilterSelect);
    swFilterSelect.style.display = '';
}

/* ---- Move Add Event button into FC toolbar beside month nav ---- */
const swAddEventBtn  = document.getElementById('sw-add-event-btn');
const swRightChunk   = calEl.querySelector('.fc-toolbar-chunk:last-child');
if (swAddEventBtn && swRightChunk) {
    swRightChunk.prepend(swAddEventBtn);
    swAddEventBtn.style.display = '';
}

/* ---- Resize observer for sidebar ---- */
const swSidebar = document.getElementById('sidebar');
if (swSidebar) {
    new ResizeObserver(() => { swCalendar.updateSize(); }).observe(swSidebar);
}
window.addEventListener('resize', () => { swCalendar.updateSize(); });

/* ---- Custom refresh button icon ---- */
const swRefreshBtn = calEl.querySelector('.fc-refresh-button');
if (swRefreshBtn) swRefreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';

/* ---- Flatpickr for event date ---- */
swFpEvtDate = flatpickr('#swEvtDate', {
    dateFormat:        'Y-m-d',
    monthSelectorType: 'dropdown',
    disableMobile:     true,
    appendTo:          document.body,
});

/* ---- Add Event Modal: populate on show ---- */
document.getElementById('swAddEventModal').addEventListener('show.bs.modal', function () {
    document.getElementById('swAddEventError').classList.add('d-none');
    if (swEditPayload) {
        document.getElementById('swAddEventModalTitleText').textContent = 'Edit Event';
        document.getElementById('swEvtId').value          = swEditPayload.id;
        document.getElementById('swEvtTitle').value       = swEditPayload.title;
        document.getElementById('swEvtType').value        = swEditPayload.event_type;
        document.getElementById('swEvtDescription').value = swEditPayload.description;
        if (swFpEvtDate) swFpEvtDate.setDate(swEditPayload.date, false);
    } else {
        document.getElementById('swAddEventModalTitleText').textContent = 'Add Event';
        document.getElementById('swEvtId').value          = '';
        document.getElementById('swEvtTitle').value       = '';
        document.getElementById('swEvtType').value        = 'other';
        document.getElementById('swEvtDescription').value = '';
        if (swFpEvtDate && swPendingDate) swFpEvtDate.setDate(swPendingDate, false);
        else if (swFpEvtDate) swFpEvtDate.clear();
    }
    swEditPayload = null;
    swPendingDate = null;
});

/* ---- Save Event ---- */
document.getElementById('swSaveEventBtn').addEventListener('click', async function () {
    const btn   = this;
    const title = document.getElementById('swEvtTitle').value.trim();
    const date  = document.getElementById('swEvtDate').value.trim();
    const id    = document.getElementById('swEvtId').value.trim();
    const errEl = document.getElementById('swAddEventError');

    errEl.classList.add('d-none');
    if (!title || !date) {
        errEl.textContent = 'Title and date are required.';
        errEl.classList.remove('d-none');
        return;
    }

    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

    try {
        const payload = {
            action:      id ? 'update' : 'create',
            title,
            event_type:  document.getElementById('swEvtType').value,
            start_date:  date,
            description: document.getElementById('swEvtDescription').value.trim(),
        };
        if (id) payload.id = id;

        const res  = await fetch(SW_EVENT_API, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('swAddEventModal')).hide();
            window.location.reload();
        } else {
            errEl.textContent = data.error || 'Failed to save event.';
            errEl.classList.remove('d-none');
        }
    } catch (e) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.classList.remove('d-none');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Event';
    }
});

/* ---- View modal: Edit button ---- */
document.getElementById('swViewEvtEditBtn').addEventListener('click', function () {
    bootstrap.Modal.getInstance(document.getElementById('swViewEventModal')).hide();
    document.getElementById('swViewEventModal').addEventListener('hidden.bs.modal', function openEdit() {
        this.removeEventListener('hidden.bs.modal', openEdit);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('swAddEventModal')).show();
    });
});

/* ---- View modal: Delete button ---- */
document.getElementById('swViewEvtDeleteBtn').addEventListener('click', async function () {
    const id = this.dataset.id;
    if (!id || !confirm('Delete this event? This cannot be undone.')) return;
    try {
        const res  = await fetch(SW_EVENT_API, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'delete', id }),
        });
        const data = await res.json();
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('swViewEventModal')).hide();
            window.location.reload();
        }
    } catch (e) { alert('Network error. Please try again.'); }
});

} /* end admin global block */

<?php else: ?>
{
/* ================================================================
   MODE: EMPLOYEE  (personal schedule, read-only)
================================================================ */
let swIsRefreshing  = false;
let swCurrentFilter = 'all';

function swMatchesFilter(el) {
    if (swCurrentFilter === 'all') return true;
    const st = el.dataset.st;
    const et = el.dataset.et;
    if (swCurrentFilter === 'leave')  return st && st.startsWith('leave_');
    if (swCurrentFilter === 'ob')     return st && st.startsWith('ob_');
    if (swCurrentFilter === 'events') return st === 'cal_event';
    return st === 'cal_event' && et === swCurrentFilter;
}

function swApplyFilter(el) { el.style.display = swMatchesFilter(el) ? '' : 'none'; }

const swFilterEl = document.getElementById('sw-schedule-filter');
if (swFilterEl) {
    const urlFilter = new URLSearchParams(window.location.search).get('filter');
    if (urlFilter && swFilterEl.querySelector(`option[value="${urlFilter}"]`)) {
        swFilterEl.value = urlFilter;
        swCurrentFilter  = urlFilter;
    }
    swFilterEl.addEventListener('change', function () {
        swCurrentFilter = this.value;
        document.querySelectorAll('#sw-calendar .fc-event').forEach(swApplyFilter);
    });
}

swCalendar = new FullCalendar.Calendar(calEl, {
    initialView:  'dayGridMonth',
    initialDate:  <?= json_encode($schedInitialDate) ?>,
    eventDisplay: 'block',
    dayMaxEvents: false,
    height:       '100%',

    customButtons: {
        refresh: {
            text:  '',
            click: function () {
                swIsRefreshing = true;
                swCalendar.refetchEvents();
            }
        },
    },

    headerToolbar: { left: 'refresh today', right: 'prev title next' },

    loading: function (isLoading) {
        if (!swIsRefreshing) return;
        const ov = document.getElementById('sw-cal-loading');
        if (isLoading) { if (ov) ov.classList.add('show'); }
        else           { if (ov) ov.classList.remove('show'); swIsRefreshing = false; }
    },

    eventSources: [
        { url: SW_SCHED_API, method: 'GET', failure: function () { console.error('Failed to fetch schedule.'); } },
        { events: function(info, successCallback) { successCallback(SW_CAL_EVENTS); } },
        { events: function(info, successCallback) { successCallback(SW_BIRTHDAY_EVENTS); } },
    ],

    eventContent: function (arg) {
        const st    = arg.event.extendedProps.shift_type;
        const props = arg.event.extendedProps;
        if ((st === 'day' || st === 'night') && props.timeInStr && props.timeOutStr) {
            return { html: '<div class="fc-admin-inner"><span class="fc-admin-label">' + arg.event.title + '</span><span class="fc-admin-time">' + props.timeInStr + ' – ' + props.timeOutStr + '</span></div>' };
        }
        return true;
    },

    eventDidMount: function (info) {
        const shiftType = info.event.extendedProps.shift_type;
        info.el.dataset.st = shiftType || '';
        info.el.dataset.et = info.event.extendedProps.event_type || '';
        swApplyFilter(info.el);

        if (shiftType === 'birthday') {
            const tEl = info.el.querySelector('.fc-event-title');
            if (tEl) tEl.innerHTML = '<i class="bi bi-cake"></i> ' + info.event.title;
            info.el.style.cursor = 'pointer';
        }
        if (shiftType === 'cal_event') {
            const iconMap = {
                holiday:'bi-umbrella-fill', party:'bi-balloon-fill',
                meeting:'bi-people-fill', announcement:'bi-megaphone-fill', other:'bi-pin-fill',
            };
            const tEl = info.el.querySelector('.fc-event-title');
            if (tEl) {
                const icon = iconMap[info.event.extendedProps.event_type] || 'bi-pin-fill';
                tEl.innerHTML = `<i class="bi ${icon}"></i> ` + info.event.title;
            }
            info.el.style.cursor = 'pointer';
        }
    },

    eventsSet: function (events) {
        const c = { day: 0, night: 0, rest: 0, leave: 0, ob: 0 };
        events.forEach(function (e) {
            const st = e.extendedProps.shift_type;
            if      (st === 'day')           c.day++;
            else if (st === 'night')         c.night++;
            else if (st === 'rest')          c.rest++;
            else if (st === 'leave_approved') c.leave++;
            else if (st === 'ob_approved')   c.ob++;
        });
        [['sched-stat-day', c.day], ['sched-stat-night', c.night],
         ['sched-stat-rest', c.rest], ['sched-stat-leave', c.leave],
         ['sched-stat-ob', c.ob]].forEach(function ([id, val]) {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        });
    },

    eventClick: function (info) {
        const props = info.event.extendedProps;
        info.jsEvent.preventDefault();

        if (props.shift_type === 'birthday') {
            const dateStr = info.event.start ? info.event.start.toLocaleDateString('en-CA') : '—';
            document.getElementById('swEmpViewEvtTitle').textContent    = info.event.title;
            document.getElementById('swEmpViewEvtTypeBadge').innerHTML  = '<i class="bi bi-cake me-1"></i>Birthday';
            document.getElementById('swEmpViewEvtTypeBadge').style.color = '#8b5cf6';
            document.getElementById('swEmpViewEvtDate').textContent     = dateStr;
            document.getElementById('swEmpViewEvtDesc').textContent     = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('swEmpViewEventModal')).show();
            return;
        }

        if (props.shift_type !== 'cal_event') return;

        const iconMap  = { holiday:'bi-umbrella-fill', party:'bi-balloon-fill', meeting:'bi-people-fill', announcement:'bi-megaphone-fill', other:'bi-pin-fill' };
        const colorMap = { holiday:'#ef4444', party:'#ec4899', meeting:'#3b82f6', announcement:'#f59e0b', other:'#6b7280' };
        const et    = props.event_type || 'other';
        const icon  = iconMap[et]  || 'bi-pin-fill';
        const color = colorMap[et] || '#6b7280';
        const dateStr = info.event.start ? info.event.start.toLocaleDateString('en-CA') : '—';

        document.getElementById('swEmpViewEvtTitle').textContent    = info.event.title;
        document.getElementById('swEmpViewEvtTypeBadge').innerHTML  = `<i class="bi ${icon} me-1"></i>${et.charAt(0).toUpperCase()+et.slice(1)}`;
        document.getElementById('swEmpViewEvtTypeBadge').style.color = color;
        document.getElementById('swEmpViewEvtDate').textContent     = dateStr;
        document.getElementById('swEmpViewEvtDesc').textContent     = props.description || '—';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('swEmpViewEventModal')).show();
    },

});

swCalendar.render();

/* ---- Move filter select into FC toolbar beside Today ---- */
const swFilterSelect = document.getElementById('sw-schedule-filter');
const swLeftChunk    = calEl.querySelector('.fc-toolbar-chunk:first-child');
if (swFilterSelect && swLeftChunk) {
    swLeftChunk.appendChild(swFilterSelect);
    swFilterSelect.style.display = '';
}

/* ---- Move modal to <body> to avoid stacking context issues ---- */
const swEmpViewModal = document.getElementById('swEmpViewEventModal');
if (swEmpViewModal) document.body.appendChild(swEmpViewModal);

/* ---- Refresh button icon ---- */
const swRefreshBtn = calEl.querySelector('.fc-refresh-button');
if (swRefreshBtn) swRefreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';

/* ---- Resize for sidebar ---- */
const swSidebar = document.getElementById('sidebar');
if (swSidebar) new ResizeObserver(() => { swCalendar.updateSize(); }).observe(swSidebar);
window.addEventListener('resize', () => { swCalendar.updateSize(); });

} /* end employee block */

<?php endif; ?>
}); /* end DOMContentLoaded */
</script>
