<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

date_default_timezone_set('Asia/Manila');

$startDate = !empty($_GET['start']) ? date('Y-m-d', strtotime($_GET['start'])) : date('Y-m-01');
$endDate   = !empty($_GET['end'])   ? date('Y-m-d', strtotime($_GET['end']))   : date('Y-m-t');

$current_page = 'logs';
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Logs</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- CSS -->
    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link rel="stylesheet" href="employee_logs.css">
</head>

<body>
    <!-- Include sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Include topbar -->
    <?php
    include '../topbar.php';
    ?>

    <div class="recordBoxWrapper">
        <div class="recordBox">

            <!-- DATE RANGE PICKER -->
            <div class="dateWrapper">
                <input type="text" id="dateRangePicker" class="recordTitle" readonly>
                <i class="bi bi-chevron-down dateIcon"></i>
                <input type="hidden" id="startDate" value="<?= $startDate ?>">
                <input type="hidden" id="endDate" value="<?= $endDate ?>">
            </div>

            <!-- Table Header -->
            <div class="tableHeaderGlass">
                <table class="table table-borderless mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Log Type</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <!-- Body with scrolling -->
            <div class="tableScroll">
                <table class="table table-hover mb-0">
                    <tbody id="logs_table_body">
                        <!-- populated by fetchLogs() -->
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- Map Hover Popup -->
    <div class="mapPopUpContainer" id="map_pop_up_container">
        
        <!-- Actual Map -->
        <div class="mapPopUp" id="map_pop_up"></div>

        <!-- Map details -->
        <div class="mapPopUpInfo" id="map_pop_up_info"></div>

        <!-- BUTTON -->
        <div style="padding: 10px;">
            <a class="openGoogleMapsBtn" id="open_gmaps_btn" href="#" target="_blank">
                Open in Google Maps
            </a>
        </div>

    </div>

    <script>
    const tbody = document.getElementById('logs_table_body');

    // FETCH — get HTML rows from get_logs.php and inject directly
    function fetchLogs() {
        const start = document.getElementById('startDate').value;
        const end   = document.getElementById('endDate').value;

        fetch(`../get_logs.php?start=${start}&end=${end}`)
            .then(res => res.text())
            .then(html => {
                tbody.innerHTML = html;
            })
            .catch(() => {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-danger">
                            Failed to load logs.
                        </td>
                    </tr>`;
            });
    }

    // FLATPICKR
    flatpickr("#dateRangePicker", {
        mode: "range",
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "F j, Y",
        defaultDate: [document.getElementById('startDate').value, document.getElementById('endDate').value],
        onChange(selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                const pad = n => String(n).padStart(2, '0');
                const toLocal = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

                const start = toLocal(selectedDates[0]);
                const end   = toLocal(selectedDates[1]);

                window.location.href = `?start=${start}&end=${end}`;
            }
        }
    });

    // MAP HOVER POPUP
    let popupMap = null;
    let hideTimeout = null;
    const mapPopup = document.getElementById('map_pop_up_container');

    // Hover in
    document.addEventListener('mouseover', e => {
        const trigger = e.target.closest('.loc-trigger');
        if (!trigger) return;

        clearTimeout(hideTimeout);

        const lat   = parseFloat(trigger.dataset.lat);
        const lng   = parseFloat(trigger.dataset.lng);
        const label = trigger.dataset.label;
        const acc   = trigger.dataset.acc;
        const dist  = trigger.dataset.dist;

        const mapBtn = document.getElementById('open_gmaps_btn');
        mapBtn.href = `https://www.google.com/maps?q=${lat},${lng}`;

        const rect = trigger.getBoundingClientRect();
        const popupHeight = 320; // approximate height of popup
        const spaceBelow = window.innerHeight - rect.bottom;
        const spaceAbove = rect.top;

        let topPos;
        if (spaceBelow < popupHeight && spaceAbove > spaceBelow) {
            // flip above
            topPos = rect.top + window.scrollY - popupHeight - 3;
        } else {
            // default below
            topPos = rect.bottom + window.scrollY + 3;
        }

        // also prevent going off right edge
        const popupWidth = 300;
        const LEFT_OFFSET = 310;

        const spaceRight = window.innerWidth - rect.left;

        let leftPos;

        if (spaceRight < popupWidth) {
            // too close to right edge → shift left more
            leftPos = rect.right + window.scrollX - popupWidth - LEFT_OFFSET;
        } else {
            // normal case → slightly shift left for better centering
            leftPos = rect.left + window.scrollX - LEFT_OFFSET;
        }

        mapPopup.style.top  = `${topPos}px`;
        mapPopup.style.left = `${leftPos}px`;
        mapPopup.style.display = 'block';

        document.getElementById('map_pop_up_info').innerHTML = `
            <b>${label}</b><br>
            Latitude: ${lat} &nbsp; &nbsp; Longitude: ${lng}<br>
            Accuracy: ±${acc} m &nbsp; Distance: ${dist} m
        `;

        if (popupMap) {
            popupMap.remove();
            popupMap = null;
        }

        setTimeout(() => {
            popupMap = L.map('map_pop_up', {
                zoomControl: false,
                attributionControl: false
            }).setView([lat, lng], 17);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png')
                .addTo(popupMap);

            L.marker([lat, lng]).addTo(popupMap);
        }, 50);
    });

    // Hover out
    document.addEventListener('mouseout', e => {
        const trigger = e.target.closest('.loc-trigger');
        if (!trigger) return;

        hideTimeout = setTimeout(() => {
            mapPopup.style.display = 'none';
            if (popupMap) {
                popupMap.remove();
                popupMap = null;
            }
        }, 200);
    });

    // Keep popup open when hovering over it
    mapPopup.addEventListener('mouseover', () => clearTimeout(hideTimeout));
    mapPopup.addEventListener('mouseout', () => {
        hideTimeout = setTimeout(() => {
            mapPopup.style.display = 'none';
            if (popupMap) {
                popupMap.remove();
                popupMap = null;
            }
        }, 200);
    });

    // initial load
    fetchLogs();
    </script>
</body>
</html>