<?php
/**
 * search_cus.php
 * ค้นหาข้อมูลลูกค้าสำหรับหน้าขายและจัดการระบบ (บังคับล็อกอิน + ป้องกันข้อมูลรั่วไหล)
 */
include "db.php";
include_once "auth_guard.php";
require_login();

header("Content-Type: application/json; charset=UTF-8");
$data = isset($_POST['phrase']) ? trim($_POST['phrase']) : '';
if ($data === '' && isset($_GET['phrase'])) $data = trim($_GET['phrase']);

$output = array();

// หากไม่มีคำค้นหา ไม่ส่งข้อมูลลูกค้าทั้งหมดออกไปเพื่อป้องกัน Data Dump
if ($data === '') {
    echo json_encode($output);
    mysqli_close($conn);
    exit;
}

$search_term = "%" . $data . "%";
$stmt = mysqli_prepare($conn, "SELECT cus_id, cus_name, phone, address FROM customer WHERE cus_name LIKE ? OR phone LIKE ? LIMIT 50");
mysqli_stmt_bind_param($stmt, "ss", $search_term, $search_term);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {    
    while ($row = mysqli_fetch_assoc($result)) {      
        $output[] = $row;
    }   
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>