<!-- OT REQUEST MODAL -->
<div class="modal fade" id="otModal" tabindex="-1" aria-labelledby="otModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header">
        <h5 class="modal-title" id="otModalLabel">File OT Request</h5>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">

        <!-- Error message -->
        <div id="otErrorMsg" class="alert alert-danger" style="display:none;"></div>

        <!-- Success message -->
        <div id="otSuccessMsg" class="alert alert-success" style="display:none;"></div>

        <!-- Step 1: Pick a Gantt row -->
        <div id="otStep1">
          <p class="ot-step-hint">Select a day to file OT for:</p>
          <div id="otGanttList">
            <p class="ot-gantt-loading">Loading...</p>
          </div>
        </div>

        <!-- Step 2: Reason form -->
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

      <!-- Modal Footer -->
      <div class="modal-footer" id="otModalFooter">

        <!-- Step 1 footer: just Close -->
        <div id="otFooterStep1">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>

        <!-- Step 2 footer: Back + Submit -->
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

        <!-- Error Message -->
        <div id="leaveErrorMsg" class="alert alert-danger" style="display:none;"></div>

        <!-- Success Message -->
        <div id="leaveSuccessMsg" class="alert alert-success" style="display:none;"></div>

        <!-- Leave Type Dropdown -->
        <div class="leave-type-section mb-3">
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

        <!-- Instruction -->
        <p class="leave-instruction" id="leaveInstruction">
          Select a leave type first to load the calendar.
        </p>

        <!-- Selected Dates Display -->
        <div id="leaveSelectedDates" class="leave-selected-dates-box" style="display:none;">
          <p class="leave-selected-dates-label">Selected Dates</p>
          <p class="leave-selected-dates-text" id="leaveSelectedDatesText"></p>
        </div>

        <!-- Calendar -->
        <div id="leaveCalendar" class="leave-calendar-wrapper"></div>

        <!-- Reason -->
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

        <!-- Error -->
        <div id="obErrorMsg" class="alert alert-danger" style="display:none;"></div>

        <!-- Success -->
        <div id="obSuccessMsg" class="alert alert-success" style="display:none;"></div>

        <!-- Instruction -->
        <p class="ob-instruction">Select a <strong class="ob-instruction-highlight">work date</strong> for your official business:</p>

        <!-- Selected Date Display -->
        <div id="obSelectedDateBox" class="ob-selected-date-box" style="display:none;">
          <p class="ob-selected-date-label">Selected Date</p>
          <p class="ob-selected-date-text" id="obSelectedDateText"></p>
        </div>

        <!-- Calendar -->
        <div id="obCalendar" class="ob-calendar-wrapper"></div>

        <!-- Client Name -->
        <label class="form-label mt-3">Client Name</label>
        <input type="text" id="obClientName" class="form-control" placeholder="Enter client name...">

        <!-- Reason -->
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

<div class="modal fade" id="logEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <!-- HEADER -->
      <div class="modal-header">
        <h5 class="modal-title">Request Log Edit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- BODY -->
      <div class="modal-body">

        <!-- STEP 1 -->
        <div id="leStep1">
          <p class="text-muted">Select a record to correct your attendance log:</p>
          <div id="leGanttList">
            <p class="text-muted">Loading...</p>
          </div>
        </div>

        <!-- STEP 2 -->
        <div id="leStep2" style="display:none;">

          <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="leBackToStep1()">
            <i class="bi bi-arrow-left"></i> Back
          </button>

          <!-- Summary -->
          <div class="card p-3 mb-3">
            <small class="text-muted">Date</small>
            <div id="leSelectedDate"></div>

            <div class="row mt-2">
              <div class="col">
                <small class="text-muted">Scheduled</small>
                <div id="leSelectedSched"></div>
              </div>
              <div class="col">
                <small class="text-muted">Current Log</small>
                <div id="leCurrentLog"></div>
              </div>
            </div>

            <small class="text-muted mt-2 d-block">Status</small>
            <div id="leStatusBadges"></div>
          </div>

          <!-- Edit Type -->
          <label class="form-label">Edit Type</label>
          <div class="custom-select" id="leEditTypeWrapper">
            <div class="form-control" id="leEditTypeTrigger" onclick="toggleLeSelect()">
              <span id="leEditTypeLabel"></span>
              <i class="bi bi-chevron-down float-end"></i>
            </div>
            <div id="leEditTypeOptions"></div>
          </div>

          <!-- Time In -->
          <div id="leTimeInGroup" style="display:none;" class="mt-3">
            <label class="form-label">Requested Time In</label>
            <input type="time" id="leRequestedTimeIn" class="form-control" oninput="updateLePreview()">
          </div>

          <!-- Time Out -->
          <div id="leTimeOutGroup" style="display:none;" class="mt-3">
            <label class="form-label">Requested Time Out</label>
            <input type="time" id="leRequestedTimeOut" class="form-control" oninput="updateLePreview()">
          </div>

          <!-- Reason -->
          <div class="mt-3">
            <label class="form-label">Reason</label>
            <textarea id="leReason" class="form-control" rows="3" placeholder="Explain the reason for this correction..."></textarea>
          </div>

          <!-- Preview -->
          <div class="alert alert-secondary mt-3" id="lePreviewBox">
            <strong>Preview</strong>
            <div id="lePreviewText">—</div>
          </div>

          <!-- Submit -->
          <button type="button" class="btn btn-primary w-100" onclick="submitLogEditRequest()">
            <i class="bi bi-check-circle-fill"></i> Submit Request
          </button>

        </div>

        <!-- FEEDBACK -->
        <div id="leErrorMsg" class="text-danger mt-2"></div>
        <div id="leSuccessMsg" class="text-success mt-2"></div>

      </div>

    </div>
  </div>
</div>

<script>
  // ===== OT MODAL =====
  let otSelectedRecord = null;
  let otModal, leaveModal, obModal;

  document.addEventListener('DOMContentLoaded', () => {
      otModal    = new bootstrap.Modal(document.getElementById('otModal'));
      leaveModal = new bootstrap.Modal(document.getElementById('leaveModal'));
      obModal    = new bootstrap.Modal(document.getElementById('obModal'));
  });

  function openOTModal() {
    // Reset state
    document.getElementById('otStep1').style.display = 'block';
    document.getElementById('otStep2').style.display = 'none';
    document.getElementById('otFooterStep1').style.display = 'flex';
    document.getElementById('otFooterStep2').style.display = 'none';
    document.getElementById('otErrorMsg').style.display = 'none';
    document.getElementById('otSuccessMsg').style.display = 'none';
    otSelectedRecord = null;

    otModal.show();
    loadOTGantt();
  }

  // Also reset when Bootstrap closes the modal (e.g. backdrop click / btn-close)
  document.getElementById('otModal').addEventListener('hidden.bs.modal', () => {
    otSelectedRecord = null;
  });

  function backToStep1() {
    document.getElementById('otStep2').style.display = 'none';
    document.getElementById('otStep1').style.display = 'block';
    document.getElementById('otFooterStep2').style.display = 'none';
    document.getElementById('otFooterStep1').style.display = 'flex';
    document.getElementById('otErrorMsg').style.display = 'none';
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
          const date = row.date;
          const lateMin = parseInt(row.late_minutes) || 0;
          const otMin = parseInt(row.overtime_minutes) || 0;
          const schedIn = row.scheduled_time_in;
          const schedOut = row.scheduled_time_out;
          const actualIn = row.actual_time_in;
          const actualOut = row.actual_time_out;
          const isPending = row.overtime_status === 'pending';
          const canFile = !isPending && lateMin < 60;

          const tsSchedIn = Date.parse(schedIn.replace(' ', 'T')) / 1000;
          const tsSchedOut = Date.parse(schedOut.replace(' ', 'T')) / 1000;
          const tsActualIn = Date.parse(actualIn.replace(' ', 'T')) / 1000;
          const tsActualOut = Date.parse(actualOut.replace(' ', 'T')) / 1000;

          const rangeStart = tsSchedIn - 7200;
          const rangeEnd = rangeStart + 64800; // fixed 18-hour window for consistent scale
          const range = 64800;

          const schedLeft = ((tsSchedIn - rangeStart) / range) * 100;
          const schedWidth = ((tsSchedOut - tsSchedIn) / range) * 100;
          const actualLeft = ((tsActualIn - rangeStart) / range) * 100;
          const actualWidth = ((tsActualOut - tsActualIn) / range) * 100;
          const otLeft = ((tsSchedOut - rangeStart) / range) * 100;
          const otWidth = ((tsActualOut - tsSchedOut) / range) * 100;
          const inPos = ((tsActualIn - rangeStart) / range) * 100;
          const outPos = ((tsActualOut - rangeStart) / range) * 100;

          const fmtTime = ts => new Date(ts * 1000).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
            timeZone: 'Asia/Manila'
          });
          const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
          });
          const fmtShort = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
          });

          const otHours = Math.floor(otMin / 60);
          const otMins = otMin % 60;
          const otLabel = otHours > 0 ? `${otHours}h ${otMins}m` : `${otMins}m`;

          const rowEl = document.createElement('div');
          rowEl.className = `ot-gantt-row ${isPending ? 'ot-pending' : canFile ? 'can-file' : 'cannot-file'}`;

          rowEl.innerHTML = `
            <div class="ot-gantt-row-inner">
              <div class="ot-date-label">
                <div class="ot-date-label-day">${fmtDate(date).split(',')[0]}</div>
                <div class="ot-date-label-short">${fmtShort(date)}</div>
              </div>
              <div class="gantt-bar-container"
                data-range-start="${rangeStart}" data-range-end="${rangeEnd}">

                <div class="gantt-cursor">
                  <div class="gantt-cursor-line"></div>
                  <div class="gantt-cursor-label"></div>
                </div>

                <div class="gantt-sched-bar"  style="left:${schedLeft}%;  width:${schedWidth}%;"></div>
                <div class="gantt-actual-bar" style="left:${actualLeft}%; width:${actualWidth}%;"></div>
                <div class="gantt-ot-bar"     style="left:${otLeft}%;     width:${otWidth}%;"></div>
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
            const errEl = document.getElementById('otErrorMsg');

            if (isPending) {
              errEl.style.display = 'block';
              errEl.textContent = 'You already have a pending OT request for this date.';
              return;
            }

            if (!canFile) {
              errEl.style.display = 'block';
              errEl.textContent = 'You cannot file an OT request because you were late for more than an hour.';
              return;
            }

            otSelectedRecord = {
              date,
              time_in: schedOut,
              time_out: actualOut.split(' ')[1],
              otMin
            };

            document.getElementById('otSelectedDate').textContent = fmtDate(date);
            document.getElementById('otSelectedTime').textContent = `${fmtTime(tsSchedOut)} — ${fmtTime(tsActualOut)}`;
            document.getElementById('otSelectedDuration').textContent = otLabel;
            document.getElementById('otReason').value = '';
            errEl.style.display = 'none';

            document.getElementById('otStep1').style.display = 'none';
            document.getElementById('otStep2').style.display = 'block';
            document.getElementById('otFooterStep1').style.display = 'none';
            document.getElementById('otFooterStep2').style.display = 'flex';
          });

          list.appendChild(rowEl);
        });

        initGanttCursors();
      })
      .catch(() => {
        list.innerHTML = '<p style="color:#ff8a8a; text-align:center;">Failed to load OT records.</p>';
      });
  }

  function submitOTRequest() {
    const reason = document.getElementById('otReason').value.trim();
    const errEl = document.getElementById('otErrorMsg');
    const sucEl = document.getElementById('otSuccessMsg');

    if (!reason) {
      errEl.style.display = 'block';
      errEl.textContent = 'Please enter a reason for your OT request.';
      return;
    }

    if (!otSelectedRecord) return;

    const formData = new FormData();
    formData.append('date', otSelectedRecord.date);
    formData.append('time_in', otSelectedRecord.time_in);
    formData.append('time_out', otSelectedRecord.time_out);
    formData.append('reason', reason);

    fetch('/DTR-Internship-Project/employee_pages/submit_ot_request.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          errEl.style.display = 'none';
          sucEl.style.display = 'block';
          sucEl.textContent = data.message;
          document.getElementById('otStep2').style.display = 'none';
          document.getElementById('otFooterStep2').style.display = 'none';
          setTimeout(() => otModal.hide(), 2000);
        } else {
          errEl.style.display = 'block';
          errEl.textContent = data.message;
        }
      })
      .catch(() => {
        errEl.style.display = 'block';
        errEl.textContent = 'Something went wrong. Please try again.';
      });
  }

  // ===== LEAVE MODAL =====
  let leaveCalendarInstance = null;
  let leaveScheduledDates = [];
  let leaveExistingDates = [];
  let leaveSelectedDates = [];
  let currentLeaveType = '';

    // Cleanup when Bootstrap closes the modal (backdrop, Esc, btn-close)
  document.getElementById('leaveModal').addEventListener('hidden.bs.modal', () => {
    if (leaveCalendarInstance) {
      leaveCalendarInstance.destroy();
      leaveCalendarInstance = null;
    }
    leaveSelectedDates = [];
    leaveExistingDates = [];
    currentLeaveType = '';
  });

  // ---- TIMEZONE FIX ----
  function localDateStr(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  function todayStr() {
    return localDateStr(new Date());
  }

  // ---- GET RULES FOR LEAVE TYPE ----
  function getLeaveRules(type) {
    switch (type) {
      case 'sick leave':
        return {
          maxDays: 4, direction: 'past', label: 'up to 4 past dates only (before today)'
        };
      case 'vacation leave':
        return {
          maxDays: 999, direction: 'future', label: 'future dates only'
        };
      case 'birthday leave':
        return {
          maxDays: 1, direction: 'any', label: '1 day only'
        };
      case 'solo parent leave':
        return {
          maxDays: 2, direction: 'any', label: 'up to 2 days'
        };
      default:
        return {
          maxDays: 0, direction: 'none', label: ''
        };
    }
  }

  // ---- CHECK IF DATE IS SELECTABLE ----
  function isDateSelectable(dateStr, rules) {
    if (leaveExistingDates.includes(dateStr)) return false;
    const today = todayStr();
    if (rules.direction === 'past') return dateStr < today;
    if (rules.direction === 'future') return dateStr > today;
    if (rules.direction === 'any') return true;
    return false;
  }

  // ---- CAPITALIZE ----
  function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  // ---- LEAVE TYPE DROPDOWN ----
  function toggleLeaveTypeDropdown() {
    document.getElementById('leaveTypeDropdown').classList.toggle('show');
  }

  function selectLeaveType(value, label) {
    document.getElementById('leaveType').value = value;
    document.getElementById('leaveTypeLabel').textContent = label;
    document.getElementById('leaveTypeDropdown').classList.remove('show');
    document.querySelector('.leaveTypeToggle').classList.add('selected');

    currentLeaveType = value;
    leaveSelectedDates = [];

    // Update instruction text
    const rules = getLeaveRules(value);
    document.getElementById('leaveInstruction').innerHTML =
      `Select <strong class="leave-instruction-highlight">${rules.label}</strong> from the calendar below:`;

    // Clear error and selected display
    document.getElementById('leaveErrorMsg').style.display = 'none';
    document.getElementById('leaveSelectedDates').style.display = 'none';
    document.getElementById('leaveSelectedDatesText').textContent = '';

    // Reload schedule dates for this leave type then re-render
    fetch(`/DTR-Internship-Project/employee_pages/get_schedule_dates.php?leave_type=${encodeURIComponent(value)}`)
      .then(res => res.json())
      .then(data => {
        leaveScheduledDates = data.scheduledDates ?? [];
        leaveExistingDates = data.leaveDates ?? [];
        renderLeaveCalendar();
      })
      .catch(() => {
        document.getElementById('leaveErrorMsg').style.display = 'block';
        document.getElementById('leaveErrorMsg').textContent = 'Failed to load schedule dates.';
      });
  }

  // Close leave type dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.leaveTypeWrapper')) {
      const dropdown = document.getElementById('leaveTypeDropdown');
      if (dropdown) dropdown.classList.remove('show');
    }
  });

  // ---- OPEN MODAL ----
  function openLeaveModal() {
    document.getElementById('leaveErrorMsg').style.display = 'none';
    document.getElementById('leaveSuccessMsg').style.display = 'none';
    document.getElementById('leaveSelectedDates').style.display = 'none';
    document.getElementById('leaveSelectedDatesText').textContent = '';
    document.getElementById('leaveReason').value = '';
    document.getElementById('leaveType').value = '';
    document.getElementById('leaveTypeLabel').textContent = 'Select leave type...';
    document.querySelector('.leaveTypeToggle').classList.remove('selected');
    document.getElementById('leaveInstruction').textContent = 'Select a leave type first to load the calendar.';
    currentLeaveType = '';
    leaveSelectedDates = [];
    leaveExistingDates = [];

    renderLeaveCalendar();
    leaveModal.show();
  }

  // ---- CLOSE MODAL ----
  function closeLeaveModal() {
    leaveModal.hide();
  }

  // ---- RENDER CALENDAR ----
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

      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: ''
      },

      // Highlight scheduled work days
      events: leaveScheduledDates.map(date => ({
        start: date,
        display: 'background',
        color: 'rgba(151, 190, 65, 0.12)'
      })),

      dayCellDidMount: function(info) {
        const dateStr = localDateStr(info.date);
        const isOnLeave = leaveExistingDates.includes(dateStr);
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
        const errEl = document.getElementById('leaveErrorMsg');

        // Must select leave type first
        if (!currentLeaveType) {
          errEl.style.display = 'block';
          errEl.textContent = 'Please select a leave type first.';
          return;
        }

        // Check if date already has a leave request
        if (leaveExistingDates.includes(dateStr)) {
          errEl.style.display = 'block';
          errEl.textContent = 'This date already has an existing leave request.';
          return;
        }

        // Check if date is selectable by direction rules
        if (!isDateSelectable(dateStr, rules)) {
          errEl.style.display = 'block';
          if (rules.direction === 'past') {
            errEl.textContent = 'Sick leave can only be filed for past dates (before today).';
          } else if (rules.direction === 'future') {
            errEl.textContent = 'Vacation leave can only be filed for future dates.';
          }
          return;
        }

        const idx = leaveSelectedDates.indexOf(dateStr);

        if (idx !== -1) {
          leaveSelectedDates.splice(idx, 1);
          errEl.style.display = 'none';
        } else {
          if (leaveSelectedDates.length >= rules.maxDays) {
            errEl.style.display = 'block';
            errEl.textContent = `${capitalize(currentLeaveType)} is limited to ${rules.maxDays} day${rules.maxDays > 1 ? 's' : ''} only.`;
            return;
          }
          leaveSelectedDates.push(dateStr);
          leaveSelectedDates.sort();
          errEl.style.display = 'none';
        }

        updateLeaveSelectedDisplay();
        renderLeaveCalendar();
      }
    });

    leaveCalendarInstance.render();
  }

  // ---- SELECTED DATES DISPLAY ----
  function updateLeaveSelectedDisplay() {
    const display = document.getElementById('leaveSelectedDates');
    const textEl = document.getElementById('leaveSelectedDatesText');

    if (leaveSelectedDates.length === 0) {
      display.style.display = 'none';
      return;
    }

    display.style.display = 'block';

    const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });

    textEl.textContent = leaveSelectedDates.length === 1 ?
      fmtDate(leaveSelectedDates[0]) :
      `${fmtDate(leaveSelectedDates[0])} → ${fmtDate(leaveSelectedDates[1])}`;
  }

  // ---- SUBMIT ----
  function submitLeaveRequest() {
    const leaveType = document.getElementById('leaveType').value;
    const reason = document.getElementById('leaveReason').value.trim();
    const errEl = document.getElementById('leaveErrorMsg');
    const sucEl = document.getElementById('leaveSuccessMsg');

    if (!leaveType) {
      errEl.style.display = 'block';
      errEl.textContent = 'Please select a leave type.';
      return;
    }
    if (leaveSelectedDates.length === 0) {
      errEl.style.display = 'block';
      errEl.textContent = 'Please select at least 1 date.';
      return;
    }
    if (!reason) {
      errEl.style.display = 'block';
      errEl.textContent = 'Please enter a reason for your leave.';
      return;
    }

    const startDate = leaveSelectedDates[0];
    const endDate = leaveSelectedDates[leaveSelectedDates.length - 1];

    const formData = new FormData();
    formData.append('leave_type', leaveType);
    formData.append('start_date', startDate);
    formData.append('end_date', endDate);
    formData.append('selected_dates', JSON.stringify(leaveSelectedDates));
    formData.append('reason', reason);

    fetch('/DTR-Internship-Project/employee_pages/submit_leave_request.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          errEl.style.display = 'none';
          sucEl.style.display = 'block';
          sucEl.textContent = data.message;
          leaveSelectedDates = [];
          setTimeout(() => leaveModal.hide(), 2000);
        } else {
          errEl.style.display = 'block';
          errEl.textContent = data.message;
        }
      })
      .catch(() => {
        errEl.style.display = 'block';
        errEl.textContent = 'Something went wrong. Please try again.';
      });
  }

  // ===== OB MODAL =====
  let obCalendarInstance = null;
  let obScheduledDates = [];
  let obExistingDates = [];
  let obSelectedDate = null;

  // Cleanup when Bootstrap closes the modal (backdrop, Esc, btn-close)
  document.getElementById('obModal').addEventListener('hidden.bs.modal', () => {
    if (obCalendarInstance) {
      obCalendarInstance.destroy();
      obCalendarInstance = null;
    }
    obSelectedDate = null;
    obScheduledDates = [];
    obExistingDates = [];
  });

  function localObDateStr(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  // ---- OPEN MODAL ----
  function openOBModal() {
    document.getElementById('obErrorMsg').style.display = 'none';
    document.getElementById('obSuccessMsg').style.display = 'none';
    document.getElementById('obSelectedDateBox').style.display = 'none';
    document.getElementById('obSelectedDateText').textContent = '';
    document.getElementById('obClientName').value = '';
    document.getElementById('obReason').value = '';
    obSelectedDate = null;
    obScheduledDates = [];
    obExistingDates = [];

    fetch('/DTR-Internship-Project/employee_pages/get_ob_dates.php')
      .then(res => res.json())
      .then(data => {
        obScheduledDates = data.scheduledDates ?? [];
        obExistingDates = data.obDates ?? [];
        renderOBCalendar();
      })
      .catch(() => {
        document.getElementById('obErrorMsg').style.display = 'block';
        document.getElementById('obErrorMsg').textContent = 'Failed to load schedule dates.';
        renderOBCalendar();
      });

    obModal.show();
  }

  // ---- CLOSE MODAL ----
  function closeOBModal() {
    obModal.hide();
  }

  // ---- RENDER CALENDAR ----
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
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: ''
      },

      events: obScheduledDates.map(date => ({
        start: date,
        display: 'background',
        color: 'rgba(151, 190, 65, 0.12)'
      })),

      dayCellDidMount: function(info) {
        const dateStr = localObDateStr(info.date);
        const isOnOB = obExistingDates.includes(dateStr);
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
        const errEl = document.getElementById('obErrorMsg');

        if (obExistingDates.includes(dateStr)) {
          errEl.style.display = 'block';
          errEl.textContent = 'You already have an OB request for this date.';
          return;
        }

        if (!obScheduledDates.includes(dateStr)) {
          errEl.style.display = 'block';
          errEl.textContent = 'Please select a scheduled work day.';
          return;
        }

        obSelectedDate = (obSelectedDate === dateStr) ? null : dateStr;
        errEl.style.display = 'none';
        updateOBSelectedDisplay();
        renderOBCalendar();
      }
    });

    obCalendarInstance.render();
  }

  // ---- SELECTED DATE DISPLAY ----
  function updateOBSelectedDisplay() {
    const box = document.getElementById('obSelectedDateBox');
    const textEl = document.getElementById('obSelectedDateText');

    if (!obSelectedDate) {
      box.style.display = 'none';
      return;
    }

    box.style.display = 'block';
    textEl.textContent = new Date(obSelectedDate + 'T00:00:00').toLocaleDateString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  }

  // ---- SUBMIT ----
  function submitOBRequest() {
    const clientName = document.getElementById('obClientName').value.trim();
    const reason = document.getElementById('obReason').value.trim();
    const errEl = document.getElementById('obErrorMsg');
    const sucEl = document.getElementById('obSuccessMsg');

    if (!obSelectedDate) {
      errEl.style.display = 'block';
      errEl.textContent = 'Please select a date.';
      return;
    }
    if (!clientName) {
      errEl.style.display = 'block';
      errEl.textContent = 'Please enter a client name.';
      return;
    }
    if (!reason) {
      errEl.style.display = 'block';
      errEl.textContent = 'Please enter a reason.';
      return;
    }

    const formData = new FormData();
    formData.append('ob_date', obSelectedDate);
    formData.append('client_name', clientName);
    formData.append('reason', reason);

    fetch('/DTR-Internship-Project/employee_pages/submit_ob_request.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          errEl.style.display = 'none';
          sucEl.style.display = 'block';
          sucEl.textContent = data.message;
          obSelectedDate = null;
          setTimeout(() => obModal.hide(), 2000);
        } else {
          errEl.style.display = 'block';
          errEl.textContent = data.message;
        }
      })
      .catch(() => {
        errEl.style.display = 'block';
        errEl.textContent = 'Something went wrong. Please try again.';
      });
  }

  let leSelectedRecord = null;
  let leEditTypeValue = '';

  /* ================================================
     CUSTOM SELECT (Edit Type)
     ================================================ */

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

  /* ================================================
     BOOTSTRAP MODAL CONTROL
     ================================================ */

  function openLogEditModal() {
    const modal = new bootstrap.Modal(document.getElementById('logEditModal'));
    modal.show();

    // reset UI state
    document.getElementById('leStep1').style.display = 'block';
    document.getElementById('leStep2').style.display = 'none';

    document.getElementById('leErrorMsg').textContent = '';
    document.getElementById('leSuccessMsg').textContent = '';

    leSelectedRecord = null;

    loadLeGantt();
  }

  function closeLogEditModal() {
    const modalEl = document.getElementById('logEditModal');
    const modal = bootstrap.Modal.getInstance(modalEl);

    if (modal) modal.hide();

    leSelectedRecord = null;
  }

  document.getElementById('logEditModal')
    .addEventListener('hidden.bs.modal', function() {

      leSelectedRecord = null;

      document.getElementById('leStep1').style.display = 'block';
      document.getElementById('leStep2').style.display = 'none';

      document.getElementById('leErrorMsg').textContent = '';
      document.getElementById('leSuccessMsg').textContent = '';

      document.getElementById('leReason').value = '';
    });

  /* ================================================
     STEP NAVIGATION
     ================================================ */

  function leBackToStep1() {
    document.getElementById('leStep2').style.display = 'none';
    document.getElementById('leStep1').style.display = 'block';

    document.getElementById('leErrorMsg').style.display = 'none';
  }

  /* ================================================
     LOAD RECORDS (GANTT)
     ================================================ */

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

          const schedStart = row.scheduled_start;
          const schedEnd = row.scheduled_end;
          const actualIn = row.actual_time_in;
          const actualOut = row.actual_time_out;
          const workDate = row.work_date;
          const recordType = row.record_type;
          const utMin = parseInt(row.undertime_minutes) || 0;
          const lateMin = parseInt(row.late_minutes) || 0;

          const tsSchedIn = new Date(schedStart.replace(' ', 'T')).getTime() / 1000;
          const tsSchedOut = new Date(schedEnd.replace(' ', 'T')).getTime() / 1000;
          const tsActualIn = new Date(actualIn.replace(' ', 'T')).getTime() / 1000;
          const tsActualOut = actualOut ? new Date(actualOut.replace(' ', 'T')).getTime() / 1000 : null;

          const rangeStart = tsSchedIn - 7200;
          const rangeEnd = tsSchedOut + 7200;
          const range = rangeEnd - rangeStart;

          const schedLeft = ((tsSchedIn - rangeStart) / range) * 100;
          const schedWidth = ((tsSchedOut - tsSchedIn) / range) * 100;
          const actualLeft = ((tsActualIn - rangeStart) / range) * 100;
          const inPos = ((tsActualIn - rangeStart) / range) * 100;

          const fmtTime = ts => new Date(ts * 1000).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
            timeZone: 'Asia/Manila'
          });

          const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
          });

          const fmtShort = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
          });

          let barsHtml = '';
          let rightLabel = '';

          if (recordType === 'no_timeout') {

            const noOutWidth = ((rangeEnd - tsActualIn) / range) * 100;

            barsHtml = `
                            <div class="gantt-sched-bar" style="left:${schedLeft}%; width:${schedWidth}%;"></div>
                            <div class="le-no-out-bar" style="left:${actualLeft}%; width:${noOutWidth}%;"></div>
                            <div class="gantt-timein-marker" style="left:${inPos}%;"></div>
                        `;

            rightLabel = `<div class="le-no-out-label">No Time Out</div>`;
          } else if (recordType === 'undertime') {

            const workedWidth = ((tsActualOut - tsActualIn) / range) * 100;
            const utLeft = ((tsActualOut - rangeStart) / range) * 100;
            const utWidth = ((tsSchedOut - tsActualOut) / range) * 100;
            const outPos = ((tsActualOut - rangeStart) / range) * 100;

            const utH = Math.floor(utMin / 60);
            const utM = utMin % 60;
            const utLabel = utH > 0 ? `${utH}h ${utM}m` : `${utM}m`;

            barsHtml = `
                            <div class="gantt-sched-bar" style="left:${schedLeft}%; width:${schedWidth}%;"></div>
                            <div class="gantt-actual-bar" style="left:${actualLeft}%; width:${workedWidth}%;"></div>
                            <div class="le-undertime-bar" style="left:${utLeft}%; width:${utWidth}%;"></div>
                            <div class="gantt-timein-marker" style="left:${inPos}%;"></div>
                            <div class="gantt-timeout-marker" style="left:${outPos}%;"></div>
                        `;

            rightLabel = `<div class="le-undertime-label">-${utLabel}</div>`;
          } else {

            const lateWidth = ((tsActualIn - tsSchedIn) / range) * 100;
            const workedWidth = tsActualOut ?
              ((tsActualOut - tsActualIn) / range) * 100 :
              ((tsSchedOut - tsActualIn) / range) * 100;

            const outPos = tsActualOut ? ((tsActualOut - rangeStart) / range) * 100 : null;

            const lateH = Math.floor(lateMin / 60);
            const lateM = lateMin % 60;
            const lateLabel = lateH > 0 ? `${lateH}h ${lateM}m` : `${lateM}m`;

            barsHtml = `
                            <div class="gantt-sched-bar" style="left:${schedLeft}%; width:${schedWidth}%;"></div>
                            <div class="le-late-bar" style="left:${schedLeft}%; width:${lateWidth}%;"></div>
                            <div class="gantt-actual-bar" style="left:${actualLeft}%; width:${workedWidth}%;"></div>
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

                            <div class="gantt-bar-container">
                                ${barsHtml}
                            </div>

                            ${rightLabel}
                        </div>
                    `;

          rowEl.addEventListener('click', () => {

            leSelectedRecord = {
              attendance_id: row.attendance_id,
              applicable_types: row.applicable_types,
              scheduled_start_ts: tsSchedIn,
              scheduled_end_ts: tsSchedOut,
              actual_time_in_ts: tsActualIn,
              actual_time_out_ts: tsActualOut,
            };

            document.getElementById('leSelectedDate').textContent = fmtDate(workDate);
            document.getElementById('leSelectedSched').textContent =
              fmtTime(tsSchedIn) + ' – ' + fmtTime(tsSchedOut);

            document.getElementById('leStep1').style.display = 'none';
            document.getElementById('leStep2').style.display = 'block';
          });

          list.appendChild(rowEl);
        });

      })
      .catch(() => {
        list.innerHTML = '<p style="color:#ff8a8a;">Failed to load records.</p>';
      });
  }

  /* ================================================
     EDIT TYPE CHANGE + PREVIEW
     ================================================ */

  function onLeEditTypeChange() {
    const type = leEditTypeValue;

    document.getElementById('leTimeInGroup').style.display =
      (type === 'time_in' || type === 'both') ? 'block' : 'none';

    document.getElementById('leTimeOutGroup').style.display =
      (type === 'time_out' || type === 'both') ? 'block' : 'none';

    updateLePreview();
  }

  function updateLePreview() {
    if (!leSelectedRecord) return;

    document.getElementById('lePreviewText').textContent = 'Preview updated';
  }

  /* ================================================
     SUBMIT
     ================================================ */

  function submitLogEditRequest() {
    const editType = leEditTypeValue;
    const reqIn = document.getElementById('leRequestedTimeIn').value.trim();
    const reqOut = document.getElementById('leRequestedTimeOut').value.trim();
    const reason = document.getElementById('leReason').value.trim();

    if (!leSelectedRecord || !reason) return;

    const formData = new FormData();
    formData.append('attendance_id', leSelectedRecord.attendance_id);
    formData.append('request_type', editType);
    formData.append('reason', reason);

    fetch('/DTR-Internship-Project/employee_pages/submit_log_edit_request.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          closeLogEditModal();
        }
      });
  }
</script>