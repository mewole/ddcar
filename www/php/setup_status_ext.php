<?php
include_once "auth_guard.php";
require_admin_or_owner();

include "db.php";
// Kill slow ALTER TABLE
$q = mysqli_query($conn, "SHOW PROCESSLIST");
while ($r = mysqli_fetch_assoc($q)) {
    if ($r['Id'] == 226098 || strpos($r['Info'], 'ALTER TABLE product') !== false) {
        mysqli_query($conn, "KILL " . $r['Id']);
        echo "Killed: " . $r['Id'] . "\n";
    }
}

// Create dedicated fast extension table
$sql = "CREATE TABLE IF NOT EXISTS product_status_ext (
    prod_id INT(11) NOT NULL PRIMARY KEY,
    is_ordered TINYINT(1) NOT NULL DEFAULT 0,
    is_preorder TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ordered (is_ordered),
    KEY idx_preorder (is_preorder)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

if (mysqli_query($conn, $sql)) {
    echo "product_status_ext created successfully!\n";
} else {
    echo "Error creating table: " . mysqli_error($conn) . "\n";
}

$res = mysqli_query($conn, "SHOW PROCESSLIST");
$list = [];
while ($r = mysqli_fetch_assoc($res)) {
    $list[] = $r;
}
echo json_encode($list, JSON_PRETTY_PRINT);
?>
