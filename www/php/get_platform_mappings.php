<?php
require_once "db.php";
require_once "platform_mapping_init.php";
header("Content-Type: application/json; charset=utf-8");

$channel = isset($_GET['channel']) ? mysqli_real_escape_string($conn, trim($_GET['channel'])) : 'all';
$status = isset($_GET['status']) ? mysqli_real_escape_string($conn, trim($_GET['status'])) : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$limit = isset($_GET['limit']) ? max(1, min(500, intval($_GET['limit']))) : 100;
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
               CASE WHEN p.img LIKE 'img/%' THEN p.img WHEN LENGTH(p.img) < 200 THEN p.img ELSE '' END as matched_img,
               (SELECT COALESCE(SUM(amount), 0) FROM warehouse WHERE prod_id = p.prod_id) as ware_amount,
               (SELECT COALESCE(SUM(amount), 0) FROM shop WHERE prod_id = p.prod_id) as shop_amount
        FROM platform_sku_mapping m
        LEFT JOIN platform_channels c ON m.channel_code = c.channel_code
        LEFT JOIN product p ON (m.target_sku != '' AND m.target_sku = p.prod_code)
        WHERE $where_sql
        ORDER BY (m.status = 'unmapped') DESC, m.id DESC
        LIMIT $offset, $limit";

$res = mysqli_query($conn, $sql);
$items = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $item = [
            "id" => intval($row['id']),
            "channel_code" => $row['channel_code'],
            "channel_name" => $row['channel_name'] ?: $row['channel_code'],
            "platform_type" => $row['platform_type'] ?: 'shopee',
            "badge_color" => $row['badge_color'] ?: '#6b7280',
            "platform_sku" => $row['platform_sku'],
            "platform_name" => $row['platform_name'],
            "mapping_type" => $row['mapping_type'],
            "target_sku" => $row['target_sku'],
            "vat_type" => $row['vat_type'],
            "status" => $row['status'],
            "matched_prod_id" => intval($row['matched_prod_id']),
            "matched_prod_name" => $row['matched_prod_name'] ?: '',
            "matched_img" => $row['matched_img'] ?: '',
            "matched_price" => floatval($row['matched_price']),
            "total_stock" => intval($row['ware_amount']) + intval($row['shop_amount']),
            "bundle_components" => [],
            "bundle_available_stock" => null
        ];

        // Process bundle components if type is bundle
        if ($row['mapping_type'] === 'bundle') {
            $bundle_data = json_decode($row['bundle_json'], true);
            if (is_array($bundle_data) && !empty($bundle_data)) {
                $min_available = 999999;
                $components = [];

                foreach ($bundle_data as $comp) {
                    $c_sku = mysqli_real_escape_string($conn, trim($comp['sku']));
                    $c_qty = max(1, intval($comp['qty'] ?? 1));
                    $c_name = $comp['name'] ?? '';

                    $cq = mysqli_query($conn, "SELECT p.prod_id, p.prod_name,
                                                      (SELECT COALESCE(SUM(amount), 0) FROM warehouse WHERE prod_id = p.prod_id) as w_amt,
                                                      (SELECT COALESCE(SUM(amount), 0) FROM shop WHERE prod_id = p.prod_id) as s_amt
                                               FROM product p WHERE prod_code = '$c_sku' LIMIT 1");
                    $stk = 0;
                    if ($cq && mysqli_num_rows($cq) > 0) {
                        $cp = mysqli_fetch_assoc($cq);
                        $stk = intval($cp['w_amt']) + intval($cp['s_amt']);
                        if (empty($c_name)) $c_name = $cp['prod_name'];
                    }

                    $possible_sets = intval(floor($stk / $c_qty));
                    if ($possible_sets < $min_available) {
                        $min_available = $possible_sets;
                    }

                    $components[] = [
                        "sku" => $c_sku,
                        "name" => $c_name,
                        "qty" => $c_qty,
                        "stock" => $stk,
                        "possible_sets" => $possible_sets
                    ];
                }

                $item['bundle_components'] = $components;
                $item['bundle_available_stock'] = ($min_available === 999999) ? 0 : max(0, $min_available);
            }
        }

        $items[] = $item;
    }
}

echo json_encode([
    "success" => true,
    "total" => $total_rows,
    "page" => $page,
    "limit" => $limit,
    "items" => $items
], JSON_UNESCAPED_UNICODE);
?>
