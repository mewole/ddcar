<?php
include "db.php";
$r = mysqli_query($conn, "SELECT COUNT(*) as c FROM product");
$d = mysqli_fetch_assoc($r);
echo json_encode($d);
?>
