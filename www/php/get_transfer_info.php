<?php
include_once "auth_guard.php";
require_login();

include "db.php";
header("Content-Type: text/html; charset=UTF-8");
mysqli_set_charset($conn, "utf8");

$imp_id = isset($_REQUEST['imp_id']) ? mysqli_real_escape_string($conn, $_REQUEST['imp_id']) : '';
$output = array();

if (!empty($imp_id)) {
    $query = "SELECT s.tran_number, s.shop_id, COALESCE(w.shop_name, 'หน้าร้าน') AS shop_name, s.date_time, s.total_tran,
                     s.supplier_name, s.ref_invoice,
                     i.item_id, i.prod_id, COALESCE(p.prod_name, 'สินค้า (ลบแล้ว)') AS prod_name, 
                     COALESCE(i.tran_cost, 0) AS tran_cost, COALESCE(i.amount, 0) AS amount, 
                     COALESCE(i.source_from, s.supplier_name, '-') AS source_from, i.supplier_sku,
                     COALESCE(u.user_name, 'ผู้ใช้') AS user_name, COALESCE(p.prod_code, '') AS prod_code, COALESCE(p.prod_price, 0) AS prod_price
              FROM transfer_in s 
              LEFT JOIN shop_detail w ON s.shop_id = w.shop_id 
              LEFT JOIN transfer_in_item i ON s.tran_id = i.tran_id 
              LEFT JOIN product p ON i.prod_id = p.prod_id 
              LEFT JOIN user u ON s.user_id = u.user_id 
              WHERE s.tran_id = '$imp_id'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {    
        while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {      
            $output[] = $row;  
        }   
    }
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>
