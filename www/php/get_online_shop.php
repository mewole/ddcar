<?php
    include "db.php";
include_once "auth_guard.php";
require_login();
    $output = array();    

    $query = "SELECT * FROM online_shop ORDER BY online_id ASC";
    $result = mysqli_query($conn, $query);
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