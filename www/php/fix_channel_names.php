<?php
include_once "auth_guard.php";
require_login();
require_once "db.php";
mysqli_query($conn, "SET NAMES utf8");
mysqli_query($conn, "SET CHARACTER SET utf8");

$name = "Shopee (แต่งระเบิด)";
$name_hex = bin2hex($name);

mysqli_query($conn, "UPDATE platform_channels SET channel_name = UNHEX('$name_hex') WHERE channel_code = 'shopee_taengraberd'");

$q = mysqli_query($conn, "SELECT channel_name, HEX(channel_name) as h FROM platform_channels WHERE channel_code = 'shopee_taengraberd'");
$r = mysqli_fetch_assoc($q);
echo "Result: " . $r['channel_name'] . " | HEX: " . $r['h'] . "\n";
?>
