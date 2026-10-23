<?php
include_once "auth_guard.php";
require_login();
include "db.php";
$id  = $_POST['id'];
    $type  = $_POST['type'];
    $name = $_POST['name'];    

    mysqli_begin_transaction($conn);     
    if($type == "โกดัง"){
        $sql = "UPDATE warehouse_detail SET ware_name = '$name' WHERE ware_id = '$id'"; 
        $result = mysqli_query($conn, $sql);   
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }     
    }else if($type == "หน้าร้าน"){
        $sql = "UPDATE shop_detail SET shop_name = '$name' WHERE shop_id = '$id'"; 
        $result = mysqli_query($conn, $sql);   
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }       
    }
    echo "success";
    mysqli_commit($conn);   
    mysqli_close($conn);
?>