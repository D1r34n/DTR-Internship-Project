
<!-- OT REQUEST MODAL -->
<div id="otModalOverlay">
    <div class="ot-modal-box">

        <!-- Modal Header -->
        <div class="ot-modal-header">
            <h5 class="ot-modal-title">File OT Request</h5>
            <button onclick="closeOTModal()" class="ot-modal-close-btn">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Step 1: Pick a Gantt row -->
        <div id="otStep1">
            <p class="ot-step-hint">Select a day to file OT for:</p>
            <div id="otGanttList">
                <p class="ot-gantt-loading">Loading...</p>
            </div>
        </div>

        <!-- Step 2: Reason form -->
        <div id="otStep2" style="display:none;">
            <button onclick="backToStep1()" class="ot-back-btn">
                <i class="bi bi-arrow-left"></i> Back
            </button>

            <div class="ot-summary-card">
                <p class="ot-summary-label">Selected Date</p>
                <p class="ot-summary-value" id="otSelectedDate"></p>
                <p class="ot-summary-label" style="margin-top:0.5rem;">OT Period</p>
                <p class="ot-summary-value highlight" id="otSelectedTime"></p>
                <p class="ot-summary-label" style="margin-top:0.5rem;">OT Duration</p>
                <p class="ot-summary-value" id="otSelectedDuration"></p>
            </div>

            <label class="ot-reason-label">Reason for OT</label>
            <textarea id="otReason" rows="3" placeholder="Enter reason for overtime..."></textarea>

            <button onclick="submitOTRequest()" class="ot-submit-btn">
                <i class="bi bi-check-circle-fill"></i> Submit OT Request
            </button>
        </div>

        <!-- Error message -->
        <div id="otErrorMsg"></div>

        <!-- Success message -->
        <div id="otSuccessMsg"></div>

    </div>
</div>

<script>
    // ===== OT MODAL =====
    let otSelectedRecord = null;

    function openOTModal() {
        document.getElementById('otModalOverlay').style.display = 'flex';
        document.getElementById('otStep1').style.display        = 'block';
        document.getElementById('otStep2').style.display        = 'none';
        document.getElementById('otErrorMsg').style.display     = 'none';
        document.getElementById('otSuccessMsg').style.display   = 'none';
        loadOTGantt();
    }

    function closeOTModal() {
        document.getElementById('otModalOverlay').style.display = 'none';
        otSelectedRecord = null;
    }

    function backToStep1() {
        document.getElementById('otStep2').style.display    = 'none';
        document.getElementById('otStep1').style.display    = 'block';
        document.getElementById('otErrorMsg').style.display = 'none';
    }

    function loadOTGantt() {
        const list = document.getElementById('otGanttList');
        list.innerHTML = '<p class="ot-gantt-loading">Loading...</p>';

        fetch('/DTR-Internship-Project/employee_pages/get_ot_records.php')
            .then(res => res.json())
            .then(records => {
                if (records.length === 0) {
                    list.innerHTML = '<p class="ot-gantt-loading">No OT records available to file.</p>';
                    return;
                }

                list.innerHTML = '';

                records.forEach(row => {
                    const date        = row.date;
                    const lateMin     = parseInt(row.late_minutes)    || 0;
                    const otMin       = parseInt(row.overtime_minutes) || 0;
                    const schedIn     = row.scheduled_time_in;
                    const schedOut    = row.scheduled_time_out;
                    const actualIn    = row.actual_time_in;
                    const actualOut   = row.actual_time_out;
                    const canFile     = lateMin < 60;

                    const tsSchedIn   = Date.parse(date + 'T' + schedIn)           / 1000;
                    const tsSchedOut  = Date.parse(date + 'T' + schedOut)          / 1000;
                    const tsActualIn  = Date.parse(actualIn.replace(' ', 'T'))     / 1000;
                    const tsActualOut = Date.parse(actualOut.replace(' ', 'T'))    / 1000;

                    const rangeStart  = tsSchedIn  - 7200;
                    const rangeEnd    = tsActualOut + 7200;
                    const range       = rangeEnd - rangeStart;

                    const schedLeft   = ((tsSchedIn   - rangeStart) / range) * 100;
                    const schedWidth  = ((tsSchedOut  - tsSchedIn)  / range) * 100;
                    const actualLeft  = ((tsActualIn  - rangeStart) / range) * 100;
                    const actualWidth = ((tsActualOut - tsActualIn) / range) * 100;
                    const otLeft      = ((tsSchedOut  - rangeStart) / range) * 100;
                    const otWidth     = ((tsActualOut - tsSchedOut) / range) * 100;
                    const inPos       = ((tsActualIn  - rangeStart) / range) * 100;
                    const outPos      = ((tsActualOut - rangeStart) / range) * 100;

                    const fmtTime  = ts => new Date(ts * 1000).toLocaleTimeString('en-US', {
                        hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila'
                    });
                    const fmtDate  = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
                        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                    });
                    const fmtShort = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
                        month: 'short', day: 'numeric'
                    });

                    const otHours = Math.floor(otMin / 60);
                    const otMins  = otMin % 60;
                    const otLabel = otHours > 0 ? `${otHours}h ${otMins}m` : `${otMins}m`;

                    const rowEl = document.createElement('div');
                    rowEl.className = `ot-gantt-row ${canFile ? 'can-file' : 'cannot-file'}`;

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
                            <div class="ot-duration-label">+${otLabel} OT</div>
                        </div>
                        ${lateMin > 0 ? `
                        <div class="ot-late-warning ${canFile ? 'can-file' : 'cannot-file'}">
                            <i class="bi bi-clock"></i> Late: ${lateMin} min${lateMin !== 1 ? 's' : ''}
                            ${!canFile ? ' — <strong>Cannot file OT (late ≥ 60 mins)</strong>' : ''}
                        </div>` : ''}
                    `;

                    rowEl.addEventListener('click', () => {
                        const errEl = document.getElementById('otErrorMsg');

                        if (!canFile) {
                            errEl.style.display = 'block';
                            errEl.textContent   = 'You cannot file an OT request because you were late for more than an hour.';
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
                        errEl.style.display = 'none';

                        document.getElementById('otStep1').style.display = 'none';
                        document.getElementById('otStep2').style.display = 'block';
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
        const reason = document.getElementById('otReason').valu']
        e.trim();
        const errEl  = document.getElementById('otErrorMsg');
        const sucEl  = document.getElementById('otSuccessMsg');

        if (!reason) {
            errEl.style.display = 'block';
            errEl.textContent   = 'Please enter a reason for your OT request.';
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
            body:   formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                errEl.style.display = 'none';
                sucEl.style.display = 'block';
                sucEl.textContent   = data.message;
                document.getElementById('otStep2').style.display = 'none';
                setTimeout(() => closeOTModal(), 2000);
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