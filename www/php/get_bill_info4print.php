<?php
include_once "auth_guard.php";
require_login();

include "db.php";
header("Content-Type: text/html; charset=UTF-8");
mysqli_set_charset($conn, "utf8");

$bill_id = isset($_REQUEST['bill_id']) ? mysqli_real_escape_string($conn, $_REQUEST['bill_id']) : '';
$output = array();

if (!empty($bill_id)) {
    $query = "SELECT s.order_number, 
                     COALESCE(c.cus_name, 'ลูกค้าทั่วไป') AS cus_name, 
                     COALESCE(c.cus_tel, '-') AS cus_tel, 
                     COALESCE(c.cus_addr, '-') AS cus_addr, 
                     s.date_time, s.payment_type, s.sum_price,
                     s.total_discount, s.total_price, s.get_price, s.chng, 
                     COALESCE(u.user_name, 'พนักงาน') AS user_name, 
                     COALESCE(p.prod_name, 'สินค้า (ลบแล้ว)') AS prod_name, 
                     COALESCE(p.prod_code, '') AS prod_code, 
                     COALESCE(i.prod_price, 0) AS prod_price,
                     COALESCE(i.prod_amount, 0) AS prod_amount, 
                     COALESCE(i.item_id, 0) AS item_id, 
                     s.comment, 
                     COALESCE(i.prod_id, 0) AS prod_id, 
                     s.type, s.branch_id, s.credit_id
              FROM sale_order s 
              LEFT JOIN customer c ON s.customer_id = c.cus_id 
              LEFT JOIN user u ON s.user_id = u.user_id 
              LEFT JOIN sale_order_item i ON s.order_id = i.order_id 
              LEFT JOIN product p ON i.prod_id = p.prod_id 
              WHERE s.order_id = '$bill_id'";
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