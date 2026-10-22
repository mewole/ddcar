<?php
require_once "db.php";
include_once "auth_guard.php";
require_login();
header("Content-Type: application/json; charset=utf-8");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?: $_POST;

$id = isset($data['id']) ? intval($data['id']) : 0;
$channel_code = isset($data['channel_code']) ? mysqli_real_escape_string($conn, trim($data['channel_code'])) : '';
$channel_name = isset($data['channel_name']) ? mysqli_real_escape_string($conn, trim($data['channel_name'])) : '';
$platform_type = isset($data['platform_type']) ? mysqli_real_escape_string($conn, trim($data['platform_type'])) : 'shopee';
$customer_name = isset($data['customer_name']) ? mysqli_real_escape_string($conn, trim($data['customer_name'])) : '';
$badge_color = isset($data['badge_color']) ? mysqli_real_escape_string($conn, trim($data['badge_color'])) : '#f97316';

if (empty($channel_code) || empty($channel_name)) {
    echo json_encode(["success" => false, "message" => "กรุณาระบุรหัสช่องทาง (code) และชื่อร้านค้า"]);
    exit;
}

if ($id > 0) {
    $sql = "UPDATE platform_channels SET 
            channel_name = '$channel_name',
            platform_type = '$platform_type',
            customer_name = '$customer_name',
            badge_color = '$badge_color'
            WHERE id = $id";
} else {
    $sql = "INSERT INTO platform_channels 
            (channel_code, channel_name, platform_type, customer_name, badge_color) 
            VALUES 
            ('$channel_code', '$channel_name', '$platform_type', '$customer_name', '$badge_color')
            ON DUPLICATE KEY UPDATE 
            channel_name = '$channel_name',
            platform_type = '$platform_type',
            customer_name = '$customer_name',
            badge_color = '$badge_color'";
}

if (mysqli_query($conn, $sql)) {
    echo json_encode(["success" => true, "message" => "บันทึกข้อมูลช่องทางร้านค้าสำเร็จ"]);
} else {
    echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาด: " . mysqli_error($conn)]);
}
?>
