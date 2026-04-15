<!-- Redirects the user to the login page if not logged in -->
<?php
session_start();

if (!isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit();
}

require_once 'db.php';

$email = $_SESSION['user_email'];
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_email = ? ORDER BY date DESC");
$stmt->execute([$email]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTR Project Acer</title>
    <link rel="stylesheet" href="record_page.css">
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
    <?php include 'top_bar.php'; ?>

    <div class="recordBoxWrapper">
        <div class="recordBox">
            <h5 class="tableTitle">My Attendance Records</h5>
            <table class="table table-bordered table-hover mt-3">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Total Work Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($records) > 0): ?>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td><?= date('F d, Y', strtotime($row['date'])) ?></td>
                                <td><?= $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '—' ?></td>
                                <td><?= $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '—' ?></td>
                                <td><?= $row['total_work_hours'] ? $row['total_work_hours'] . ' hrs' : '—' ?></td>
                                <td><?= $row['status'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  </body>
</html>