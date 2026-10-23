<?php
include_once "auth_guard.php";
require_login();

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

// High-speed 2-step lookup: avoids cross-collation join table scan
$sql = "SELECT m.*, c.channel_name, c.platform_type, c.badge_color, c.customer_name as channel_customer
        FROM platform_sku_mapping m
        LEFT JOIN platform_channels c ON m.channel_code = c.channel_code
        WHERE $where_sql
        ORDER BY (m.status = 'unmapped') DESC, m.id DESC
        LIMIT $offset, $limit";

$res = mysqli_query($conn, $sql);
$items = [];
$target_skus = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $sku = trim($row['target_sku'] ?? '');
        if (!empty($sku)) {
            $target_skus[] = "'" . mysqli_real_escape_string($conn, $sku) . "'";
        }

        // Format TikTok product URL
        $prod_url = $row['product_url'] ?? '';
        if (strpos($prod_url, 'seller-th.tiktok.com/product/edit/') !== false) {
            preg_match('/edit\/([0-9]+)/', $prod_url, $t_match);
            if (!empty($t_match[1])) {
                $prod_url = 'https://www.tiktok.com/view/product/' . $t_match[1];
            }
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
            "product_url" => $prod_url,
            "mapping_type" => $row['mapping_type'],
            "target_sku" => $row['target_sku'],
            "bundle_json" => $row['bundle_json'],
            "vat_type" => $row['vat_type'],
            "status" => $row['status'],
            "matched_prod_id" => 0,
            "matched_prod_name" => '',
            "matched_img" => '',
            "matched_price" => 0.0,
            "total_stock" => 0,
            "bundle_components" => [],
            "bundle_available_stock" => null
        ];
    }
}

// Step 2: Fetch matched products via indexed IN query (< 5ms)
$prod_map = [];
$prod_ids = [];
if (!empty($target_skus)) {
    $sku_list = implode(',', array_unique($target_skus));
    $q_p = mysqli_query($conn, "SELECT prod_id, prod_code, prod_name, prod_price,
            CASE WHEN img LIKE 'img/%' THEN img WHEN LENGTH(img) < 200 THEN img ELSE '' END as matched_img
            FROM product WHERE prod_code IN ($sku_list)");
    if ($q_p) {
        while ($p = mysqli_fetch_assoc($q_p)) {
            $prod_map[$p['prod_code']] = $p;
            $prod_ids[] = intval($p['prod_id']);
        }
    }
}

// Attach matched product details
$img_dir = __DIR__ . '/../img/products';
foreach ($items as &$it) {
    $tsku = $it['target_sku'];
    if (!empty($tsku) && isset($prod_map[$tsku])) {
        $p = $prod_map[$tsku];
        $it['matched_prod_id'] = intval($p['prod_id']);
        $it['matched_prod_name'] = $p['prod_name'] ?: '';
        $it['matched_price'] = floatval($p['prod_price']);

        // Check product image on disk
        $m_img = $p['matched_img'];
        if (empty($m_img)) {
            if (file_exists($img_dir . '/' . $p['prod_id'] . '.jpg')) {
                $m_img = 'img/products/' . $p['prod_id'] . '.jpg';
            } else if (file_exists($img_dir . '/' . $p['prod_id'] . '.png')) {
                $m_img = 'img/products/' . $p['prod_id'] . '.png';
            }
        }
        $it['matched_img'] = $m_img;

        // Fallback: If image_url is empty, use DDCar product image
        if (empty($it['image_url']) && !empty($m_img)) {
            $it['image_url'] = $m_img;
        }
    }
}
unset($it);

// Ultra-fast Batch Stock Query for found products (< 10ms)
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