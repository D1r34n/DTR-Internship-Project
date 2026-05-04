<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Schedule</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="employee_schedule.css">

    <style>
        html, body { height: 100%; margin: 0; }
        body { display: flex; min-height: 100vh; }
        #sidebar { flex-shrink: 0; }
        #main-wrapper {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            min-width: 0;
            overflow-y: auto;
        }
        #topbar { flex-shrink: 0; position: sticky; top: 0; z-index: 100; }
    </style>
</head>
<body>
    <?php $currentPage = 'schedule'; include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">
        <?php include '../topbar_revised.php'; ?>

        <div class="card card-glass schedules-card">
            <div class="card-body d-flex flex-column schedules-card-body">

                <!-- SHIFT LEGEND -->
                <div class="shiftLegend">
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
                </div>

                <!-- CALENDAR -->
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div><!-- #main-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const calendarEl = document.getElementById('calendar');

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',

                customButtons: {
                    refresh: {
                        text: 'Refresh',
                        click: function () {
                            calendar.refetchEvents();
                        }
                    }
                },

                headerToolbar: {
                    left:   'prev,next today',
                    center: 'title',
                    right:  'refresh'
                },

                events: {
                    url:     '../get_schedule.php',
                    method:  'GET',
                    failure: function () {
                        console.error('Failed to fetch schedule.');
                    }
                },

                // ---- Style each event based on shift type ----
                eventDidMount: function(info) {
                    const shiftType = info.event.extendedProps.shift_type;

                    if (shiftType === 'night_continuation') {
                        info.el.style.border          = '2px dashed #4da3ff';
                        info.el.style.backgroundColor = 'rgba(77, 163, 255, 0.15)';
                        info.el.style.color           = '#4da3ff';
                        info.el.style.borderRadius    = '4px';
                    }
                },

                eventDisplay: 'block',
                dayMaxEvents: true,
                height:       '100%',
            });

            calendar.render();

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

            /* Watch layout changes (sidebar expand/collapse) */
            window.addEventListener('resize', resizeCalendar);

            const sidebar = document.getElementById('sidebar');

            const observer = new ResizeObserver(() => {
                calendar.updateSize();
            });

            observer.observe(sidebar);
        });
    </script>
</body>
</html>