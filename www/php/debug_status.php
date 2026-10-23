<?php
include_once "auth_guard.php";
require_login();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$_POST['rich'] = 1;
$_POST['limit'] = 5;
$_POST['filter'] = 'all';
include "get_status_prod.php";
?>
