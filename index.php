<?php
$bg_image = "images/drt_bg.jpg";
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

        body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('<?php echo $bg_image; ?>');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        filter: blur(8px);
        z-index: -1;
}

        body {
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

        h2 {
            margin-bottom: 60px;
            font-size: 24px;
            color: #333;
        }


         /* EMAIL AND PASSWORD */
        .field-group {
            text-align: left;
            margin-bottom: 30px;
        }

        .field-group label {
            display: block;
            font-size: 18px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 8px;
        }

        .field-group input {
            width: 100%;
            padding: 10px 14px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 0px;
            outline: none;
            transition: border-color 0.2s;
        }

        .field-group input:focus {
            border-color: #97be41;
        }

        
        /* REMEMBER ME AND FORGOT SECTION */
        .remember-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: -20px; 
        }

        .remember-row .remember-me {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 18px;
            color: #333;
        }

        .remember-row .remember-me input[type="checkbox"] {
            width: 14px;
            height: 14px;
            cursor: pointer;
            accent-color: #97be41
        }

        .remember-row .forgot-password {
            font-size: 18px;
            color: #97be41;
            text-decoration: none;
        }

        .remember-row .forgot-password:hover {
            text-decoration: underline;
        }
        
        
        /* SIGN IN SECTION */
        .btn-signin {
            width: 100%;
            padding: 12px;
            margin-top: 25px; 
            margin-bottom: 20px;
            background-color: #97be41;
            color: white;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 34px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-signin:hover {
            background-color: #7ea832;
        }
        .btn-signin:active {
            background-color: #97be41;
        }

            /* NO ACCOUNT SECTION */
        .no-account-group {
            text-align: center;
            margin-bottom: 30px;
        }

        .no-account-group label {
            display: block;
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        /* RESPONSIVE SECTION */
        @media (max-width: 480px) {
            .container {
                width: 90%;
                height: auto;
            }

            .field-group label {
                font-size: 15px;
            }

            .remember-row .remember-me {
                font-size: 14px;
            }

            .remember-row .forgot-password {
                font-size: 14px;
            }

            .no-account-group label {
                font-size: 14px;
            }
        }

        @media (max-width: 360px) {
            .container {
                width: 95%;
                height: auto;
            }

            .logo {
                width: 110px;
            }

            h2 {
                font-size: 20px;
            }

            .field-group label {
                font-size: 13px;
            }
        }

    </style>
</head>
<body>

    <div class="container">
        <img src="<?php echo $logo; ?>" alt="HSN Logo" class="logo">
        <h2>Sign In</h2>

        <div class="field-group">
            <label>Email</label>
            <input type="email" placeholder="Enter your email">
        </div>

        <div class="field-group">
            <label>Password</label>
            <input type="password" placeholder="Enter your password">
        </div>

        <div class="remember-row">
            <label class="remember-me">
                <input type="checkbox"> Remember me
            </label>
           <a href="https://www.hsnservice.com/" target="_blank" class="forgot-password"><u>Forgot password?</u></a>
        </div>
        
        <button class="btn-signin">Sign In</button>
        
        <div class="no-account-group">
            <label>Don't have an HSN ID? </label>
            <a href="https://www.hsnservice.com/" target="_blank" style="color: #97be41; text-decoration: none;"><u>Contact your HR admin</u></a>
        </div>
    </div>

</body>
</html>