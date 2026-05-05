<!-- TOAST CONTAINER -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 99999;">
  <div id="appToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
    <div class="d-flex">
      <div class="toast-body" id="appToastMsg"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<!-- OT REQUEST MODAL -->
<div class="modal fade" id="otModal" tabindex="-1" aria-labelledby="otModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="otModalLabel">File OT Request</h5>
      </div>

      <div class="modal-body">
        <div id="otStep1">
          <p class="ot-step-hint">Select a day to file OT for:</p>
          <div id="otGanttList">
            <p class="ot-gantt-loading">Loading...</p>
          </div>
        </div>

        <div id="otStep2" style="display:none;">
          <div class="ot-summary-card">
            <p class="ot-summary-label">Selected Date</p>
            <p class="ot-summary-value" id="otSelectedDate"></p>
            <p class="ot-summary-label mt-2">OT Period</p>
            <p class="ot-summary-value highlight" id="otSelectedTime"></p>
            <p class="ot-summary-label mt-2">OT Duration</p>
            <p class="ot-summary-value" id="otSelectedDuration"></p>
          </div>

          <label class="ot-reason-label form-label mt-3">Reason for OT</label>
          <textarea id="otReason" class="form-control" rows="3" placeholder="Enter reason for overtime..."></textarea>
        </div>
      </div>

      <div class="modal-footer" id="otModalFooter">
        <div id="otFooterStep1">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
        <div id="otFooterStep2" style="display:none;">
          <button type="button" class="btn btn-outline-secondary" onclick="backToStep1()">
            <i class="bi bi-arrow-left"></i> Back
          </button>
          <button type="button" class="btn btn-primary" onclick="submitOTRequest()">
            <i class="bi bi-check-circle-fill"></i> Submit OT Request
          </button>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- LEAVE REQUEST MODAL -->
<div class="modal fade" id="leaveModal" tabindex="-1" aria-labelledby="leaveModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="leaveModalLabel">File Leave Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Leave Type</label>
          <div class="leaveTypeWrapper">
            <div class="leaveTypeToggle" onclick="toggleLeaveTypeDropdown()">
              <span id="leaveTypeLabel">Select leave type...</span>
              <i class="bi bi-chevron-down"></i>
            </div>
            <div class="leaveTypeMenu" id="leaveTypeDropdown">
              <div class="leaveTypeItem" onclick="selectLeaveType('sick leave', 'Sick Leave')">Sick Leave</div>
              <div class="leaveTypeItem" onclick="selectLeaveType('vacation leave', 'Vacation Leave')">Vacation Leave</div>
              <div class="leaveTypeItem" onclick="selectLeaveType('birthday leave', 'Birthday Leave')">Birthday Leave</div>
              <div class="leaveTypeItem" onclick="selectLeaveType('solo parent leave', 'Solo Parent Leave')">Solo Parent Leave</div>
            </div>
          </div>
          <input type="hidden" id="leaveType" value="">
        </div>

        <p class="leave-instruction" id="leaveInstruction">
          Select a leave type first to load the calendar.
        </p>

        <div id="leaveSelectedDates" class="leave-selected-dates-box" style="display:none;">
          <p class="leave-selected-dates-label">Selected Dates</p>
          <p class="leave-selected-dates-text" id="leaveSelectedDatesText"></p>
        </div>

        <div id="leaveCalendar"></div>

        <label class="form-label mt-3">Reason</label>
        <textarea id="leaveReason" class="form-control" rows="3" placeholder="Enter reason for leave..."></textarea>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="submitLeaveRequest()">
          <i class="bi bi-check-circle-fill"></i> Submit Leave Request
        </button>
      </div>

    </div>
  </div>
</div>

<!-- OB REQUEST MODAL -->
<div class="modal fade" id="obModal" tabindex="-1" aria-labelledby="obModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="obModalLabel">File OB Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p class="ob-instruction">Select a <strong class="ob-instruction-highlight">work date</strong> for your official business:</p>

        <div id="obSelectedDateBox" class="ob-selected-date-box" style="display:none;">
          <p class="ob-selected-date-label">Selected Date</p>
          <p class="ob-selected-date-text" id="obSelectedDateText"></p>
        </div>

        <div id="obCalendar"></div>

        <label class="form-label mt-3">Client Name</label>
        <input type="text" id="obClientName" class="form-control" placeholder="Enter client name...">

        <label class="form-label mt-3">Reason</label>
        <textarea id="obReason" class="form-control" rows="3" placeholder="Enter reason for official business..."></textarea>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="submitOBRequest()">
          <i class="bi bi-check-circle-fill"></i> Submit OB Request
        </button>
      </div>

    </div>
  </div>
</div>

<!-- LOG EDIT REQUEST MODAL -->
<div class="modal fade" id="logEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Request Log Edit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div id="leStep1">
          <p class="text-lightest">Select a record to correct your attendance log:</p>
          <div id="leGanttList">
            <p class="text-muted">Loading...</p>
          </div>
        </div>

        <div id="leStep2" style="display:none;">

          <button type="button" class="le-back-btn" onclick="leBackToStep1()">
            <i class="bi bi-arrow-left"></i> Back
          </button>

          <div class="le-summary-card">
            <p class="le-summary-label">Date</p>
            <p class="le-summary-value" id="leSelectedDate"></p>
            <div class="le-summary-row">
              <div>
                <p class="le-summary-label">Scheduled</p>
                <p class="le-summary-value" id="leSelectedSched"></p>
              </div>
              <div>
                <p class="le-summary-label">Current Log</p>
                <p class="le-summary-value" id="leCurrentLog"></p>
              </div>
            </div>
            <p class="le-summary-label" style="margin-top:.75rem;">Status</p>
            <div id="leStatusBadges"></div>
          </div>

          <label class="le-input-label">Edit Type</label>
          <div class="le-custom-select" id="leEditTypeWrapper">
            <div class="le-custom-select-trigger" id="leEditTypeTrigger" onclick="toggleLeSelect()">
              <span id="leEditTypeLabel">Select type...</span>
              <i class="bi bi-chevron-down le-select-chevron"></i>
            </div>
            <div class="le-custom-select-options" id="leEditTypeOptions"></div>
          </div>

          <div id="leTimeInGroup" style="display:none;" class="mt-3">
            <label class="le-input-label">Requested Time In</label>
            <input type="time" id="leRequestedTimeIn" class="le-time-input" oninput="updateLePreview()">
          </div>

          <div id="leTimeOutGroup" style="display:none;" class="mt-3">
            <label class="le-input-label">Requested Time Out</label>
            <input type="time" id="leRequestedTimeOut" class="le-time-input" oninput="updateLePreview()">
          </div>

          <div class="mt-3">
            <label class="le-input-label">Reason</label>
            <textarea id="leReason" rows="3" placeholder="Explain the reason for this correction..."></textarea>
          </div>

          <div class="le-preview-box" id="lePreviewBox">
            <p class="le-preview-label">Preview</p>
            <p class="le-preview-text" id="lePreviewText">—</p>
          </div>

          <button type="button" class="le-submit-btn w-100 mt-3" onclick="submitLogEditRequest()">
            <i class="bi bi-check-circle-fill"></i> Submit Request
          </button>

        </div>

      </div>

    </div>
  </div>
</div>

<script>
  // ===== SHARED UTILITIES =====

  function showToast(message, type = 'error') {
    const toast = document.getElementById('appToast');
    const msg   = document.getElementById('appToastMsg');

    toast.classList.remove('bg-danger', 'bg-success', 'text-white');
    toast.classList.add(type === 'success' ? 'bg-success' : 'bg-danger', 'text-white');
    msg.textContent = message;

    bootstrap.Toast.getOrCreateInstance(toast).show();
  }

  function localDateStr(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  function todayStr() {
    return localDateStr(new Date());
  }

  function fmtTime(ts) {
    return new Date(ts * 1000).toLocaleTimeString('en-US', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
      timeZone: 'Asia/Manila'
    });
  }

  function fmtDate(d) {
    return new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  }

  function fmtShort(d) {
    return new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric'
    });
  }

  // ===== MODAL INSTANCES =====

  let otModal, leaveModal, obModal;

  document.addEventListener('DOMContentLoaded', () => {
    otModal    = new bootstrap.Modal(document.getElementById('otModal'));
    leaveModal = new bootstrap.Modal(document.getElementById('leaveModal'));
    obModal    = new bootstrap.Modal(document.getElementById('obModal'));
  });

  // ===== OT MODAL =====

  let otSelectedRecord = null;

  document.getElementById('otModal').addEventListener('hidden.bs.modal', () => {
    otSelectedRecord = null;
  });

  function openOTModal() {
    document.getElementById('otStep1').style.display     = 'block';
    document.getElementById('otStep2').style.display     = 'none';
    document.getElementById('otFooterStep1').style.display = 'flex';
    document.getElementById('otFooterStep2').style.display = 'none';
    otSelectedRecord = null;

    otModal.show();
    loadOTGantt();
  }

  function backToStep1() {
    document.getElementById('otStep2').style.display       = 'none';
    document.getElementById('otStep1').style.display       = 'block';
    document.getElementById('otFooterStep2').style.display = 'none';
    document.getElementById('otFooterStep1').style.display = 'flex';
  }

  function loadOTGantt() {
    const list = document.getElementById('otGanttList');
    list.innerHTML = '<p class="ot-gantt-loading">Loading...</p>';

    fetch('../employee_pages/get_ot_records.php')
      .then(res => res.json())
      .then(records => {
        if (records.length === 0) {
          list.innerHTML = '<p class="ot-gantt-loading">No OT records available to file.</p>';
          return;
        }

        list.innerHTML = '';

        records.forEach(row => {
          const date      = row.date;
          const lateMin   = parseInt(row.late_minutes) || 0;
          const otMin     = parseInt(row.overtime_minutes) || 0;
          const schedIn   = row.scheduled_time_in;
          const schedOut  = row.scheduled_time_out;
          const actualIn  = row.actual_time_in;
          const actualOut = row.actual_time_out;
          const isPending = row.overtime_status === 'pending';
          const canFile   = !isPending && lateMin < 60;

          const tsSchedIn   = Date.parse(schedIn.replace(' ', 'T'))  / 1000;
          const tsSchedOut  = Date.parse(schedOut.replace(' ', 'T')) / 1000;
          const tsActualIn  = Date.parse(actualIn.replace(' ', 'T')) / 1000;
          const tsActualOut = Date.parse(actualOut.replace(' ', 'T')) / 1000;

          const rangeStart  = tsSchedIn - 7200;
          const range       = 64800;

          const schedLeft   = ((tsSchedIn   - rangeStart) / range) * 100;
          const schedWidth  = ((tsSchedOut  - tsSchedIn)  / range) * 100;
          const actualLeft  = ((tsActualIn  - rangeStart) / range) * 100;
          const actualWidth = ((tsActualOut - tsActualIn) / range) * 100;
          const otLeft      = ((tsSchedOut  - rangeStart) / range) * 100;
          const otWidth     = ((tsActualOut - tsSchedOut) / range) * 100;
          const inPos       = ((tsActualIn  - rangeStart) / range) * 100;
          const outPos      = ((tsActualOut - rangeStart) / range) * 100;

          const otHours = Math.floor(otMin / 60);
          const otMins  = otMin % 60;
          const otLabel = otHours > 0 ? `${otHours}h ${otMins}m` : `${otMins}m`;

          const rowEl = document.createElement('div');
          rowEl.className = `ot-gantt-row ${isPending ? 'ot-pending' : canFile ? 'can-file' : 'cannot-file'}`;

          rowEl.innerHTML = `
            <div class="ot-gantt-row-inner">
              <div class="ot-date-label">
                <div class="ot-date-label-day">${fmtDate(date).split(',')[0]}</div>
                <div class="ot-date-label-short">${fmtShort(date)}</div>
              </div>
              <div class="gantt-bar-container">
                <div class="gantt-cursor">
                  <div class="gantt-cursor-line"></div>
                  <div class="gantt-cursor-label"></div>
                </div>
                <div class="gantt-sched-bar"      style="left:${schedLeft}%;  width:${schedWidth}%;"></div>
                <div class="gantt-actual-bar"     style="left:${actualLeft}%; width:${actualWidth}%;"></div>
                <div class="gantt-ot-bar"         style="left:${otLeft}%;     width:${otWidth}%;"></div>
                <div class="gantt-timein-marker"  style="left:${inPos}%;"></div>
                <div class="gantt-timeout-marker" style="left:${outPos}%;"></div>
              </div>
              <div class="ot-duration-label ${isPending ? 'ot-pending-label' : ''}">
                ${isPending ? 'OT Pending' : '+' + otLabel + ' OT'}
              </div>
            </div>
            ${isPending ? `
            <div class="ot-late-warning can-file">
              <i class="bi bi-hourglass-split"></i> OT request is pending admin approval.
            </div>` : lateMin > 0 ? `
            <div class="ot-late-warning ${canFile ? 'can-file' : 'cannot-file'}">
              <i class="bi bi-clock"></i> Late: ${lateMin} min${lateMin !== 1 ? 's' : ''}
              ${!canFile ? ' — <strong>Cannot file OT (late ≥ 60 mins)</strong>' : ''}
            </div>` : ''}
          `;

          rowEl.addEventListener('click', () => {
            if (isPending) {
              showToast('You already have a pending OT request for this date.');
              return;
            }
            if (!canFile) {
              showToast('You cannot file an OT request because you were late for more than an hour.');
              return;
            }

            otSelectedRecord = {
              date,
              time_in:  schedOut,
              time_out: actualOut.split(' ')[1],
              otMin
            };

            document.getElementById('otSelectedDate').textContent     = fmtDate(date);
            document.getElementById('otSelectedTime').textContent     = `${fmtTime(tsSchedOut)} — ${fmtTime(tsActualOut)}`;
            document.getElementById('otSelectedDuration').textContent = otLabel;
            document.getElementById('otReason').value                 = '';

            document.getElementById('otStep1').style.display       = 'none';
            document.getElementById('otStep2').style.display       = 'block';
            document.getElementById('otFooterStep1').style.display = 'none';
            document.getElementById('otFooterStep2').style.display = 'flex';
          });

          list.appendChild(rowEl);
        });

        initGanttCursors();
      })
      .catch(() => {
        showToast('Failed to load OT records. Please try again.');
      });
  }

  function submitOTRequest() {
    const reason = document.getElementById('otReason').value.trim();

    if (!reason) {
      showToast('Please enter a reason for your OT request.');
      return;
    }
    if (!otSelectedRecord) return;

    const formData = new FormData();
    formData.append('date',     otSelectedRecord.date);
    formData.append('time_in',  otSelectedRecord.time_in);
    formData.append('time_out', otSelectedRecord.time_out);
    formData.append('reason',   reason);

    fetch('/DTR-Internship-Project/employee_pages/submit_ot_request.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast(data.message, 'success');
          document.getElementById('otStep2').style.display       = 'none';
          document.getElementById('otFooterStep2').style.display = 'none';
          setTimeout(() => otModal.hide(), 2000);
        } else {
          showToast(data.message);
        }
      })
      .catch(() => {
        showToast('Something went wrong. Please try again.');
      });
  }

  // ===== LEAVE MODAL =====

  let leaveCalendarInstance = null;
  let leaveScheduledDates   = [];
  let leaveExistingDates    = [];
  let leaveSelectedDates    = [];
  let currentLeaveType      = '';

  document.getElementById('leaveModal').addEventListener('hidden.bs.modal', () => {
    if (leaveCalendarInstance) {
      leaveCalendarInstance.destroy();
      leaveCalendarInstance = null;
    }
    leaveSelectedDates = [];
    leaveExistingDates = [];
    currentLeaveType   = '';
  });

  document.getElementById('leaveModal').addEventListener('shown.bs.modal', () => {
    if (leaveCalendarInstance) leaveCalendarInstance.updateSize();
  });

  function getLeaveRules(type) {
    switch (type) {
      case 'sick leave':         return { maxDays: 4,   direction: 'past',   label: 'up to 4 past dates only (before today)' };
      case 'vacation leave':    return { maxDays: 999, direction: 'future', label: 'future dates only' };
      case 'birthday leave':    return { maxDays: 1,   direction: 'any',    label: '1 day only' };
      case 'solo parent leave': return { maxDays: 2,   direction: 'any',    label: 'up to 2 days' };
      default:                   return { maxDays: 0,   direction: 'none',   label: '' };
    }
  }

  function isDateSelectable(dateStr, rules) {
    if (leaveExistingDates.includes(dateStr)) return false;
    const today = todayStr();
    if (rules.direction === 'past')   return dateStr < today;
    if (rules.direction === 'future') return dateStr > today;
    if (rules.direction === 'any')    return true;
    return false;
  }

  function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function toggleLeaveTypeDropdown() {
    document.getElementById('leaveTypeDropdown').classList.toggle('show');
  }

  function selectLeaveType(value, label) {
    document.getElementById('leaveType').value            = value;
    document.getElementById('leaveTypeLabel').textContent = label;
    document.getElementById('leaveTypeDropdown').classList.remove('show');
    document.querySelector('.leaveTypeToggle').classList.add('selected');

    currentLeaveType   = value;
    leaveSelectedDates = [];

    const rules = getLeaveRules(value);
    document.getElementById('leaveInstruction').innerHTML =
      `Select <strong class="leave-instruction-highlight">${rules.label}</strong> from the calendar below:`;

    document.getElementById('leaveSelectedDates').style.display    = 'none';
    document.getElementById('leaveSelectedDatesText').textContent  = '';

    fetch(`/DTR-Internship-Project/employee_pages/get_schedule_dates.php?leave_type=${encodeURIComponent(value)}`)
      .then(res => res.json())
      .then(data => {
        leaveScheduledDates = data.scheduledDates ?? [];
        leaveExistingDates  = data.leaveDates     ?? [];
        renderLeaveCalendar();
      })
      .catch(() => {
        showToast('Failed to load schedule dates. Please try again.');
      });
  }

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.leaveTypeWrapper')) {
      const dropdown = document.getElementById('leaveTypeDropdown');
      if (dropdown) dropdown.classList.remove('show');
    }
  });

  function openLeaveModal() {
    document.getElementById('leaveSelectedDates').style.display   = 'none';
    document.getElementById('leaveSelectedDatesText').textContent = '';
    document.getElementById('leaveReason').value                  = '';
    document.getElementById('leaveType').value                    = '';
    document.getElementById('leaveTypeLabel').textContent         = 'Select leave type...';
    document.querySelector('.leaveTypeToggle').classList.remove('selected');
    document.getElementById('leaveInstruction').textContent       = 'Select a leave type first to load the calendar.';

    currentLeaveType   = '';
    leaveSelectedDates = [];
    leaveExistingDates = [];

    renderLeaveCalendar();
    leaveModal.show();
  }

  function renderLeaveCalendar() {
    if (leaveCalendarInstance) {
      leaveCalendarInstance.destroy();
      leaveCalendarInstance = null;
    }

    const calendarEl = document.getElementById('leaveCalendar');
    calendarEl.innerHTML = '';
    const rules = getLeaveRules(currentLeaveType);

    leaveCalendarInstance = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      height: 400,
      headerToolbar: { left: 'prev,next today', center: 'title', right: '' },

      events: leaveScheduledDates.map(date => ({
        start: date, display: 'background', color: 'rgba(151, 190, 65, 0.12)'
      })),

      dayCellDidMount: function(info) {
        const dateStr    = localDateStr(info.date);
        const isOnLeave  = leaveExistingDates.includes(dateStr);
        const selectable = currentLeaveType && isDateSelectable(dateStr, rules);

        if (isOnLeave) {
          info.el.classList.add('fc-day-on-leave');
          info.el.classList.add('fc-day-dimmed');
        } else if (!selectable) {
          info.el.classList.add('fc-day-dimmed');
        }

        if (leaveSelectedDates.includes(dateStr)) {
          info.el.classList.add('fc-day-selected');
          const dayTop = info.el.querySelector('.fc-daygrid-day-top');
          if (dayTop && !dayTop.querySelector('.leave-check')) {
            const check = document.createElement('span');
            check.className = 'leave-check';
            check.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
            dayTop.appendChild(check);
          }
        }
      },

      dateClick: function(info) {
        const dateStr = info.dateStr;

        if (!currentLeaveType) {
          showToast('Please select a leave type first.');
          return;
        }
        if (leaveExistingDates.includes(dateStr)) {
          showToast('This date already has an existing leave request.');
          return;
        }
        if (!isDateSelectable(dateStr, rules)) {
          showToast(rules.direction === 'past'
            ? 'Sick leave can only be filed for past dates (before today).'
            : 'Vacation leave can only be filed for future dates.');
          return;
        }

        const idx = leaveSelectedDates.indexOf(dateStr);
        if (idx !== -1) {
          leaveSelectedDates.splice(idx, 1);
        } else {
          if (leaveSelectedDates.length >= rules.maxDays) {
            showToast(`${capitalize(currentLeaveType)} is limited to ${rules.maxDays} day${rules.maxDays > 1 ? 's' : ''} only.`);
            return;
          }
          leaveSelectedDates.push(dateStr);
          leaveSelectedDates.sort();
        }

        updateLeaveSelectedDisplay();
        renderLeaveCalendar();
      }
    });

    leaveCalendarInstance.render();
  }

  function updateLeaveSelectedDisplay() {
    const display = document.getElementById('leaveSelectedDates');
    const textEl  = document.getElementById('leaveSelectedDatesText');

    if (leaveSelectedDates.length === 0) {
      display.style.display = 'none';
      return;
    }

    display.style.display = 'block';
    textEl.textContent = leaveSelectedDates.length === 1
      ? fmtDate(leaveSelectedDates[0])
      : `${fmtDate(leaveSelectedDates[0])} → ${fmtDate(leaveSelectedDates[1])}`;
  }

  function submitLeaveRequest() {
    const leaveType = document.getElementById('leaveType').value;
    const reason    = document.getElementById('leaveReason').value.trim();

    if (!leaveType) {
      showToast('Please select a leave type.');
      return;
    }
    if (leaveSelectedDates.length === 0) {
      showToast('Please select at least 1 date.');
      return;
    }
    if (!reason) {
      showToast('Please enter a reason for your leave.');
      return;
    }

    const startDate = leaveSelectedDates[0];
    const endDate   = leaveSelectedDates[leaveSelectedDates.length - 1];

    const formData = new FormData();
    formData.append('leave_type',     leaveType);
    formData.append('start_date',     startDate);
    formData.append('end_date',       endDate);
    formData.append('selected_dates', JSON.stringify(leaveSelectedDates));
    formData.append('reason',         reason);

    fetch('/DTR-Internship-Project/employee_pages/submit_leave_request.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast(data.message, 'success');
          leaveSelectedDates = [];
          setTimeout(() => leaveModal.hide(), 2000);
        } else {
          showToast(data.message);
        }
      })
      .catch(() => {
        showToast('Something went wrong. Please try again.');
      });
  }

  // ===== OB MODAL =====

  let obCalendarInstance = null;
  let obScheduledDates   = [];
  let obExistingDates    = [];
  let obSelectedDate     = null;

  document.getElementById('obModal').addEventListener('hidden.bs.modal', () => {
    if (obCalendarInstance) {
      obCalendarInstance.destroy();
      obCalendarInstance = null;
    }
    obSelectedDate   = null;
    obScheduledDates = [];
    obExistingDates  = [];
  });

  document.getElementById('obModal').addEventListener('shown.bs.modal', () => {
    if (obCalendarInstance) obCalendarInstance.updateSize();
  });

  function openOBModal() {
    document.getElementById('obSelectedDateBox').style.display = 'none';
    document.getElementById('obSelectedDateText').textContent  = '';
    document.getElementById('obClientName').value              = '';
    document.getElementById('obReason').value                  = '';
    obSelectedDate   = null;
    obScheduledDates = [];
    obExistingDates  = [];

    fetch('/DTR-Internship-Project/employee_pages/get_ob_dates.php')
      .then(res => res.json())
      .then(data => {
        obScheduledDates = data.scheduledDates ?? [];
        obExistingDates  = data.obDates        ?? [];
        renderOBCalendar();
      })
      .catch(() => {
        showToast('Failed to load schedule dates. Please try again.');
        renderOBCalendar();
      });

    obModal.show();
  }

  function renderOBCalendar() {
    if (obCalendarInstance) {
      obCalendarInstance.destroy();
      obCalendarInstance = null;
    }

    const calendarEl = document.getElementById('obCalendar');
    calendarEl.innerHTML = '';

    obCalendarInstance = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      height: 400,
      headerToolbar: { left: 'prev,next today', center: 'title', right: '' },

      events: obScheduledDates.map(date => ({
        start: date, display: 'background', color: 'rgba(151, 190, 65, 0.12)'
      })),

      dayCellDidMount: function(info) {
        const dateStr   = localDateStr(info.date);
        const isOnOB    = obExistingDates.includes(dateStr);
        const isWorking = obScheduledDates.includes(dateStr);

        if (isOnOB) {
          info.el.classList.add('fc-day-on-ob');
          info.el.classList.add('fc-day-dimmed-ob');
        } else if (!isWorking) {
          info.el.classList.add('fc-day-dimmed-ob');
        }

        if (obSelectedDate === dateStr) {
          info.el.classList.add('fc-day-selected-ob');
          const dayTop = info.el.querySelector('.fc-daygrid-day-top');
          if (dayTop && !dayTop.querySelector('.ob-check')) {
            const check = document.createElement('span');
            check.className = 'ob-check';
            check.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
            dayTop.appendChild(check);
          }
        }
      },

      dateClick: function(info) {
        const dateStr = info.dateStr;

        if (obExistingDates.includes(dateStr)) {
          showToast('You already have an OB request for this date.');
          return;
        }
        if (!obScheduledDates.includes(dateStr)) {
          showToast('Please select a scheduled work day.');
          return;
        }

        obSelectedDate = (obSelectedDate === dateStr) ? null : dateStr;
        updateOBSelectedDisplay();
        renderOBCalendar();
      }
    });

    obCalendarInstance.render();
  }

  function updateOBSelectedDisplay() {
    const box    = document.getElementById('obSelectedDateBox');
    const textEl = document.getElementById('obSelectedDateText');

    if (!obSelectedDate) {
      box.style.display = 'none';
      return;
    }

    box.style.display  = 'block';
    textEl.textContent = fmtDate(obSelectedDate);
  }

  function submitOBRequest() {
    const clientName = document.getElementById('obClientName').value.trim();
    const reason     = document.getElementById('obReason').value.trim();

    if (!obSelectedDate) {
      showToast('Please select a date.');
      return;
    }
    if (!clientName) {
      showToast('Please enter a client name.');
      return;
    }
    if (!reason) {
      showToast('Please enter a reason.');
      return;
    }

    const formData = new FormData();
    formData.append('ob_date',     obSelectedDate);
    formData.append('client_name', clientName);
    formData.append('reason',      reason);

    fetch('/DTR-Internship-Project/employee_pages/submit_ob_request.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast(data.message, 'success');
          obSelectedDate = null;
          setTimeout(() => obModal.hide(), 2000);
        } else {
          showToast(data.message);
        }
      })
      .catch(() => {
        showToast('Something went wrong. Please try again.');
      });
  }

  // ===== LOG EDIT MODAL =====

  let leSelectedRecord = null;
  let leEditTypeValue  = '';

  function toggleLeSelect() {
    document.getElementById('leEditTypeWrapper').classList.toggle('open');
  }

  function selectLeEditType(val, label) {
    leEditTypeValue = val;
    document.getElementById('leEditTypeLabel').textContent = label;
    document.getElementById('leEditTypeWrapper').classList.remove('open');
    document.querySelectorAll('.le-custom-select-option').forEach(o => {
      o.classList.toggle('selected', o.dataset.value === val);
    });
    onLeEditTypeChange();
  }

  document.addEventListener('click', e => {
    const wrapper = document.getElementById('leEditTypeWrapper');
    if (wrapper && !wrapper.contains(e.target)) {
      wrapper.classList.remove('open');
    }
  });

  function openLogEditModal() {
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('logEditModal'));
    modal.show();

    document.getElementById('leStep1').style.display    = 'block';
    document.getElementById('leStep2').style.display    = 'none';

    leSelectedRecord = null;
    loadLeGantt();
  }

  function closeLogEditModal() {
    const modalEl = document.getElementById('logEditModal');
    const modal   = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
    leSelectedRecord = null;
  }

  document.getElementById('logEditModal').addEventListener('hidden.bs.modal', function() {
    leSelectedRecord = null;
    document.getElementById('leStep1').style.display = 'block';
    document.getElementById('leStep2').style.display = 'none';
    document.getElementById('leReason').value        = '';
  });

  function leBackToStep1() {
    document.getElementById('leStep2').style.display = 'none';
    document.getElementById('leStep1').style.display = 'block';
  }

  function loadLeGantt() {
    const list = document.getElementById('leGanttList');
    list.innerHTML = '<p class="le-gantt-loading">Loading...</p>';

    fetch('/DTR-Internship-Project/employee_pages/get_no_timeout_records.php')
      .then(res => res.json())
      .then(records => {
        if (!records.length) {
          list.innerHTML = '<p class="le-gantt-loading">No records found.</p>';
          return;
        }

        list.innerHTML = '';

        records.forEach(row => {
          const schedStart  = row.scheduled_start;
          const schedEnd    = row.scheduled_end;
          const actualIn    = row.actual_time_in;
          const actualOut   = row.actual_time_out;
          const workDate    = row.work_date;
          const recordType  = row.record_type;
          const utMin       = parseInt(row.undertime_minutes) || 0;
          const lateMin     = parseInt(row.late_minutes)      || 0;

          const tsSchedIn   = new Date(schedStart.replace(' ', 'T')).getTime() / 1000;
          const tsSchedOut  = new Date(schedEnd.replace(' ', 'T')).getTime()   / 1000;
          const tsActualIn  = new Date(actualIn.replace(' ', 'T')).getTime()   / 1000;
          const tsActualOut = actualOut ? new Date(actualOut.replace(' ', 'T')).getTime() / 1000 : null;

          const rangeStart = tsSchedIn - 7200;
          const rangeEnd   = tsSchedOut + 7200;
          const range      = rangeEnd - rangeStart;

          const schedLeft  = ((tsSchedIn  - rangeStart) / range) * 100;
          const schedWidth = ((tsSchedOut - tsSchedIn)  / range) * 100;
          const actualLeft = ((tsActualIn - rangeStart) / range) * 100;
          const inPos      = ((tsActualIn - rangeStart) / range) * 100;

          let barsHtml   = '';
          let rightLabel = '';

          if (recordType === 'no_timeout') {
            const noOutWidth = ((rangeEnd - tsActualIn) / range) * 100;
            barsHtml = `
              <div class="gantt-sched-bar"     style="left:${schedLeft}%;  width:${schedWidth}%;"></div>
              <div class="le-no-out-bar"       style="left:${actualLeft}%; width:${noOutWidth}%;"></div>
              <div class="gantt-timein-marker" style="left:${inPos}%;"></div>
            `;
            rightLabel = `<div class="le-no-out-label">No Time Out</div>`;

          } else if (recordType === 'undertime') {
            const workedWidth = ((tsActualOut - tsActualIn)  / range) * 100;
            const utLeft      = ((tsActualOut - rangeStart)  / range) * 100;
            const utWidth     = ((tsSchedOut  - tsActualOut) / range) * 100;
            const outPos      = ((tsActualOut - rangeStart)  / range) * 100;
            const utH         = Math.floor(utMin / 60);
            const utM         = utMin % 60;
            const utLabel     = utH > 0 ? `${utH}h ${utM}m` : `${utM}m`;
            barsHtml = `
              <div class="gantt-sched-bar"      style="left:${schedLeft}%;  width:${schedWidth}%;"></div>
              <div class="gantt-actual-bar"     style="left:${actualLeft}%; width:${workedWidth}%;"></div>
              <div class="le-undertime-bar"     style="left:${utLeft}%;     width:${utWidth}%;"></div>
              <div class="gantt-timein-marker"  style="left:${inPos}%;"></div>
              <div class="gantt-timeout-marker" style="left:${outPos}%;"></div>
            `;
            rightLabel = `<div class="le-undertime-label">-${utLabel}</div>`;

          } else {
            const lateWidth   = ((tsActualIn  - tsSchedIn)  / range) * 100;
            const workedWidth = tsActualOut
              ? ((tsActualOut - tsActualIn) / range) * 100
              : ((tsSchedOut  - tsActualIn) / range) * 100;
            const outPos    = tsActualOut ? ((tsActualOut - rangeStart) / range) * 100 : null;
            const lateH     = Math.floor(lateMin / 60);
            const lateM     = lateMin % 60;
            const lateLabel = lateH > 0 ? `${lateH}h ${lateM}m` : `${lateM}m`;
            barsHtml = `
              <div class="gantt-sched-bar"     style="left:${schedLeft}%;  width:${schedWidth}%;"></div>
              <div class="le-late-bar"         style="left:${schedLeft}%;  width:${lateWidth}%;"></div>
              <div class="gantt-actual-bar"    style="left:${actualLeft}%; width:${workedWidth}%;"></div>
              <div class="gantt-timein-marker" style="left:${inPos}%;"></div>
              ${outPos !== null ? `<div class="gantt-timeout-marker" style="left:${outPos}%;"></div>` : ''}
            `;
            rightLabel = `<div class="le-late-label">+${lateLabel} late</div>`;
          }

          const rowEl = document.createElement('div');
          rowEl.className = `le-gantt-row le-gantt-${recordType}`;
          rowEl.innerHTML = `
            <div class="ot-gantt-row-inner">
              <div class="ot-date-label">
                <div class="ot-date-label-day">${fmtDate(workDate).split(',')[0]}</div>
                <div class="ot-date-label-short">${fmtShort(workDate)}</div>
              </div>
              <div class="gantt-bar-container">${barsHtml}</div>
              ${rightLabel}
            </div>
          `;

          rowEl.addEventListener('click', () => {
            leSelectedRecord = {
              attendance_id:      row.attendance_id,
              applicable_types:   row.applicable_types,
              scheduled_start_ts: tsSchedIn,
              scheduled_end_ts:   tsSchedOut,
              actual_time_in_ts:  tsActualIn,
              actual_time_out_ts: tsActualOut,
            };

            document.getElementById('leSelectedDate').textContent  = fmtDate(workDate);
            document.getElementById('leSelectedSched').textContent = fmtTime(tsSchedIn) + ' – ' + fmtTime(tsSchedOut);
            document.getElementById('leCurrentLog').textContent    = fmtTime(tsActualIn) + ' – ' + (tsActualOut ? fmtTime(tsActualOut) : 'No Time Out');

            const badgesEl = document.getElementById('leStatusBadges');
            badgesEl.innerHTML = row.applicable_types.map(t => {
              if (t === 'no_timeout') return '<span class="le-status-badge le-badge-no-timeout">No Time Out</span>';
              if (t === 'undertime')  return '<span class="le-status-badge le-badge-undertime">Undertime</span>';
              if (t === 'late')       return '<span class="le-status-badge le-badge-late">Late Arrival</span>';
              return '';
            }).join('');

            const hasLate = row.applicable_types.includes('late');
            const hasOut  = row.applicable_types.includes('no_timeout') || row.applicable_types.includes('undertime');
            const opts    = [];
            if (hasLate)           opts.push({ val: 'time_in',  label: 'Correct Time In' });
            if (hasOut)            opts.push({ val: 'time_out', label: 'Correct Time Out' });
            if (hasLate && hasOut) opts.push({ val: 'both',     label: 'Correct Both' });

            document.getElementById('leEditTypeOptions').innerHTML = opts.map(o =>
              `<div class="le-custom-select-option" data-value="${o.val}" onclick="selectLeEditType('${o.val}','${o.label}')">${o.label}</div>`
            ).join('');

            document.getElementById('leRequestedTimeIn').value  = '';
            document.getElementById('leRequestedTimeOut').value = '';

            if (opts.length > 0) selectLeEditType(opts[0].val, opts[0].label);

            document.getElementById('leStep1').style.display = 'none';
            document.getElementById('leStep2').style.display = 'block';
          });

          list.appendChild(rowEl);
        });
      })
      .catch(() => {
        showToast('Failed to load records. Please try again.');
      });
  }

  function onLeEditTypeChange() {
    const type = leEditTypeValue;
    document.getElementById('leTimeInGroup').style.display  = (type === 'time_in'  || type === 'both') ? 'block' : 'none';
    document.getElementById('leTimeOutGroup').style.display = (type === 'time_out' || type === 'both') ? 'block' : 'none';
    updateLePreview();
  }

  function updateLePreview() {
    if (!leSelectedRecord) return;

    const fmtInput = val => {
      if (!val) return '<span style="opacity:.5">—</span>';
      const [h, m] = val.split(':').map(Number);
      const d = new Date();
      d.setHours(h, m, 0, 0);
      return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    };

    const type   = leEditTypeValue;
    const reqIn  = document.getElementById('leRequestedTimeIn').value.trim();
    const reqOut = document.getElementById('leRequestedTimeOut').value.trim();
    const curIn  = fmtTime(leSelectedRecord.actual_time_in_ts);
    const curOut = leSelectedRecord.actual_time_out_ts
      ? fmtTime(leSelectedRecord.actual_time_out_ts)
      : 'No Time Out';

    const lines = [];
    if (type === 'time_in'  || type === 'both') lines.push(`<b>Time In:</b>  ${curIn} &rarr; ${fmtInput(reqIn)}`);
    if (type === 'time_out' || type === 'both') lines.push(`<b>Time Out:</b> ${curOut} &rarr; ${fmtInput(reqOut)}`);

    document.getElementById('lePreviewText').innerHTML = lines.join('<br>');
  }

  function submitLogEditRequest() {
    const editType = leEditTypeValue;
    const reqIn    = document.getElementById('leRequestedTimeIn').value.trim();
    const reqOut   = document.getElementById('leRequestedTimeOut').value.trim();
    const reason   = document.getElementById('leReason').value.trim();

    if (!leSelectedRecord || !editType || !reason) {
      showToast('Please fill in all required fields.');
      return;
    }
    if ((editType === 'time_in'  || editType === 'both') && !reqIn) {
      showToast('Please enter a requested time in.');
      return;
    }
    if ((editType === 'time_out' || editType === 'both') && !reqOut) {
      showToast('Please enter a requested time out.');
      return;
    }

    const formData = new FormData();
    formData.append('attendance_id', leSelectedRecord.attendance_id);
    formData.append('request_type',  editType);
    formData.append('reason',        reason);
    if (editType === 'time_in'  || editType === 'both') formData.append('requested_time_in',  reqIn);
    if (editType === 'time_out' || editType === 'both') formData.append('requested_time_out', reqOut);

    fetch('/DTR-Internship-Project/employee_pages/submit_log_edit_request.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast(data.message, 'success');
          setTimeout(() => closeLogEditModal(), 1500);
        } else {
          showToast(data.message);
        }
      })
      .catch(() => {
        showToast('Something went wrong. Please try again.');
      });
  }
</script>