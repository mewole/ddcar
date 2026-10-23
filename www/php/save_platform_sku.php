<?php
include_once "auth_guard.php";
require_login();
require_once "db.php";
header("Content-Type: application/json; charset=utf-8");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?: $_POST;

$prod_id = isset($data['prod_id']) ? intval($data['prod_id']) : 0;
$prod_code = isset($data['prod_code']) ? mysqli_real_escape_string($conn, trim($data['prod_code'])) : '';
$shopee_sku = isset($data['shopee_sku']) ? mysqli_real_escape_string($conn, trim($data['shopee_sku'])) : '';
$lazada_sku = isset($data['lazada_sku']) ? mysqli_real_escape_string($conn, trim($data['lazada_sku'])) : '';
$tiktok_sku = isset($data['tiktok_sku']) ? mysqli_real_escape_string($conn, trim($data['tiktok_sku'])) : '';
$vat_stock = isset($data['vat_stock']) ? intval($data['vat_stock']) : 0;
$non_vat_stock = isset($data['non_vat_stock']) ? intval($data['non_vat_stock']) : 0;

if ($prod_id <= 0) {
    echo json_encode(["success" => false, "message" => "ไม่พบรหัสสินค้า (prod_id)"]);
    exit;
}

if (empty($prod_code)) {
    $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT prod_code FROM product WHERE prod_id = $prod_id"));
    $prod_code = $p ? $p['prod_code'] : '';
}

// Check if existing record exists
$exist_q = mysqli_query($conn, "SELECT prod_id FROM product_platform_sku WHERE prod_id = $prod_id");
if ($exist_q && mysqli_num_rows($exist_q) > 0) {
    $updates = ["vat_stock = $vat_stock", "non_vat_stock = $non_vat_stock"];
    if (isset($data['shopee_sku'])) $updates[] = "shopee_sku = '$shopee_sku'";
    if (isset($data['lazada_sku'])) $updates[] = "lazada_sku = '$lazada_sku'";
    if (isset($data['tiktok_sku'])) $updates[] = "tiktok_sku = '$tiktok_sku'";
    $sql = "UPDATE product_platform_sku SET " . implode(", ", $updates) . " WHERE prod_id = $prod_id";
} else {
    $sql = "INSERT INTO product_platform_sku 
            (prod_id, prod_code, shopee_sku, lazada_sku, tiktok_sku, vat_stock, non_vat_stock) 
            VALUES 
            ($prod_id, '$prod_code', '$shopee_sku', '$lazada_sku', '$tiktok_sku', $vat_stock, $non_vat_stock)";
}

if (mysqli_query($conn, $sql)) {
    echo json_encode(["success" => true, "message" => "บันทึกข้อมูลเรียบร้อย"]);
} else {
    echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาด: " . mysqli_error($conn)]);
}
?>