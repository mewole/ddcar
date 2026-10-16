<?php
include "db.php";
$r = mysqli_query($conn, "SELECT VERSION() as v");
$d = mysqli_fetch_assoc($r);
echo json_encode($d);
?>
