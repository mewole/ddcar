<?php
include_once "auth_guard.php";
require_login();
include "db.php";
$name  = $_POST['cus_name'];   
    $phone  = $_POST['phone'];   
    $addr  = $_POST['addr'];  
    $type = $_POST['type']; 
   
    $sql = "INSERT INTO customer (cus_name,cus_tel,cus_addr,cus_type) VALUE ('$name','$phone','$addr','$type')"; 
    $result = mysqli_query($conn, $sql); 

    if($result){
        echo "success";    
    }else{
        echo "fail";  
    }	
	mysqli_close($conn);
?>