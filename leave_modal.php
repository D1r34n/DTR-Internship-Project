<style>
    /* ---- Leave Modal Calendar Overrides ---- */
    #leaveCalendar .fc {
        color: var(--text-light);
        font-family: 'Poppins', sans-serif;
    }

    #leaveCalendar .fc .fc-toolbar-title {
        font-size: 1rem;
        font-weight: 500;
        color: var(--text-light);
    }

    #leaveCalendar .fc .fc-button {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid var(--glass-border);
        color: var(--text-light);
        font-family: 'Poppins', sans-serif;
        font-size: 0.8rem;
        border-radius: 8px;
        box-shadow: none;
        transition: background 0.2s ease;
    }

    #leaveCalendar .fc .fc-button:hover {
        background: rgba(255, 255, 255, 0.14);
        border-color: rgba(255, 255, 255, 0.2);
        color: var(--text-lightest);
        box-shadow: none;
    }

    #leaveCalendar .fc .fc-button:focus {
        box-shadow: none;
        outline: none;
    }

    #leaveCalendar .fc .fc-today-button:disabled {
        background-color: #97be41;
        border-color: #97be41;
        color: #fff;
        opacity: 1;
    }

    #leaveCalendar .fc .fc-col-header-cell {
        background: rgba(255, 255, 255, 0.04);
        border-color: var(--glass-border);
        padding: 0.5rem 0;
    }

    #leaveCalendar .fc .fc-col-header-cell-cushion {
        color: var(--text-light);
        font-size: 0.75rem;
        font-weight: 500;
        text-decoration: none;
        text-transform: uppercase;
        letter-spacing: 0.07em;
    }

    #leaveCalendar .fc .fc-daygrid-day {
        background: transparent;
        border-color: var(--glass-border);
    }

    #leaveCalendar .fc .fc-daygrid-day-number {
        color: var(--text-light);
        font-size: 0.8rem;
        text-decoration: none;
        padding: 4px 8px;
    }

    #leaveCalendar .fc .fc-day-today {
        background: rgba(151, 190, 65, 0.08) !important;
    }

    #leaveCalendar .fc .fc-day-today .fc-daygrid-day-number {
        color: #97be41;
        font-weight: 600;
    }

    #leaveCalendar .fc .fc-scrollgrid {
        border-color: var(--glass-border);
        border-radius: 10px;
        overflow: hidden;
    }

    #leaveCalendar .fc .fc-scrollgrid-section > td,
    #leaveCalendar .fc .fc-scrollgrid-section > th {
        border-color: var(--glass-border);
        background-color: transparent;
    }

    #leaveCalendar .fc-day-selected {
        background: rgba(151, 190, 65, 0.2) !important;
    }

    #leaveCalendar .fc-daygrid-day-top {
        position: relative;
    }

    #leaveCalendar .leave-check {
        display: inline-flex;
        align-items: center;
        margin-left: 4px;
        color: #97be41;
        font-size: 0.7rem;
        vertical-align: middle;
    }
</style>

<!-- LEAVE REQUEST MODAL -->
<div id="leaveModalOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; justify-content:center; align-items:center;">
    <div style="background:var(--glass-bg); backdrop-filter:blur(32px) saturate(160%) brightness(0.3); -webkit-backdrop-filter:blur(32px) saturate(160%) brightness(0.3); border:1px solid var(--glass-border); border-radius:16px; padding:2rem; width:750px; max-height:85vh; overflow-y:auto; box-shadow:0 12px 35px rgba(0,0,0,0.5); position:relative;">

        <!-- Modal Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h5 style="color:#fff; margin:0; font-weight:600;">File Leave Request</h5>
            <button onclick="closeLeaveModal()" style="background:none; border:none; color:#aaa; font-size:1.3rem; cursor:pointer;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Leave Type Dropdown -->
        <div style="margin-bottom:1rem;">
            <label style="color:rgba(255,255,255,0.7); font-size:0.85rem; font-weight:600; display:block; margin-bottom:0.5rem;">Leave Type</label>
            <select id="leaveType" style="width:100%; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.15); border-radius:8px; color:#fff; padding:0.5rem 0.8rem; font-family:'Poppins',sans-serif; font-size:0.875rem; outline:none;">
                <option value="" disabled selected>Select leave type...</option>
                <option value="sick leave">Sick Leave</option>
                <option value="vacation leave">Vacation Leave</option>
                <option value="birthday leave">Birthday Leave</option>
                <option value="solo parent leave">Solo Parent Leave</option>
            </select>
        </div>

        <!-- Instruction -->
        <p style="color:rgba(255,255,255,0.6); font-size:0.85rem; margin-bottom:1rem;">
            Select up to <strong style="color:#97be41;">2 work days</strong> from the calendar below:
        </p>

        <!-- Selected Dates Display -->
        <div id="leaveSelectedDates" style="display:none; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:0.8rem 1rem; margin-bottom:1rem;">
            <p style="color:#aaa; font-size:0.8rem; margin:0 0 0.3rem;">Selected Dates</p>
            <p style="color:#97be41; font-weight:600; margin:0;" id="leaveSelectedDatesText"></p>
        </div>

        <!-- Calendar -->
        <div id="leaveCalendar" style="margin-bottom:1.5rem;"></div>

        <!-- Reason -->
        <label style="color:rgba(255,255,255,0.7); font-size:0.85rem; font-weight:600; display:block; margin-bottom:0.5rem;">Reason</label>
        <textarea id="leaveReason" rows="3" placeholder="Enter reason for leave..." style="width:100%; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.15); border-radius:8px; color:#fff; padding:0.6rem 0.8rem; font-family:'Poppins',sans-serif; font-size:0.875rem; outline:none; resize:none;"></textarea>

        <!-- Submit Button -->
        <button onclick="submitLeaveRequest()" style="margin-top:1rem; background:#97be41; color:#fff; border:none; padding:0.5rem 1.5rem; border-radius:8px; font-family:'Poppins',sans-serif; font-size:0.9rem; cursor:pointer; transition:background 0.2s;">
            <i class="bi bi-check-circle-fill"></i> Submit Leave Request
        </button>

        <!-- Error Message -->
        <div id="leaveErrorMsg" style="display:none; margin-top:1rem; background:rgba(220,53,69,0.15); border:1px solid rgba(220,53,69,0.3); border-radius:8px; padding:0.8rem 1rem; color:#ff8a8a; font-size:0.875rem;"></div>

        <!-- Success Message -->
        <div id="leaveSuccessMsg" style="display:none; margin-top:1rem; background:rgba(151,190,65,0.15); border:1px solid rgba(151,190,65,0.3); border-radius:8px; padding:0.8rem 1rem; color:#97be41; font-size:0.875rem;"></div>

    </div>
</div>

<script>
    // ===== LEAVE MODAL =====
    let leaveCalendarInstance = null;
    let leaveScheduledDates   = [];
    let leaveSelectedDates    = [];

    function openLeaveModal() {
        document.getElementById('leaveModalOverlay').style.display  = 'flex';
        document.getElementById('leaveErrorMsg').style.display      = 'none';
        document.getElementById('leaveSuccessMsg').style.display    = 'none';
        document.getElementById('leaveSelectedDates').style.display = 'none';
        document.getElementById('leaveSelectedDatesText').textContent = '';
        document.getElementById('leaveReason').value                = '';
        document.getElementById('leaveType').value                  = '';
        leaveSelectedDates = [];

        fetch('get_schedule_dates.php')
            .then(res => res.json())
            .then(dates => {
                leaveScheduledDates = dates;
                renderLeaveCalendar();
            })
            .catch(() => {
                document.getElementById('leaveErrorMsg').style.display = 'block';
                document.getElementById('leaveErrorMsg').textContent   = 'Failed to load schedule dates.';
            });
    }

    function closeLeaveModal() {
        document.getElementById('leaveModalOverlay').style.display = 'none';
        if (leaveCalendarInstance) {
            leaveCalendarInstance.destroy();
            leaveCalendarInstance = null;
        }
        leaveSelectedDates = [];
    }

    function renderLeaveCalendar() {
        if (leaveCalendarInstance) {
            leaveCalendarInstance.destroy();
            leaveCalendarInstance = null;
        }

        const calendarEl = document.getElementById('leaveCalendar');
        calendarEl.innerHTML = '';

        leaveCalendarInstance = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 400,

            headerToolbar: {
                left:   'prev,next today',
                center: 'title',
                right:  ''
            },

            events: leaveScheduledDates.map(date => ({
                start:   date,
                display: 'background',
                color:   'rgba(151, 190, 65, 0.15)'
            })),

            dayCellDidMount: function(info) {
                const dateStr     = info.date.toISOString().split('T')[0];
                const today       = new Date().toISOString().split('T')[0];
                const isScheduled = leaveScheduledDates.includes(dateStr);
                const isFuture    = dateStr > today;
                const isSelected  = leaveSelectedDates.includes(dateStr);

                if (!isScheduled || !isFuture) {
                    info.el.style.opacity       = '0.3';
                    info.el.style.pointerEvents = 'none';
                }

                if (isSelected) {
                    info.el.style.backgroundColor = 'rgba(151, 190, 65, 0.3)';
                    info.el.style.borderRadius    = '6px';
                }
            },

            dateClick: function(info) {
                const dateStr = info.dateStr;
                const today   = new Date().toISOString().split('T')[0];

                if (!leaveScheduledDates.includes(dateStr) || dateStr <= today) return;

                const idx = leaveSelectedDates.indexOf(dateStr);

                if (idx !== -1) {
                    leaveSelectedDates.splice(idx, 1);
                } else {
                    if (leaveSelectedDates.length >= 2) {
                        const errEl = document.getElementById('leaveErrorMsg');
                        errEl.style.display = 'block';
                        errEl.textContent   = 'You can only select up to 2 dates.';
                        return;
                    }
                    leaveSelectedDates.push(dateStr);
                    leaveSelectedDates.sort();
                }

                if (leaveSelectedDates.length <= 2) {
                    document.getElementById('leaveErrorMsg').style.display = 'none';
                }

                updateLeaveSelectedDisplay();
                renderLeaveCalendar();
            }
        });

        leaveCalendarInstance.render();

        // Apply highlights after render
        setTimeout(() => {
            leaveSelectedDates.forEach(dateStr => {
                const cell = document.querySelector(`#leaveCalendar [data-date="${dateStr}"]`);
                if (cell) {
                    cell.classList.add('fc-day-selected');

                    const dayTop = cell.querySelector('.fc-daygrid-day-top');
                    if (dayTop && !dayTop.querySelector('.leave-check')) {
                        const check     = document.createElement('span');
                        check.className = 'leave-check';
                        check.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
                        dayTop.appendChild(check);
                    }
                }
            });
        }, 100);
    }

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
        formData.append('leave_type', leaveType);
        formData.append('start_date', startDate);
        formData.append('end_date',   endDate);
        formData.append('reason',     reason);

        fetch('submit_leave_request.php', {
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