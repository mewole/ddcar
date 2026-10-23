<?php
include_once "auth_guard.php";
require_login();
require_once "db.php";
$term = isset($_GET['term']) ? trim(mysqli_real_escape_string($conn, $_GET['term'])) : '';
if (empty($term) && isset($_POST['term'])) {
    $term = trim(mysqli_real_escape_string($conn, $_POST['term']));
}

if (empty($term)) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([]);
    exit;
}

$limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : 20;

$sql = "SELECT p.prod_id, p.prod_code, p.prod_name, p.prod_cost, p.prod_price,
               CASE WHEN p.img LIKE 'img/%' THEN p.img WHEN LENGTH(p.img) < 200 THEN p.img ELSE '' END as img,
               (SELECT COALESCE(SUM(amount), 0) FROM warehouse WHERE prod_id = p.prod_id) as ware_amount,
               (SELECT COALESCE(SUM(amount), 0) FROM shop WHERE prod_id = p.prod_id) as shop_amount,
               (SELECT COUNT(*) FROM bundle_recipes WHERE bundle_sku COLLATE utf8_unicode_ci = p.prod_code) as bundle_component_count
        FROM product p
        WHERE p.prod_code LIKE '%$term%' OR p.prod_name LIKE '%$term%'
        LIMIT $limit";

$res = mysqli_query($conn, $sql);
$results = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $tot_stock = intval($row['ware_amount']) + intval($row['shop_amount']);
        $results[] = [
            "id" => intval($row['prod_id']),
            "code" => $row['prod_code'],
            "name" => $row['prod_name'],
            "cost" => floatval($row['prod_cost']),
            "price" => floatval($row['prod_price']),
            "img" => $row['img'] ?: '',
            "total_stock" => $tot_stock,
            "ware_stock" => intval($row['ware_amount']),
            "shop_stock" => intval($row['shop_amount']),
            "is_bundle" => intval($row['bundle_component_count']) > 0
        ];
    }
}

header("Content-Type: application/json; charset=utf-8");
echo json_encode($results, JSON_UNESCAPED_UNICODE);
?>
