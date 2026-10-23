<?php
include_once "auth_guard.php";
require_login();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-User-Id");
header("Content-Type: text/html; charset=UTF-8");

include "db.php";
$ware_id = isset($_POST['ware_id']) ? mysqli_real_escape_string($conn, $_POST['ware_id']) : (isset($_GET['ware_id']) ? mysqli_real_escape_string($conn, $_GET['ware_id']) : '');
$type = isset($_POST['type']) ? trim($_POST['type']) : (isset($_GET['type']) ? trim($_GET['type']) : '');
$prod_id = isset($_POST['prod_id']) ? intval($_POST['prod_id']) : (isset($_GET['prod_id']) ? intval($_GET['prod_id']) : 0);
$output = array();   
   
if ($prod_id > 0 && !empty($ware_id)) {
    if ($type == "โกดัง" || $type == "ware" || $type == "warehouse" || strpos($type, 'ware') !== false || strpos($type, 'ด') !== false) {
        $query = "SELECT amount FROM warehouse WHERE prod_id = '$prod_id' AND ware_id = '$ware_id' LIMIT 1";
    } else {
        $query = "SELECT amount FROM shop WHERE prod_id = '$prod_id' AND shop_id = '$ware_id' LIMIT 1";
    }        
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {    
        $row = mysqli_fetch_assoc($result);
        $output[] = $row;                  
    }
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>