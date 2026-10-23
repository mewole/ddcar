<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
include "db.php";
$user_id  = $_POST['user_id'];
    $user_name  = $_POST['name'];
    $role  = $_POST['role'];     
    $phone  = $_POST['phone'];
    $pass  = $_POST['pass'];
    $hash_pass = password_hash("$pass", PASSWORD_DEFAULT);

    if($pass == '********'){
        $sql = "UPDATE user SET user_name = '$user_name',user_role = '$role',phone = '$phone' WHERE user_id = '$user_id'";
        $result = mysqli_query($conn, $sql);
    }else{
        $sql = "UPDATE user SET user_name = '$user_name',user_role = '$role',phone = '$phone',pass = '$hash_pass' WHERE user_id = '$user_id'";
        $result = mysqli_query($conn, $sql);
    }    
            
    if($result){
        echo "success";       
    }else{        
        echo "fail";      
    }    
    mysqli_close($conn);
?>