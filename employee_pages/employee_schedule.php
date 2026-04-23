<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Schedule</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link rel="stylesheet" href="employee_schedule.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../topbar.php'; ?>

    <div class="scheduleBoxWrapper">
        <div class="scheduleBox">

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
            </div>

            <!-- CALENDAR -->
            <div id="calendar"></div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

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
            const wrapper = document.querySelector('.scheduleBoxWrapper');
            wrapper.addEventListener('transitionend', function (e) {
                if (e.propertyName === 'width') {
                    calendar.updateSize();
                }
            });
        });
    </script>
</body>
</html>