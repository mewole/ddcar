<?php
include_once "auth_guard.php";
require_login();

include "db.php";
header("Content-Type: application/json; charset=UTF-8");
mysqli_set_charset($conn, "utf8");

$credit_id = isset($_REQUEST['credit_id']) ? mysqli_real_escape_string($conn, $_REQUEST['credit_id']) : '';
$output = array();

if (!empty($credit_id)) {
    $query = "SELECT s.order_id, s.order_number, 
                     COALESCE(c.cus_name, 'ลูกค้าทั่วไป') AS cus_name, 
                     s.total_price, s.date_time, s.payment_type, 
                     COALESCE(d.credit_num, '-') AS credit_num 
              FROM sale_order s 
              LEFT JOIN customer c ON s.customer_id = c.cus_id 
              LEFT JOIN credit d ON s.credit_id = d.credit_id
              WHERE s.credit_id = '$credit_id' AND s.order_number NOT LIKE '%(cancel)%'";
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