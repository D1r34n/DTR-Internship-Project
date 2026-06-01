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

$stmt = $pdo->prepare("
    SELECT first_name, last_name, email, profile_image
    FROM employees
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

$role       = $_SESSION['user_role'];

// Validate role
if (!in_array($role, ['superadmin', 'manager', 'admin', 'workforce', 'employee'])) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// Page titles
$titles = [
    'employee' => [
        'dashboard' => 'Employee Dashboard',
        'records'   => 'Employee Records',
        'schedule'  => 'Employee Schedule',
        'logs'      => 'Activity Logs',
    ],
    'superadmin' => [
        'dashboard'         => 'Super Admin Dashboard',
        'records'           => 'Super Admin Records',
        'schedule'          => 'Super Admin Schedule',
        'logs'              => 'Activity Logs',
        'manage_employees'  => 'Manage Employees',
        'employee_requests' => 'Employee Requests',
        'schedule_requests' => 'Schedule Requests',
        'departments'       => 'Departments',
        'attendance_report' => 'Attendance Report',
        'filing_report'     => 'Filing Report',
        'leave_report'      => 'Leave Taken Report',
        'leave_summary'     => 'Leave Summary Report'
    ],
];

$title = $titles[$role][$currentPage ?? 'dashboard'] ?? 'Dashboard';

/* =========================================================
   AUTO BREADCRUMB FROM URL
========================================================= */
$parts = array_values(array_filter(explode('/', $_SERVER['PHP_SELF'])));

$excludeSegments = [
    'localhost', 'DTR-Internship-Project', 'DTR Internship Project',
    'regular_pages',
    'system_functions', 'dropdown_requests', 'assets', 'includes', 'db',
];

$segments = [];
foreach ($parts as $part) {
    $clean = str_replace('.php', '', $part);
    if (in_array($clean, $excludeSegments) || $clean === '' || $clean === 'index') continue;
    $segments[] = $clean;
}

$breadcrumbLabels = [
    'dashboard_page'     => 'Dashboard',
    'records_page'       => 'Records',
    'management_pages'   => 'Management',
    'employee_schedule'  => 'Schedule',
    'reports_pages'      => 'Reports'
];

$breadcrumbPath = [['label' => 'HSN DTR System']];
foreach ($segments as $index => $seg) {
    $label = $breadcrumbLabels[$seg] ?? ucwords(str_replace('_', ' ', $seg));
    $breadcrumbPath[] = $index === array_key_last($segments)
        ? ['label' => $label]
        : ['label' => $label];
}

/* =========================================================
   ATTENDANCE STATE
========================================================= */
date_default_timezone_set('Asia/Manila');

$stmt = $pdo->prepare("
    SELECT log_type FROM logs
    WHERE employee_id = ?
      AND log_type IN ('IN', 'OUT', 'BREAK_IN', 'BREAK_OUT')
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

                <button
                    class="btn dropdown-toggle d-flex align-items-center gap-2"
                    type="button"
                    data-bs-toggle="dropdown"
                    data-bs-auto-close="outside"
                    aria-expanded="false">

                    <!-- PROFILE IMAGE -->
                    <img 
                        src="../assets/user_profiles/<?= htmlspecialchars($_SESSION['profile_image'] ?? 'default_profile.png') ?>"
                        class="topbar-profile-image"
                    >

                    <!-- USER NAME -->
                    <span>
                        <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'User') ?>
                    </span>

                    <div class="vr"></div>

                    <!-- ROLE -->
                    <span class="empRole empRole-<?= $role ?>">
                        <?= ucfirst($role) ?>
                    </span>

                </button>

                <!-- DROPDOWN MENU -->
                <ul class="dropdown-menu dropdown-menu-end">

                    <li>
                        <a class="dropdown-item"
                        href="#"
                        data-bs-toggle="modal"
                        data-bs-target="#otModal"
                        onclick="openOTModal()">

                            <i class="bi bi-clock-history"></i>
                            Request OT

                        </a>
                    </li>

                    <li>
                        <a class="dropdown-item"
                        href="#"
                        data-bs-toggle="modal"
                        data-bs-target="#leaveModal"
                        onclick="openLeaveModal()">

                            <i class="bi bi-calendar-x"></i>
                            Request Leave

                        </a>
                    </li>

                    <li>
                        <a class="dropdown-item"
                        href="#"
                        data-bs-toggle="modal"
                        data-bs-target="#obModal"
                        onclick="openOBModal()">

                            <i class="bi bi-briefcase"></i>
                            Request OB

                        </a>
                    </li>

                    <li>
                        <a class="dropdown-item"
                        href="#"
                        onclick="openLogEditModal(); return false;">
                            <i class="bi bi-pencil-square"></i>
                            Request Log Edit
                        </a>
                    </li>

                    <li><hr class="dropdown-divider"></li>

                    <li>
                        <button class="dropdown-item"
                        href="#"
                        data-bs-toggle="modal"
                        data-bs-target="#editProfileImageModal">
                            <i class="bi bi-person"></i>
                            View Profile
                        </button>
                    </li>

                    <li>
                        <div class="dropdown-item d-flex align-items-center justify-content-between w-100 gap-3" id="theme-toggle-container">
                            <div class="d-flex align-items-center gap-2">
                                <i id="theme-icon" class="bi bi-moon-stars-fill" style="font-size:0.85rem;color:var(--text-light)"></i>
                                <span id="theme-label" class="text-secondary">Dark Mode</span>
                            </div>
                            <div class="theme-pill-wrapper">
                                <input type="checkbox" id="themeCheckbox" hidden onchange="toggleTheme(this.checked)">
                                <label class="theme-pill-track" for="themeCheckbox">
                                    <span class="theme-pill-knob"></span>
                                    <i class="bi bi-sun-fill theme-icon-sun"></i>
                                    <i class="bi bi-moon-fill theme-icon-moon"></i>
                                </label>
                            </div>
                        </div>
                    </li>

                    <li><hr class="dropdown-divider"></li>

                    <li>
                        <a class="dropdown-item text-danger logout-item"
                        href="../authentication_pages/logout.php">
                            <i class="bi bi-box-arrow-right"></i>
                            Logout
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

<!-- EDIT PROFILE IMAGE MODAL -->
<div class="modal fade"
     id="editProfileImageModal"
     tabindex="-1"
     aria-labelledby="editProfileImageModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileImageModalLabel">
                    Edit Profile Image
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body p-3">

                <!-- Current preview (always visible) -->
                <div class="d-flex justify-content-center mb-3">
                    <div class="profile-preview-ring">
                        <img
                            id="profileImagePreview"
                            src="../assets/user_profiles/<?= htmlspecialchars($_SESSION['profile_image'] ?? 'default_profile.png') ?>"
                            alt="Profile Preview"
                            class="profile-image-preview"
                        >
                        <video id="profileWebcam" autoplay playsinline
                            class="profile-image-preview"
                            style="display:none; transform:scaleX(-1);">
                        </video>
                    </div>
                </div>

                <canvas id="profileCanvas" style="display:none;"></canvas>

                <!-- TWO OPTION CARDS -->
                <div class="row g-3" id="profileOptionCards">

                    <!-- UPLOAD CARD -->
                    <div class="col-6">
                        <label class="profile-option-card <?= 'active' ?>" id="uploadOptionCard"
                            onclick="switchProfileTab('upload')" style="cursor:pointer;">
                            <div class="profile-option-card-icon">
                                <i class="bi bi-cloud-arrow-up-fill"></i>
                            </div>
                            <div class="profile-option-card-title">Upload File</div>
                            <div class="profile-option-card-sub">JPG, PNG, WEBP</div>
                        </label>
                    </div>

                    <!-- CAMERA CARD -->
                    <div class="col-6">
                        <label class="profile-option-card" id="cameraOptionCard"
                            onclick="switchProfileTab('camera')" style="cursor:pointer;">
                            <div class="profile-option-card-icon">
                                <i class="bi bi-camera-fill"></i>
                            </div>
                            <div class="profile-option-card-title">Take Photo</div>
                            <div class="profile-option-card-sub">Use your camera</div>
                        </label>
                    </div>

                </div>

                <!-- UPLOAD SECTION -->
                <div id="profileUploadSection" class="mt-3">
                    <input type="file" id="profileImageInput" accept="image/*" class="form-control">
                </div>

                <!-- CAMERA SECTION -->
                <div id="profileCameraSection" style="display:none;" class="mt-3">
                    <button type="button" class="btn btn-info w-100"
                            id="profileCameraActionBtn" onclick="handleCameraAction()">
                        <i class="bi bi-camera-fill"></i> Capture
                    </button>
                    <div id="profileCameraError" style="display:none;" class="webcam-error mt-3">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <p>Camera not available</p>
                    </div>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="saveProfileImageBtn"
                        onclick="saveProfileImage()">
                    <i class="bi bi-check-circle-fill"></i> Save Image
                </button>
            </div>

        </div>
    </div>
</div>
<?php include '../toast.php'; ?>
<script defer>
let isProcessing      = false;
let isBreakProcessing = false;
let cachedPosition    = null;

const GEO_OPTS = { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 };

function geoErrorMessage(err) {
    if (!err) return 'Unable to get your location. Please try again.';
    switch (err.code) {
        case 1: return 'Location permission denied. Please allow location access in your browser settings and try again.';
        case 2: return 'Location unavailable. Please check your device GPS or network settings.';
        case 3: return 'Location request timed out. Please try again.';
        default: return 'Unable to get your location. Please try again.';
    }
}

document.addEventListener('DOMContentLoaded', () => {

    // Cross-tab attendance sync
    window.addEventListener('storage', e => {
        if (e.key !== 'attendance_update') return;
        const status   = localStorage.getItem('attendance_tap_result');
        const statusEl = document.getElementById('dashboard-status');
        if (!statusEl) return;
        statusEl.textContent = status === 'timed_in' ? 'Timed In' : 'Timed Out';
    });

    // GPS cache — warm up position so Time In/Out is instant
    // Only needed for roles that clock in; skip for admin/superadmin to avoid
    // a console error when they have location permission denied.
    const needsGeo = <?= in_array($role, ['employee', 'workforce', 'manager']) ? 'true' : 'false' ?>;
    if (needsGeo && navigator.geolocation) {
        navigator.geolocation.watchPosition(
            pos => { cachedPosition = pos; },
            err => console.warn('GPS watch error:', err.message),
            { enableHighAccuracy: false, maximumAge: 60000, timeout: 15000 }
        );
    }
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
    const onError = err => { alert(geoErrorMessage(err)); reset(); };

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
                alert('Shift has already ended. You cannot time in for this shift anymore.');
                reset();
                return;
            }

            if (data.error === 'log_corrupted') {
                alert(data.message || 'Your attendance log is corrupted. Please contact your administrator.');
                reset();
                return;
            }

            if (data.error === 'server_error' || data.error === 'unauthorized' || data.error === 'missing_location') {
                alert('Something went wrong. Please try again.');
                reset();
                return;
            }

            if (data.tap === 'timed_in') {
                clearSpinner(timeOutHTML());
                if (statusEl) statusEl.textContent = 'Timed In';
            } else if (data.tap === 'timed_out') {
                clearSpinner(timeInHTML());
                if (statusEl) statusEl.textContent = 'Timed Out';
            } else {
                reset();
                return;
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

    if (!navigator.geolocation) { onError(null); return; }
    cachedPosition
        ? submit(cachedPosition)
        : navigator.geolocation.getCurrentPosition(submit, onError, GEO_OPTS);
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
    const onError = err => { alert(geoErrorMessage(err)); reset(); };

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

    if (!navigator.geolocation) { onError(null); return; }
    cachedPosition
        ? submit(cachedPosition)
        : navigator.geolocation.getCurrentPosition(submit, onError, GEO_OPTS);
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
    confirmBtn.disabled   = true;  // stay disabled until camera is producing frames

    webcamModalInst.show();

    navigator.mediaDevices.getUserMedia({ video: true, audio: false })
        .then(stream => {
            webcamStream    = stream;
            video.srcObject = stream;
            video.addEventListener('playing', () => {
                confirmBtn.disabled = false;
            }, { once: true });
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

/* -------------------------------------------------------
   EDIT PROFILE MODAL
------------------------------------------------------- */
let profileWebcamStream  = null;
let profileCapturedBlob  = null;
let currentProfileImgSrc = '../assets/user_profiles/<?= htmlspecialchars($_SESSION['profile_image'] ?? 'default_profile.png') ?>';

document.getElementById('editProfileImageModal').addEventListener('hidden.bs.modal', () => {
    stopProfileWebcam();
    switchProfileTab('upload');
    profileCapturedBlob = null;
    document.getElementById('profileImageInput').value = '';
    document.getElementById('profileImagePreview').src = currentProfileImgSrc + '?t=' + Date.now();
});

function switchProfileTab(tab) {
    const isUpload = tab === 'upload';

    document.getElementById('profileUploadSection').style.display = isUpload ? '' : 'none';
    document.getElementById('profileCameraSection').style.display = isUpload ? 'none' : '';

    document.getElementById('uploadOptionCard').classList.toggle('active', isUpload);
    document.getElementById('cameraOptionCard').classList.toggle('active', !isUpload);

    if (isUpload) {
        stopProfileWebcam();
    } else {
        profileCapturedBlob = null;
        setCameraBtn('ready');   // reset button state when switching to camera
        startProfileWebcam();
    }
}

// Single action button — toggles between Capture and Retake
function handleCameraAction() {
    const btn = document.getElementById('profileCameraActionBtn');
    const isCaptured = btn.dataset.state === 'captured';

    if (isCaptured) {
        retakeProfilePhoto();
    } else {
        captureProfilePhoto();
    }
}

function setCameraBtn(state) {
    const btn = document.getElementById('profileCameraActionBtn');
    if (!btn) return;
    btn.dataset.state = state;

    if (state === 'captured') {
        btn.className = 'btn btn-warning w-100';
        btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Retake';
    } else {
        btn.className = 'btn btn-info w-100';
        btn.innerHTML = '<i class="bi bi-camera-fill"></i> Capture';
    }
}

function startProfileWebcam() {
    const video   = document.getElementById('profileWebcam');
    const preview = document.getElementById('profileImagePreview');
    const errEl   = document.getElementById('profileCameraError');

    preview.style.display = 'none';
    video.style.display   = '';
    errEl.style.display   = 'none';

    setCameraBtn('ready');

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })
        .then(stream => {
            profileWebcamStream = stream;
            video.srcObject     = stream;
        })
        .catch(() => {
            video.style.display   = 'none';
            preview.style.display = '';
            errEl.style.display   = 'flex';
        });
}

function stopProfileWebcam() {
    if (profileWebcamStream) {
        profileWebcamStream.getTracks().forEach(t => t.stop());
        profileWebcamStream = null;
    }
    const video   = document.getElementById('profileWebcam');
    const preview = document.getElementById('profileImagePreview');
    if (video)   { video.srcObject = null; video.style.display = 'none'; }
    if (preview) preview.style.display = '';
}

function captureProfilePhoto() {
    const video   = document.getElementById('profileWebcam');
    const canvas  = document.getElementById('profileCanvas');
    const preview = document.getElementById('profileImagePreview');

    const vw   = video.videoWidth;
    const vh   = video.videoHeight;
    const size = Math.min(vw, vh);
    const sx   = (vw - size) / 2;
    const sy   = (vh - size) / 2;

    canvas.width  = size;
    canvas.height = size;

    const ctx = canvas.getContext('2d');
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.translate(size, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, sx, sy, size, size, 0, 0, size, size);

    canvas.toBlob(blob => {
        profileCapturedBlob   = blob;
        preview.src           = URL.createObjectURL(blob);
        video.style.display   = 'none';
        preview.style.display = '';

        setCameraBtn('captured');
        stopProfileWebcam();
    }, 'image/png');
}

function retakeProfilePhoto() {
    profileCapturedBlob = null;
    startProfileWebcam();
}

document.getElementById('profileImageInput').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    profileCapturedBlob = null;
    document.getElementById('profileImagePreview').src = URL.createObjectURL(file);
});

function updateProfileImageUI(src) {
    const cacheBusted = src + '?t=' + Date.now();
    document.querySelectorAll('.topbar-profile-image, #profileImagePreview')
        .forEach(img => img.src = cacheBusted);
    currentProfileImgSrc = src;
}

/* -------------------------------------------------------
   THEME TOGGLE
------------------------------------------------------- */
function toggleTheme(isDark) {
    document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');

    const icon  = document.getElementById('theme-icon');
    const label = document.getElementById('theme-label');

    if (isDark) {
        icon.className  = 'bi bi-moon-stars-fill';
        label.textContent = 'Dark Mode';
    } else {
        icon.className  = 'bi bi-sun-fill';
        label.textContent = 'Light Mode';
    }
}

(function initTheme() {
    const saved       = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isDark      = saved ? (saved === 'dark') : prefersDark;

    document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');

    document.addEventListener('DOMContentLoaded', () => {
        const cb = document.getElementById('themeCheckbox');
        if (cb) cb.checked = isDark;
        toggleTheme(isDark);
    });
})();

function saveProfileImage() {
    const input      = document.getElementById('profileImageInput');
    const hasFile    = input.files.length > 0;
    const hasCapture = !!profileCapturedBlob;

    if (!hasFile && !hasCapture) {
        showToast('Please select an image or take a photo.', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('profile_image', hasCapture ? profileCapturedBlob : input.files[0],
                    hasCapture ? 'capture.png' : input.files[0].name);

    const btn = document.getElementById('saveProfileImageBtn');
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

    fetch('../system_functions/upload_profile_image.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Save Image';
            if (data.success) {
                updateProfileImageUI('../assets/user_profiles/' + data.filename);
                bootstrap.Modal.getInstance(document.getElementById('editProfileImageModal')).hide();
                
                showToast('Profile image updated.', 'success');
            } else {
                showToast(data.error || 'Upload failed.', 'danger');
            }
        })
        .catch(() => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Save Image';
            showToast('Upload error. Please try again.', 'danger');
        });
}

</script>