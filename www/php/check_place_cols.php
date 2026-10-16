<?php
include "db.php";
$r = mysqli_query($conn, "SHOW COLUMNS FROM warehouse_place");
$cols = [];
while ($c = mysqli_fetch_assoc($r)) {
    $cols[] = $c['Field'];
}
echo json_encode($cols);
?>
