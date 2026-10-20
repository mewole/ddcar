<?php
include "db.php";
$cus_id = isset($_POST['cus_id']) ? intval($_POST['cus_id']) : 0;   
$prod_id = isset($_POST['prod_id']) ? intval($_POST['prod_id']) : 0;
$output = array();       

if ($cus_id > 0 && $prod_id > 0) {
    $query = "SELECT cus_price FROM detail_cus WHERE prod_id = '$prod_id' AND cus_id = '$cus_id' LIMIT 1"; 
    $result = mysqli_query($conn, $query);   
    if ($result && mysqli_num_rows($result) > 0) {    
        $row = mysqli_fetch_assoc($result); 
        $output[] = $row;
    }
}

echo json_encode($output);
mysqli_close($conn);
?>