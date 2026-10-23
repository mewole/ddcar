<?php
include_once "auth_guard.php";
require_login();
include "db.php";
$cus_id  = $_POST['cus_id'];   
    $name  = $_POST['cus_name'];   
    $phone  = $_POST['phone'];   
    $addr  = $_POST['addr'];    
    $type = $_POST['type'];
  
    $sql = "UPDATE customer SET cus_name = '$name',cus_tel = '$phone',cus_addr = '$addr',cus_type = '$type' WHERE cus_id = '$cus_id'"; 
    $result = mysqli_query($conn, $sql); 

    if($result){
        echo "success";       
    }else{
        echo "fail";   
    }	
	mysqli_close($conn);
?>