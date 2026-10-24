<?php
include_once "auth_guard.php";
require_login();

require_once "db.php";
header("Content-Type: application/json; charset=utf-8");

$sql = "SELECT c.*,
        (SELECT COUNT(*) FROM platform_sku_mapping WHERE channel_code = c.channel_code AND status != 'hidden') as total_items,
        (SELECT COUNT(*) FROM platform_sku_mapping WHERE channel_code = c.channel_code AND status = 'unmapped') as unmapped_items,
        (SELECT COUNT(*) FROM platform_sku_mapping WHERE channel_code = c.channel_code AND status = 'mapped') as mapped_items
        FROM platform_channels c
        WHERE c.is_active = 1
        ORDER BY c.id ASC";

$res = mysqli_query($conn, $sql);
$channels = [];
$total_all = 0;
$unmapped_all = 0;
$mapped_all = 0;

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $t = intval($row['total_items']);
        $u = intval($row['unmapped_items']);
        $m = intval($row['mapped_items']);
        $total_all += $t;
        $unmapped_all += $u;
        $mapped_all += $m;

        $channels[] = [
            "id" => intval($row['id']),
            "channel_code" => $row['channel_code'],
            "channel_name" => $row['channel_name'],
            "platform_type" => $row['platform_type'],
            "customer_name" => $row['customer_name'],
            "badge_color" => $row['badge_color'] ?: '#6b7280',
            "total_items" => $t,
            "unmapped_items" => $u,
            "mapped_items" => $m
        ];
    }
}

echo json_encode([
    "success" => true,
    "summary" => [
        "total" => $total_all,
        "unmapped" => $unmapped_all,
        "mapped" => $mapped_all
    ],
    "channels" => $channels
], JSON_UNESCAPED_UNICODE);
?>
