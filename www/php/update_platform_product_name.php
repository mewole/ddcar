<?php
include_once "auth_guard.php";
require_login();
require_once "db.php";
header("Content-Type: application/json; charset=utf-8");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?: $_POST;

$id = isset($data['id']) ? intval($data['id']) : 0;
$platform_name = isset($data['platform_name']) ? trim($data['platform_name']) : '';

if ($id <= 0 || empty($platform_name)) {
    echo json_encode(["success" => false, "message" => "ข้อมูลไม่ถูกต้อง กรุณาระบุ ID และชื่อสินค้า"]);
    exit;
}

$esc_name = mysqli_real_escape_string($conn, $platform_name);

// Find existing record
$q = mysqli_query($conn, "SELECT channel_code, platform_sku FROM platform_sku_mapping WHERE id = $id LIMIT 1");
if (!$q || mysqli_num_rows($q) == 0) {
    echo json_encode(["success" => false, "message" => "ไม่พบรายการสินค้าที่ต้องการแก้ไข"]);
    exit;
}
$row = mysqli_fetch_assoc($q);
$ch_code = mysqli_real_escape_string($conn, $row['channel_code']);
$sku = mysqli_real_escape_string($conn, $row['platform_sku']);

$sql = "UPDATE platform_sku_mapping SET platform_name = '$esc_name' WHERE id = $id";
if (mysqli_query($conn, $sql)) {
    // Also update order items if any
    @mysqli_query($conn, "UPDATE platform_order_items SET platform_name = '$esc_name' 
                          WHERE channel_code = '$ch_code' AND platform_sku = '$sku'");

    echo json_encode([
        "success" => true,
        "message" => "อัปเดตชื่อสินค้าเรียบร้อย",
        "id" => $id,
        "platform_name" => $platform_name
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        "success" => false,
        "message" => "ไม่สามารถบันทึกได้: " . mysqli_error($conn)
    ], JSON_UNESCAPED_UNICODE);
}
?>
