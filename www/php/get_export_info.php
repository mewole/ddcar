<?php
include_once "auth_guard.php";
require_login();

include "db.php";
header("Content-Type: application/json; charset=UTF-8");
mysqli_set_charset($conn, "utf8");

$exp_id = isset($_REQUEST['exp_id']) ? mysqli_real_escape_string($conn, $_REQUEST['exp_id']) : '';
$output = array();

if (!empty($exp_id)) {
    $query = "SELECT s.stock_out_number, s.ware_id, 
                     COALESCE(w.ware_name, 'โกดัง') AS ware_name, 
                     s.date_time, 
                     COALESCE(i.item_id, 0) AS item_id, 
                     COALESCE(i.prod_id, 0) AS prod_id, 
                     COALESCE(p.prod_name, 'สินค้า (ลบแล้ว)') AS prod_name, 
                     COALESCE(p.prod_code, '') AS prod_code, 
                     COALESCE(i.stock_out_cost, 0) AS stock_out_cost, 
                     COALESCE(i.amount, 0) AS amount, 
                     COALESCE(u.user_name, 'ผู้ใช้') AS user_name 
              FROM stock_out s 
              LEFT JOIN warehouse_detail w ON s.ware_id = w.ware_id 
              LEFT JOIN stock_out_item i ON s.stock_out_id = i.stock_out_id 
              LEFT JOIN product p ON i.prod_id = p.prod_id 
              LEFT JOIN user u ON s.user_id = u.user_id 
              WHERE s.stock_out_id = '$exp_id'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {    
        while ($row = mysqli_fetch_array($result)) {      
            $output[] = $row;  
        }   
    }
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>