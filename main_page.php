
<!-- Redirects the user to the login page if not logged in -->
<?php
session_start();

if (!isset($_SESSION['user_email'])) {
    // header("Location: index.php");
    // exit();
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap demo</title>
    <link rel="stylesheet" href="main_page.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
  <body>
    <div class="navBar">
        <a href="index.php"><img src="images/HSN.png" alt="DTR Logo" class="logo"></a>
        <div class="navUserProfile">
            <i class="bi bi-person-fill" alt="User Icon"></i>
            <a class="userEmail"><?php echo $_SESSION['user_email'] ?? 'user@gmail.com'; ?></a>
            <i class="bi bi-caret-down-fill dropdownToggle"></i>
        </div>
    </div>

 <div class="container dashBoard">

  <!-- TOP ROW -->
  <div class="row">
    <div class="col-md-6 box">
      <div id="current_time">8:00 AM</div>
      <div id="current_date">Monday, January 1, 2024</div>
    </div>

        <div class="col-md-6 d-flex justify-content-center align-items-center">
            Attendance Summary
        </div>
  </div>

  <!-- BOTTOM ROW -->
  <div class="row">
        <div class="col-md-6 d-flex justify-content-center align-items-center">
            <button class="btn btn-primary">Time In</button>
        </div>

        <div class="col-md-6 d-flex justify-content-center align-items-center">
            Graph
        </div>
  </div>

</div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  </body>
</html>