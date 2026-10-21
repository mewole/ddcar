<?php
require_once "db.php";
header("Content-Type: application/json; charset=utf-8");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data) {
    $data = $_POST;
}

$supplier_name = isset($data['supplier_name']) ? mysqli_real_escape_string($conn, trim($data['supplier_name'])) : '';
$prod_code = isset($data['prod_code']) ? mysqli_real_escape_string($conn, trim($data['prod_code'])) : '';
$prod_name = isset($data['prod_name']) ? mysqli_real_escape_string($conn, trim($data['prod_name'])) : '';
$qty = isset($data['qty']) ? intval($data['qty']) : 1;
if ($qty < 1) $qty = 1;
$cost_amount = isset($data['cost_amount']) ? floatval($data['cost_amount']) : 0;
$pending_vat = isset($data['pending_vat_amount']) ? floatval($data['pending_vat_amount']) : round($cost_amount * 0.07, 2);
$platform = isset($data['platform']) ? mysqli_real_escape_string($conn, trim($data['platform'])) : 'Online';
$order_date = isset($data['order_date']) ? mysqli_real_escape_string($conn, trim($data['order_date'])) : date('Y-m-d');
$ref_invoice = isset($data['ref_invoice']) ? mysqli_real_escape_string($conn, trim($data['ref_invoice'])) : '';
$notes = isset($data['notes']) ? mysqli_real_escape_string($conn, trim($data['notes'])) : '';

if (empty($supplier_name) || empty($prod_name)) {
    echo json_encode(["success" => false, "message" => "กรุณากรอกชื่อซัพพลายเออร์และชื่อสินค้า"]);
    exit;
}

$sql = "INSERT INTO pending_input_vat 
        (supplier_name, ref_invoice, order_date, prod_code, prod_name, qty, cost_amount, pending_vat_amount, platform, status, notes) 
        VALUES 
        ('$supplier_name', '$ref_invoice', '$order_date', '$prod_code', '$prod_name', $qty, $cost_amount, $pending_vat, '$platform', 'pending_invoice', '$notes')";

if (mysqli_query($conn, $sql)) {
    echo json_encode(["success" => true, "message" => "เพิ่มรายการเข้าคิวตามบิลภาษีซื้อเรียบร้อย", "id" => mysqli_insert_id($conn)]);
} else {
    echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาด: " . mysqli_error($conn)]);
}
?>