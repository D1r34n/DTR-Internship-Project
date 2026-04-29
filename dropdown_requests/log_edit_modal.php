<!-- LOG EDIT REQUEST MODAL -->
<div id="logEditModalOverlay">
    <div class="log-edit-modal-box">

        <!-- Modal Header -->
        <div class="log-edit-modal-header">
            <h5 class="log-edit-modal-title">Request Log Edit</h5>
            <button onclick="closeLogEditModal()" class="log-edit-modal-close-btn">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Step 1: Pick a record -->
        <div id="leStep1">
            <p class="le-step-hint">Select a day where you forgot to time out:</p>
            <div id="leGanttList">
                <p class="le-gantt-loading">Loading...</p>
            </div>
        </div>

        <!-- Step 2: Fill in details -->
        <div id="leStep2" style="display:none;">
            <button onclick="leBackToStep1()" class="le-back-btn">
                <i class="bi bi-arrow-left"></i> Back
            </button>

            <div class="le-summary-card">
                <p class="le-summary-label">Date</p>
                <p class="le-summary-value" id="leSelectedDate"></p>
                <div class="le-summary-row">
                    <div>
                        <p class="le-summary-label" style="margin-top:0.5rem;">Scheduled</p>
                        <p class="le-summary-value" id="leSelectedSched"></p>
                    </div>
                    <div>
                        <p class="le-summary-label" style="margin-top:0.5rem;">Time In</p>
                        <p class="le-summary-value highlight" id="leSelectedTimeIn"></p>
                    </div>
                    <div id="leRecordedTimeOutRow" style="display:none;">
                        <p class="le-summary-label" style="margin-top:0.5rem;">Recorded Time Out</p>
                        <p class="le-summary-value" style="color:#f0ad4e;" id="leRecordedTimeOut"></p>
                    </div>
                </div>
            </div>

            <label class="le-input-label">Requested Time Out</label>
            <input type="time" id="leRequestedTimeOut" class="le-time-input">

            <label class="le-input-label" style="margin-top:1rem;">Reason</label>
            <textarea id="leReason" rows="3" placeholder="Explain why you forgot to time out..."></textarea>

            <button onclick="submitLogEditRequest()" class="le-submit-btn">
                <i class="bi bi-check-circle-fill"></i> Submit Request
            </button>
        </div>

        <!-- Feedback -->
        <div id="leErrorMsg"></div>
        <div id="leSuccessMsg"></div>

    </div>
</div>

<script>
    let leSelectedRecord = null;

    function openLogEditModal() {
        document.getElementById('logEditModalOverlay').style.display = 'flex';
        document.getElementById('leStep1').style.display             = 'block';
        document.getElementById('leStep2').style.display             = 'none';
        document.getElementById('leErrorMsg').style.display          = 'none';
        document.getElementById('leSuccessMsg').style.display        = 'none';
        loadLeGantt();
    }

    function closeLogEditModal() {
        document.getElementById('logEditModalOverlay').style.display = 'none';
        leSelectedRecord = null;
    }

    function leBackToStep1() {
        document.getElementById('leStep2').style.display    = 'none';
        document.getElementById('leStep1').style.display    = 'block';
        document.getElementById('leErrorMsg').style.display = 'none';
    }

    function loadLeGantt() {
        const list = document.getElementById('leGanttList');
        list.innerHTML = '<p class="le-gantt-loading">Loading...</p>';

        fetch('/DTR-Internship-Project/employee_pages/get_no_timeout_records.php')
            .then(res => res.json())
            .then(records => {
                if (records.length === 0) {
                    list.innerHTML = '<p class="le-gantt-loading">No records found.</p>';
                    return;
                }

                list.innerHTML = '';

                records.forEach(row => {
                    const schedStart   = row.scheduled_start;
                    const schedEnd     = row.scheduled_end;
                    const actualIn     = row.actual_time_in;
                    const actualOut    = row.actual_time_out;
                    const workDate     = row.work_date;
                    const recordType   = row.record_type; // 'no_timeout' | 'undertime'
                    const utMin        = parseInt(row.undertime_minutes) || 0;

                    const tsSchedIn    = new Date(schedStart.replace(' ', 'T')).getTime() / 1000;
                    const tsSchedOut   = new Date(schedEnd.replace(' ', 'T')).getTime()   / 1000;
                    const tsActualIn   = new Date(actualIn.replace(' ', 'T')).getTime()   / 1000;
                    const tsActualOut  = actualOut ? new Date(actualOut.replace(' ', 'T')).getTime() / 1000 : null;

                    const rangeStart   = tsSchedIn  - 7200;
                    const rangeEnd     = tsSchedOut + 7200;
                    const range        = rangeEnd - rangeStart;

                    const schedLeft    = ((tsSchedIn  - rangeStart) / range) * 100;
                    const schedWidth   = ((tsSchedOut - tsSchedIn)  / range) * 100;
                    const actualLeft   = ((tsActualIn - rangeStart) / range) * 100;
                    const inPos        = ((tsActualIn - rangeStart) / range) * 100;

                    const fmtTime = ts => new Date(ts * 1000).toLocaleTimeString('en-US', {
                        hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila'
                    });
                    const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
                        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                    });
                    const fmtShort = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
                        month: 'short', day: 'numeric'
                    });

                    let barsHtml  = '';
                    let rightLabel = '';

                    if (recordType === 'no_timeout') {
                        const noOutWidth = ((rangeEnd - tsActualIn) / range) * 100;
                        barsHtml = `
                            <div class="gantt-sched-bar"     style="left:${schedLeft}%;  width:${schedWidth}%;"></div>
                            <div class="le-no-out-bar"       style="left:${actualLeft}%; width:${noOutWidth}%;"></div>
                            <div class="gantt-timein-marker" style="left:${inPos}%;"></div>
                        `;
                        rightLabel = `<div class="le-no-out-label">No Time Out</div>`;

                    } else {
                        // undertime: show worked bar + undertime gap
                        const workedWidth  = ((tsActualOut - tsActualIn)  / range) * 100;
                        const utLeft       = ((tsActualOut - rangeStart)  / range) * 100;
                        const utWidth      = ((tsSchedOut  - tsActualOut) / range) * 100;
                        const outPos       = ((tsActualOut - rangeStart)  / range) * 100;
                        const utH          = Math.floor(utMin / 60);
                        const utM          = utMin % 60;
                        const utLabel      = utH > 0 ? `${utH}h ${utM}m` : `${utM}m`;
                        barsHtml = `
                            <div class="gantt-sched-bar"      style="left:${schedLeft}%;  width:${schedWidth}%;"></div>
                            <div class="gantt-actual-bar"     style="left:${actualLeft}%; width:${workedWidth}%;"></div>
                            <div class="le-undertime-bar"     style="left:${utLeft}%;     width:${utWidth}%;"></div>
                            <div class="gantt-timein-marker"  style="left:${inPos}%;"></div>
                            <div class="gantt-timeout-marker" style="left:${outPos}%;"></div>
                        `;
                        rightLabel = `<div class="le-undertime-label">-${utLabel}</div>`;
                    }

                    const rowEl = document.createElement('div');
                    rowEl.className = `le-gantt-row le-gantt-${recordType}`;
                    rowEl.innerHTML = `
                        <div class="ot-gantt-row-inner">
                            <div class="ot-date-label">
                                <div class="ot-date-label-day">${fmtDate(workDate).split(',')[0]}</div>
                                <div class="ot-date-label-short">${fmtShort(workDate)}</div>
                            </div>
                            <div class="gantt-bar-container"
                                data-range-start="${rangeStart}" data-range-end="${rangeEnd}">
                                <div class="gantt-cursor">
                                    <div class="gantt-cursor-line"></div>
                                    <div class="gantt-cursor-label"></div>
                                </div>
                                ${barsHtml}
                            </div>
                            ${rightLabel}
                        </div>
                    `;

                    rowEl.addEventListener('click', () => {
                        leSelectedRecord = {
                            attendance_id: row.attendance_id,
                            work_date:     workDate,
                            sched:         fmtTime(tsSchedIn) + ' – ' + fmtTime(tsSchedOut),
                            time_in:       fmtTime(tsActualIn),
                            record_type:   recordType,
                        };

                        document.getElementById('leSelectedDate').textContent   = fmtDate(workDate);
                        document.getElementById('leSelectedSched').textContent  = leSelectedRecord.sched;
                        document.getElementById('leSelectedTimeIn').textContent = leSelectedRecord.time_in;

                        // Show/hide "Recorded Time Out" row for undertime
                        const utRow = document.getElementById('leRecordedTimeOutRow');
                        if (recordType === 'undertime' && tsActualOut) {
                            document.getElementById('leRecordedTimeOut').textContent = fmtTime(tsActualOut);
                            utRow.style.display = 'block';
                        } else {
                            utRow.style.display = 'none';
                        }

                        document.getElementById('leRequestedTimeOut').value = '';
                        document.getElementById('leReason').value           = '';
                        document.getElementById('leErrorMsg').style.display = 'none';

                        document.getElementById('leStep1').style.display = 'none';
                        document.getElementById('leStep2').style.display = 'block';
                    });

                    list.appendChild(rowEl);
                });

                initGanttCursors();
            })
            .catch(() => {
                list.innerHTML = '<p style="color:#ff8a8a; text-align:center;">Failed to load records.</p>';
            });
    }

    function submitLogEditRequest() {
        const timeOut = document.getElementById('leRequestedTimeOut').value.trim();
        const reason  = document.getElementById('leReason').value.trim();
        const errEl   = document.getElementById('leErrorMsg');
        const sucEl   = document.getElementById('leSuccessMsg');

        errEl.style.display = 'none';
        sucEl.style.display = 'none';

        if (!timeOut) {
            errEl.style.display = 'block';
            errEl.textContent   = 'Please enter your requested time out.';
            return;
        }
        if (!reason) {
            errEl.style.display = 'block';
            errEl.textContent   = 'Please enter a reason.';
            return;
        }
        if (!leSelectedRecord) return;

        const formData = new FormData();
        formData.append('attendance_id',      leSelectedRecord.attendance_id);
        formData.append('requested_time_out', timeOut);
        formData.append('reason',             reason);

        fetch('/DTR-Internship-Project/employee_pages/submit_log_edit_request.php', {
            method: 'POST',
            body:   formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                errEl.style.display = 'none';
                sucEl.style.display = 'block';
                sucEl.textContent   = data.message;
                document.getElementById('leStep2').style.display = 'none';
                setTimeout(() => closeLogEditModal(), 2000);
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
