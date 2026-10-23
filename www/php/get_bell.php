<?php
include_once "auth_guard.php";
require_login();
include "db.php";
$sql = "SELECT status FROM bell"; 
    $result = mysqli_query($conn, $sql);    
   
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){
            echo $row['status'];
        }
    }else{
       echo "fail";
    } 
    mysqli_close($conn);
?>