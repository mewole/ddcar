<?php
    include "db.php";
include_once "auth_guard.php";
require_login();
    $name  = $_POST['name']; 
    $type  = $_POST['type'];
    
    if($type == "โกดัง"){
        $sql = "INSERT INTO warehouse_detail (ware_name) VALUE ('$name')"; 
    }else if($type == "หน้าร้าน"){
        $sql = "INSERT INTO shop_detail (shop_name) VALUE ('$name')"; 
    }   
    $result = mysqli_query($conn, $sql);    

    if($result){
        echo "success";       
    }else{
        echo "fail";        
    }	
	mysqli_close($conn);
?>