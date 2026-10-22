<?php
require_once "db.php";
include_once "auth_guard.php";
require_login();
header("Content-Type: application/json; charset=utf-8");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data) {
    $data = $_POST;
}

$id = isset($data['id']) ? intval($data['id']) : 0;
$tax_invoice_no = isset($data['tax_invoice_no']) ? mysqli_real_escape_string($conn, trim($data['tax_invoice_no'])) : '';
$notes = isset($data['notes']) ? mysqli_real_escape_string($conn, trim($data['notes'])) : '';

if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "ไม่พบรหัสรายการที่ต้องการเคลียร์"]);
    exit;
}

$sql = "UPDATE pending_input_vat 
        SET status = 'cleared', 
            tax_invoice_no = '$tax_invoice_no', 
            notes = '$notes', 
            cleared_at = NOW() 
        WHERE id = $id";

if (mysqli_query($conn, $sql)) {
    echo json_encode(["success" => true, "message" => "บันทึกเคลียร์ใบกำกับภาษีเรียบร้อย"]);
} else {
    echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาด: " . mysqli_error($conn)]);
}
?>