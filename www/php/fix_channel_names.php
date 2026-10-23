<?php
include_once "auth_guard.php";
require_admin_or_owner();

require_once "db.php";
mysqli_query($conn, "SET NAMES utf8");
mysqli_query($conn, "SET CHARACTER SET utf8");

$name = "Shopee (แต่งระเบิด)";
$name_hex = bin2hex($name);

mysqli_query($conn, "UPDATE platform_channels SET channel_name = UNHEX('$name_hex') WHERE channel_code = 'shopee_taengraberd'");
mysqli_query($conn, "UPDATE platform_channels SET customer_name = 'Shopee kingkong' WHERE channel_code = 'shopee_kingkong'");

$q = mysqli_query($conn, "SELECT channel_code, channel_name, customer_name FROM platform_channels");
while ($r = mysqli_fetch_assoc($q)) {
    echo $r['channel_code'] . " -> " . $r['channel_name'] . " (" . $r['customer_name'] . ")\n";
}
?>
