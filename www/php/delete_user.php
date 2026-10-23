<?php
include_once "auth_guard.php";
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('HTTP/1.1 405 Method Not Allowed'); exit; }  
    include "db.php"; 
    $name  = $_POST['name'];
  
    $sql = "DELETE FROM user WHERE user_name = '$name'";
    $result = mysqli_query($conn, $sql);      
        
    if($result){
        echo "success";       
    }else{
        echo "fail";      
    }    
    mysqli_close($conn);
?>