<?php
include_once "auth_guard.php";
require_login();

include "db.php";   
  
    $sql = "SELECT url,version FROM version";
    $result = mysqli_query($conn, $sql);
   
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){
            $output[] = $row;
        }
    }
    
    if($result){
        echo json_encode($output);		   
    }else{
        echo "fail";
    }
    mysqli_close($conn);
?>