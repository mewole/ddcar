<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
require_once "db.php";
header("Content-Type: application/json; charset=utf-8");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data) {
    $data = $_POST;
    if (isset($data['children']) && is_string($data['children'])) {
        $data['children'] = json_decode($data['children'], true);
    }
}

$bundle_sku = isset($data['bundle_sku']) ? mysqli_real_escape_string($conn, trim($data['bundle_sku'])) : '';
$bundle_name = isset($data['bundle_name']) ? mysqli_real_escape_string($conn, trim($data['bundle_name'])) : '';
$children = isset($data['children']) && is_array($data['children']) ? $data['children'] : [];

if (empty($bundle_sku)) {
    echo json_encode(["success" => false, "message" => "กรุณาระบุ Bundle Master SKU"]);
    exit;
}

mysqli_begin_transaction($conn);
try {
    // Clear old recipe components for this bundle
    $del = mysqli_query($conn, "DELETE FROM bundle_recipes WHERE bundle_sku = '$bundle_sku'");
    if (!$del) {
        throw new Exception(mysqli_error($conn));
    }

    // Insert new recipe components
    foreach ($children as $c) {
        $child_sku = mysqli_real_escape_string($conn, trim($c['child_sku'] ?? ''));
        $child_name = mysqli_real_escape_string($conn, trim($c['child_name'] ?? ''));
        $ratio = intval($c['ratio'] ?? 1);
        if ($ratio < 1) $ratio = 1;
        $is_hidden_bom = !empty($c['is_hidden_bom']) ? 1 : 0;

        if (!empty($child_sku)) {
            $ins = mysqli_query($conn, "INSERT INTO bundle_recipes 
                (bundle_sku, bundle_name, child_sku, child_name, ratio, is_hidden_bom) 
                VALUES ('$bundle_sku', '$bundle_name', '$child_sku', '$child_name', $ratio, $is_hidden_bom)");
            if (!$ins) {
                throw new Exception(mysqli_error($conn));
            }
        }
    }

    mysqli_commit($conn);
    echo json_encode(["success" => true, "message" => "บันทึกสูตรสินค้าชุดเรียบร้อย"]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
}
?>