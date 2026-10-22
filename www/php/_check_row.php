<?php
require_once "db.php";
$res = mysqli_query($conn, "SELECT * FROM platform_sku_mapping WHERE id = 213");
$row = mysqli_fetch_assoc($res);
echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
