<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include_once "auth_guard.php";

$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$is_internal = ($client_ip === '127.0.0.1' || $client_ip === '::1' || strpos($client_ip, '100.') === 0 || strpos($client_ip, '192.168.') === 0);

if (!is_admin_or_owner() && !$is_internal) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(["success" => false, "error" => "Forbidden: ฟังก์ชันนี้สงวนสิทธิ์เฉพาะเครือข่ายภายในหรือผู้ดูแลระบบ"]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
include "db.php";

$tables = [];

// 1. ตาราง product_return (บันทึกหัวเอกสารรับคืนสินค้า RT26-xxxxx)
$sql1 = "CREATE TABLE IF NOT EXISTS product_return (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_code VARCHAR(50) NOT NULL UNIQUE,
    bill_id INT NULL,
    bill_number VARCHAR(50) NULL,
    order_sn VARCHAR(100) NULL,
    customer_id INT NULL,
    customer_name VARCHAR(255) NOT NULL,
    return_date DATE NOT NULL,
    actual_return_date DATE NOT NULL,
    return_type VARCHAR(50) NOT NULL DEFAULT 'ขายได้',
    refund_type VARCHAR(50) NOT NULL DEFAULT 'คืนเงินสด',
    total_refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_vat TINYINT(1) NOT NULL DEFAULT 0,
    tax_id VARCHAR(50) NULL,
    tax_address TEXT NULL,
    original_bill_date VARCHAR(50) NULL,
    original_bill_amount DECIMAL(12,2) DEFAULT 0.00,
    correct_amount DECIMAL(12,2) DEFAULT 0.00,
    diff_amount DECIMAL(12,2) DEFAULT 0.00,
    diff_vat DECIMAL(12,2) DEFAULT 0.00,
    credit_note_number VARCHAR(50) NULL,
    reason TEXT NOT NULL,
    item_images TEXT NULL,
    created_by VARCHAR(100) NOT NULL,
    approved_by VARCHAR(100) NULL,
    status VARCHAR(50) DEFAULT 'สมบูรณ์',
    created_at DATETIME NULL,
    KEY idx_return_code (return_code),
    KEY idx_bill_id (bill_id),
    KEY idx_actual_date (actual_return_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
mysqli_query($conn, $sql1);
$tables['product_return'] = mysqli_error($conn) ?: 'OK';

// 2. ตาราง product_return_item (รายการสินค้าในเอกสารรับคืน)
$sql2 = "CREATE TABLE IF NOT EXISTS product_return_item (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_id INT NOT NULL,
    return_code VARCHAR(50) NOT NULL,
    prod_id INT NOT NULL,
    prod_code VARCHAR(100) NOT NULL,
    prod_name VARCHAR(255) NOT NULL,
    qty INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    destination VARCHAR(50) NOT NULL DEFAULT 'stock',
    branch_type VARCHAR(50) DEFAULT 'หน้าร้าน',
    branch_id INT DEFAULT 1,
    damage_reason TEXT NULL,
    damage_images TEXT NULL,
    created_at DATETIME NULL,
    KEY idx_return_id (return_id),
    KEY idx_prod_id (prod_id),
    KEY idx_destination (destination)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
mysqli_query($conn, $sql2);
$tables['product_return_item'] = mysqli_error($conn) ?: 'OK';

// 3. ตาราง product_claim_supplier (ระบบติดตามเคลมร้านค้า/ซัพพลายเออร์)
$sql3 = "CREATE TABLE IF NOT EXISTS product_claim_supplier (
    id INT AUTO_INCREMENT PRIMARY KEY,
    claim_code VARCHAR(50) NOT NULL UNIQUE,
    return_id INT NULL,
    return_code VARCHAR(50) NULL,
    prod_id INT NOT NULL,
    prod_code VARCHAR(100) NOT NULL,
    prod_name VARCHAR(255) NOT NULL,
    qty INT NOT NULL,
    supplier_name VARCHAR(255) NOT NULL,
    claim_date DATE NOT NULL,
    supplier_ref_no VARCHAR(100) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'รอส่งเคลม',
    result_action VARCHAR(50) DEFAULT 'รอผล',
    damage_images TEXT NULL,
    notes TEXT NULL,
    refund_amount DECIMAL(12,2) DEFAULT 0.00,
    unresolved_action VARCHAR(50) DEFAULT 'none',
    branch_id INT DEFAULT 1,
    branch_type VARCHAR(50) DEFAULT 'หน้าร้าน',
    created_by VARCHAR(100) NOT NULL,
    closed_by VARCHAR(100) NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_claim_code (claim_code),
    KEY idx_status (status),
    KEY idx_claim_date (claim_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
mysqli_query($conn, $sql3);
$tables['product_claim_supplier'] = mysqli_error($conn) ?: 'OK';

mysqli_close($conn);

echo json_encode(['success' => true, 'tables' => $tables], JSON_UNESCAPED_UNICODE);
?>
