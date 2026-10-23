<?php
include_once "auth_guard.php";
require_staff_or_admin();

include "db.php";
$sql = "UPDATE bell SET status = 'normal'";
    $result = mysqli_query($conn, $sql);
    if($result){
        echo "success";
    }else{
        echo "fail";
    }  
    mysqli_close($conn);
?>