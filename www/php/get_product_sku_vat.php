<?php
require_once "db.php";
header("Content-Type: application/json; charset=utf-8");

$prod_id = isset($_GET['prod_id']) ? intval($_GET['prod_id']) : 0;
$prod_code = isset($_GET['prod_code']) ? mysqli_real_escape_string($conn, trim($_GET['prod_code'])) : '';

$where = "1=1";
if ($prod_id > 0) {
    $where = "p.prod_id = $prod_id";
} elseif (!empty($prod_code)) {
    $where = "p.prod_code = '$prod_code'";
} else {
    echo json_encode(["success" => false, "message" => "กรุณาระบุ prod_id หรือ prod_code"]);
    exit;
}

$sql = "SELECT p.prod_id, p.prod_code, p.prod_name, p.prod_cost, p.prod_price,
               s.shopee_sku, s.lazada_sku, s.tiktok_sku, s.vat_stock, s.non_vat_stock,
               (SELECT COALESCE(SUM(amount), 0) FROM warehouse WHERE prod_id = p.prod_id) as ware_amount,
               (SELECT COALESCE(SUM(amount), 0) FROM shop WHERE prod_id = p.prod_id) as shop_amount,
               (SELECT COUNT(*) FROM bundle_recipes WHERE bundle_sku = p.prod_code) as bundle_component_count
        FROM product p
        LEFT JOIN product_platform_sku s ON p.prod_id = s.prod_id
        WHERE $where
        LIMIT 1";

$res = mysqli_query($conn, $sql);
if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    $total_stock = intval($row['ware_amount']) + intval($row['shop_amount']);
    $vat_stock = $row['vat_stock'] !== null ? intval($row['vat_stock']) : $total_stock;
    $non_vat_stock = $row['non_vat_stock'] !== null ? intval($row['non_vat_stock']) : 0;

    echo json_encode([
        "success" => true,
        "data" => [
            "prod_id" => intval($row['prod_id']),
            "prod_code" => $row['prod_code'],
            "prod_name" => $row['prod_name'],
            "prod_cost" => floatval($row['prod_cost']),
            "prod_price" => floatval($row['prod_price']),
            "shopee_sku" => $row['shopee_sku'] ?: '',
            "lazada_sku" => $row['lazada_sku'] ?: '',
            "tiktok_sku" => $row['tiktok_sku'] ?: '',
            "total_stock" => $total_stock,
            "ware_stock" => intval($row['ware_amount']),
            "shop_stock" => intval($row['shop_amount']),
            "vat_stock" => $vat_stock,
            "non_vat_stock" => $non_vat_stock,
            "is_bundle" => intval($row['bundle_component_count']) > 0,
            "bundle_components" => intval($row['bundle_component_count'])
        ]
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["success" => false, "message" => "ไม่พบข้อมูลสินค้า"]);
}
?>