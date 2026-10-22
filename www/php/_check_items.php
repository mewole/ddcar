<?php
require_once "db.php";
$res = mysqli_query($conn, "SELECT id, channel_code, platform_sku, platform_name, updated_at FROM platform_sku_mapping WHERE platform_sku LIKE '%1736503908611163414%'");
$rows = [];
while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = $r;
}
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
