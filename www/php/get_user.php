<?php
include_once "auth_guard.php";
require_login();

/**
 * get_user.php
 * ดึงรายชื่อผู้ใช้งานในระบบ
 * ป้องกันความปลอดภัย: ส่งเฉพาะ user_id, user_name, phone, user_role (ไม่ส่งรหัสผ่าน Hash เด็ดขาด)
 */
include "db.php";
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
$output = array();    

$query = "SELECT user_id, user_name, phone, user_role FROM user ORDER BY user_id ASC";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {    
    while ($row = mysqli_fetch_assoc($result)) {      
        $output[] = $row;  
    }   
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>