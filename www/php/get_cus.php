<?php
include_once "auth_guard.php";
require_login();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-User-Id");
header("Content-Type: text/html; charset=UTF-8");

include "db.php";
$output = array();

$query = "SELECT cus_id, cus_name, cus_tel, cus_addr, cus_type FROM customer ORDER BY cus_id ASC";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {    
    while ($row = mysqli_fetch_assoc($result)) {      
        $output[] = $row;  
    }   
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>