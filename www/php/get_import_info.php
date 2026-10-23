<?php
include_once "auth_guard.php";
require_login();

include "db.php";
header("Content-Type: text/html; charset=UTF-8");
$imp_id = isset($_POST['imp_id']) ? mysqli_real_escape_string($conn, $_POST['imp_id']) : '';
$output = array();

$query = "SELECT s.stock_number, s.ware_id, COALESCE(w.ware_name, 'โกดัง') AS ware_name, s.date_time, s.total_stock,
                 s.supplier_name, s.ref_invoice,
                 i.item_id, i.prod_id, COALESCE(p.prod_name, 'สินค้า (รหัสลบแล้ว)') AS prod_name, 
                 COALESCE(i.stock_cost, 0) AS stock_cost, COALESCE(i.amount, 0) AS amount, 
                 COALESCE(i.source_from, s.supplier_name, '-') AS source_from, i.supplier_sku,
                 COALESCE(u.user_name, 'แอดมิน') AS user_name, COALESCE(p.prod_code, '') AS prod_code, COALESCE(p.prod_price, 0) AS prod_price 
          FROM stock_in s 
          LEFT JOIN warehouse_detail w ON s.ware_id = w.ware_id 
          LEFT JOIN stock_in_item i ON s.stock_id = i.stock_id 
          LEFT JOIN product p ON i.prod_id = p.prod_id 
          LEFT JOIN user u ON s.user_id = u.user_id 
          WHERE s.stock_id = '$imp_id'";
$result = mysqli_query($conn, $query);

if($result && mysqli_num_rows($result) > 0){    
  while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){      
    $output[] = $row;  
  }   
}
if($result){
    echo json_encode($output, JSON_UNESCAPED_UNICODE);		   
}else{
    echo "fail";
}
mysqli_close($conn);
?>