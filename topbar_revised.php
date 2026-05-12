<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

$employeeId = $_SESSION['user_id'];
$role       = $_SESSION['user_role'];

// Validate role
if (!in_array($role, ['admin', 'employee', 'workforce'])) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// Page titles
$titles = [
    'employee' => [
        'dashboard' => 'Employee Dashboard',
        'records'   => 'Employee Records',
        'schedule'  => 'Schedule',
        'logs'      => 'Activity Logs',
    ],
    'workforce' => [
        'dashboard'          => 'Employee Dashboard',
        'records'            => 'Employee Records',
        'schedule'           => 'Employee Schedule',
        'logs'               => 'Employee Activity Logs',
        'workforce_schedule' => 'Manage Schedules',
        'workforce_logs'     => 'Manage Logs',
    ],
    'admin' => [
        'dashboard'         => 'Admin Dashboard',
        'manage_employees'  => 'Manage Employees',
        'employee_requests' => 'Employee Requests',
        'schedule_requests' => 'Schedule Requests',
        'employee_logs'     => 'Employee Logs',
        'departments'       => 'Departments',
    ],
];

$title = $titles[$role][$currentPage] ?? 'Dashboard';

/* =========================================================
   AUTO BREADCRUMB FROM URL
========================================================= */
$parts = array_values(array_filter(explode('/', $_SERVER['PHP_SELF'])));

$excludeSegments = [
    'localhost', 'DTR-Internship-Project', 'DTR Internship Project',
    'admin_pages', 'employee_pages', 'workforce_pages',
    'system_functions', 'dropdown_requests', 'assets', 'includes', 'db',
];

$segments = [];
foreach ($parts as $part) {
    $clean = str_replace('.php', '', $part);
    if (in_array($clean, $excludeSegments) || $clean === '' || $clean === 'index') continue;
    $segments[] = $clean;
}

$breadcrumbPath = [['label' => 'HSN DTR System']];
foreach ($segments as $index => $seg) {
    $label = ucwords(str_replace('_', ' ', $seg));
    $breadcrumbPath[] = $index === array_key_last($segments)
        ? ['label' => $label]
        : ['label' => $label, 'url' => '/' . $seg . '.php'];
}

/* =========================================================
   ATTENDANCE STATE
========================================================= */
date_default_timezone_set('Asia/Manila');

$stmt = $pdo->prepare("
    SELECT log_type FROM logs
    WHERE employee_id = ?
    ORDER BY log_time DESC
    LIMIT 1
");
$stmt->execute([$employeeId]);
$lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

$hasTimeIn  = $lastLog && $lastLog['log_type'] === 'IN';
$isOnBreak  = $lastLog && $lastLog['log_type'] === 'BREAK_IN';
$isBreakOut = $lastLog && $lastLog['log_type'] === 'BREAK_OUT';
$timedIn    = $hasTimeIn || $isOnBreak || $isBreakOut;
$breakDisabled = !$timedIn || $isBreakOut;
?>

<!-- Modal CSS — topbar-specific, not duplicated in layout_start -->
<link rel="stylesheet" href="../dropdown_requests/ot_modal.css">
<link rel="stylesheet" href="../dropdown_requests/leave_modal.css">
<link rel="stylesheet" href="../dropdown_requests/ob_modal.css">
<link rel="stylesheet" href="../dropdown_requests/log_edit_modal.css">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

<nav class="navbar topbar">
    <div class="container-fluid d-flex align-items-center justify-content-between">

        <!-- LEFT: Breadcrumb + Title -->
        <div class="d-flex flex-column">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb text-breadcrumb">
                    <?php foreach ($breadcrumbPath as $index => $crumb): ?>
                        <?php $isLast = $index === array_key_last($breadcrumbPath); ?>
                        <li class="breadcrumb-item <?= $isLast ? 'active' : '' ?>"
                            <?= $isLast ? 'aria-current="page"' : '' ?>>
                            <?php if (!$isLast && isset($crumb['url'])): ?>
                                <a href="<?= htmlspecialchars($crumb['url']) ?>">
                                    <?= htmlspecialchars($crumb['label']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($crumb['label']) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="navbar-brand mb-0 h2 text-light"><?= htmlspecialchars($title) ?></div>
        </div>

        <!-- RIGHT: Actions -->
        <div class="d-flex align-items-center gap-3">

            <!-- Time In/Out -->
            <div class="btn-group" id="attendance-btn-group">
                <?php if (!$timedIn): ?>
                    <button class="btn btn-success" onclick="openWebcamModal()">
                        <i class="bi bi-stopwatch-fill"></i> Time In
                    </button>
                <?php else: ?>
                    <button class="btn btn-danger" onclick="openWebcamModal()">
                        <i class="bi bi-stopwatch-fill"></i> Time Out
                    </button>
                    <button type="button"
                            class="btn btn-danger dropdown-toggle dropdown-toggle-split"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="outside"
                            aria-expanded="false">

                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu break-menu">
                        <li>
                            <button class="btn btn-break"
                                    id="break-action-btn"
                                    data-state="<?= $isOnBreak ? 'out' : 'in' ?>"
                                    onclick="handleBreak()"
                                    <?= $isBreakOut ? 'disabled' : '' ?>>
                                <?php if ($isOnBreak): ?>
                                    <i class="bi bi-arrow-return-right"></i> Resume Work
                                <?php else: ?>
                                    <i class="bi bi-cup-hot-fill"></i> Take Break
                                <?php endif; ?>
                            </button>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="vr"></div>

            <!-- User Dropdown -->
            <div class="dropdown">
                <button class="btn dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                    <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'User') ?>
                    
                    <div class="vr"></div>
            
                    <span class="empRole empRole-<?= $role ?>">
                        <?= ucfirst($role) ?>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="#"
                           data-bs-toggle="modal" data-bs-target="#otModal"
                           onclick="openOTModal()">
                            <i class="bi bi-clock-history"></i> Request OT
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#"
                           data-bs-toggle="modal" data-bs-target="#leaveModal"
                           onclick="openLeaveModal()">
                            <i class="bi bi-calendar-x"></i> Request Leave
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#"
                           data-bs-toggle="modal" data-bs-target="#obModal"
                           onclick="openOBModal()">
                            <i class="bi bi-briefcase"></i> Request OB
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#" onclick="openLogEditModal(); return false;">
                            <i class="bi bi-pencil-square"></i> Request Log Edit
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item logout-item" href="../authentication_pages/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</nav>

<?php include '../dropdown_requests/modal_request.php'; ?>

<!-- WEBCAM TIME IN/OUT MODAL -->
<div class="modal fade" id="webcamModal" tabindex="-1" aria-labelledby="webcamModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="webcamModalLabel">Confirm Attendance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-3">
        <div class="webcam-wrapper">
          <video id="webcamFeed" autoplay playsinline muted></video>
          <div id="webcamError" style="display:none;" class="webcam-error">
            <i class="bi bi-camera-video-off-fill"></i>
            <p>Camera unavailable</p>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="webcamConfirmBtn" onclick="confirmAttendance()">
          <i class="bi bi-check-circle-fill"></i> Confirm
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.webcam-wrapper {
    position: relative;
    width: 100%;
    border-radius: 12px;
    overflow: hidden;
    background: rgba(0, 0, 0, 0.4);
    aspect-ratio: 4 / 3;
    display: flex;
    align-items: center;
    justify-content: center;
}

#webcamFeed {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 12px;
    transform: scaleX(-1);
}

.webcam-error {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.85rem;
}

.webcam-error i {
    font-size: 2.5rem;
}

.webcam-error p {
    margin: 0;
}
</style>

<script defer>
let isProcessing      = false;
let isBreakProcessing = false;
let cachedPosition    = null;

document.addEventListener('DOMContentLoaded', () => {

    // Cross-tab attendance sync
    window.addEventListener('storage', e => {
        if (e.key !== 'attendance_update') return;
        const status   = localStorage.getItem('attendance_tap_result');
        const statusEl = document.getElementById('dashboard-status');
        if (!statusEl) return;
        statusEl.textContent = status === 'timed_in' ? 'Timed In' : 'Timed Out';
    });

    // GPS cache
    navigator.geolocation.watchPosition(
        pos => cachedPosition = pos,
        err => console.warn('GPS watch error:', err),
        { enableHighAccuracy: false, maximumAge: 60000, timeout: 10000 }
    );
});

/* -------------------------------------------------------
   HTML TEMPLATES
------------------------------------------------------- */
const timeInHTML = () => `
    <button class="btn btn-success" onclick="openWebcamModal()">
        <i class="bi bi-stopwatch-fill"></i> Time In
    </button>`;

const timeOutHTML = () => `
    <button class="btn btn-danger" onclick="openWebcamModal()">
        <i class="bi bi-stopwatch-fill"></i> Time Out
    </button>
    <button type="button"
            class="btn btn-danger dropdown-toggle dropdown-toggle-split"
            data-bs-toggle="dropdown"
            aria-expanded="false">
        <span class="visually-hidden">Toggle Dropdown</span>
    </button>
    <ul class="dropdown-menu break-menu">
        <li>
            <button class="btn btn-break"
                    id="break-action-btn"
                    data-state="in"
                    onclick="handleBreak()">
                <i class="bi bi-cup-hot-fill"></i> Take Break
            </button>
        </li>
    </ul>`;

const spinnerCooldownHTML = secs => `
    <button
        class="btn btn-spinner-circle position-relative"
        disabled>

        <span
            class="spinner-border spinner-border-sm"
            role="status"
            aria-hidden="true">
        </span>

        <span
            id="cooldown-text"
            class="cooldown-count position-absolute top-50 start-50 translate-middle">
            ${secs}
        </span>

    </button>
`;

/* -------------------------------------------------------
   UI HELPERS
------------------------------------------------------- */
const grp = () => document.getElementById('attendance-btn-group');

const showSpinner = (html = spinnerCooldownHTML('')) => {
    const g = grp();
    g.style.width    = g.offsetWidth + 'px';
    g.style.overflow = 'hidden';
    void g.offsetWidth;
    g.innerHTML = html;
    g.classList.add('is-loading');
};

const clearSpinner = html => {
    const g = grp();
    g.innerHTML = html;
    g.classList.remove('is-loading');
    // style.width is still the locked px value — CSS transition animates 42px → locked width
    g.addEventListener('transitionend', () => {
        g.style.width    = '';
        g.style.overflow = '';
    }, { once: true });
};

// Break button helpers
const setLoading = btn => {
    if (!btn) return;
    btn.disabled = true;
    btn.dataset.originalHtml = btn.innerHTML;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>`;
};

const restoreButton = btn => {
    if (!btn) return;
    btn.disabled = false;
    if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
};

const lockBreakBtn = btn => {
    if (!btn) return;
    btn.disabled = true;
    new MutationObserver(() => { if (!btn.disabled) btn.disabled = true; })
        .observe(btn, { attributes: true, attributeFilter: ['disabled'] });
};

/* -------------------------------------------------------
   TIME IN / OUT
------------------------------------------------------- */
const handleTimeIn = async () => {
    if (isProcessing) return;

    const statusEl     = document.getElementById('dashboard-status');
    const originalHTML = grp().innerHTML;
    isProcessing       = true;

    showSpinner();

    const reset   = () => { clearSpinner(originalHTML); isProcessing = false; };
    const onError = () => { alert('Location permission required.'); reset(); };

    const submit = async pos => {
        try {
            const payload = {
                lat:      pos.coords.latitude,
                lng:      pos.coords.longitude,
                accuracy: pos.coords.accuracy
            };
            if (capturedPhotoB64) {
                payload.photo    = capturedPhotoB64;
                capturedPhotoB64 = null;
            }
            const res = await fetch('../system_functions/attendance_tap.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload)
            });

            const data = await res.json();

            if (data.error === 'too_fast') {

                let remaining = data.seconds_remaining;

                showSpinner(
                    spinnerCooldownHTML(remaining)
                );

                const tick = () => {

                    if (remaining <= 0) {
                        clearSpinner(originalHTML);
                        isProcessing = false;
                        return;
                    }

                    remaining--;

                    const el =
                        document.getElementById(
                            'cooldown-text'
                        );

                    if (el) {
                        el.textContent = remaining;
                    }

                    setTimeout(tick, 1000);
                };

                setTimeout(tick, 1000);

                return;
            }

            if (data.error === 'shift_ended') {
                alert('Shift ended. You are marked absent.');
                reset();
                return;
            }

            if (data.tap === 'timed_in') {
                clearSpinner(timeOutHTML());
                if (statusEl) statusEl.textContent = 'Timed In';
            } else if (data.tap === 'timed_out') {
                clearSpinner(timeInHTML());
                if (statusEl) statusEl.textContent = 'Timed Out';
            }

            localStorage.setItem('attendance_tap_result', data.tap);
            localStorage.setItem('attendance_update', Date.now());
            document.dispatchEvent(new CustomEvent('attendance_tapped'));

        } catch (err) {
            console.error(err);
            reset();
        } finally {
            isProcessing = false;
        }
    };

    cachedPosition
        ? submit(cachedPosition)
        : navigator.geolocation.getCurrentPosition(submit, onError);
};

/* -------------------------------------------------------
   BREAK HANDLER
------------------------------------------------------- */
const handleBreak = async () => {
    if (isBreakProcessing) return;

    const btn = document.getElementById('break-action-btn');
    if (!btn) return;

    isBreakProcessing = true;
    const originalGroupHTML = grp().innerHTML;
    setLoading(btn);

    const reset   = () => { restoreButton(btn); isBreakProcessing = false; };
    const onError = () => { alert('Location permission required.'); reset(); };

    const submit = async pos => {
        try {
            const res = await fetch('../system_functions/attendance_tap.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    lat:       pos.coords.latitude,
                    lng:       pos.coords.longitude,
                    accuracy:  pos.coords.accuracy,
                    break_tap: true
                })
            });

            const data = await res.json();

            if (data.error === 'too_fast') {
                let remaining = data.seconds_remaining;
                showSpinner(spinnerCooldownHTML(remaining));

                const tick = () => {
                    if (remaining <= 0) {
                        clearSpinner(originalGroupHTML);
                        isBreakProcessing = false;
                        return;
                    }
                    remaining--;
                    const el = document.getElementById('cooldown-text');
                    if (el) el.textContent = remaining;
                    setTimeout(tick, 1000);
                };
                setTimeout(tick, 1000);
                return;
            }

            if (data.tap === 'break_in') {
                btn.disabled      = false;
                btn.dataset.state = 'out';
                btn.innerHTML     = `<i class="bi bi-arrow-return-right"></i> Resume Work`;
                document.dispatchEvent(new CustomEvent('attendance_tapped'));
            }

            if (data.tap === 'break_out') {
                btn.dataset.state = 'in';
                btn.innerHTML     = `<i class="bi bi-cup-hot-fill"></i> Take Break`;
                lockBreakBtn(btn);
                document.dispatchEvent(new CustomEvent('attendance_tapped'));
            }

            if (data.error === 'not_timed_in') {
                btn.innerHTML = btn.dataset.originalHtml || `<i class="bi bi-cup-hot-fill"></i> Take Break`;
                lockBreakBtn(btn);
            }

        } catch (err) {
            console.error(err);
            reset();
        } finally {
            isBreakProcessing = false;
        }
    };

    cachedPosition
        ? submit(cachedPosition)
        : navigator.geolocation.getCurrentPosition(submit, onError);
};

window.handleTimeIn = handleTimeIn;
window.handleBreak  = handleBreak;

/* -------------------------------------------------------
   WEBCAM MODAL
------------------------------------------------------- */
let webcamStream      = null;
let webcamModalInst   = null;
let capturedPhotoB64  = null;

document.addEventListener('DOMContentLoaded', () => {
    webcamModalInst = new bootstrap.Modal(document.getElementById('webcamModal'));

    document.getElementById('webcamModal').addEventListener('hidden.bs.modal', () => {
        if (webcamStream) {
            webcamStream.getTracks().forEach(t => t.stop());
            webcamStream = null;
        }
        const video = document.getElementById('webcamFeed');
        if (video) video.srcObject = null;
    });
});

const openWebcamModal = () => {
    const video      = document.getElementById('webcamFeed');
    const errorEl    = document.getElementById('webcamError');
    const confirmBtn = document.getElementById('webcamConfirmBtn');

    video.style.display   = 'block';
    errorEl.style.display = 'none';
    confirmBtn.disabled   = false;

    webcamModalInst.show();

    navigator.mediaDevices.getUserMedia({ video: true, audio: false })
        .then(stream => {
            webcamStream    = stream;
            video.srcObject = stream;
        })
        .catch(() => {
            video.style.display   = 'none';
            errorEl.style.display = 'block';
        });
};

const confirmAttendance = () => {
    const video = document.getElementById('webcamFeed');
    if (video && video.readyState >= 2) {
        const canvas = document.createElement('canvas');
        canvas.width  = video.videoWidth  || 640;
        canvas.height = video.videoHeight || 480;
        // un-mirror for the saved file
        const ctx = canvas.getContext('2d');
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0);
        capturedPhotoB64 = canvas.toDataURL('image/jpeg', 0.85);
    }

    if (webcamStream) {
        webcamStream.getTracks().forEach(t => t.stop());
        webcamStream = null;
    }
    webcamModalInst.hide();
    handleTimeIn();
};

window.openWebcamModal   = openWebcamModal;
window.confirmAttendance = confirmAttendance;
</script>