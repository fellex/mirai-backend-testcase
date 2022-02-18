<?php
date_default_timezone_set('UTC');
/*error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);*/
ini_set('max_execution_time', 1200); // 20 минут, должно хватить со sleep(1)

require 'Base.php';
require 'MySQL.php';
require 'TimezoneAPI.php';

$config = include 'config.php';
$base = new Base($config);
$base->run();
exit;
