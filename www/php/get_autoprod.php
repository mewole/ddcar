<?php
/**
 * get_autoprod.php
 * คืนค่ารายการสินค้าทั้งหมดสำหรับ Autocomplete ในหน้าขายและจัดการสต็อก
 * ป้องกันการรั่วไหลของต้นทุน: ตัด prod_cost ออกทั้งหมด และบังคับยืนยันตัวตน
 */
if (!ob_start("ob_gzhandler")) {
    ob_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-cache, no-store, must-revalidate');

include "db.php";
include_once "auth_guard.php";
require_login(); // บังคับว่าต้องเข้าสู่ระบบก่อนดึงข้อมูลแคตตาล็อกสินค้า

// ดึงเฉพาะรหัส ชื่อ และราคาขาย (ปลีก/ส่ง) ไม่ส่งต้นทุนออกไปเด็ดขาด
$query = "SELECT prod_id, prod_name, prod_code, prod_price, prod_pricesend FROM product";     
$result = mysqli_query($conn, $query);

$output = array();
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $output[] = $row;
    }
}

if ($result) {
    echo json_encode($output);		   
} else {
    echo "fail";
}
mysqli_close($conn);
?>