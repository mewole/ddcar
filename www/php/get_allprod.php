<?php
include_once "auth_guard.php";
require_login();
include "db.php";
$output = array(); 
   
    $query = "SELECT * FROM product ORDER BY prod_name ASC";     
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0){
      ini_set('memory_limit', '-1');
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