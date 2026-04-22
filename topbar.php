<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- AUTH CHECK ----
if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

$employeeId = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// ---- ROLE VALIDATION ----
if (!in_array($role, ['admin', 'employee'])) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// ---- PAGE TITLES ----
$titles = [
    'employee' => [
        'dashboard' => 'Employee Dashboard',
        'records'   => 'Employee Records',
        'schedule'  => 'Employee Schedule',
        'logs'      => 'Employee Activity Logs',
    ],
    'admin' => [
        'dashboard' => 'Admin Dashboard',
        'employees' => 'Employees',
        'schedule'  => 'Schedules',
        'requests'  => 'Requests',
        'logs'      => 'Logs',
    ]
];

$current_page = $current_page ?? 'dashboard';
$title = $titles[$role][$current_page] ?? 'Dashboard';

// ---- DATE ----
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// =====================================================
// ✅ SINGLE SOURCE OF TRUTH: ATTENDANCE ONLY
// =====================================================
$stmt = $pdo->prepare("
    SELECT actual_time_in, actual_time_out
    FROM attendances
    WHERE employee_id = ? AND work_date = ?
    LIMIT 1
");
$stmt->execute([$employeeId, $today]);

$attendance = $stmt->fetch(PDO::FETCH_ASSOC) ?? [
    'actual_time_in' => null,
    'actual_time_out' => null
];

$hasTimeIn  = !empty($attendance['actual_time_in'] ?? null);
$hasTimeOut = !empty($attendance['actual_time_out'] ?? null);

// FINAL STATE (ONLY THIS CONTROLS UI)
$timedIn = $hasTimeIn && !$hasTimeOut;

// Optional formatted display (for future use if needed)
$currentStatus = $timedIn ? 'Timed In' : 'Timed Out';

?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- TOP BAR -->
<div class="topBar">
    <h4 class="dashboardTitle"><?= htmlspecialchars($title) ?></h4>

    <div class="topBarRight">

        <!-- TIME IN / OUT BUTTON -->
        <button
            class="timeInButton <?= $timedIn ? 'btn-out' : 'btn-in' ?>"
            id="timeInBtn"
            onclick="handleTimeIn()"
        >
            <i class="bi bi-stopwatch-fill timeInIcon"></i>
            <span id="timeInLabel">
                <?= $timedIn ? 'Time Out' : 'Time In' ?>
            </span>
        </button>

        <div class="verticalDivider"></div>

        <!-- USER DROPDOWN -->
        <div class="navUserProfile">
            <div class="userDropdownWrapper">

                <span class="userEmail dropdown-toggle" id="userDropdownToggle">
                    <i class="bi bi-person-fill userProfileIcon"></i>
                    <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'User') ?>
                </span>

                <div class="userDropdownMenu" id="userDropdownMenu">
                    <div class="dropdownSection">

                        <a href="#" class="userDropdownItem" onclick="openOTModal(); return false;">
                            <i class="bi bi-clock-history"></i> Request OT
                        </a>

                        <a href="#" class="userDropdownItem">
                            <i class="bi bi-calendar-x"></i> Request Leave
                        </a>

                        <a href="#" class="userDropdownItem">
                            <i class="bi bi-briefcase"></i> Request OB
                        </a>

                        <a href="#" class="userDropdownItem">
                            <i class="bi bi-pencil-square"></i> Request Log Edit
                        </a>

                        <div class="horizontalDivider"></div>

                        <a href="../index.php" class="logoutText">
                            <i class="bi bi-box-arrow-right logoutIcon"></i> Logout
                        </a>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- OPTIONAL: expose state to JS (for sync with gantt/table later) -->
<script>
    window.__ATTENDANCE_STATE__ = {
        timedIn: <?= $timedIn ? 'true' : 'false' ?>
    };
</script>

<!-- OT REQUEST MODAL -->
<div id="otModalOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; justify-content:center; align-items:center;">
    <div style="background:var(--glass-bg); backdrop-filter:blur(32px) saturate(160%) brightness(0.3); -webkit-backdrop-filter:blur(32px) saturate(160%) brightness(0.3); border:1px solid var(--glass-border); border-radius:16px; padding:2rem; width:700px; max-height:80vh; overflow-y:auto; box-shadow:0 12px 35px rgba(0,0,0,0.5); position:relative;">

        <!-- Modal Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h5 style="color:#fff; margin:0; font-weight:600;">File OT Request</h5>
            <button onclick="closeOTModal()" style="background:none; border:none; color:#aaa; font-size:1.3rem; cursor:pointer;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Step 1: Pick a Gantt row -->
        <div id="otStep1">
            <p style="color:rgba(255,255,255,0.6); font-size:0.85rem; margin-bottom:1rem;">Select a day to file OT for:</p>
            <div id="otGanttList" style="display:flex; flex-direction:column; gap:2.5rem;">
                <p style="color:#aaa; text-align:center;">Loading...</p>
            </div>
        </div>

        <!-- Step 2: Reason form -->
        <div id="otStep2" style="display:none;">
            <button onclick="backToStep1()" style="background:none; border:none; color:#aaa; font-size:0.85rem; cursor:pointer; margin-bottom:1rem;">
                <i class="bi bi-arrow-left"></i> Back
            </button>

            <div style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:1rem; margin-bottom:1.5rem;">
                <p style="color:#aaa; font-size:0.8rem; margin:0 0 0.3rem;">Selected Date</p>
                <p style="color:#fff; font-weight:600; margin:0;" id="otSelectedDate"></p>
                <p style="color:#aaa; font-size:0.8rem; margin:0.5rem 0 0.3rem;">OT Period</p>
                <p style="color:#97be41; font-weight:600; margin:0;" id="otSelectedTime"></p>
                <p style="color:#aaa; font-size:0.8rem; margin:0.5rem 0 0.3rem;">OT Duration</p>
                <p style="color:#fff; font-weight:600; margin:0;" id="otSelectedDuration"></p>
            </div>

            <label style="color:rgba(255,255,255,0.7); font-size:0.85rem; font-weight:600; display:block; margin-bottom:0.5rem;">Reason for OT</label>
            <textarea id="otReason" rows="3" placeholder="Enter reason for overtime..." style="width:100%; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.15); border-radius:8px; color:#fff; padding:0.6rem 0.8rem; font-family:'Poppins',sans-serif; font-size:0.875rem; outline:none; resize:none;"></textarea>

            <button onclick="submitOTRequest()" style="margin-top:1rem; background:#97be41; color:#fff; border:none; padding:0.5rem 1.5rem; border-radius:8px; font-family:'Poppins',sans-serif; font-size:0.9rem; cursor:pointer; transition:background 0.2s;">
                <i class="bi bi-check-circle-fill"></i> Submit OT Request
            </button>
        </div>

        <!-- Error message -->
        <div id="otErrorMsg" style="display:none; margin-top:1rem; background:rgba(220,53,69,0.15); border:1px solid rgba(220,53,69,0.3); border-radius:8px; padding:0.8rem 1rem; color:#ff8a8a; font-size:0.875rem;"></div>

        <!-- Success message -->
        <div id="otSuccessMsg" style="display:none; margin-top:1rem; background:rgba(151,190,65,0.15); border:1px solid rgba(151,190,65,0.3); border-radius:8px; padding:0.8rem 1rem; color:#97be41; font-size:0.875rem;"></div>

    </div>
</div>

<!-- JavaScript -->
<script defer>

    // ===== GANTT CURSORS =====
    function initGanttCursors() {
        const tooltip     = document.getElementById('gantt_tooltip');
        const gtSched     = document.getElementById('gt-sched');
        const gtActualIn  = document.getElementById('gt-actual-in');
        const gtActualOut = document.getElementById('gt-actual-out');
        const gtLateRow   = document.getElementById('gt-late-row');
        const gtLate      = document.getElementById('gt-late');
        const gtOtRow     = document.getElementById('gt-ot-row');
        const gtOt        = document.getElementById('gt-ot');
        const gtUtRow = document.getElementById('gt-ut-row');
        const gtUt    = document.getElementById('gt-ut');

        document.querySelectorAll('.ganttBarContainer').forEach(container => {
            const line  = container.querySelector('.ganttCursorLine');
            const label = container.querySelector('.ganttCursorLabel');
            if (!line || !label) return;

            const rangeStart = parseInt(container.dataset.rangeStart);
            const rangeEnd   = parseInt(container.dataset.rangeEnd);
            const range      = rangeEnd - rangeStart;

            const hasData = !!container.dataset.actualIn;

            container.addEventListener('mousemove', (e) => {
                const rect    = container.getBoundingClientRect();
                const x       = e.clientX - rect.left;
                const percent = Math.max(0, Math.min(1, x / rect.width));
                const time    = Math.floor(rangeStart + (percent * range));

                line.style.left  = (percent * 100) + '%';
                label.style.left = (percent * 100) + '%';
                label.textContent = new Date(time * 1000).toLocaleTimeString('en-US', {
                    hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila'
                });

                if (hasData && tooltip) {
                    gtSched.textContent     = container.dataset.schedIn + ' – ' + container.dataset.schedOut;
                    gtActualIn.textContent  = container.dataset.actualIn;
                    gtActualOut.textContent = container.dataset.actualOut;

                    // Show if late
                    if (container.dataset.late) {
                        gtLate.textContent          = container.dataset.late;
                        gtLateRow.style.display     = 'flex';
                    } else {
                        gtLateRow.style.display = 'none';
                    }

                    // Show overtime if it is approved or rejected
                    if (container.dataset.overtime) {
                        gtOt.textContent  = container.dataset.overtime;
                        const status      = container.dataset.overtimeStatus;
                        gtOtRow.className = 'ganttToolTipRow ganttToolTipOverTime'
                                        + (status === 'approved' ? ' approved' : status === 'rejected' ? ' rejected' : '');
                        gtOtRow.style.display = 'flex';
                    } else {
                        gtOtRow.style.display = 'none';
                    }
                    
                    // Show undertime only when it is the next day (day is finished)
                    const isToday = container.dataset.isToday === '1';

                    if (container.dataset.undertime && !isToday) {
                        gtUt.textContent = container.dataset.undertime;
                        gtUtRow.style.display = 'flex';
                    } else {
                        gtUtRow.style.display = 'none';
                    }

                    tooltip.style.left = e.clientX + 'px';
                    tooltip.style.top  = e.clientY + 'px';
                    tooltip.classList.add('visible');
                }
            });

            container.addEventListener('mouseleave', () => {
                if (tooltip) tooltip.classList.remove('visible');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initGanttCursors();

        window.addEventListener('storage', (event) => {
            if (event.key !== 'attendance_update') return;

            const tap   = localStorage.getItem('attendance_tap_result');
            const btn   = document.getElementById('timeInBtn');
            const label = document.getElementById('timeInLabel');
            if (tap === 'timed_in') {
                btn.classList.remove('btn-in');
                btn.classList.add('btn-out');
                label.textContent = 'Time Out';
            } else if (tap === 'timed_out') {
                btn.classList.remove('btn-out');
                btn.classList.add('btn-in');
                label.textContent = 'Time In';
            }
        });
    });
    
    let isProcessing = false;

    function debounce(fn, delay = 1000) {
        let timer;
        return (...args) => {
            if (timer) return;
            fn(...args);
            timer = setTimeout(() => timer = null, delay);
        };
    }

    const handleTimeIn = debounce(async () => {
        if (isProcessing) return;

        const btn   = document.getElementById('timeInBtn');
        const label = btn.querySelector('#timeInLabel');
        const status = document.getElementById('dashboard_status');

        isProcessing = true;
        btn.disabled = true;

        try {

            navigator.geolocation.getCurrentPosition(async (pos) => {

                const res = await fetch('../attendance_tap.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        accuracy: pos.coords.accuracy
                    })
                });

                const response = await res.json();

                console.log(response);

                if (response.tap === 'timed_in') {
                    btn.classList.remove('btn-in');
                    btn.classList.add('btn-out');
                    label.textContent = 'Time Out';
                    if (status) status.textContent = 'Timed In';
                } else if (response.tap === 'timed_out') {
                    btn.classList.remove('btn-out');
                    btn.classList.add('btn-in');
                    label.textContent = 'Time In';
                    if (status) status.textContent = 'Timed Out';
                }

                if (response.tap === 'timed_in' || response.tap === 'timed_out') {
                    localStorage.setItem('attendance_tap_result', response.tap);
                    localStorage.setItem('attendance_update', Date.now());
                }

                // ⚠️ this is your second error source
                if (typeof getTotalWorkedHours === 'function') {
                    getTotalWorkedHours();
                }

                if (document.getElementById('logs_table_body')) fetchLogs();
                if (document.getElementById('attendance_table_body')) loadAttendance();

            }, (err) => {
                console.error("GPS denied:", err);
                alert("Location permission is required for time in/out.");
            });

        } catch (err) {
            console.log('Error:', err);
        } finally {
            setTimeout(() => {
                isProcessing = false;
                btn.disabled = false;
            }, 1000);
        }
    }, 800);

    // Dropdown menu
    const toggle = document.getElementById('userDropdownToggle');
    const menu   = document.getElementById('userDropdownMenu');
    let isOpen   = false;

    toggle.addEventListener('mouseenter', () => menu.classList.add('show'));
    toggle.addEventListener('mouseleave', () => { if (!isOpen) menu.classList.remove('show'); });
    menu.addEventListener('mouseenter',   () => menu.classList.add('show'));
    menu.addEventListener('mouseleave',   () => { if (!isOpen) menu.classList.remove('show'); });

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        isOpen = !isOpen;
        menu.classList.toggle('show', isOpen);
    });

    document.addEventListener('click', (e) => {
        if (!toggle.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove('show');
            isOpen = false;
        }
    });

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
        list.innerHTML = '<p style="color:#aaa; text-align:center;">Loading...</p>';

        fetch('get_ot_records.php')
            .then(res => res.json())
            .then(records => {
                if (records.length === 0) {
                    list.innerHTML = '<p style="color:#aaa; text-align:center;">No OT records available to file.</p>';
                    return;
                }

                list.innerHTML = '';

                records.forEach(row => {
                    const date        = row.date;
                    const lateMin     = parseInt(row.late_minutes)     || 0;
                    const otMin       = parseInt(row.overtime_minutes)  || 0;
                    const schedIn     = row.scheduled_time_in;
                    const schedOut    = row.scheduled_time_out;
                    const actualIn    = row.actual_time_in;
                    const actualOut   = row.actual_time_out;
                    const canFile     = lateMin < 60;

                    // Timestamps
                    const tsSchedIn   = Date.parse(date + 'T' + schedIn)  / 1000;
                    const tsSchedOut  = Date.parse(date + 'T' + schedOut) / 1000;
                    const tsActualIn  = Date.parse(actualIn.replace(' ', 'T'))  / 1000;
                    const tsActualOut = Date.parse(actualOut.replace(' ', 'T')) / 1000;

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

                    const fmtTime = ts => new Date(ts * 1000).toLocaleTimeString('en-US', {
                        hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila'
                    });
                    const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
                        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                    });
                    const fmtShort = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
                        month: 'short', day: 'numeric'
                    });

                    const otHours = Math.floor(otMin / 60);
                    const otMins  = otMin % 60;
                    const otLabel = otHours > 0 ? `${otHours}h ${otMins}m` : `${otMins}m`;

                    const rowEl = document.createElement('div');
                    rowEl.style.cssText = `
                        cursor: ${canFile ? 'pointer' : 'not-allowed'};
                        opacity: ${canFile ? '1' : '0.5'};
                        padding: 0.5rem;
                        border-radius: 10px;
                        border: 1px solid ${canFile ? 'rgba(151,190,65,0.2)' : 'rgba(220,53,69,0.2)'};
                        transition: background 0.2s;
                    `;

                    rowEl.innerHTML = `
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:1.5rem;">
                            <div style="width:4rem; font-size:0.875rem; color:rgba(255,255,255,0.8); flex-shrink:0;">
                                <div>${fmtDate(date).split(',')[0]}</div>
                                <div style="font-size:0.75rem; color:#aaa;">${fmtShort(date)}</div>
                            </div>
                            <div class="ganttBarContainer"
                                style="position:relative; flex:1; height:30px; background:rgba(255,255,255,0.05); background-image:repeating-linear-gradient(to right, rgba(255,255,255,0.08) 0px, rgba(255,255,255,0.08) 1px, transparent 1px, transparent calc(100% / 24)); border-radius:8px; overflow:visible;"
                                data-range-start="${rangeStart}" data-range-end="${rangeEnd}">

                                <div class="ganttCursor">
                                    <div class="ganttCursorLine"></div>
                                    <div class="ganttCursorLabel"></div>
                                </div>

                                <!-- Scheduled bar -->
                                <div style="position:absolute; top:0; height:100%; left:${schedLeft}%; width:${schedWidth}%; background:rgba(150,150,255,0.25); border:1px dashed #7b7bff; border-radius:10px;"></div>
                                <!-- Actual bar -->
                                <div style="position:absolute; top:0; height:100%; left:${actualLeft}%; width:${actualWidth}%; background:rgba(25,135,84,0.75); border-radius:10px;"></div>
                                <!-- OT bar -->
                                <div style="position:absolute; top:0; height:100%; left:${otLeft}%; width:${otWidth}%; background:#4da3ff; border-radius:10px;"></div>
                                <!-- Markers -->
                                <div style="position:absolute; top:-6px; height:calc(100% + 12px); left:${inPos}%; width:2px; border-left:2px solid #22c55e; filter:drop-shadow(0 0 6px rgba(34,197,94,0.8)); z-index:999;"></div>
                                <div style="position:absolute; top:-6px; height:calc(100% + 12px); left:${outPos}%; width:2px; border-left:2px solid #dc3545; filter:drop-shadow(0 0 6px rgba(249,115,22,0.8)); z-index:999;"></div>
                            </div>
                            <div style="font-size:0.75rem; color:#4da3ff; white-space:nowrap; flex-shrink:0;">+${otLabel} OT</div>
                        </div>
                        ${lateMin > 0 ? `
                        <div style="font-size:0.75rem; color:${canFile ? '#f0ad4e' : '#ff8a8a'}; padding:0 0.5rem 0.3rem;">
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

                    if (canFile) {
                        rowEl.addEventListener('mouseenter', () => rowEl.style.background = 'rgba(151,190,65,0.07)');
                        rowEl.addEventListener('mouseleave', () => rowEl.style.background = 'transparent');
                    }

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

        fetch('submit_ot_request.php', {
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