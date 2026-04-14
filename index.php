<?php
$bg_image = "images/bg.png";
$page_title = "DTR Project Acer";
$logo = "images/HSN.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-image: url('<?php echo $bg_image; ?>');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;

            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            text-align: center;
            background-color: white;
            padding: 40px;
            border-radius: 12px;
            height: 600px;
            width: 440px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .logo {
            width: 145px;
            margin-bottom: 20px;
            margin-top: -20px;
        }
    </style>
</head>
<body>

   <div class="container">
        <img src="<?php echo $logo; ?>" alt="HSN Logo" class="logo">
        <h2>Sign In</h2>
    </div>

</body>
</html>