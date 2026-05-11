<?php
/* =========================================================
   FILE: fullcalendar_schedule_test.php
   PURPOSE:
   Simple FullCalendar schedule setter playground.

   FEATURES:
   - Click a date to create a schedule
   - Drag events
   - Resize events
   - Delete events
   - Uses localStorage only (no database yet)

   REQUIREMENTS:
   - Bootstrap 5
   - FullCalendar CDN

   AUTHOR: ChatGPT
========================================================= */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FullCalendar Schedule Test</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FullCalendar -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">

    <style>
        body {
            background: #10141c;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 2rem;
        }

        .calendar-wrapper {
            max-width: 1200px;
            margin: auto;

            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;

            padding: 1.5rem;

            backdrop-filter: blur(24px);
        }

        #calendar {
            height: 80vh;
        }

        .fc {
            color: white;
        }

        .fc-toolbar-title {
            font-size: 1.1rem !important;
        }

        .fc-button {
            background: rgba(255,255,255,0.08) !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            border-radius: 10px !important;
        }

        .fc-button:hover {
            background: rgba(255,255,255,0.14) !important;
        }

        .fc-event {
            border: none !important;
            border-radius: 8px !important;
            padding: 2px 6px !important;
        }
    </style>
</head>
<body>

<div class="calendar-wrapper">
    <div id="calendar"></div>
</div>

<!-- FullCalendar -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const calendarEl = document.getElementById('calendar');

    // =====================================================
    // LOAD SAVED EVENTS
    // =====================================================

    const savedEvents = JSON.parse(localStorage.getItem('fcSchedules') || '[]');

    // =====================================================
    // CALENDAR
    // =====================================================

    const calendar = new FullCalendar.Calendar(calendarEl, {

        initialView: 'dayGridMonth',

        selectable: true,
        editable: true,

        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },

        events: savedEvents,

        // =================================================
        // CLICK DATE TO CREATE SCHEDULE
        // =================================================

        dateClick(info) {

            const title = prompt('Schedule title:');

            if (!title) return;

            const event = {
                id: Date.now().toString(),
                title: title,
                start: info.dateStr,
                allDay: true
            };

            calendar.addEvent(event);

            saveEvents();
        },

        // =================================================
        // EVENT CLICK
        // =================================================

        eventClick(info) {

            const confirmDelete = confirm(
                `Delete "${info.event.title}"?`
            );

            if (confirmDelete) {
                info.event.remove();
                saveEvents();
            }
        },

        // =================================================
        // DRAG / RESIZE
        // =================================================

        eventDrop() {
            saveEvents();
        },

        eventResize() {
            saveEvents();
        }
    });

    calendar.render();

    // =====================================================
    // SAVE EVENTS
    // =====================================================

    function saveEvents() {

        const events = calendar.getEvents().map(event => ({
            id: event.id,
            title: event.title,
            start: event.startStr,
            end: event.endStr,
            allDay: event.allDay
        }));

        localStorage.setItem(
            'fcSchedules',
            JSON.stringify(events)
        );

        console.log('Schedules saved.');
    }
});
</script>

</body>
</html>