<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
include "db.php";
$prod_id  = $_POST['prod_id'];
   
    $sql = "UPDATE product SET min_amount=0,day_transport=0 WHERE prod_id = '$prod_id'";
    $result = mysqli_query($conn, $sql);       
        
    if($result){
        echo "success";        
    }else{
        echo "fail";       
    }    
    mysqli_close($conn);
?>