<?php
include "db.php";
$res = mysqli_query($conn, "SELECT COUNT(*) as c FROM product WHERE min_amount != '' AND min_amount != '0' AND min_amount IS NOT NULL");
$row = mysqli_fetch_assoc($res);
echo json_encode($row);
?>
