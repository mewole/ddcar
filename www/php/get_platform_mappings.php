<?php
require_once "db.php";
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$channel = isset($_GET['channel']) ? mysqli_real_escape_string($conn, trim($_GET['channel'])) : 'all';
$status = isset($_GET['status']) ? mysqli_real_escape_string($conn, trim($_GET['status'])) : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$limit = isset($_GET['limit']) ? max(1, min(200, intval($_GET['limit']))) : 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$where = ["1=1"];

if ($channel !== 'all' && !empty($channel)) {
    $where[] = "m.channel_code = '$channel'";
}

if ($status === 'unmapped') {
    $where[] = "m.status = 'unmapped'";
} elseif ($status === 'mapped') {
    $where[] = "m.status = 'mapped'";
}

if (!empty($search)) {
    $where[] = "(m.platform_sku LIKE '%$search%' OR m.platform_name LIKE '%$search%' OR m.target_sku LIKE '%$search%')";
}

$where_sql = implode(" AND ", $where);

// Count total matching
$count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM platform_sku_mapping m WHERE $where_sql");
$total_rows = $count_res ? intval(mysqli_fetch_assoc($count_res)['total']) : 0;

// Query items
$sql = "SELECT m.*, c.channel_name, c.platform_type, c.badge_color, c.customer_name as channel_customer,
               p.prod_id as matched_prod_id, p.prod_name as matched_prod_name, p.prod_price as matched_price,
               CASE WHEN p.img LIKE 'img/%' THEN p.img WHEN LENGTH(p.img) < 200 THEN p.img ELSE '' END as matched_img
        FROM platform_sku_mapping m
        LEFT JOIN platform_channels c ON m.channel_code = c.channel_code
        LEFT JOIN product p ON (m.target_sku != '' AND m.target_sku = p.prod_code)
        WHERE $where_sql
        ORDER BY (m.status = 'unmapped') DESC, m.id DESC
        LIMIT $offset, $limit";

$res = mysqli_query($conn, $sql);
$items = [];
$prod_ids = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $pid = intval($row['matched_prod_id']);
        if ($pid > 0) {
            $prod_ids[] = $pid;
        }

        $items[] = [
            "id" => intval($row['id']),
            "channel_code" => $row['channel_code'],
            "channel_name" => $row['channel_name'] ?: $row['channel_code'],
            "platform_type" => $row['platform_type'] ?: 'shopee',
            "badge_color" => $row['badge_color'] ?: '#6b7280',
            "platform_sku" => $row['platform_sku'],
            "platform_name" => $row['platform_name'],
            "image_url" => $row['image_url'] ?? '',
            "product_url" => $row['product_url'] ?? '',
            "mapping_type" => $row['mapping_type'],
            "target_sku" => $row['target_sku'],
            "bundle_json" => $row['bundle_json'],
            "vat_type" => $row['vat_type'],
            "status" => $row['status'],
            "matched_prod_id" => $pid,
            "matched_prod_name" => $row['matched_prod_name'] ?: '',
            "matched_img" => $row['matched_img'] ?: '',
            "matched_price" => floatval($row['matched_price']),
            "total_stock" => 0,
            "bundle_components" => [],
            "bundle_available_stock" => null
        ];
    }
}

// Ultra-fast Batch Stock Query for found products
if (!empty($prod_ids)) {
    $pid_str = implode(',', array_unique($prod_ids));
    $stock_map = [];

    $wq = mysqli_query($conn, "SELECT prod_id, SUM(amount) as amt FROM warehouse WHERE prod_id IN ($pid_str) GROUP BY prod_id");
    if ($wq) {
        while ($r = mysqli_fetch_assoc($wq)) {
            $stock_map[$r['prod_id']] = intval($r['amt']);
        }
    }

    $sq = mysqli_query($conn, "SELECT prod_id, SUM(amount) as amt FROM shop WHERE prod_id IN ($pid_str) GROUP BY prod_id");
    if ($sq) {
        while ($r = mysqli_fetch_assoc($sq)) {
            $stock_map[$r['prod_id']] = ($stock_map[$r['prod_id']] ?? 0) + intval($r['amt']);
        }
    }

    foreach ($items as &$it) {
        if ($it['matched_prod_id'] > 0 && isset($stock_map[$it['matched_prod_id']])) {
            $it['total_stock'] = $stock_map[$it['matched_prod_id']];
        }
    }
    unset($it);
}

// Process bundle components if any
foreach ($items as &$it) {
    if ($it['mapping_type'] === 'bundle' && !empty($it['bundle_json'])) {
        $bundle_data = json_decode($it['bundle_json'], true);
        if (is_array($bundle_data) && !empty($bundle_data)) {
            $it['bundle_components'] = $bundle_data;
        }
    }
    unset($it['bundle_json']);
}
unset($it);

echo json_encode([
    "success" => true,
    "total" => $total_rows,
    "page" => $page,
    "limit" => $limit,
    "items" => $items
], JSON_UNESCAPED_UNICODE);
?>
