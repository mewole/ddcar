<?php
include_once "auth_guard.php";
require_login();

require_once "db.php";
header("Content-Type: application/json; charset=utf-8");

// Fetch existing bundle recipes
$recipes_sql = "SELECT * FROM bundle_recipes ORDER BY id ASC";
$recipes_res = mysqli_query($conn, $recipes_sql);
$recipes_map = [];
while ($row = mysqli_fetch_assoc($recipes_res)) {
    $bsku = $row['bundle_sku'];
    if (!isset($recipes_map[$bsku])) {
        $recipes_map[$bsku] = [];
    }
    $recipes_map[$bsku][] = $row;
}

$mode = isset($_GET['mode']) ? trim($_GET['mode']) : 'online_active';

// Fetch candidate products based on mode
if ($mode === 'online_active') {
    // Only show products that have a recipe in bundle_recipes OR are sold online as bundles
    $recipe_skus = array_keys($recipes_map);
    $escaped_skus = array_map(function($s) use ($conn) { return "'" . mysqli_real_escape_string($conn, $s) . "'"; }, $recipe_skus);
    $sku_in = !empty($escaped_skus) ? implode(',', $escaped_skus) : "''";

    $prod_sql = "SELECT p.prod_id, p.prod_name, p.prod_code, p.prod_price, p.prod_cost 
                 FROM product p 
                 WHERE p.prod_code IN ($sku_in)
                    OR p.prod_code IN (SELECT DISTINCT target_sku FROM platform_sku_mapping WHERE mapping_type = 'bundle' AND target_sku != '')
                 ORDER BY p.prod_id DESC";
} else {
    // All 394 candidate items from warehouse
    $prod_sql = "SELECT prod_id, prod_name, prod_code, prod_price, prod_cost 
                 FROM product 
                 WHERE (prod_name LIKE '%คู่%' OR prod_name LIKE '%ชุด%' OR prod_name LIKE '%SET%' OR prod_name LIKE '%โลโก้%')
                 ORDER BY prod_id DESC";
}
$prod_res = mysqli_query($conn, $prod_sql);

$items = [];
$mapped_count = 0;
$seen_skus = [];

if ($prod_res) {
    while ($p = mysqli_fetch_assoc($prod_res)) {
        $sku = trim($p['prod_code']);
        $seen_skus[$sku] = true;
        $children = isset($recipes_map[$sku]) ? $recipes_map[$sku] : [];
        $is_mapped = count($children) > 0;
        if ($is_mapped) {
            $mapped_count++;
        }
        $items[] = [
            "id" => $p['prod_id'],
            "sku" => $sku,
            "name" => $p['prod_name'],
            "price" => floatval($p['prod_price']),
            "cost" => floatval($p['prod_cost']),
            "is_mapped" => $is_mapped,
            "children" => $children
        ];
    }
}

// Also include any recipe SKUs that might be virtual/bundle-only (like BD-SNORKEL-SET or AUTO-...)
foreach ($recipes_map as $bsku => $children) {
    if (!isset($seen_skus[$bsku])) {
        $mapped_count++;
        $items[] = [
            "id" => 0,
            "sku" => $bsku,
            "name" => $children[0]['bundle_name'] ?: ("ชุดรหัส " . $bsku),
            "price" => 0,
            "cost" => 0,
            "is_mapped" => true,
            "children" => $children
        ];
    }
}

$total = count($items);
$pending = max(0, $total - $mapped_count);

echo json_encode([
    "success" => true,
    "total" => $total,
    "mapped" => $mapped_count,
    "pending" => $pending,
    "percent" => $total > 0 ? round(($mapped_count / $total) * 100, 1) : 0,
    "items" => $items
], JSON_UNESCAPED_UNICODE);
?>