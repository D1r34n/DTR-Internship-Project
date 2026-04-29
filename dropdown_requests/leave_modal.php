<!-- LEAVE REQUEST MODAL -->
<div id="leaveModalOverlay">
    <div class="leave-modal-box">

        <!-- Modal Header -->
        <div class="leave-modal-header">
            <h5 class="leave-modal-title">File Leave Request</h5>
            <button onclick="closeLeaveModal()" class="leave-modal-close-btn">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Leave Type Dropdown -->
        <div class="leave-type-section">
            <label class="leave-label">Leave Type</label>
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
        <div id="leaveSelectedDates" class="leave-selected-dates-box">
            <p class="leave-selected-dates-label">Selected Dates</p>
            <p class="leave-selected-dates-text" id="leaveSelectedDatesText"></p>
        </div>

        <!-- Calendar -->
        <div id="leaveCalendar" class="leave-calendar-wrapper"></div>

        <!-- Reason -->
        <label class="leave-label">Reason</label>
        <textarea id="leaveReason" class="leave-textarea" rows="3" placeholder="Enter reason for leave..."></textarea>

        <!-- Submit Button -->
        <button onclick="submitLeaveRequest()" class="leave-submit-btn">
            <i class="bi bi-check-circle-fill"></i> Submit Leave Request
        </button>

        <!-- Error Message -->
        <div id="leaveErrorMsg" class="leave-error-msg"></div>

        <!-- Success Message -->
        <div id="leaveSuccessMsg" class="leave-success-msg"></div>

    </div>
</div>

<script>
    // ===== LEAVE MODAL =====
    let leaveCalendarInstance = null;
    let leaveScheduledDates   = [];
    let leaveExistingDates    = [];
    let leaveSelectedDates    = [];
    let currentLeaveType      = '';

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
        switch(type) {
            case 'sick leave':
                return { maxDays: 4,   direction: 'past',   label: 'up to 4 past dates only (before today)' };
            case 'vacation leave':
                return { maxDays: 999, direction: 'future', label: 'future dates only' };
            case 'birthday leave':
                return { maxDays: 1,   direction: 'any',    label: '1 day only' };
            case 'solo parent leave':
                return { maxDays: 2,   direction: 'any',    label: 'up to 2 days' };
            default:
                return { maxDays: 0,   direction: 'none',   label: '' };
        }
    }

    // ---- CHECK IF DATE IS SELECTABLE ----
    function isDateSelectable(dateStr, rules) {
        if (leaveExistingDates.includes(dateStr)) return false;
        const today = todayStr();
        if (rules.direction === 'past')   return dateStr < today;
        if (rules.direction === 'future') return dateStr > today;
        if (rules.direction === 'any')    return true;
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
        document.getElementById('leaveType').value            = value;
        document.getElementById('leaveTypeLabel').textContent = label;
        document.getElementById('leaveTypeDropdown').classList.remove('show');
        document.querySelector('.leaveTypeToggle').classList.add('selected');

        currentLeaveType   = value;
        leaveSelectedDates = [];

        // Update instruction text
        const rules = getLeaveRules(value);
        document.getElementById('leaveInstruction').innerHTML =
            `Select <strong class="leave-instruction-highlight">${rules.label}</strong> from the calendar below:`;

        // Clear error and selected display
        document.getElementById('leaveErrorMsg').style.display        = 'none';
        document.getElementById('leaveSelectedDates').style.display   = 'none';
        document.getElementById('leaveSelectedDatesText').textContent = '';

        // Reload schedule dates for this leave type then re-render
        fetch(`/DTR-Internship-Project/employee_pages/get_schedule_dates.php?leave_type=${encodeURIComponent(value)}`)
            .then(res => res.json())
            .then(data => {
                leaveScheduledDates = data.scheduledDates ?? [];
                leaveExistingDates  = data.leaveDates     ?? [];
                renderLeaveCalendar();
            })
            .catch(() => {
                document.getElementById('leaveErrorMsg').style.display = 'block';
                document.getElementById('leaveErrorMsg').textContent   = 'Failed to load schedule dates.';
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
        document.getElementById('leaveModalOverlay').style.display    = 'flex';
        document.getElementById('leaveErrorMsg').style.display        = 'none';
        document.getElementById('leaveSuccessMsg').style.display      = 'none';
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

        // Render empty calendar on open
        renderLeaveCalendar();
    }

    // ---- CLOSE MODAL ----
    function closeLeaveModal() {
        document.getElementById('leaveModalOverlay').style.display = 'none';
        if (leaveCalendarInstance) {
            leaveCalendarInstance.destroy();
            leaveCalendarInstance = null;
        }
        leaveSelectedDates = [];
        leaveExistingDates = [];
        currentLeaveType   = '';
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
                left:   'prev,next today',
                center: 'title',
                right:  ''
            },

            // Highlight scheduled work days
            events: leaveScheduledDates.map(date => ({
                start:   date,
                display: 'background',
                color:   'rgba(151, 190, 65, 0.12)'
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
                        const check     = document.createElement('span');
                        check.className = 'leave-check';
                        check.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
                        dayTop.appendChild(check);
                    }
                }
            },

            dateClick: function(info) {
                const dateStr = info.dateStr;
                const errEl   = document.getElementById('leaveErrorMsg');

                // Must select leave type first
                if (!currentLeaveType) {
                    errEl.style.display = 'block';
                    errEl.textContent   = 'Please select a leave type first.';
                    return;
                }

                // Check if date already has a leave request
                if (leaveExistingDates.includes(dateStr)) {
                    errEl.style.display = 'block';
                    errEl.textContent   = 'This date already has an existing leave request.';
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
                        errEl.textContent   = `${capitalize(currentLeaveType)} is limited to ${rules.maxDays} day${rules.maxDays > 1 ? 's' : ''} only.`;
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
        const textEl  = document.getElementById('leaveSelectedDatesText');

        if (leaveSelectedDates.length === 0) {
            display.style.display = 'none';
            return;
        }

        display.style.display = 'block';

        const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });

        textEl.textContent = leaveSelectedDates.length === 1
            ? fmtDate(leaveSelectedDates[0])
            : `${fmtDate(leaveSelectedDates[0])} → ${fmtDate(leaveSelectedDates[1])}`;
    }

    // ---- SUBMIT ----
    function submitLeaveRequest() {
        const leaveType = document.getElementById('leaveType').value;
        const reason    = document.getElementById('leaveReason').value.trim();
        const errEl     = document.getElementById('leaveErrorMsg');
        const sucEl     = document.getElementById('leaveSuccessMsg');

        if (!leaveType) {
            errEl.style.display = 'block';
            errEl.textContent   = 'Please select a leave type.';
            return;
        }
        if (leaveSelectedDates.length === 0) {
            errEl.style.display = 'block';
            errEl.textContent   = 'Please select at least 1 date.';
            return;
        }
        if (!reason) {
            errEl.style.display = 'block';
            errEl.textContent   = 'Please enter a reason for your leave.';
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
            body:   formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                errEl.style.display = 'none';
                sucEl.style.display = 'block';
                sucEl.textContent   = data.message;
                leaveSelectedDates  = [];
                setTimeout(() => closeLeaveModal(), 2000);
            } else {
                errEl.style.display = 'block';
                errEl.textContent   = data.message;
            }
        })
        .catch(() => {
            errEl.style.display = 'block';
            errEl.textContent   = 'Something went wrong. Please try again.';
        });
    }
</script>