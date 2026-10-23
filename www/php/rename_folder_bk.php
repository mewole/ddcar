<?php
include_once "auth_guard.php";
require_staff_or_admin();

include "db.php";
    $name  = $_POST['name'];   
    $old_name  = $_POST['old_name'];

    $sql = "UPDATE tree SET title = '$name' WHERE title = '$old_name'";
    $result = mysqli_query($conn, $sql);
            
    if($result){
        echo "success";      
    }else{
        echo "fail";      
    }    
    mysqli_close($conn);
?>