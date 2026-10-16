<?php
include "db.php";
$res = mysqli_query($conn, "SHOW PROCESSLIST");
$list = [];
while ($r = mysqli_fetch_assoc($res)) {
    $list[] = $r;
}
echo json_encode($list, JSON_PRETTY_PRINT);
?>
