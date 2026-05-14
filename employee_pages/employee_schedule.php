<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

// ── Handle save-event POST ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit(); }

    $data        = json_decode(file_get_contents('php://input'), true);
    $title       = trim($data['title']       ?? '');
    $eventType   = $data['event_type']       ?? 'other';
    $startDate   = $data['start_date']       ?? '';
    $endDate     = $data['end_date']         ?? null;
    $description = trim($data['description'] ?? '');

    if (!$title || !$startDate) {
        http_response_code(400);
        echo json_encode(['error' => 'Title and start date are required.']);
        exit();
    }

    $validTypes = ['holiday', 'party', 'meeting', 'announcement', 'other'];
    if (!in_array($eventType, $validTypes)) $eventType = 'other';

    $colors = [
        'holiday' => '#ef4444', 'party' => '#ec4899',
        'meeting' => '#3b82f6', 'announcement' => '#f59e0b', 'other' => '#6b7280',
    ];

    $stmt = $pdo->prepare("
        INSERT INTO events (title, description, event_type, start_datetime, end_datetime, color, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$title, $description ?: null, $eventType, $startDate, $endDate ?: null, $colors[$eventType], $_SESSION['user_id']]);
    echo json_encode(['success' => true, 'id' => (int) $pdo->lastInsertId()]);
    exit();
}

// ── Fetch calendar events (all users) ────────────────────
$calEvents = [];
foreach ($pdo->query("SELECT id, title, event_type, start_datetime, end_datetime, color FROM events ORDER BY start_datetime ASC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $calEvents[] = [
        'id'            => $r['id'],
        'title'         => $r['title'],
        'start'         => $r['start_datetime'],
        'end'           => $r['end_datetime'] ?: null,
        'allDay'        => true,
        'color'         => $r['color'] ?: '#0d6efd',
        'textColor'     => '#fff',
        'extendedProps' => ['shift_type' => 'cal_event', 'event_type' => $r['event_type']],
    ];
}

// ── Birthdays (admin only) ────────────────────────────────
$birthdayEvents = [];
if ($isAdmin) {
    $curYear = (int) date('Y');
    foreach ($pdo->query("SELECT CONCAT(first_name,' ',last_name) AS full_name, birthdate FROM employees WHERE birthdate IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $md = date('m-d', strtotime($r['birthdate']));
        foreach (range($curYear - 1, $curYear + 2) as $yr) {
            $birthdayEvents[] = ['title' => $r['full_name'], 'start' => "$yr-$md", 'allDay' => true, 'color' => '#8b5cf6', 'textColor' => '#fff', 'extendedProps' => ['shift_type' => 'birthday']];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Schedule</title>

    <!-- 1. Third-party CSS FIRST -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Custom Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

    <!-- 2. Your global CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <!-- 3. Page-specific CSS -->
    <link rel="stylesheet" href="employee_schedule.css">
</head>
<body>
    <?php $currentPage = 'schedule'; include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">
        <?php include '../topbar_revised.php'; ?>

        <div class="card card-neutral schedules-card">
            <div class="card-body d-flex flex-column schedules-card-body">

                <!-- SHIFT LEGEND -->
                <div class="shiftLegend">
                    <?php if (!$isAdmin): ?>
                    <div class="shiftLegendItem">
                        <div class="shiftLegendDot day"></div>
                        Day Shift
                    </div>
                    <div class="shiftLegendItem">
                        <div class="shiftLegendDot night"></div>
                        Night Shift
                    </div>
                    <div class="shiftLegendItem">
                        <div class="shiftLegendDot night-cont"></div>
                        Night Shift (cont.)
                    </div>
                    <div class="shiftLegendItem">
                        <div class="shiftLegendDot rest"></div>
                        Rest Day
                    </div>
                    <div class="shiftLegendItem">
                        <div class="shiftLegendDot leave-approved"></div>
                        On Leave
                    </div>
                    <div class="shiftLegendItem">
                        <div class="shiftLegendDot ob-approved"></div>
                        On OB
                    </div>
                    <div class="shiftLegendItem">
                        <div class="shiftLegendDot leave-pending"></div>
                        Leave/OB Pending
                    </div>
                    <div class="shiftLegendItem">
                        <div class="shiftLegendDot leave-rejected"></div>
                        Leave/OB Rejected
                    </div>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                    <select id="scheduleFilter" class="schedule-filter-select">
                        <option value="all">All</option>
                        <option value="events">All Events</option>
                        <option value="leave">On Leave</option>
                        <option value="ob">On OB</option>
                        <option value="birthday">Birthday</option>
                        <option value="holiday">Holiday</option>
                        <option value="meeting">Meeting</option>
                        <option value="announcement">Announcement</option>
                        <option value="party">Party</option>
                        <option value="other">Other</option>
                    </select>
                    <button class="btn btn-sm btn-add-event ms-auto"
                            data-bs-toggle="modal"
                            data-bs-target="#addEventModal">
                        <i class="bi bi-plus-lg"></i> Add Event
                    </button>
                    <?php endif; ?>
                </div>

                <!-- CALENDAR -->
                <div id="calendar-wrapper">
                    <div id="calendar-loading">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>
</div><!-- #main-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const calendarEl = document.getElementById('calendar');
            let isRefreshing  = false;
            let currentFilter = 'all';

            function matchesFilter(el) {
                if (currentFilter === 'all') return true;
                const st = el.dataset.st;
                const et = el.dataset.et;
                if (currentFilter === 'leave')   return st && st.startsWith('leave_');
                if (currentFilter === 'ob')      return st && st.startsWith('ob_');
                if (currentFilter === 'birthday') return st === 'birthday';
                if (currentFilter === 'events')  return st === 'cal_event';
                return st === 'cal_event' && et === currentFilter;
            }

            function applyFilter(el) {
                el.style.display = matchesFilter(el) ? '' : 'none';
            }

            const filterEl = document.getElementById('scheduleFilter');
            if (filterEl) {
                // Pre-select filter from URL param
                const urlFilter = new URLSearchParams(window.location.search).get('filter');
                if (urlFilter && filterEl.querySelector(`option[value="${urlFilter}"]`)) {
                    filterEl.value = urlFilter;
                    currentFilter  = urlFilter;
                }

                filterEl.addEventListener('change', function () {
                    currentFilter = this.value;
                    document.querySelectorAll('#calendar .fc-event').forEach(applyFilter);
                });
            }

            function formatDateLabel(date) {
                const options = { year: 'numeric', month: 'short', day: 'numeric' };
                return new Date(date).toLocaleDateString(undefined, options);
            }

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',

                customButtons: {
                    refresh: {
                        text: '',
                        click: function () {
                            isRefreshing = true;
                            calendar.refetchEvents();
                        }
                    },
                },

                loading: function (isLoading) {
                    if (!isRefreshing) return;
                    const overlay = document.getElementById('calendar-loading');
                    if (isLoading) {
                        if (overlay) overlay.classList.add('show');
                    } else {
                        if (overlay) overlay.classList.remove('show');
                        isRefreshing = false;
                    }
                },

                headerToolbar: {
                    left:   'refresh today',
                    right:  'prev title next'
                },

                eventSources: [
                    {
                        url:     '../get_schedule.php',
                        method:  'GET',
                        failure: function () { console.error('Failed to fetch schedule.'); }
                    },
                    <?= json_encode($calEvents) ?>,
                    <?php if ($isAdmin): ?>
                    <?= json_encode($birthdayEvents) ?>,
                    <?php endif; ?>
                ],

                // ---- Style each event based on shift type ----
                eventDidMount: function(info) {
                    const shiftType = info.event.extendedProps.shift_type;
                    info.el.dataset.st = shiftType || '';
                    info.el.dataset.et = info.event.extendedProps.event_type || '';
                    applyFilter(info.el);

                    if (shiftType === 'night_continuation') {
                        info.el.style.border          = '2px dashed #4da3ff';
                        info.el.style.backgroundColor = 'rgba(77, 163, 255, 0.15)';
                        info.el.style.color           = '#4da3ff';
                        info.el.style.borderRadius    = '4px';
                    }

                    if (shiftType === 'birthday') {
                        const titleEl = info.el.querySelector('.fc-event-title');
                        if (titleEl) {
                            titleEl.innerHTML = '<i class="bi bi-cake"></i> ' + info.event.title;
                        }
                    }

                    if (shiftType === 'cal_event') {
                        const iconMap = {
                            holiday:      'bi-umbrella-fill',
                            party:        'bi-balloon-fill',
                            meeting:      'bi-people-fill',
                            announcement: 'bi-megaphone-fill',
                            other:        'bi-pin-fill',
                        };
                        const titleEl = info.el.querySelector('.fc-event-title');
                        if (titleEl) {
                            const icon = iconMap[info.event.extendedProps.event_type] || 'bi-pin-fill';
                            titleEl.innerHTML = '<i class="bi ' + icon + '"></i> ' + info.event.title;
                        }
                    }
                },

                eventDisplay: 'block',
                dayMaxEvents: false,
                height:       '100%',
            });

            calendar.render();

            // Custom refresh button on toolbar
            const refreshBtn = calendarEl.querySelector('.fc-refresh-button');
            if (refreshBtn) refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';

            // Resize calendar when sidebar expands
            function debounce(fn, delay = 100) {
                let t;
                return (...args) => {
                    clearTimeout(t);
                    t = setTimeout(() => fn(...args), delay);
                };
            }

            const resizeCalendar = debounce(() => {
                calendar.updateSize();
            }, 150);

            // Watch layout changes (sidebar expand/collapse)
            window.addEventListener('resize', resizeCalendar);

            const sidebar = document.getElementById('sidebar');

            const observer = new ResizeObserver(() => {
                calendar.updateSize();
            });

            observer.observe(sidebar);
        });
    </script>

    <?php if ($isAdmin): ?>
    <!-- ── Add Event Modal ──────────────────────────────────── -->
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEventModalLabel">
                        <i class="bi bi-calendar-plus me-2"></i>Add Event
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="addEventError" class="alert alert-danger d-none"></div>

                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" id="evtTitle" class="form-control" placeholder="Event title">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Event Type</label>
                        <select id="evtType" class="form-select">
                            <option value="holiday">Holiday</option>
                            <option value="party">Party</option>
                            <option value="meeting">Meeting</option>
                            <option value="announcement">Announcement</option>
                            <option value="other" selected>Other</option>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="text" id="evtStartDate" class="form-control" placeholder="Select date">
                        </div>
                        <div class="col">
                            <label class="form-label">End Date <small class="text-muted">(optional)</small></label>
                            <input type="text" id="evtEndDate" class="form-control" placeholder="Select date">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description <small class="text-muted">(optional)</small></label>
                        <textarea id="evtDescription" class="form-control" rows="2" placeholder="Short description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEventBtn">
                        <i class="bi bi-check-lg me-1"></i>Save Event
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        let fpStart, fpEnd;

        document.addEventListener('DOMContentLoaded', function () {
            fpStart = flatpickr('#evtStartDate', { dateFormat: 'Y-m-d' });
            fpEnd   = flatpickr('#evtEndDate',   { dateFormat: 'Y-m-d' });

            // Clear form when modal opens
            document.getElementById('addEventModal').addEventListener('show.bs.modal', function () {
                document.getElementById('evtTitle').value       = '';
                document.getElementById('evtType').value        = 'other';
                document.getElementById('evtDescription').value = '';
                document.getElementById('addEventError').classList.add('d-none');
                fpStart.clear();
                fpEnd.clear();
            });

            document.getElementById('saveEventBtn').addEventListener('click', async function () {
                const btn   = this;
                const title = document.getElementById('evtTitle').value.trim();
                const start = document.getElementById('evtStartDate').value.trim();
                const errEl = document.getElementById('addEventError');

                errEl.classList.add('d-none');

                if (!title || !start) {
                    errEl.textContent = 'Title and start date are required.';
                    errEl.classList.remove('d-none');
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

                try {
                    const res  = await fetch(location.pathname, {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            title:       title,
                            event_type:  document.getElementById('evtType').value,
                            start_date:  start,
                            end_date:    document.getElementById('evtEndDate').value || null,
                            description: document.getElementById('evtDescription').value.trim(),
                        })
                    });
                    const data = await res.json();

                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('addEventModal')).hide();
                        // Refetch calendar sources so the new event appears
                        document.querySelector('#calendar').__fc?.refetchEvents?.();
                        // Fallback: reload if needed
                        window.location.reload();
                    } else {
                        errEl.textContent = data.error || 'Failed to save event.';
                        errEl.classList.remove('d-none');
                    }
                } catch (e) {
                    errEl.textContent = 'Network error. Please try again.';
                    errEl.classList.remove('d-none');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Event';
                }
            });
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
