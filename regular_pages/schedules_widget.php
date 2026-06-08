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

$schedStatsApiPath   ??= '../get_schedule_stats.php';

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
        <button id="sw-add-event-btn" class="btn btn-success btn-add-event"
                data-bs-toggle="modal"
                data-bs-target="#swAddEventModal"
                style="display:none">
            <i class="bi bi-plus-lg"></i> Add Event
        </button>
    </div>

    <!-- filter dropdown; moved into FC toolbar by JS after render -->
    <div id="sw-schedule-filter" class="dropdown" style="display:none">
        <button class="btn sw-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">All</button>
        <ul class="dropdown-menu">
            <li><button class="dropdown-item active" type="button" data-sw-filter="all">All</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="leave">On Leave</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="ob">On OB</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="birthday">Birthday</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="events">All Events</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="holiday">Holiday</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="meeting">Meeting</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="announcement">Announcement</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="party">Party</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="other">Other</button></li>
        </ul>
    </div>

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

    <!-- filter dropdown; moved into FC toolbar by JS after render -->
    <div id="sw-schedule-filter" class="dropdown" style="display:none">
        <button class="btn sw-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">All</button>
        <ul class="dropdown-menu">
            <li><button class="dropdown-item active" type="button" data-sw-filter="all">All</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="leave">On Leave</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="ob">On OB</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="events">All Events</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="holiday">Holiday</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="meeting">Meeting</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="announcement">Announcement</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="party">Party</button></li>
            <li><button class="dropdown-item" type="button" data-sw-filter="other">Other</button></li>
        </ul>
    </div>
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

                    <div id="swTimeSection">
                        <!-- Tab switcher -->
                        <div class="sw-sched-tab-switcher mb-3">
                            <button type="button" class="sw-sched-tab active" id="swTabPreset">
                                <i class="bi bi-collection me-1"></i> Use preset
                            </button>
                            <button type="button" class="sw-sched-tab" id="swTabManual">
                                <i class="bi bi-clock me-1"></i> Set manually
                            </button>
                        </div>

                        <!-- Preset panel -->
                        <div id="swPresetPanel" class="mb-3">
                            <label class="form-label">Preset schedule</label>
                            <div class="dropdown">
                                <button type="button" class="btn dropdown-toggle w-100 sw-preset-bs-btn text-start"
                                        id="swPresetSchedTrigger" aria-expanded="false">
                                    <span id="swPresetSchedDisplay">Select a preset schedule…</span>
                                </button>
                                <ul class="dropdown-menu sw-preset-bs-menu" id="swPresetSchedMenu" aria-labelledby="swPresetSchedTrigger"></ul>
                            </div>
                            <small class="text-muted mt-1 d-block">Choose from saved schedules. Time in, time out and rest days will be filled automatically.</small>
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
                                    const li = document.createElement('li');
                                    const a  = document.createElement('a');
                                    a.className   = 'dropdown-item sw-preset-item';
                                    a.href        = '#';
                                    a.dataset.in  = to24(ih, im);
                                    a.dataset.out = to24(oh, om);
                                    a.textContent = `${fmt(ih,im)} – ${fmt(oh,om)}`;
                                    li.appendChild(a);
                                    menu.appendChild(li);
                                }
                            })();
                            </script>
                        </div>

                        <!-- Manual panel -->
                        <div id="swManualPanel" class="row g-3 mb-3" style="display:none;">
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
                    </div>

                    <div class="mb-3" id="swSelectDatesSection">
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
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input me-2" type="checkbox" role="switch" id="swIsSingleRestDay">
                            <label class="form-check-label" for="swIsSingleRestDay">Is Rest Day</label>
                        </div>
                    </div>

                </div>

                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary border border-secondary" data-bs-dismiss="modal">Cancel</button>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" id="swDeleteSchedBtn" class="btn btn-danger" style="display:none;">
                            <i class="bi bi-trash-fill me-1"></i>Delete Schedule
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle-fill me-1"></i>
                            <?= $isWorkforce ? 'Submit for Approval' : 'Save Schedule' ?>
                        </button>
                    </div>
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
                    <input type="hidden" id="swEvtType" value="other">
                    
                    <div class="dropdown w-100">
                        <button class="btn btn-outline-secondary dropdown-toggle w-100 form-control bg-transparent text-start" 
                                type="button" 
                                id="swEvtTypeDropdownBtn" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false"
                                >
                            <span id="swEvtTypeDropdownLabel">Other</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark w-100" aria-labelledby="swEvtTypeDropdownBtn">
                            <li><a class="dropdown-item" href="#" data-value="holiday">Holiday</a></li>
                            <li><a class="dropdown-item" href="#" data-value="party">Party</a></li>
                            <li><a class="dropdown-item" href="#" data-value="meeting">Meeting</a></li>
                            <li><a class="dropdown-item" href="#" data-value="announcement">Announcement</a></li>
                            <li><a class="dropdown-item" href="#" data-value="other">Other</a></li>
                        </ul>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Select Date <span class="text-danger">*</span></label>
                    <input type="text" id="swEvtDate" class="form-control" placeholder="Select date" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description <small class="text-meta">(optional)</small></label>
                    <textarea id="swEvtDescription" class="form-control" rows="2"
                              placeholder="Short description…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-neutral" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="swSaveEventBtn">
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

<!-- ═══════════════════════════════════════════════════
     DELETE EVENT CONFIRM MODAL  (admin global view)
════════════════════════════════════════════════════ -->
<div class="modal fade" id="swDeleteEventConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Delete Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete this event? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="swDeleteEventConfirmBtn">
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
const SW_EMP_ID     = <?= $isScoped ? (int)$schedEmployeeId : 'null' ?>;
const SW_EMP_URL_ID = <?= $isScoped ? json_encode($schedEmpUrlId) : 'null' ?>;
const SW_CAL_API    = <?= json_encode($schedCalApiPath) ?>;
const SW_SAVE_API   = <?= json_encode($schedSaveApiPath) ?>;
const SW_EVENT_API  = <?= json_encode($schedEventApiPath) ?>;
const SW_CAL_EVENTS = <?= json_encode($schedCalEvents) ?>;
const SW_BIRTHDAY_EVENTS = <?= json_encode($schedBirthdayEvents) ?>;
const SW_SCHED_API  = <?= json_encode($schedApiPath) ?>;
const SW_STATS_API  = <?= json_encode($schedStatsApiPath) ?>;

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
    ['swManageScheduleModal','swEditSchedModal','swAddEventModal','swViewEventModal','swDeleteSchedModal','swDeleteEventConfirmModal'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) document.body.appendChild(el);
    });

    const calEl = document.getElementById('sw-calendar');

    let swCurrentFilter = 'all';
    let swIsRefreshing  = false;

    function swMatchesFilter(el) {
        if (swCurrentFilter === 'all') return true;
        const st = el.dataset.st;
        const et = el.dataset.et;
        if (swCurrentFilter === 'leave')    return st && st.startsWith('leave_');
        if (swCurrentFilter === 'ob')       return st && st.startsWith('ob_');
        <?php if ($isAdmin && !$isScoped): ?>
        if (swCurrentFilter === 'birthday') return st === 'birthday';
        <?php endif; ?>
        if (swCurrentFilter === 'events')   return st === 'cal_event';
        return st === 'cal_event' && et === swCurrentFilter;
    }

    function swApplyFilter(el) { el.style.display = swMatchesFilter(el) ? '' : 'none'; }

    const swFilterEl = document.getElementById('sw-schedule-filter');
    if (swFilterEl) {
        const swFilterBtn = swFilterEl.querySelector('.sw-filter-btn');

        function swSetFilter(value) {
            const item = swFilterEl.querySelector(`[data-sw-filter="${value}"]`);
            if (!item) return;
            swCurrentFilter = value;
            if (swFilterBtn) swFilterBtn.textContent = item.textContent.trim();
            swFilterEl.querySelectorAll('[data-sw-filter]').forEach(el =>
                el.classList.toggle('active', el.dataset.swFilter === value)
            );
            document.querySelectorAll('#sw-calendar .fc-event').forEach(swApplyFilter);
        }

        const urlFilter = new URLSearchParams(window.location.search).get('filter');
        if (urlFilter && swFilterEl.querySelector(`[data-sw-filter="${urlFilter}"]`)) {
            swSetFilter(urlFilter);
        }

        swFilterEl.addEventListener('click', function (e) {
            const item = e.target.closest('[data-sw-filter]');
            if (item) swSetFilter(item.dataset.swFilter);
        });
    }

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
/* Dates that are On Leave / On OB — not editable as schedules (no add/edit) */
let swLeaveOrOBDates = new Set();
/* Night-shift continuation days → the originating schedule date (so the
   "↪ until …" marker can be hover-edited; it edits the night shift it belongs to) */
let swNightContOrigin = new Map();
/* Leave/OB Rejected days that still have an underlying schedule (the rejected
   request doesn't remove the shift, so it stays editable) */
let swRejectedSchedDates = new Set();
/* FIX Bug 4 — track the schedule_id of the date being deleted */
let _swPendingDeleteDate = null;
let _swPendingDeleteId   = null;
let _swDeleteInProgress  = false;
let _swSkipDateClick     = false;

swCalendar = new FullCalendar.Calendar(calEl, {
    initialView:  'dayGridMonth',
    firstDay:     0,
    headerToolbar: false,
    height:       'auto',
    initialDate:  <?= json_encode($schedInitialDate) ?>,
    dayMaxEvents: false,
    eventDisplay: 'block',
    
    // ADD THIS DATECLICK PATTERN HERE AS WELL:
    dateClick: function(info) {
        // Capture clicked string values safely
        const dateStr = info.dateStr; 
        
        // Check if day already has an active entry sequence
        if (swScheduledDates.has(dateStr)) {
            // Logic to transition cleanly into Edit Mode instead
            document.getElementById('swIsEditMode').value = "1";
            document.getElementById('swSchedModalTitle').textContent = "Edit Schedule - " + dateStr;
            // ... trigger your Edit Modal sequence
        } else {
            // Fresh Schedule Setup Sequence
            document.getElementById('swIsEditMode').value = "0";
            
            // Push values directly to your custom DatePickers or Input buffers
            const singleDatePicker = document.getElementById('swEditSchedDatePicker');
            if (singleDatePicker) {
                singleDatePicker.value = dateStr;
            }
            
            // Pop the specific management wizard modal wrapper visible
            const manageModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('swManageScheduleModal'));
            manageModal.show();
        }
    },

    eventOrder: function (a, b) {
        const aIsCont = a.extendedProps.type === 'night-cont';
        const bIsCont = b.extendedProps.type === 'night-cont';
        if (aIsCont && !bIsCont) return -1;
        if (!aIsCont && bIsCont) return  1;
        return 0;
    },

    loading: function (isLoading) {
        const ov = document.getElementById('sw-cal-loading');
        if (ov) ov.classList.toggle('show', isLoading);
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
        /* FIX Bug 5 — defer overlay icon update so dayCellDidMount has finished
           painting all cells before we try to query them */
        swScheduledDates = new Set(
            events.filter(e => ['day', 'night', 'rest'].includes(e.extendedProps.type))
                  .map(e => e.startStr)
        );
        swLeaveOrOBDates = new Set(
            events.filter(e => ['on-leave', 'on-ob'].includes(e.extendedProps.type))
                  .map(e => e.startStr)
        );
        swNightContOrigin = new Map();
        events.filter(e => e.extendedProps.type === 'night-cont').forEach(function (e) {
            swNightContOrigin.set(e.startStr, e.extendedProps.originDate || _swPrevDay(e.startStr));
        });
        swRejectedSchedDates = new Set(
            events.filter(e => e.extendedProps.type === 'leave-rejected' && e.extendedProps.hasSchedule)
                  .map(e => e.startStr)
        );
        const count = swScheduledDates.size;
        const chip  = document.getElementById('sw-chip-sched-count');
        if (chip) chip.textContent = count + ' scheduled day' + (count !== 1 ? 's' : '');

        requestAnimationFrame(function () {
            const eventDates = new Set(events.map(e => e.startStr));
            document.querySelectorAll('#sw-calendar .fc-daygrid-day').forEach(function (cell) {
                const dateStr = cell.dataset.date;
                cell.classList.toggle('fc-day-has-events', eventDates.has(dateStr));

                const overlay = cell.querySelector('.sw-day-add-overlay');
                if (overlay) {
                    // A scheduled day, or a night-shift continuation day (which edits
                    // the originating shift), both get the edit pencil.
                    const isEditable = swScheduledDates.has(dateStr) ||
                        swRejectedSchedDates.has(dateStr) ||
                        (swNightContOrigin.has(dateStr) && !swLeaveOrOBDates.has(dateStr));
                    if (isEditable) {
                        overlay.classList.add('is-edit-mode');
                        overlay.innerHTML = '<i class="bi bi-pencil-fill"></i>';
                    } else {
                        overlay.classList.remove('is-edit-mode');
                        overlay.innerHTML = '<i class="bi bi-plus-circle"></i>';
                    }
                }
            });
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
        const props = info.event.extendedProps;
        const type  = props.type;

        if (['day', 'night', 'rest'].includes(type) || props.isRestDay) {
            info.el.style.setProperty('background-color', 'transparent', 'important');
            info.el.style.setProperty('border-color',     'transparent', 'important');
            info.el.style.setProperty('box-shadow',       'none',        'important');

            const timeEl = info.el.querySelector('.fc-event-time');
            if (timeEl) {
                timeEl.style.setProperty('background-color', 'transparent', 'important');
                timeEl.style.setProperty('padding',          '0 2px',       'important');
                timeEl.style.setProperty('color',            'var(--text-color, #fff)', 'important');
            }

            const titleEl = info.el.querySelector('.fc-event-title');
            if (titleEl) {
                titleEl.style.setProperty('padding',      '2px 6px',     'important');
                titleEl.style.setProperty('border-radius','4px',         'important');
                titleEl.style.setProperty('display',      'inline-block','important');
                titleEl.style.setProperty('color',        '#ffffff',     'important');

                if (type === 'rest' || props.isRestDay) {
                    titleEl.style.setProperty('background-color', 'var(--shift-rest)',     'important');
                } else if (type === 'night') {
                    titleEl.style.setProperty('background-color', 'var(--shift-night)',    'important');
                } else if (type === 'day') {
                    titleEl.style.setProperty('background-color', 'var(--primary-color)',  'important');
                }
            }
        }
    },

    /* FIX Bug 2 — dateClick was empty; now actually opens the correct modal */
    dateClick: function (info) {
        if (_swSkipDateClick) return;
        swHandleSchedClick(info.dateStr);
    },

    /* Clicking a shift pill (Day/Night/Rest) doesn't fire dateClick in FullCalendar,
       so handle it here too — opens the same add/edit modal as clicking the cell. */
    eventClick: function (info) {
        if (_swSkipDateClick) return;
        const props = info.event.extendedProps;
        /* The "↪ until …" continuation pill edits its originating night shift */
        if (props.type === 'night-cont') {
            info.jsEvent.preventDefault();
            swHandleSchedClick(props.originDate || _swPrevDay(info.event.startStr.slice(0, 10)));
            return;
        }
        const editable = ['day', 'night', 'rest', 'leave-rejected'].includes(props.type) || props.isRestDay;
        if (!editable) return;
        info.jsEvent.preventDefault();
        swHandleSchedClick(info.event.startStr.slice(0, 10));
    },
});

/* YYYY-MM-DD of the day before the given date string */
function _swPrevDay(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    d.setDate(d.getDate() - 1);
    return `${d.getFullYear()}-${swPad(d.getMonth() + 1)}-${swPad(d.getDate())}`;
}

/* Shared add/edit entry point used by both dateClick and eventClick */
function swHandleSchedClick(dateStr) {
    /* On Leave / On OB days aren't schedules — clicking does nothing */
    if (swLeaveOrOBDates.has(dateStr)) return;

    /* A night-shift continuation day with no schedule of its own edits the
       originating night shift instead of trying to add a new one. */
    if (!swScheduledDates.has(dateStr) && swNightContOrigin.has(dateStr)) {
        dateStr = swNightContOrigin.get(dateStr);
    }

    /* Find an event on this date that carries an editable schedule: a normal
       day/night/rest shift, or a rejected leave/OB whose underlying shift stands. */
    const existing = swCalendar.getEvents().find(function (e) {
        if (e.startStr !== dateStr) return false;
        const p = e.extendedProps;
        return ['day', 'night', 'rest'].includes(p.type) || p.isRestDay ||
               (p.type === 'leave-rejected' && p.hasSchedule);
    });

    if (existing) {
        /* --- EDIT MODE --- */
        const props = existing.extendedProps;
        document.getElementById('swDeleteSchedBtn').style.display = 'block';
        /* store schedule id alongside date so delete can use it */
        _swPendingDeleteId = existing.id || null;
        swOpenManageModalAsEdit(
            dateStr,
            props.schedInVal  || '',
            props.schedOutVal || '',
            props.type === 'rest' || !!props.isRestDay
        );
    } else {
        /* --- ADD MODE --- */
        document.getElementById('swDeleteSchedBtn').style.display = 'none';
        _swPendingDeleteId   = null;
        _swPendingDeleteDate = null;
        swOpenManageModalWithDate(dateStr);
    }
}

swCalendar.render();

/* ---- Resize for sidebar / window ---- */
const swScopedSidebar = document.getElementById('sidebar');
if (swScopedSidebar) new ResizeObserver(function () { swCalendar.updateSize(); }).observe(swScopedSidebar);
window.addEventListener('resize', function () { swCalendar.updateSize(); });

/* ---- Delete button in manage modal footer ---- */
/* FIX Bug 1 — was trying to submit the add-form inline; now delegates to swDoDeleteConfirm
   which properly uses _swPendingDeleteDate / _swPendingDeleteId and shows the confirm modal */
document.getElementById('swDeleteSchedBtn').addEventListener('click', function () {
    if (!_swPendingDeleteDate) return;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('swDeleteSchedModal')).show();
});

/* ---- Flatpickr ---- */
swFpEdit = flatpickr('#swEditSchedDatePicker', {
    mode:       'range',
    dateFormat: 'Y-m-d',
    appendTo:   document.body,
    onChange: function (dates) {
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
    mode:       'range',
    dateFormat: 'Y-m-d',
    appendTo:   document.body,
    onChange: function (dates) {
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
document.querySelectorAll('#swRestDayToggles .rest-day-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const dow = parseInt(btn.dataset.dow);
        if (btn.classList.contains('active')) {
            btn.classList.remove('active');
            swSelectedRestDays = swSelectedRestDays.filter(function (d) { return d !== dow; });
        } else {
            if (swSelectedRestDays.length >= 2) return;
            btn.classList.add('active');
            swSelectedRestDays.push(dow);
        }
        document.getElementById('swRestDaysDirty').value = '1';
    });
});

/* FIX Bug 3 — wire up the single-date rest day checkbox so the time section reacts */
document.getElementById('swIsSingleRestDay').addEventListener('change', function () {
    const isRest = this.checked;
    const timeSection = document.getElementById('swTimeSection');
    if (timeSection) timeSection.style.display = isRest ? 'none' : '';
    if (!isRest) {
        document.getElementById('swAddModalTimeIn').value  = '';
        document.getElementById('swAddModalTimeOut').value = '';
    }
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

    /* In edit mode the date picker is hidden and we're intentionally editing the
       one existing date, so skip the redundant "already exists, replace?" confirm. */
    const isEditMode = document.getElementById('swSelectDatesSection').style.display === 'none';

    if (hasDates && !isEditMode) {
        const conflicts = datesToSchedule.filter(function (d) { return swScheduledDates.has(d); });
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
        .then(function (r) {
            if (!r.ok && r.status !== 200) throw new Error('save failed');
            bootstrap.Modal.getInstance(document.getElementById('swManageScheduleModal'))?.hide();
            const msg = SW_IS_WORKFORCE ? 'Schedule submitted for approval.' : 'Schedule saved successfully.';
            if (typeof showToast === 'function') showToast(msg, 'success');
            if (swCalendar) swCalendar.refetchEvents();
        })
        .catch(function () {
            if (typeof showToast === 'function') showToast('Failed to save schedule. Please try again.', 'danger');
        });
});

/* ---- Edit Schedule form submit ---- */
document.getElementById('swEditSchedForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!swPrepareEditSubmit()) return;
    fetch(this.getAttribute('action'), { method: 'POST', body: new FormData(this) })
        .then(function (r) { return r.text(); })
        .then(function (text) {
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
        .catch(function () {
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

/* ---- Preset schedule (custom tooltip-style popover) ----
   The menu is portaled to <body> and fixed-positioned so it escapes the modal's
   backdrop-filter containing block (otherwise it gets clipped inside the modal). */
(function () {
    const trigger = document.getElementById('swPresetSchedTrigger');
    const menu    = document.getElementById('swPresetSchedMenu');
    if (!trigger || !menu) return;

    let swPresetOpen = false;

    function positionPresetMenu() {
        const r = trigger.getBoundingClientRect();
        menu.style.position  = 'fixed';
        menu.style.top       = (r.bottom + 6) + 'px';
        menu.style.left      = r.left + 'px';
        /* setProperty with priority so a stale .w-100 (width:100% !important) can't win */
        menu.style.setProperty('width', r.width + 'px', 'important');
        /* Cap height to the space below the trigger so it scrolls instead of
           overflowing. Use !important to beat the global
           `.dropdown-menu { overflow: visible !important }` rule in components.css. */
        const avail = window.innerHeight - r.bottom - 16;
        menu.style.setProperty('max-height', Math.max(140, Math.min(240, avail)) + 'px', 'important');
        menu.style.setProperty('overflow-y', 'auto', 'important');
        menu.style.setProperty('overflow-x', 'hidden', 'important');
    }

    function openPresetMenu() {
        document.body.appendChild(menu);          // portal out of the modal
        menu.classList.add('show', 'sw-preset-tooltip');
        positionPresetMenu();
        swPresetOpen = true;
        trigger.setAttribute('aria-expanded', 'true');
        trigger.classList.add('show');
    }

    function closePresetMenu() {
        menu.classList.remove('show', 'sw-preset-tooltip');
        swPresetOpen = false;
        trigger.setAttribute('aria-expanded', 'false');
        trigger.classList.remove('show');
    }
    window.swClosePresetMenu = closePresetMenu;

    trigger.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        swPresetOpen ? closePresetMenu() : openPresetMenu();
    });

    // Close when clicking outside the menu/trigger
    document.addEventListener('click', function (e) {
        if (swPresetOpen && !menu.contains(e.target) && !trigger.contains(e.target)) closePresetMenu();
    });

    // Keep it anchored while open
    window.addEventListener('scroll', function () { if (swPresetOpen) positionPresetMenu(); }, true);
    window.addEventListener('resize', function () { if (swPresetOpen) positionPresetMenu(); });

    // Close when the modal is dismissed
    document.getElementById('swManageScheduleModal')?.addEventListener('hidden.bs.modal', closePresetMenu);

    // Select a preset
    menu.addEventListener('click', function (e) {
        const item = e.target.closest('.sw-preset-item');
        if (!item) return;
        e.preventDefault();
        document.getElementById('swAddModalTimeIn').value  = item.dataset.in;
        document.getElementById('swAddModalTimeOut').value = item.dataset.out;
        menu.querySelectorAll('.sw-preset-item').forEach(function (el) { el.classList.remove('active'); });
        item.classList.add('active');
        document.getElementById('swPresetSchedDisplay').textContent = item.textContent;
        closePresetMenu();
    });
})();

/* ---- Tab switcher ---- */
function swSwitchTab(tab) {
    const presetPanel = document.getElementById('swPresetPanel');
    const manualPanel = document.getElementById('swManualPanel');
    const tabPreset   = document.getElementById('swTabPreset');
    const tabManual   = document.getElementById('swTabManual');
    if (tab === 'preset') {
        presetPanel.style.display = '';
        manualPanel.style.display = 'none';
        tabPreset.classList.add('active');
        tabManual.classList.remove('active');
    } else {
        presetPanel.style.display = 'none';
        manualPanel.style.display = '';
        tabPreset.classList.remove('active');
        tabManual.classList.add('active');
    }
}
document.getElementById('swTabPreset').addEventListener('click', function () { swSwitchTab('preset'); });
document.getElementById('swTabManual').addEventListener('click', function () { swSwitchTab('manual'); });

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
    if (!single) document.getElementById('swIsSingleRestDay').checked = false;
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
    document.querySelector('#swManageScheduleModal .modal-title').innerHTML =
        '<i class="bi bi-calendar-week me-2"></i>Manage Schedule';
    document.getElementById('swAddSchedForm').querySelector('button[type="submit"]').innerHTML =
        `<i class="bi bi-check-circle-fill me-1"></i>${SW_IS_WORKFORCE ? 'Submit for Approval' : 'Save Schedule'}`;

    /* Show date picker section (hidden in edit mode) */
    document.getElementById('swSelectDatesSection').style.display = '';

    document.getElementById('swAddModalTimeIn').value  = '';
    document.getElementById('swAddModalTimeOut').value = '';
    document.querySelectorAll('#swPresetSchedMenu .sw-preset-item').forEach(function (el) { el.classList.remove('active'); });
    document.getElementById('swPresetSchedDisplay').textContent = 'Select a preset schedule…';
    swSwitchTab('preset');

    /* Reset time section visibility when opening fresh */
    const timeSection = document.getElementById('swTimeSection');
    if (timeSection) timeSection.style.display = '';

    swSelectedDatesAdd = [];
    swRenderDateTagsAdd();
    if (swFpAdd) swFpAdd.clear();
    swSelectedRestDays = [];
    document.querySelectorAll('#swRestDayToggles .rest-day-toggle').forEach(function (btn) { btn.classList.remove('active'); });

    document.getElementById('swRestDaysDirty').value          = '0';
    document.getElementById('swIsSingleRestDay').checked      = false;
    document.getElementById('swSingleDateRestDaySection').style.display = 'none';
    document.getElementById('swRestDaySection').style.display            = '';

    document.getElementById('swAddSchedForm').querySelector('input[name="action"]').value = 'save_combined';

    /* FIX Bug 1 — clear pending delete context when opening modal fresh */
    _swPendingDeleteDate = null;
    _swPendingDeleteId   = null;
    document.getElementById('swDeleteSchedBtn').style.display = 'none';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('swManageScheduleModal')).show();
}

function swOpenManageModalWithDate(dateStr) {
    swOpenManageModal();
    swSelectedDatesAdd  = [dateStr];
    _swPendingDeleteDate = dateStr;
    if (swFpAdd) swFpAdd.setDate([dateStr, dateStr], false);
    swRenderDateTagsAdd();
}

function swOpenManageModalAsEdit(dateStr, timeIn, timeOut, isRestDay) {
    swOpenManageModal();

    /* Format dateStr (YYYY-MM-DD) → "Month Day, Year" for the title */
    const _d = new Date(dateStr + 'T00:00:00');
    const _formatted = _d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

    document.querySelector('#swManageScheduleModal .modal-title').innerHTML =
        `<i class="bi bi-pencil-square me-2"></i>Edit Schedule for ${_formatted}`;
    document.getElementById('swAddSchedForm').querySelector('button[type="submit"]').innerHTML =
        `<i class="bi bi-check-circle-fill me-1"></i>${SW_IS_WORKFORCE ? 'Submit Edit for Approval' : 'Update Schedule'}`;

    /* Hide the date picker — date is already conveyed in the title */
    document.getElementById('swSelectDatesSection').style.display = 'none';

    /* Keep the form's native 'save_combined' action (set in swOpenManageModal).
       That handler updates existing rows in place and processes both selected_dates
       (schedule) and single_rest_dates (rest day), so rest⇄schedule edits both save.
       The old 'save_schedule' override ignored single_rest_dates, breaking the
       "scheduled day → rest day" conversion. */

    swSelectedDatesAdd   = [dateStr];
    _swPendingDeleteDate = dateStr;
    if (swFpAdd) swFpAdd.setDate([dateStr, dateStr], false);
    swRenderDateTagsAdd();

    const restDayCheck = document.getElementById('swIsSingleRestDay');
    if (isRestDay) {
        restDayCheck.checked = true;
        restDayCheck.dispatchEvent(new Event('change'));
    } else {
        restDayCheck.checked = false;
        restDayCheck.dispatchEvent(new Event('change'));
        swSwitchTab('manual');
        document.getElementById('swAddModalTimeIn').value  = timeIn;
        document.getElementById('swAddModalTimeOut').value = timeOut;
    }
}

function swOpenRestDayEditModal(dateStr) {
    swOpenManageModalWithDate(dateStr);
    const cb = document.getElementById('swIsSingleRestDay');
    cb.checked = true;
    cb.dispatchEvent(new Event('change'));
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

function swDeleteSchedule(date) {
    if (_swDeleteInProgress) return;
    _swPendingDeleteDate = date;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('swDeleteSchedModal')).show();
}

window.swDoDeleteConfirm = function () {
    const date = _swPendingDeleteDate;
    if (!date) return;

    bootstrap.Modal.getInstance(document.getElementById('swDeleteSchedModal'))?.hide();
    /* Also close the manage modal if it was open */
    bootstrap.Modal.getInstance(document.getElementById('swManageScheduleModal'))?.hide();

    _swDeleteInProgress = true;

    const base = SW_SAVE_API.replace(/\?.*$/, '');
    /* FIX Bug 4 — include schedule_id in delete request when available */
    const idParam = _swPendingDeleteId ? `&schedule_id=${encodeURIComponent(_swPendingDeleteId)}` : '';
    fetch(`${base}?employee_id=${SW_EMP_URL_ID}&ajax_delete=1&emp=${SW_EMP_ID}&date=${date}${idParam}`)
        .then(function (r) { return r.text(); })
        .then(function (text) {
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
            document.dispatchEvent(new CustomEvent('scheduleDeleted', { detail: { date: date } }));
        })
        .catch(function () {
            if (typeof showToast === 'function') showToast('Something went wrong. Please try again.', 'danger');
        })
        .finally(function () {
            _swDeleteInProgress  = false;
            _swPendingDeleteDate = null;
            _swPendingDeleteId   = null;
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
let swPendingDate   = null;
let swEditPayload   = null;
let swFpEvtDate     = null;

/* ================================================================
   MODE: ADMIN GLOBAL (Calendar Setup Snippet)
   ================================================================ */
swCalendar = new FullCalendar.Calendar(calEl, {
    initialView:   'dayGridMonth',
    firstDay:      0,
    editable:      false,
    selectable:    true, // Enables clickable cell overlays
    
    dateClick: function (info) {
        // Look for the specific Flatpickr instance for your events modal
        // (Usually called swFpEvtDate or similar where you initialize flatpickr('#swEvtDate'))
        const dateInput = document.getElementById('swEvtDate');
        
        if (window.swFpEvtDate) {
            window.swFpEvtDate.setDate(info.dateStr, true);
        } else if (dateInput && dateInput._flatpickr) {
            // Fallback if it is tied directly onto the element's instance properties
            dateInput._flatpickr.setDate(info.dateStr, true);
        } else if (dateInput) {
            dateInput.value = info.dateStr;
        }

        // Reset standard clean form fields
        document.getElementById('swEvtId').value = '';
        document.getElementById('swEvtTitle').value = '';
        document.getElementById('swEvtDescription').value = '';
        document.getElementById('swAddEventError').classList.add('d-none');
        
        document.getElementById('swEvtType').value = 'other';
        document.getElementById('swEvtTypeDropdownLabel').textContent = 'Other';
        document.getElementById('swAddEventModalTitleText').textContent = 'Add Event';

        const modalEl = document.getElementById('swAddEventModal');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    },

    eventOrder: function (a, b) {
        const aIsCont = a.extendedProps.shift_type === 'night_continuation';
        const bIsCont = b.extendedProps.shift_type === 'night_continuation';
        if (aIsCont && !bIsCont) return -1;
        if (!aIsCont && bIsCont) return  1;
        return 0;
    },

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
        if (st && st !== 'cal_event' && st !== 'birthday') {
            return { html: '<div class="fc-admin-inner"><span class="fc-admin-label">' + arg.event.title + '</span></div>' };
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
            info.el.style.cursor = 'pointer';
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

    datesSet: function (info) {
        const d     = info.view.currentStart;
        const year  = d.getFullYear();
        const month = d.getMonth() + 1;
        const start = `${year}-${swPad(month)}-01`;
        const end   = `${year}-${swPad(month)}-${swPad(new Date(year, month, 0).getDate())}`;
        const monthName = d.toLocaleDateString('en-US', { month: 'long' });

        document.querySelectorAll('.sched-month-label').forEach(function (el) {
            el.textContent = 'This ' + monthName;
        });

        fetch(`${SW_STATS_API}?start=${start}&end=${end}`)
            .then(function (r) { return r.json(); })
            .then(function (s) {
                [['sched-stat-day', s.day], ['sched-stat-night', s.night],
                 ['sched-stat-rest', s.rest], ['sched-stat-leave', s.leave],
                 ['sched-stat-ob', s.ob]].forEach(function ([id, val]) {
                    const el = document.getElementById(id);
                    if (el) el.textContent = val ?? '—';
                });
            })
            .catch(function () {});
    },

    eventsSet: function (events) {
        const eventDates = new Set(events.map(function (e) { return e.startStr; }));
        document.querySelectorAll('#sw-calendar .fc-daygrid-day').forEach(function (cell) {
            const hasEvent = eventDates.has(cell.dataset.date);
            cell.classList.toggle('fc-day-has-events', hasEvent);
            const tip = bootstrap.Tooltip.getInstance(cell);
            if (tip) { if (hasEvent) tip.disable(); else tip.enable(); }
        });
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
    new ResizeObserver(function () { swCalendar.updateSize(); }).observe(swSidebar);
}
window.addEventListener('resize', function () { swCalendar.updateSize(); });

/* ---- Custom refresh button icon ---- */
const swRefreshBtn = calEl.querySelector('.fc-refresh-button');
if (swRefreshBtn) swRefreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';

// Initialize Flatpickr for the Add Event date input field
window.swFpEvtDate = flatpickr("#swEvtDate", {
    altInput: true,                  // Show a hidden, formatted input to the user
    altFormat: "F j, Y",             // Human-readable format: Month Day, Year (e.g., June 4, 2026)
    dateFormat: "Y-m-01" < "Y-m-d" ? "Y-m-d" : "Y-m-d", // Keeps underlying backend value as YYYY-MM-DD
    allowInput: false
});
// ==========================================================
// BOOTSTRAP DROPDOWN VALUE INTERCEPTOR (EVENT TYPE)
// ==========================================================
document.querySelectorAll('#swAddEventModal .dropdown-item').forEach(function (item) {
    item.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        
        const selectedValue = this.getAttribute('data-value');
        const selectedText  = this.textContent.trim();
        
        // Update the hidden input element so the form serializes perfectly
        const hiddenInput = document.getElementById('swEvtType');
        if (hiddenInput) {
            hiddenInput.value = selectedValue;
        }
        
        // Update the human-readable visible text on the button label
        const visualLabel = document.getElementById('swEvtTypeDropdownLabel');
        if (visualLabel) {
            visualLabel.textContent = selectedText;
        }

        // PROGRAMMATICALLY COLLAPSE THE DROPDOWN CONTAINER SAFELY
        const dropdownBtn = document.getElementById('swEvtTypeDropdownBtn');
        if (dropdownBtn) {
            const bsDropdown = bootstrap.Dropdown.getOrCreateInstance(dropdownBtn);
            bsDropdown.hide();
        }
    });
});

/* ---- Add Event Modal: populate on show ---- */
document.getElementById('swAddEventModal').addEventListener('show.bs.modal', function () {
    document.getElementById('swAddEventError').classList.add('d-none');
    if (swEditPayload) {
        document.getElementById('swAddEventModalTitleText').textContent = 'Edit Event';
        document.getElementById('swEvtId').value = swEditPayload.id;
        document.getElementById('swEvtTitle').value = swEditPayload.title;
        document.getElementById('swEvtType').value = swEditPayload.event_type;
        document.getElementById('swEvtDescription').value = swEditPayload.description;
        if (swFpEvtDate) swFpEvtDate.setDate(swEditPayload.date, false);

        // SYNC BOOTSTRAP DROPDOWN LABEL TEXT ON EDIT
        const currentType = swEditPayload.event_type || 'other';
        const matchingItem = document.querySelector(`#swAddEventModal .dropdown-item[data-value="${currentType}"]`);
        document.getElementById('swEvtTypeDropdownLabel').textContent = matchingItem ? matchingItem.textContent : 'Other';

    } else {
        document.getElementById('swAddEventModalTitleText').textContent = 'Add Event';
        document.getElementById('swEvtId').value = '';
        document.getElementById('swEvtTitle').value = '';
        document.getElementById('swEvtType').value = 'other';
        document.getElementById('swEvtDescription').value = '';
        if (!document.getElementById('swEvtDate').value) {
            swFpEvtDate.clear();
        }

        // RESET BOOTSTRAP DROPDOWN LABEL TEXT ON ADD NEW
        document.getElementById('swEvtTypeDropdownLabel').textContent = 'Other';
    }
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
            // LOOK HERE: Grabs your hidden form parameter value seamlessly!
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
let _swPendingDeleteEvtId = null;

document.getElementById('swViewEvtDeleteBtn').addEventListener('click', function () {
    const id = this.dataset.id;
    if (!id) return;
    _swPendingDeleteEvtId = id;
    bootstrap.Modal.getInstance(document.getElementById('swViewEventModal'))?.hide();
    document.getElementById('swViewEventModal').addEventListener('hidden.bs.modal', function openConfirm() {
        this.removeEventListener('hidden.bs.modal', openConfirm);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('swDeleteEventConfirmModal')).show();
    });
});

document.getElementById('swDeleteEventConfirmBtn').addEventListener('click', async function () {
    const id = _swPendingDeleteEvtId;
    if (!id) return;
    _swPendingDeleteEvtId = null;
    bootstrap.Modal.getInstance(document.getElementById('swDeleteEventConfirmModal'))?.hide();
    try {
        const res  = await fetch(SW_EVENT_API, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'delete', id }),
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        }
    } catch (e) {
        if (typeof showToast === 'function') showToast('Network error. Please try again.', 'danger');
    }
});

} /* end admin global block */

<?php else: ?>
{
/* ================================================================
   MODE: EMPLOYEE  (personal schedule, read-only)
================================================================ */
swCalendar = new FullCalendar.Calendar(calEl, {
    initialView:  'dayGridMonth',
    firstDay:     0,
    initialDate:  <?= json_encode($schedInitialDate) ?>,
    eventDisplay: 'block',
    dayMaxEvents: false,
    height:       '100%',

    eventOrder: function (a, b) {
        const aIsCont = a.extendedProps.shift_type === 'night_continuation';
        const bIsCont = b.extendedProps.shift_type === 'night_continuation';
        if (aIsCont && !bIsCont) return -1;
        if (!aIsCont && bIsCont) return  1;
        return 0;
    },

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
        if (st && st !== 'cal_event' && st !== 'birthday') {
            return { html: '<div class="fc-admin-inner"><span class="fc-admin-label">' + arg.event.title + '</span></div>' };
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
            if      (st === 'day')            c.day++;
            else if (st === 'night')          c.night++;
            else if (st === 'rest')           c.rest++;
            else if (st === 'leave_approved') c.leave++;
            else if (st === 'ob_approved')    c.ob++;
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
            document.getElementById('swEmpViewEvtTitle').textContent     = info.event.title;
            document.getElementById('swEmpViewEvtTypeBadge').innerHTML   = '<i class="bi bi-cake me-1"></i>Birthday';
            document.getElementById('swEmpViewEvtTypeBadge').style.color = '#8b5cf6';
            document.getElementById('swEmpViewEvtDate').textContent      = dateStr;
            document.getElementById('swEmpViewEvtDesc').textContent      = '';
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

        document.getElementById('swEmpViewEvtTitle').textContent     = info.event.title;
        document.getElementById('swEmpViewEvtTypeBadge').innerHTML   = `<i class="bi ${icon} me-1"></i>${et.charAt(0).toUpperCase()+et.slice(1)}`;
        document.getElementById('swEmpViewEvtTypeBadge').style.color = color;
        document.getElementById('swEmpViewEvtDate').textContent      = dateStr;
        document.getElementById('swEmpViewEvtDesc').textContent      = props.description || '—';

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
if (swSidebar) new ResizeObserver(function () { swCalendar.updateSize(); }).observe(swSidebar);
window.addEventListener('resize', function () { swCalendar.updateSize(); });

} /* end employee block */

<?php endif; ?>
}); /* end DOMContentLoaded */
</script>