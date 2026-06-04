

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
          <button type="button" id="otSubmitBtn" class="btn btn-primary" onclick="submitOTRequest()">
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
              <div class="leaveTypeItem text-meta" style="pointer-events:none;opacity:0.5;">Loading types…</div>
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
        <button type="button" class="btn btn-success" onclick="submitLeaveRequest()">
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
        <button type="button" class="btn btn-success" onclick="submitOBRequest()">
          <i class="bi bi-check-circle-fill"></i> Submit OB Request
        </button>
      </div>

    </div>
  </div>
</div>

<!-- SCHEDULE EDIT REQUEST MODAL -->
<div class="modal fade" id="scheduleEditModal" tabindex="-1" aria-labelledby="scheduleEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="scheduleEditModalLabel">Request Schedule Edit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p class="se-step-hint">Select a future schedule on the calendar to request an edit for:</p>
        <p class="se-loading" id="seCalLoading">Loading...</p>
        <div id="seCalendar"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

<!-- SCHEDULE EDIT — DETAIL POPUP (opens on top of the calendar; mirrors admin Edit Schedule modal) -->
<div class="modal fade" id="seEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Request Schedule Edit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="se-summary-card">
          <p class="se-summary-label">Selected Date</p>
          <p class="se-summary-value" id="seSelectedDate"></p>
          <p class="se-summary-label mt-2">Current Schedule</p>
          <div class="se-summary-shift-row">
            <span class="se-shift-label" id="seShiftLabelBadge"></span>
            <span class="se-shift-time" id="seCurrentTimes"></span>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="se-field-label form-label">Requested Time In</label>
            <input type="time" id="seNewTimeIn" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="se-field-label form-label">Requested Time Out <small class="text-muted">(next day if night)</small></label>
            <input type="time" id="seNewTimeOut" class="form-control">
          </div>
        </div>

        <div class="mb-1">
          <label class="se-field-label form-label">Reason</label>
          <textarea id="seReason" class="form-control" rows="3" placeholder="Enter reason for schedule change..."></textarea>
        </div>
      </div>

      <div class="modal-footer" style="justify-content: space-between;">
        <button type="button" class="btn btn-outline-secondary" onclick="seBackToCalendar()">
          <i class="bi bi-arrow-left"></i> Back
        </button>
        <button type="button" id="seSubmitBtn" class="btn btn-primary" onclick="submitScheduleEditRequest()">
          <i class="bi bi-check-circle-fill"></i> Submit Request
        </button>
      </div>

    </div>
  </div>
</div>

<!-- LOG EDIT REQUEST MODAL -->
<div class="modal fade" id="logEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header align-items-start">
        <div>
          <h5 class="modal-title">Request Log Edit</h5>
          <small class="text-tertiary" id="log-edit-today-label"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" style="display:flex;flex-direction:column;gap:1rem;">

        <small class="text-tertiary" style="line-height:1.5;">Enter the corrected time for any log below — you can submit one edit or both at once.</small>

        <!-- Time In Card -->
        <div id="log-edit-card-in" class="card card-success">
          <div class="card-body">
            <div class="card-header p-0">
              <div class="hstack d-flex mb-3">
                <div class="icon-box icon-box-sm icon-box-success">
                  <i class="bi bi-box-arrow-in-right"></i>
                </div>
                <span class="text-primary ms-3 fw-bold">Time In</span>
                <span class="text-primary ms-auto d-flex align-items-center gap-1">
                  Current:
                  <span class="text-secondary fw-bold" id="log-edit-current-in"></span>
                  <span id="log-edit-status-in" class="badge rounded-pill" style="display:none !important"></span>
                </span>
              </div>
            </div>

            <div class="vstack d-flex gap-3">
              <div class="input-group">
                <input type="time" id="log-edit-new-time-in" class="form-control">
                <button type="button" class="input-group-text" style="cursor:pointer;" tabindex="-1" onclick="clearLeInput('in')">
                  <i class="bi bi-x-lg" style="font-size:0.7rem;"></i>
                </button>
              </div>
              <div><textarea id="log-edit-reason-time-in" class="form-control" rows="4" placeholder="Reason (optional)"></textarea></div>
            </div>

          </div>
        </div>

        <!-- Time Out Card -->
        <div id="log-edit-card-out" class="card card-danger">
          <div class="card-body">
            <div class="card-header p-0">
              <div class="hstack d-flex mb-3">
                <div class="icon-box icon-box-sm icon-box-danger">
                  <i class="bi bi-box-arrow-right"></i>
                </div>
                <span class="text-primary ms-3 fw-bold">Time Out</span>
                <span class="text-primary ms-auto d-flex align-items-center gap-1">
                  Current:
                  <span class="text-secondary fw-bold" id="log-edit-current-out"></span>
                  <span id="log-edit-status-out" class="badge rounded-pill" style="display:none"></span>
                </span>
              </div>
            </div>

            <div class="vstack d-flex gap-3">
              <div class="input-group">
                <input type="time" id="log-edit-new-time-out" class="form-control">
                <button type="button" class="input-group-text" style="cursor:pointer;" tabindex="-1" onclick="clearLeInput('out')">
                  <i class="bi bi-x-lg" style="font-size:0.7rem;"></i>
                </button>
              </div>
              <div><textarea id="log-edit-reason-time-out" class="form-control" rows="4" placeholder="Reason (optional)"></textarea></div>
            </div>

          </div>
        </div>
      </div>

      <div class="modal-footer">        
        <button type="button" class="btn btn-neutral w-100" id="log-edit-submit-btn" disabled onclick="submitLeEdit()">
          <i class="bi bi-send"></i> Submit Edit
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  // ===== SHARED UTILITIES =====

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

  function modalFmtDate(d) {
    return new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  }

  function modalFmtShort(d) {
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

    document.getElementById('log-edit-new-time-in').addEventListener('input', updateLeSubmitBtn);
    document.getElementById('log-edit-new-time-out').addEventListener('input', updateLeSubmitBtn);
  });

  // ===== OT MODAL =====

  let otSelectedRecord = null;

  document.getElementById('otModal').addEventListener('hidden.bs.modal', () => {
    otSelectedRecord = null;
    const btn = document.getElementById('otSubmitBtn');
    btn.disabled  = false;
    btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Submit OT Request';
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

    fetch('/DTR-Internship-Project/dropdown_requests/get_ot_records.php')
      .then(res => res.json())
      .then(records => {
        if (!Array.isArray(records) || records.length === 0) {
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
                <div class="ot-date-label-day">${modalFmtDate(date).split(',')[0]}</div>
                <div class="ot-date-label-short">${modalFmtShort(date)}</div>
              </div>
              <div class="gantt-bar-container" data-range-start="${rangeStart}" data-range="${range}">
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
              showToast('You already have a pending OT request for this date.', 'warning');
              return;
            }
            if (!canFile) {
              showToast('You cannot file an OT request because you were late for more than an hour.', 'warning');
              return;
            }

            otSelectedRecord = {
              date,
              time_in:  schedOut,
              time_out: actualOut.split(' ')[1],
              otMin
            };

            document.getElementById('otSelectedDate').textContent     = modalFmtDate(date);
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
      .catch(err => {
        console.error('OT records fetch error:', err);
        showToast('Failed to load OT records. Please try again.', 'danger');
      });
  }

  function initGanttCursors() {
    document.querySelectorAll('#otGanttList .gantt-bar-container').forEach(container => {
      const cursor      = container.querySelector('.gantt-cursor');
      const cursorLabel = container.querySelector('.gantt-cursor-label');
      if (!cursor || !cursorLabel) return;

      const rangeStart = parseInt(container.dataset.rangeStart) || 0;
      const range      = parseInt(container.dataset.range)      || 64800;

      cursor.style.display = 'none';

      container.addEventListener('mousemove', e => {
        const rect = container.getBoundingClientRect();
        const pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        const ts   = rangeStart + pct * range;
        cursor.style.display = 'block';
        cursor.style.left    = `${pct * 100}%`;
        cursorLabel.textContent = fmtTime(ts);
      });

      container.addEventListener('mouseleave', () => {
        cursor.style.display = 'none';
      });
    });
  }

  function submitOTRequest() {
    const reason = document.getElementById('otReason').value.trim();

    if (!reason) {
      showToast('Please enter a reason for your OT request.', 'warning');
      return;
    }
    if (!otSelectedRecord) return;

    const btn = document.getElementById('otSubmitBtn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';

    const formData = new FormData();
    formData.append('date',     otSelectedRecord.date);
    formData.append('time_in',  otSelectedRecord.time_in);
    formData.append('time_out', otSelectedRecord.time_out);
    formData.append('reason',   reason);

    fetch('/DTR-Internship-Project/dropdown_requests/request_ot.php', {
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
          showToast(data.message, 'danger');
          btn.disabled  = false;
          btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Submit OT Request';
        }
      })
      .catch(() => {
        showToast('Something went wrong. Please try again.', 'danger');
        btn.disabled  = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Submit OT Request';
      });
  }

  // ===== LEAVE MODAL =====

  let leaveCalendarInstance = null;
  let leaveScheduledDates   = [];
  let leaveExistingDates    = [];
  let leaveSelectedDates    = [];
  let currentLeaveType      = '';
  let _leaveTypesData       = [];

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
    const found = _leaveTypesData.find(t => t.name === type);
    if (!found) return { maxDays: 0, direction: 'none', label: '' };
    return {
      maxDays:   parseInt(found.max_days, 10),
      direction: found.direction,
      label:     found.description_label || ''
    };
  }

  function buildLeaveTypeMenu(types) {
    const menu = document.getElementById('leaveTypeDropdown');
    if (!types || !types.length) {
      menu.innerHTML = '<div class="leaveTypeItem" style="pointer-events:none;opacity:0.5;">No leave types available.</div>';
      return;
    }
    menu.innerHTML = types.map(t => {
      const safeName  = t.name.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
      const safeLabel = t.label.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
      return `<div class="leaveTypeItem" onclick="selectLeaveType('${safeName}','${safeLabel}')">${t.label}</div>`;
    }).join('');
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

    fetch(`/DTR-Internship-Project/dropdown_requests/get_schedule_dates.php?leave_type=${encodeURIComponent(value)}`)
      .then(res => res.json())
      .then(data => {
        leaveScheduledDates = data.scheduledDates ?? [];
        leaveExistingDates  = data.leaveDates     ?? [];
        renderLeaveCalendar();
      })
      .catch(() => {
        showToast('Failed to load schedule dates. Please try again.', 'danger');
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

    if (_leaveTypesData.length === 0) {
      fetch('/DTR-Internship-Project/dropdown_requests/get_leave_types.php')
        .then(r => r.json())
        .then(types => {
          _leaveTypesData = types;
          buildLeaveTypeMenu(types);
        })
        .catch(() => showToast('Failed to load leave types. Please try again.', 'danger'));
    }

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
          showToast('Please select a leave type first.', 'warning');
          return;
        }
        if (leaveExistingDates.includes(dateStr)) {
          showToast('This date already has an existing leave request.', 'danger');
          return;
        }
        if (!isDateSelectable(dateStr, rules)) {
          showToast(rules.direction === 'past'
            ? 'Sick leave can only be filed for past dates (before today).'
            : 'Vacation leave can only be filed for future dates.', 'warning');
          return;
        }

        const idx = leaveSelectedDates.indexOf(dateStr);
        if (idx !== -1) {
          leaveSelectedDates.splice(idx, 1);
          info.dayEl.classList.remove('fc-day-selected');
          const check = info.dayEl.querySelector('.leave-check');
          if (check) check.remove();
        } else {
          if (leaveSelectedDates.length >= rules.maxDays) {
            showToast(`${capitalize(currentLeaveType)} is limited to ${rules.maxDays} day${rules.maxDays > 1 ? 's' : ''} only.`, 'warning');
            return;
          }
          leaveSelectedDates.push(dateStr);
          leaveSelectedDates.sort();
          info.dayEl.classList.add('fc-day-selected');
          const dayTop = info.dayEl.querySelector('.fc-daygrid-day-top');
          if (dayTop && !dayTop.querySelector('.leave-check')) {
            const check = document.createElement('span');
            check.className = 'leave-check';
            check.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
            dayTop.appendChild(check);
          }
        }

        updateLeaveSelectedDisplay();
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
      ? modalFmtDate(leaveSelectedDates[0])
      : `${modalFmtDate(leaveSelectedDates[0])} → ${modalFmtDate(leaveSelectedDates[1])}`;
  }

  function submitLeaveRequest() {
    const leaveType = document.getElementById('leaveType').value;
    const reason    = document.getElementById('leaveReason').value.trim();

    if (!leaveType) {
      showToast('Please select a leave type.', 'warning');
      return;
    }
    if (leaveSelectedDates.length === 0) {
      showToast('Please select at least 1 date.', 'warning');
      return;
    }
    if (!reason) {
      showToast('Please enter a reason for your leave.', 'warning');
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

    fetch('/DTR-Internship-Project/dropdown_requests/request_leave.php', {
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
          showToast(data.message, 'danger');
        }
      })
      .catch(() => {
        showToast('Something went wrong. Please try again.', 'danger');
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

    fetch('/DTR-Internship-Project/dropdown_requests/get_ob_dates.php')
      .then(res => res.json())
      .then(data => {
        obScheduledDates = data.scheduledDates ?? [];
        obExistingDates  = data.obDates        ?? [];
        renderOBCalendar();
      })
      .catch(() => {
        showToast('Failed to load schedule dates. Please try again.', 'danger');
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
          showToast('You already have an OB request for this date.', 'danger');
          return;
        }
        if (!obScheduledDates.includes(dateStr)) {
          showToast('Please select a scheduled work day.', 'warning');
          return;
        }

        if (obSelectedDate === dateStr) {
          obSelectedDate = null;
          info.dayEl.classList.remove('fc-day-selected-ob');
          const check = info.dayEl.querySelector('.ob-check');
          if (check) check.remove();
        } else {
          if (obSelectedDate) {
            const prevCell = document.querySelector('#obCalendar .fc-day-selected-ob');
            if (prevCell) {
              prevCell.classList.remove('fc-day-selected-ob');
              const prevCheck = prevCell.querySelector('.ob-check');
              if (prevCheck) prevCheck.remove();
            }
          }
          obSelectedDate = dateStr;
          info.dayEl.classList.add('fc-day-selected-ob');
          const dayTop = info.dayEl.querySelector('.fc-daygrid-day-top');
          if (dayTop && !dayTop.querySelector('.ob-check')) {
            const check = document.createElement('span');
            check.className = 'ob-check';
            check.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
            dayTop.appendChild(check);
          }
        }

        updateOBSelectedDisplay();
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
    textEl.textContent = modalFmtDate(obSelectedDate);
  }

  function submitOBRequest() {
    const clientName = document.getElementById('obClientName').value.trim();
    const reason     = document.getElementById('obReason').value.trim();

    if (!obSelectedDate) {
      showToast('Please select a date.', 'danger');
      return;
    }
    if (!clientName) {
      showToast('Please enter a client name.', 'danger');
      return;
    }
    if (!reason) {
      showToast('Please enter a reason.', 'danger');
      return;
    }

    const formData = new FormData();
    formData.append('ob_date',     obSelectedDate);
    formData.append('client_name', clientName);
    formData.append('reason',      reason);

    fetch('/DTR-Internship-Project/dropdown_requests/request_ob.php', {
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
          showToast(data.message, 'danger');
        }
      })
      .catch(() => {
        showToast('Something went wrong. Please try again.', 'danger');
      });
  }

  // ===== LOG EDIT MODAL (TODAY) =====

  let le2LogIdIn  = null;
  let le2LogIdOut = null;

  function openLogEditModal() {
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('logEditModal'));

    const today = new Date();
    document.getElementById('log-edit-today-label').textContent =
      today.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) +
      ' · Changes require admin approval';

    le2LogIdIn  = null;
    le2LogIdOut = null;
    loadLeTodayLogs();
    modal.show();
  }

  const timeInSelect = document.getElementById('log-edit-new-time-in');
  const timeOutSelect = document.getElementById('log-edit-new-time-out');

  // Time IN picker
  timeInSelect.addEventListener('click', () => {
      try {
          timeInSelect.showPicker?.();
      } catch (error) {
          console.log("Picker not supported or failed:", error);
      }
  });

  // Time OUT picker
  timeOutSelect.addEventListener('click', () => {
      try {
          timeOutSelect.showPicker?.();
      } catch (error) {
          console.log("Picker not supported or failed:", error);
      }
  });

  document.getElementById('logEditModal').addEventListener('hidden.bs.modal', function () {
    le2LogIdIn  = null;
    le2LogIdOut = null;
    document.getElementById('log-edit-reason-time-in').value  = '';
    document.getElementById('log-edit-reason-time-out').value = '';
    const btn = document.getElementById('log-edit-submit-btn');
    btn.disabled  = true;
    btn.className = 'btn btn-neutral w-100';
    btn.innerHTML = '<i class="bi bi-send"></i> Submit Edit';
  });

  function setLeCardLocked(type, locked) {
    document.getElementById(`log-edit-card-${type}`).classList.toggle('card-locked', locked);
    document.getElementById(`log-edit-new-time-${type}`).disabled = locked;
  }

  function setLeStatus(type, status) {
    const el = document.getElementById(`log-edit-status-${type}`);
    el.className = 'badge rounded-pill';
    if (!status) { el.style.setProperty('display', 'none', 'important'); return; }
    el.style.removeProperty('display');
    const cfg = {
      pending:  ['status-pending',  '<i class="bi bi-hourglass-split"></i> Pending'],
      approved: ['status-approved', '<i class="bi bi-check-circle-fill"></i> Edited'],
      norecord: ['status-rejected', `<i class="bi bi-x-circle-fill"></i> No Time ${type === 'in' ? 'In' : 'Out'}`],
    }[status];
    el.classList.add(cfg[0]);
    el.innerHTML = cfg[1];
  }

  function loadLeTodayLogs() {
    document.getElementById('log-edit-new-time-in').value  = '';
    document.getElementById('log-edit-new-time-out').value = '';
    document.getElementById('log-edit-reason-time-in').value  = '';
    document.getElementById('log-edit-reason-time-out').value = '';

    const inpIn  = document.getElementById('log-edit-new-time-in');
    const inpOut = document.getElementById('log-edit-new-time-out');

    setLeCardLocked('in', false);
    setLeCardLocked('out', false);
    setLeStatus('in', null);
    setLeStatus('out', null);
    document.getElementById('log-edit-current-in').textContent  = '';
    document.getElementById('log-edit-current-out').textContent = '';

    fetch('/DTR-Internship-Project/dropdown_requests/get_logedit_logs.php')
      .then(r => r.json())
      .then(logs => {
        le2LogIdIn  = null;
        le2LogIdOut = null;

        logs.forEach(log => {
          const dt         = new Date(log.log_time);
          const pad        = n => String(n).padStart(2, '0');
          const timeOnly   = `${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
          const fmt        = dt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
          const hasPending = log.has_pending == 1;
          const hasApproved = log.has_approved == 1;
          if (log.log_type === 'IN') {
            le2LogIdIn = log.log_id;

            document.getElementById('log-edit-current-in').textContent = fmt;

            setLeCardLocked('in', hasPending || hasApproved);
            setLeStatus('in', hasPending ? 'pending' : hasApproved ? 'approved' : null);
          } else if (log.log_type === 'OUT') {
            le2LogIdOut = log.log_id;

            document.getElementById('log-edit-current-out').textContent = fmt;

            setLeCardLocked('out', hasPending || hasApproved);
            setLeStatus('out', hasPending ? 'pending' : hasApproved ? 'approved' : null);
          }
        });

        if (!le2LogIdIn) {
          setLeCardLocked('in', true);
          setLeStatus('in', 'norecord');
        }
        if (!le2LogIdOut) {
          setLeCardLocked('out', true);
          setLeStatus('out', 'norecord');
        }

        updateLeSubmitBtn();
      })
      .catch(() => showToast("Failed to load today's logs.", 'danger'));
  }

  function clearLeInput(type) {
    document.getElementById(type === 'in' ? 'log-edit-new-time-in' : 'log-edit-new-time-out').value = '';
    updateLeSubmitBtn();
  }

  function updateLeSubmitBtn() {
    const btn    = document.getElementById('log-edit-submit-btn');
    const inpIn  = document.getElementById('log-edit-new-time-in');
    const inpOut = document.getElementById('log-edit-new-time-out');

    const hasIn  = !inpIn.disabled  && inpIn.value  && le2LogIdIn;
    const hasOut = !inpOut.disabled && inpOut.value && le2LogIdOut;

    if (hasIn && hasOut) {
      btn.disabled  = false;
      btn.className = 'btn btn-success w-100';
      btn.innerHTML = '<i class="bi bi-send"></i> Submit Both Edit';
    } else if (hasIn) {
      btn.disabled  = false;
      btn.className = 'btn btn-success w-100';
      btn.innerHTML = '<i class="bi bi-send"></i> Submit Time In Edit';
    } else if (hasOut) {
      btn.disabled  = false;
      btn.className = 'btn btn-success w-100';
      btn.innerHTML = '<i class="bi bi-send"></i> Submit Time Out Edit';
    } else {
      btn.disabled  = true;
      btn.className = 'btn btn-neutral w-100';
      btn.innerHTML = '<i class="bi bi-send"></i> Submit Edit';
    }
  }

  // ===== SCHEDULE EDIT REQUEST MODAL =====

  let seSelectedScheduleId   = null;
  let seSelectedScheduleDate = null;
  let seCalendarInstance     = null;

  // Same endpoint the schedules tab uses — it already classifies day/night/rest/leave/OB.
  const SE_SCHEDULE_API = '/DTR-Internship-Project/get_schedule.php';

  function openScheduleEditModal() {
    seSelectedScheduleId   = null;
    seSelectedScheduleDate = null;
    renderSeCalendar();
  }

  // Open the native time picker as soon as either time field is clicked/focused.
  ['seNewTimeIn', 'seNewTimeOut'].forEach(function (id) {
    const el = document.getElementById(id);
    if (!el) return;
    const openPicker = function () { try { el.showPicker(); } catch (e) {} };
    el.addEventListener('click', openPicker);
    el.addEventListener('focus', openPicker);
  });

  // Reset the edit popup whenever it closes (Cancel, submit, or backdrop).
  document.getElementById('seEditModal').addEventListener('hidden.bs.modal', function () {
    seSelectedScheduleId   = null;
    seSelectedScheduleDate = null;
    document.getElementById('seNewTimeIn').value  = '';
    document.getElementById('seNewTimeOut').value = '';
    document.getElementById('seReason').value     = '';
    const btn = document.getElementById('seSubmitBtn');
    btn.disabled  = false;
    btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Submit Request';
  });

  // Calendar is rendered while the modal is still hidden (zero width), so
  // resize it once the modal is fully shown.
  document.getElementById('scheduleEditModal').addEventListener('shown.bs.modal', function () {
    if (seCalendarInstance) seCalendarInstance.updateSize();
  });

  // Editable = a future day/night shift with no pending edit. Leave/OB/rest are
  // already separate event types from get_schedule.php, so no client classification needed.
  function seIsEditable(ev) {
    const p = ev.extendedProps || {};
    if (p.shift_type !== 'day' && p.shift_type !== 'night') return false;
    if (!ev.startStr || ev.startStr <= todayStr()) return false;
    return p.editStatus !== 'pending';
  }

  function renderSeCalendar() {
    if (seCalendarInstance) {
      seCalendarInstance.destroy();
      seCalendarInstance = null;
    }

    const calendarEl = document.getElementById('seCalendar');
    calendarEl.innerHTML = '';
    const loadingEl = document.getElementById('seCalLoading');

    seCalendarInstance = new FullCalendar.Calendar(calendarEl, {
      initialView:  'dayGridMonth',
      height:       400,
      eventDisplay: 'block',
      dayMaxEvents: false,
      headerToolbar: { left: 'prev,next today', center: 'title', right: '' },

      // FullCalendar passes start/end so the month refetches like the schedules tab.
      events: {
        url:     SE_SCHEDULE_API,
        method:  'GET',
        failure: function () { showToast('Failed to load schedules.', 'danger'); },
      },

      loading: function (isLoading) {
        if (loadingEl) loadingEl.style.display = isLoading ? 'block' : 'none';
      },

      // Mirror the schedules tab: shift label + time for day/night, label only for rejected.
      eventContent: function (arg) {
        const p  = arg.event.extendedProps;
        const st = p.shift_type;
        if ((st === 'day' || st === 'night') && p.timeInStr && p.timeOutStr) {
          return { html:
            '<div class="fc-admin-inner">' +
              '<span class="fc-admin-label">' + arg.event.title + '</span>' +
              '<span class="fc-admin-time">' + p.timeInStr + ' – ' + p.timeOutStr + '</span>' +
            '</div>'
          };
        }
        if (st === 'leave_rejected' || st === 'ob_rejected') {
          return { html: '<div class="fc-admin-inner"><span class="fc-admin-label">' + arg.event.title + '</span></div>' };
        }
        return true;
      },

      eventDidMount: function (info) {
        info.el.classList.add(seIsEditable(info.event) ? 'se-ev-editable' : 'se-ev-locked');
      },

      // Grey out past / today cells — no future shift can land there.
      dayCellDidMount: function (info) {
        if (localDateStr(info.date) <= todayStr()) info.el.classList.add('fc-day-dimmed');
      },

      eventClick: function (info) {
        info.jsEvent.preventDefault();
        seTryEdit(info.event);
      },
    });

    seCalendarInstance.render();
  }

  function seTryEdit(ev) {
    const p  = ev.extendedProps || {};
    const st = p.shift_type;

    if (st !== 'day' && st !== 'night') return;   // leave / OB / rest / continuation — not editable
    if (!ev.startStr || ev.startStr <= todayStr()) {
      showToast('You can only request edits for future schedules.', 'warning');
      return;
    }
    if (p.editStatus === 'pending') {
      showToast('You already have a pending edit request for this schedule.', 'warning');
      return;
    }

    seOpenEditPopup(p.schedId, ev.startStr, {
      label:    ev.title,
      color:    st === 'night' ? '#4da3ff' : '#97be41',
      startFmt: p.timeInStr,
      endFmt:   (p.timeOutStr || '').replace(' ↪', ''),
      inVal:    p.schedInVal,
      outVal:   p.schedOutVal,
    });
  }

  function seOpenEditPopup(id, date, shift) {
    seSelectedScheduleId   = id;
    seSelectedScheduleDate = date;

    document.getElementById('seSelectedDate').textContent   = modalFmtDate(date);
    document.getElementById('seCurrentTimes').textContent   = shift.startFmt + ' – ' + shift.endFmt;
    const badge = document.getElementById('seShiftLabelBadge');
    badge.textContent       = shift.label || 'Day Shift';
    badge.style.background  = shift.color || '#97be41';
    // Pre-fill the inputs with the schedule's current times.
    document.getElementById('seNewTimeIn').value  = shift.inVal  || '';
    document.getElementById('seNewTimeOut').value = shift.outVal || '';
    document.getElementById('seReason').value     = '';

    // Close the calendar, then open the edit modal (one modal visible at a time).
    seSwapModal('scheduleEditModal', 'seEditModal');
  }

  // Hide one modal and, once it's fully hidden, show the other — avoids a
  // leftover backdrop from briefly stacking two modals.
  function seSwapModal(hideId, showId) {
    const hideEl   = document.getElementById(hideId);
    const showEl   = document.getElementById(showId);
    const hideInst = bootstrap.Modal.getInstance(hideEl);
    if (hideInst) {
      hideEl.addEventListener('hidden.bs.modal', function onHidden() {
        hideEl.removeEventListener('hidden.bs.modal', onHidden);
        bootstrap.Modal.getOrCreateInstance(showEl).show();
      });
      hideInst.hide();
    } else {
      bootstrap.Modal.getOrCreateInstance(showEl).show();
    }
  }

  // Back from the edit modal → reopen the calendar.
  function seBackToCalendar() {
    seSwapModal('seEditModal', 'scheduleEditModal');
  }

  function submitScheduleEditRequest() {
    const newTimeIn  = document.getElementById('seNewTimeIn').value.trim();
    const newTimeOut = document.getElementById('seNewTimeOut').value.trim();
    const reason     = document.getElementById('seReason').value.trim();

    if (!seSelectedScheduleId) {
      showToast('No schedule selected.', 'danger');
      return;
    }
    if (!newTimeIn) {
      showToast('Please enter a requested time in.', 'danger');
      return;
    }
    if (!newTimeOut) {
      showToast('Please enter a requested time out.', 'danger');
      return;
    }
    if (!reason) {
      showToast('Please enter a reason for the schedule change.', 'danger');
      return;
    }

    const btn = document.getElementById('seSubmitBtn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';

    const fd = new FormData();
    fd.append('schedule_id', seSelectedScheduleId);
    fd.append('new_time_in',  newTimeIn);
    fd.append('new_time_out', newTimeOut);
    fd.append('reason',       reason);

    fetch('/DTR-Internship-Project/dropdown_requests/request_schedule_edit.php', {
      method: 'POST',
      body: fd,
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          showToast(data.message, 'success');
          // Calendar is already closed; just close the edit modal.
          bootstrap.Modal.getInstance(document.getElementById('seEditModal'))?.hide();
        } else {
          showToast(data.message, 'danger');
          btn.disabled  = false;
          btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Submit Request';
        }
      })
      .catch(() => {
        showToast('Something went wrong. Please try again.', 'danger');
        btn.disabled  = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Submit Request';
      });
  }

  async function submitLeEdit() {
    const btn    = document.getElementById('log-edit-submit-btn');
    const inpIn  = document.getElementById('log-edit-new-time-in');
    const inpOut = document.getElementById('log-edit-new-time-out');

    const hasIn  = !inpIn.disabled  && inpIn.value  && le2LogIdIn;
    const hasOut = !inpOut.disabled && inpOut.value && le2LogIdOut;

    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';

    const today = new Date();
    const pad   = n => String(n).padStart(2, '0');
    const datePrefix = `${today.getFullYear()}-${pad(today.getMonth()+1)}-${pad(today.getDate())}`;

    const postEdit = (logId, timeVal, reason) => {
      const fd = new FormData();
      fd.append('log_id',       logId);
      fd.append('new_datetime', `${datePrefix} ${timeVal}:00`);
      fd.append('reason',       reason);
      return fetch('/DTR-Internship-Project/dropdown_requests/request_log_edit.php', { method: 'POST', body: fd })
        .then(r => r.json());
    };

    try {
      if (hasIn && hasOut) {
        const [rIn, rOut] = await Promise.all([
          postEdit(le2LogIdIn,  inpIn.value,  document.getElementById('log-edit-reason-time-in').value.trim()),
          postEdit(le2LogIdOut, inpOut.value, document.getElementById('log-edit-reason-time-out').value.trim()),
        ]);
        if (rIn.success && rOut.success) {
          showToast('Both log edits submitted for approval.', 'success');
        } else if (rIn.success) {
          showToast('Time In submitted. Time Out: ' + rOut.message, 'warning');
        } else if (rOut.success) {
          showToast('Time Out submitted. Time In: ' + rIn.message, 'warning');
        } else {
          showToast(rIn.message || rOut.message, 'danger');
          updateLeSubmitBtn();
          return;
        }
      } else if (hasIn) {
        const r = await postEdit(le2LogIdIn, inpIn.value, document.getElementById('log-edit-reason-time-in').value.trim());
        if (!r.success) { showToast(r.message, 'danger'); updateLeSubmitBtn(); return; }
        showToast(r.message, 'success');
      } else if (hasOut) {
        const r = await postEdit(le2LogIdOut, inpOut.value, document.getElementById('log-edit-reason-time-out').value.trim());
        if (!r.success) { showToast(r.message, 'danger'); updateLeSubmitBtn(); return; }
        showToast(r.message, 'success');
      }

      const modal = bootstrap.Modal.getInstance(document.getElementById('logEditModal'));
      if (modal) setTimeout(() => modal.hide(), 1500);
    } catch {
      showToast('Something went wrong. Please try again.', 'danger');
      updateLeSubmitBtn();
    }
  }

</script>