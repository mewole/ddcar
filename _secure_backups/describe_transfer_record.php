<?php
include "auth_guard.php";
require_admin_or_owner();
header('Content-Type: application/json; charset=utf-8');
include "db.php";

$cols = [];
$res = mysqli_query($conn, "DESCRIBE transfer_record");
while ($r = mysqli_fetch_assoc($res)) {
    $cols[] = $r;
}

$dirs = [];
$res2 = mysqli_query($conn, "SELECT DISTINCT direction FROM transfer_record");
while ($r = mysqli_fetch_assoc($res2)) {
    $dirs[] = $r['direction'];
}

echo json_encode(['columns' => $cols, 'existing_directions' => $dirs], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
