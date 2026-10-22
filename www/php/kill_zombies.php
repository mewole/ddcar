<?php
include "db.php";
include_once "auth_guard.php";
require_login();
$zombies = [226059, 226061, 226064, 226066, 226107];
foreach ($zombies as $zid) {
    mysqli_query($conn, "KILL $zid");
}
$res = mysqli_query($conn, "SHOW PROCESSLIST");
$list = [];
while ($r = mysqli_fetch_assoc($res)) {
    $list[] = $r;
}
echo json_encode($list, JSON_PRETTY_PRINT);
?>
