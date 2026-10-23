<?php
include_once "auth_guard.php";
require_login();

/**
 * get_suppliers.php
 * คืนค่ารายชื่อร้านค้า/ซัพพลายเออร์ทั้งหมดในระบบ สำหรับ Autocomplete และ Datalist
 */
header('Content-Type: application/json; charset=utf-8');
include "db.php";
$suppliers = [];

$sql = "SELECT DISTINCT supplier_name AS name FROM (
            SELECT supplier_name FROM stock_in WHERE supplier_name IS NOT NULL AND TRIM(supplier_name) != ''
            UNION
            SELECT supplier_name FROM transfer_in WHERE supplier_name IS NOT NULL AND TRIM(supplier_name) != ''
            UNION
            SELECT supplier_name FROM product_supplier_sku WHERE supplier_name IS NOT NULL AND TRIM(supplier_name) != ''
            UNION
            SELECT source_from AS supplier_name FROM stock_in_item WHERE source_from IS NOT NULL AND TRIM(source_from) != ''
            UNION
            SELECT source_from AS supplier_name FROM transfer_in_item WHERE source_from IS NOT NULL AND TRIM(source_from) != ''
        ) AS all_sup
        ORDER BY name ASC";

$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $name = trim($row['name']);
        if (!empty($name) && !in_array($name, $suppliers)) {
            $suppliers[] = $name;
        }
    }
}

echo json_encode($suppliers, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>
