<?php
include_once "auth_guard.php";
require_login();

include "db.php";
$r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM product WHERE min_amount != '' AND min_amount != '0' AND min_amount IS NOT NULL"));
echo "Total with min_amount: " . $r['c'];
?>
