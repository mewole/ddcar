<?php
include_once "auth_guard.php";
require_login();

/**
 * search_cus.php
 * ค้นหาข้อมูลลูกค้าสำหรับหน้าขายและจัดการระบบ (web_sale.html, select_cus.html)
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-User-Id");
header("Content-Type: application/json; charset=UTF-8");

include "db.php";
$data = isset($_POST['phrase']) ? trim($_POST['phrase']) : '';
if ($data === '' && isset($_GET['phrase'])) $data = trim($_GET['phrase']);
if ($data === '' && isset($_POST['search'])) $data = trim($_POST['search']);
if ($data === '' && isset($_GET['search'])) $data = trim($_GET['search']);

$output = array();

if ($data !== '') {
    $search_term = "%" . $data . "%";
    $stmt = mysqli_prepare($conn, "SELECT cus_id, cus_name, cus_tel, cus_addr, cus_type FROM customer WHERE cus_name LIKE ? OR cus_tel LIKE ? ORDER BY cus_name ASC LIMIT 50");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $search_term, $search_term);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $output[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
} else {
    // If no search phrase, return top customers for instant selection
    $result = mysqli_query($conn, "SELECT cus_id, cus_name, cus_tel, cus_addr, cus_type FROM customer ORDER BY cus_id ASC LIMIT 100");
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $output[] = $row;
        }
    }
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>