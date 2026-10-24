<?php
include_once "auth_guard.php";
require_login();

/**
 * get_autoprod.php
 * คืนค่ารายการสินค้าทั้งหมดสำหรับ Autocomplete ในหน้าขายและจัดการสต็อก
 * ป้องกันการรั่วไหลของต้นทุน: ตัด prod_cost ออกทั้งหมด
 */
if (!ob_start("ob_gzhandler")) {
    ob_start();
}
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-User-Id");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-cache, no-store, must-revalidate');

include "db.php";

$user_role = get_current_user_role();
$can_view_cost = in_array($user_role, ['เจ้าของร้าน', 'ผู้ดูแลระบบ', 'แอดมิน']);

if ($can_view_cost) {
    // แอดมินและเจ้าของร้านสามารถเห็นต้นทุนเดิมเพื่ออ้างอิงในการลงรับสินค้าเข้าได้
    $query = "SELECT prod_id, prod_name, prod_code, prod_price, prod_pricesend, prod_cost, prod_cost as cost FROM product";
} else {
    // พนักงานและสิทธิ์ดูคลังสินค้า: ไม่ส่งต้นทุนออกไปเด็ดขาด (Blind Cost)
    $query = "SELECT prod_id, prod_name, prod_code, prod_price, prod_pricesend FROM product";
}
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