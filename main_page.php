
<!-- Redirects the user to the login page if not logged in -->
<?php
session_start();

if (!isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit();
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTR Project Acer</title>
    <link rel="stylesheet" href="main_page.css">
    <link rel="stylesheet" href="side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body::before {
            background-image: url('images/drt_bg.jpg');
        }
    </style>
</head>
  <body>
    <!-- SIDEBAR -->
    <?php include 'side_bar.php'; ?>

    <!-- TOPBAR -->
    
    <?php 
    $current_page = 'dashboard';
    include 'top_bar.php'; ?>

    <div class="dashboardContent">

        <p id="currentDate"></p>
        <h1 id="currentTime"></h1>

        <div class="dashboardSummary">
            <div class="summaryCard">
                <p>Currently</p>
                <h5>Timed Out</h5>
            </div>
            
            <div class="summaryCard">
                <p>Total Hours This Week</p>
                <h5>0 hours</h5>
            </div>

            <div class="summaryCard">
                <p>Total Hours This Month</p>
                <h5>0 hours</h5>
            </div>
        </div>
    </div>

    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

    <script>
        function updateDateTime() {
            const now = new Date();

            const dateOptions = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const formattedDate = now.toLocaleDateString(undefined, dateOptions);

            const timeOptions = { 
                hour: 'numeric', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            const formattedTime = now.toLocaleTimeString(undefined, timeOptions);

            document.getElementById("currentDate").textContent = formattedDate;
            document.getElementById("currentTime").textContent = formattedTime;
        }

        updateDateTime();

        // This updates every second (1000 milliseconds)
        setInterval(updateDateTime, 1000);
    </script>
  </body>
</html>