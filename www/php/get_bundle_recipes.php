<?php
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

// Fetch candidate products from DDcar
$prod_sql = "SELECT prod_id, prod_name, prod_code, prod_price, prod_cost 
             FROM product 
             WHERE (prod_name LIKE '%คู่%' OR prod_name LIKE '%ชุด%' OR prod_name LIKE '%SET%' OR prod_name LIKE '%โลโก้%')
             ORDER BY prod_id DESC";
$prod_res = mysqli_query($conn, $prod_sql);

$items = [];
$mapped_count = 0;
while ($p = mysqli_fetch_assoc($prod_res)) {
    $sku = trim($p['prod_code']);
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

$total = count($items);
$pending = $total - $mapped_count;

echo json_encode([
    "success" => true,
    "total" => $total,
    "mapped" => $mapped_count,
    "pending" => $pending,
    "percent" => $total > 0 ? round(($mapped_count / $total) * 100, 1) : 0,
    "items" => $items
], JSON_UNESCAPED_UNICODE);
?>