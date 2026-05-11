<?php

require_once __DIR__ . '/env.php';

define('MAIL_HOST',       $_ENV['MAIL_HOST']);
define('MAIL_PORT',       $_ENV['MAIL_PORT']);
define('MAIL_USERNAME',   $_ENV['MAIL_USERNAME']);
define('MAIL_PASSWORD',   $_ENV['MAIL_PASSWORD']);
define('MAIL_FROM_EMAIL', $_ENV['MAIL_FROM_EMAIL']);
define('MAIL_FROM_NAME',  $_ENV['MAIL_FROM_NAME']);