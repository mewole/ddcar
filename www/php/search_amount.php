<?php
include "db.php";
include_once "auth_guard.php";
require_login();    
$ware_id = isset($_POST['ware_id']) ? mysqli_real_escape_string($conn, $_POST['ware_id']) : '';
$type = isset($_POST['type']) ? $_POST['type'] : '';
$prod_id = isset($_POST['prod_id']) ? intval($_POST['prod_id']) : 0;
$output = array();   
   
if ($type == "โกดัง" || $type == "ware" || $type == "warehouse") {
    $query = "SELECT amount FROM warehouse WHERE prod_id = '$prod_id' AND ware_id = '$ware_id' LIMIT 1";
} else {
    $query = "SELECT amount FROM shop WHERE prod_id = '$prod_id' AND shop_id = '$ware_id' LIMIT 1";
}        
$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {    
    $row = mysqli_fetch_assoc($result);
    $output[] = $row;                  
}

echo json_encode($output);
mysqli_close($conn);
?>