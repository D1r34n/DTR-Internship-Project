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
            <p class="le-step-hint">Select a record to correct your attendance log:</p>
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
                        <p class="le-summary-label" style="margin-top:0.5rem;">Current Log</p>
                        <p class="le-summary-value" id="leCurrentLog"></p>
                    </div>
                </div>
                <p class="le-summary-label" style="margin-top:0.75rem;">Status</p>
                <div id="leStatusBadges"></div>
            </div>

            <label class="le-input-label">Edit Type</label>
            <div class="le-custom-select" id="leEditTypeWrapper">
                <div class="le-custom-select-trigger" id="leEditTypeTrigger" onclick="toggleLeSelect()">
                    <span id="leEditTypeLabel"></span>
                    <i class="bi bi-chevron-down le-select-chevron"></i>
                </div>
                <div class="le-custom-select-options" id="leEditTypeOptions"></div>
            </div>

            <div id="leTimeInGroup" style="display:none; margin-top:1rem;">
                <label class="le-input-label">Requested Time In</label>
                <input type="time" id="leRequestedTimeIn" class="le-time-input" oninput="updateLePreview()">
            </div>

            <div id="leTimeOutGroup" style="display:none; margin-top:1rem;">
                <label class="le-input-label">Requested Time Out</label>
                <input type="time" id="leRequestedTimeOut" class="le-time-input" oninput="updateLePreview()">
            </div>

            <label class="le-input-label" style="margin-top:1rem;">Reason</label>
            <textarea id="leReason" rows="3" placeholder="Explain the reason for this correction..."></textarea>

            <div class="le-preview-box" id="lePreviewBox">
                <p class="le-preview-label">Preview</p>
                <p class="le-preview-text" id="lePreviewText">—</p>
            </div>

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
        if (wrapper && !wrapper.contains(e.target)) wrapper.classList.remove('open');
    });

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

                    const rangeStart  = tsSchedIn  - 7200;
                    const rangeEnd    = tsSchedOut + 7200;
                    const range       = rangeEnd - rangeStart;

                    const schedLeft   = ((tsSchedIn  - rangeStart) / range) * 100;
                    const schedWidth  = ((tsSchedOut - tsSchedIn)  / range) * 100;
                    const actualLeft  = ((tsActualIn - rangeStart) / range) * 100;
                    const inPos       = ((tsActualIn - rangeStart) / range) * 100;

                    const fmtTime  = ts => new Date(ts * 1000).toLocaleTimeString('en-US', {
                        hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila'
                    });
                    const fmtDate  = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
                        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                    });
                    const fmtShort = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
                        month: 'short', day: 'numeric'
                    });

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
                        // late: gap from scheduled start to actual time-in
                        const lateWidth  = ((tsActualIn - tsSchedIn)  / range) * 100;
                        const workedWidth = tsActualOut
                            ? ((tsActualOut - tsActualIn) / range) * 100
                            : ((tsSchedOut  - tsActualIn) / range) * 100;
                        const outPos      = tsActualOut ? ((tsActualOut - rangeStart) / range) * 100 : null;
                        const lateH       = Math.floor(lateMin / 60);
                        const lateM       = lateMin % 60;
                        const lateLabel   = lateH > 0 ? `${lateH}h ${lateM}m` : `${lateM}m`;
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
                            attendance_id:      row.attendance_id,
                            applicable_types:   row.applicable_types,
                            scheduled_start_ts: tsSchedIn,
                            scheduled_end_ts:   tsSchedOut,
                            actual_time_in_ts:  tsActualIn,
                            actual_time_out_ts: tsActualOut,
                        };

                        document.getElementById('leSelectedDate').textContent  = fmtDate(workDate);
                        document.getElementById('leSelectedSched').textContent = fmtTime(tsSchedIn) + ' – ' + fmtTime(tsSchedOut);

                        // Current Log with color-coded time in
                        const timeInColor = tsActualIn > tsSchedIn ? '#f0ad4e' : '#97be41';
                        const timeOutHtml = tsActualOut
                            ? `<span>${fmtTime(tsActualOut)}</span>`
                            : `<span style="color:#ff8a8a; font-style:italic;">Missing</span>`;
                        document.getElementById('leCurrentLog').innerHTML =
                            `<span style="color:${timeInColor};">${fmtTime(tsActualIn)}</span> – ${timeOutHtml}`;

                        // Status badges
                        const badgeColors = { no_timeout: 'le-badge-no-timeout', undertime: 'le-badge-undertime', late: 'le-badge-late' };
                        const badgeNames  = { no_timeout: 'No Time Out',         undertime: 'Undertime',          late: 'Late' };
                        document.getElementById('leStatusBadges').innerHTML =
                            row.applicable_types.map(t =>
                                `<span class="le-status-badge ${badgeColors[t]}">${badgeNames[t]}</span>`
                            ).join('');

                        // Edit Type dropdown
                        const hasLate    = row.applicable_types.includes('late');
                        const hasTimeout = row.applicable_types.some(t => t === 'no_timeout' || t === 'undertime');
                        const editTypeEl = document.getElementById('leEditTypeOptions');
                        editTypeEl.innerHTML = '';
                        leEditTypeValue = '';

                        const addOpt = (val, label, isDefault) => {
                            const o = document.createElement('div');
                            o.className = 'le-custom-select-option' + (isDefault ? ' selected' : '');
                            o.dataset.value = val;
                            o.textContent = label;
                            o.onclick = () => selectLeEditType(val, label);
                            editTypeEl.appendChild(o);
                            if (isDefault) {
                                leEditTypeValue = val;
                                document.getElementById('leEditTypeLabel').textContent = label;
                            }
                        };

                        if (hasLate && hasTimeout) {
                            addOpt('both',     'Correct Both',     true);
                            addOpt('time_in',  'Correct Time In',  false);
                            addOpt('time_out', 'Correct Time Out', false);
                        } else if (hasLate) {
                            addOpt('time_in', 'Correct Time In', true);
                        } else {
                            addOpt('time_out', 'Correct Time Out', true);
                        }

                        document.getElementById('leReason').value           = '';
                        document.getElementById('leErrorMsg').style.display = 'none';
                        document.getElementById('lePreviewText').textContent = '—';

                        onLeEditTypeChange();

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

    function onLeEditTypeChange() {
        const type = leEditTypeValue;
        document.getElementById('leTimeInGroup').style.display  = (type === 'time_in'  || type === 'both') ? 'block' : 'none';
        document.getElementById('leTimeOutGroup').style.display = (type === 'time_out' || type === 'both') ? 'block' : 'none';
        document.getElementById('leRequestedTimeIn').value  = '';
        document.getElementById('leRequestedTimeOut').value = '';
        updateLePreview();
    }

    function updateLePreview() {
        if (!leSelectedRecord) return;

        const type       = leEditTypeValue;
        const reqIn      = document.getElementById('leRequestedTimeIn').value;
        const reqOut     = document.getElementById('leRequestedTimeOut').value;
        const schedStart = leSelectedRecord.scheduled_start_ts;
        const schedEnd   = leSelectedRecord.scheduled_end_ts;

        let effIn  = leSelectedRecord.actual_time_in_ts;
        let effOut = leSelectedRecord.actual_time_out_ts;

        if ((type === 'time_in' || type === 'both') && reqIn) {
            const [h, m] = reqIn.split(':').map(Number);
            const d = new Date(schedStart * 1000);
            d.setHours(h, m, 0, 0);
            effIn = d.getTime() / 1000;
        }

        if ((type === 'time_out' || type === 'both') && reqOut) {
            const [h, m] = reqOut.split(':').map(Number);
            const d = new Date(schedEnd * 1000);
            d.setHours(h, m, 0, 0);
            if (d.getTime() / 1000 <= effIn) d.setDate(d.getDate() + 1);
            effOut = d.getTime() / 1000;
        }

        const labels   = [];
        const lateMin  = Math.max(0, Math.round((effIn - schedStart) / 60));
        labels.push(lateMin === 0 ? 'On Time' : `${lateMin}m Late`);

        if (effOut === null) {
            labels.push('Missing Time Out');
        } else {
            const undertimeMin = Math.max(0, Math.round((schedEnd - effOut) / 60));
            const overtimeMin  = Math.max(0, Math.round((effOut   - schedEnd) / 60));
            if (undertimeMin > 0)     labels.push(`${undertimeMin}m Undertime`);
            else if (overtimeMin > 0) labels.push(`${overtimeMin}m Overtime`);
        }

        document.getElementById('lePreviewText').textContent = labels.join(' | ');
    }

    function submitLogEditRequest() {
        const editType = leEditTypeValue;
        const reqIn    = document.getElementById('leRequestedTimeIn').value.trim();
        const reqOut   = document.getElementById('leRequestedTimeOut').value.trim();
        const reason   = document.getElementById('leReason').value.trim();
        const errEl    = document.getElementById('leErrorMsg');
        const sucEl    = document.getElementById('leSuccessMsg');

        errEl.style.display = 'none';
        sucEl.style.display = 'none';

        if ((editType === 'time_in' || editType === 'both') && !reqIn) {
            errEl.style.display = 'block';
            errEl.textContent   = 'Please enter your requested time in.';
            return;
        }
        if ((editType === 'time_out' || editType === 'both') && !reqOut) {
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
        formData.append('attendance_id', leSelectedRecord.attendance_id);
        formData.append('request_type',  editType);
        if (reqIn)  formData.append('requested_time_in',  reqIn);
        if (reqOut) formData.append('requested_time_out', reqOut);
        formData.append('reason', reason);

        fetch('/DTR-Internship-Project/employee_pages/submit_log_edit_request.php', {
            method: 'POST',
            body:   formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
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
