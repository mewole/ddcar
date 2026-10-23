<?php
include_once "auth_guard.php";
require_login();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-User-Id");
header("Content-Type: text/html; charset=UTF-8");

include "db.php";
$cus_id = isset($_POST['cus_id']) ? mysqli_real_escape_string($conn, $_POST['cus_id']) : '';   
$ware_id = isset($_POST['ware_id']) ? mysqli_real_escape_string($conn, $_POST['ware_id']) : '';
$type = isset($_POST['type']) ? trim($_POST['type']) : '';
$output = array();

$query = "";
if (isset($_POST['qr']) && trim($_POST['qr']) !== '') {
    $qr = mysqli_real_escape_string($conn, trim($_POST['qr']));    
    $query = "SELECT * FROM product WHERE prod_code = '$qr' LIMIT 1";
} else if (isset($_POST['prod_id']) && trim($_POST['prod_id']) !== '') {
    $prod_id = mysqli_real_escape_string($conn, trim($_POST['prod_id'])); 
    $query = "SELECT * FROM product WHERE prod_id = '$prod_id' LIMIT 1";
}

if ($query !== '') {
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {    
        while ($row = mysqli_fetch_array($result)) { 
            $p_id = $row['prod_id']; 
            if (!empty($cus_id)) {
                $query1 = "SELECT cus_price FROM detail_cus WHERE prod_id = '$p_id' AND cus_id = '$cus_id' LIMIT 1"; 
                $result1 = mysqli_query($conn, $query1);   
                if ($result1 && mysqli_num_rows($result1) > 0) {    
                    while ($row1 = mysqli_fetch_array($result1)) { 
                        $cus_price = $row1['cus_price'];
                        array_push($row, $cus_price);              
                    }   
                }
            }
            if ($type == "โกดัง" || $type == "ware" || $type == "warehouse" || strpos($type, 'ware') !== false || strpos($type, 'ด') !== false) {
                $query2 = "SELECT amount FROM warehouse WHERE prod_id = '$p_id' AND ware_id = '$ware_id' LIMIT 1";
            } else {
                $query2 = "SELECT amount FROM shop WHERE prod_id = '$p_id' AND shop_id = '$ware_id' LIMIT 1";
            }        
            $result2 = mysqli_query($conn, $query2);
            if ($result2 && mysqli_num_rows($result2) > 0) {    
                while ($row2 = mysqli_fetch_array($result2)) {      
                    $row['min_amount'] = $row2['amount'];
                }   
            } else {
                $row['min_amount'] = 0;
            } 
            $output[] = $row;  
        }   
    }
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>