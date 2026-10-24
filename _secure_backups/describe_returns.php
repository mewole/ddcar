<?php
include "auth_guard.php";
require_admin_or_owner();
header('Content-Type: application/json; charset=utf-8');
include "db.php";

$cols_ret = [];
$res = mysqli_query($conn, "DESCRIBE product_return");
while ($r = mysqli_fetch_assoc($res)) {
    $cols_ret[] = ['field' => $r['Field'], 'type' => $r['Type'], 'default' => $r['Default']];
}

$cols_item = [];
$res2 = mysqli_query($conn, "DESCRIBE product_return_item");
while ($r = mysqli_fetch_assoc($res2)) {
    $cols_item[] = ['field' => $r['Field'], 'type' => $r['Type'], 'default' => $r['Default']];
}

echo json_encode([
    'product_return' => $cols_ret,
    'product_return_item' => $cols_item
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
