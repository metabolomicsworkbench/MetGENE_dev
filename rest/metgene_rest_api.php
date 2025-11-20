<?php

############## Mano add default lines #################
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', FALSE);
ini_set("log_errors", TRUE);
define('EOL', (PHP_SAPI == 'cli') ? PHP_EOL : '<br />');
date_default_timezone_set('America/Los_Angeles');
#######################################################

set_time_limit(0);
ini_set('memory_limit', '2048M');

#$thisfilename = __FILE__; echo "Inside file: $thisfilename<br>";

include_once "metgene_rest_api_class.php";

// Initiate Library
#include("../../rest.php");
#$thisfilename = __FILE__; echo "<html><head>Test</head><body>Inside file: $thisfilename<br></body></html>";

$api = new API;
$str = "Dummy";
$api->processApi($str);
?>
