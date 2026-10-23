<?php
include_once "auth_guard.php";
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('HTTP/1.1 405 Method Not Allowed'); exit; }  
    include "db.php";
    $cus_id  = $_POST['cus_id'];

    mysqli_begin_transaction($conn);
    $sql = "DELETE FROM customer WHERE cus_id = '$cus_id'";
    $result = mysqli_query($conn, $sql);    
    if(!$result){
        mysqli_rollback($conn);
        echo "fail";
        exit;
    }    
    $sql = "DELETE FROM detail_cus WHERE cus_id = '$cus_id'";
    $result = mysqli_query($conn, $sql);  
    if(!$result){
        mysqli_rollback($conn);
        echo "fail";
        exit;
    }        
    mysqli_commit($conn); 
    echo "success";  
    mysqli_close($conn);
?>