<?php
require_once "db.php";
header("Content-Type: application/json; charset=utf-8");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?: $_POST;

$id = isset($data['id']) ? intval($data['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "ไม่พบรหัสรายการที่ต้องการลบ"]);
    exit;
}

// Fetch platform sku before delete to clean up bundle_recipes if needed
$chk = mysqli_query($conn, "SELECT platform_sku, mapping_type FROM platform_sku_mapping WHERE id = $id");
if ($chk && mysqli_num_rows($chk) > 0) {
    $row = mysqli_fetch_assoc($chk);
    if ($row['mapping_type'] === 'bundle') {
        $sku_esc = mysqli_real_escape_string($conn, $row['platform_sku']);
        @mysqli_query($conn, "DELETE FROM bundle_recipes WHERE bundle_sku = '$sku_esc'");
    }
}

$sql = "DELETE FROM platform_sku_mapping WHERE id = $id";
if (mysqli_query($conn, $sql)) {
    echo json_encode(["success" => true, "message" => "ลบรายการจับคู่เรียบร้อย"]);
} else {
    echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาด: " . mysqli_error($conn)]);
}
?>
