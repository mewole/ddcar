<?php
include "db.php";
$r = mysqli_query($conn, "SELECT status, count(*) as c FROM product GROUP BY status");
$list = [];
while ($d = mysqli_fetch_assoc($r)) {
    $list[$d['status']] = $d['c'];
}
echo json_encode($list, JSON_PRETTY_PRINT);
?>
