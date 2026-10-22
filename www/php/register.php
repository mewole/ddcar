<?php
    include "db.php";
include_once "auth_guard.php";
require_login();
    $name  = $_POST['user']; 
    $pass  = $_POST['pass'];   
    $phone  = $_POST['phone'];   
    $role  = $_POST['role'];
    $hash_pass = password_hash("$pass", PASSWORD_DEFAULT);

    $sql = "INSERT INTO user (user_name,pass,phone,user_role) VALUE ('$name','$hash_pass','$phone','$role')"; 
    $result = mysqli_query($conn, $sql); 

    if($result){
        echo "success";           
    }else{
        echo "fail";      
    }	
	mysqli_close($conn);
?>