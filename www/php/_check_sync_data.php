<?php
require_once "db.php";

$orders_q = mysqli_query($conn, "SELECT channel_code, COUNT(*) as cnt, MAX(created_at) as latest FROM platform_orders GROUP BY channel_code");
$orders = [];
while ($r = mysqli_fetch_assoc($orders_q)) {
    $orders[] = $r;
}

$items_q = mysqli_query($conn, "SELECT channel_code, COUNT(*) as cnt, MAX(created_at) as latest FROM platform_order_items GROUP BY channel_code");
$items = [];
while ($r = mysqli_fetch_assoc($items_q)) {
    $items[] = $r;
}

$sync_log = mysqli_query($conn, "SHOW TABLES LIKE 'platform_sync_logs'");
$has_log = mysqli_num_rows($sync_log) > 0;

echo json_encode([
    "platform_orders" => $orders,
    "platform_order_items" => $items,
    "has_sync_logs" => $has_log
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
