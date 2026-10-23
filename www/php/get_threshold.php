<?php
include_once "auth_guard.php";
require_login();
include "db.php";
$output = array();   

    $query = "SELECT prod_name,prod_id,min_amount,day_transport,prod_code FROM product 
    WHERE min_amount != 0 AND day_transport != 0 ORDER BY min_amount ASC";
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