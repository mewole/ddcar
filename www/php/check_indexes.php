<?php
include "db.php";
$r = mysqli_query($conn, "SHOW INDEX FROM product");
$list = [];
while ($d = mysqli_fetch_assoc($r)) {
    $list[] = $d['Key_name'] . ' -> ' . $d['Column_name'];
}
echo json_encode(array_values(array_unique($list)), JSON_PRETTY_PRINT);
?>
