<!-- OB REQUEST MODAL -->
<div id="obModalOverlay">
    <div class="ob-modal-box">

        <!-- Modal Header -->
        <div class="ob-modal-header">
            <h5 class="ob-modal-title">File OB Request</h5>
            <button onclick="closeOBModal()" class="ob-modal-close-btn">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

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
        <label class="ob-label">Client Name</label>
        <input type="text" id="obClientName" class="ob-input" placeholder="Enter client name...">

        <!-- Reason -->
        <label class="ob-label" style="margin-top:1rem;">Reason</label>
        <textarea id="obReason" class="ob-textarea" rows="3" placeholder="Enter reason for official business..."></textarea>

        <!-- Submit -->
        <button onclick="submitOBRequest()" class="ob-submit-btn">
            <i class="bi bi-check-circle-fill"></i> Submit OB Request
        </button>

        <!-- Error -->
        <div id="obErrorMsg" class="ob-error-msg"></div>

        <!-- Success -->
        <div id="obSuccessMsg" class="ob-success-msg"></div>

    </div>
</div>

<script>
    // ===== OB MODAL =====
    let obCalendarInstance = null;
    let obScheduledDates   = [];
    let obExistingDates    = [];
    let obSelectedDate     = null;

    function localObDateStr(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    // ---- OPEN MODAL ----
    function openOBModal() {
        document.getElementById('obModalOverlay').style.display    = 'flex';
        document.getElementById('obErrorMsg').style.display        = 'none';
        document.getElementById('obSuccessMsg').style.display      = 'none';
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
                document.getElementById('obErrorMsg').style.display = 'block';
                document.getElementById('obErrorMsg').textContent   = 'Failed to load schedule dates.';
                renderOBCalendar();
            });
    }

    // ---- CLOSE MODAL ----
    function closeOBModal() {
        document.getElementById('obModalOverlay').style.display = 'none';
        if (obCalendarInstance) {
            obCalendarInstance.destroy();
            obCalendarInstance = null;
        }
        obSelectedDate   = null;
        obScheduledDates = [];
        obExistingDates  = [];
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
                left:   'prev,next today',
                center: 'title',
                right:  ''
            },

            events: obScheduledDates.map(date => ({
                start:   date,
                display: 'background',
                color:   'rgba(151, 190, 65, 0.12)'
            })),

            dayCellDidMount: function(info) {
                const dateStr   = localObDateStr(info.date);
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
                        const check     = document.createElement('span');
                        check.className = 'ob-check';
                        check.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
                        dayTop.appendChild(check);
                    }
                }
            },

            dateClick: function(info) {
                const dateStr = info.dateStr;
                const errEl   = document.getElementById('obErrorMsg');

                if (obExistingDates.includes(dateStr)) {
                    errEl.style.display = 'block';
                    errEl.textContent   = 'You already have an OB request for this date.';
                    return;
                }

                if (!obScheduledDates.includes(dateStr)) {
                    errEl.style.display = 'block';
                    errEl.textContent   = 'Please select a scheduled work day.';
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
        const box    = document.getElementById('obSelectedDateBox');
        const textEl = document.getElementById('obSelectedDateText');

        if (!obSelectedDate) {
            box.style.display = 'none';
            return;
        }

        box.style.display  = 'block';
        textEl.textContent = new Date(obSelectedDate + 'T00:00:00').toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
    }

    // ---- SUBMIT ----
    function submitOBRequest() {
        const clientName = document.getElementById('obClientName').value.trim();
        const reason     = document.getElementById('obReason').value.trim();
        const errEl      = document.getElementById('obErrorMsg');
        const sucEl      = document.getElementById('obSuccessMsg');

        if (!obSelectedDate) {
            errEl.style.display = 'block';
            errEl.textContent   = 'Please select a date.';
            return;
        }
        if (!clientName) {
            errEl.style.display = 'block';
            errEl.textContent   = 'Please enter a client name.';
            return;
        }
        if (!reason) {
            errEl.style.display = 'block';
            errEl.textContent   = 'Please enter a reason.';
            return;
        }

        const formData = new FormData();
        formData.append('ob_date',     obSelectedDate);
        formData.append('client_name', clientName);
        formData.append('reason',      reason);

        fetch('/DTR-Internship-Project/employee_pages/submit_ob_request.php', {
            method: 'POST',
            body:   formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                errEl.style.display = 'none';
                sucEl.style.display = 'block';
                sucEl.textContent   = data.message;
                obSelectedDate      = null;
                setTimeout(() => closeOBModal(), 2000);
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
