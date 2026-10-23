<?php
include_once "auth_guard.php";
require_login();
include "db.php";
$output = array();   

    $query = "SELECT shop_id,shop_name FROM shop_detail ORDER BY shop_id ASC";
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0){    
      while($row = mysqli_fetch_array($result)){
        $shop_id = $row['shop_id'];    
      
        $query = "SELECT amount FROM shop WHERE shop_id = '$shop_id'";
        $result1 = mysqli_query($conn, $query);

        $sum = 0;
        if(mysqli_num_rows($result1) > 0){    
          while($row1 = mysqli_fetch_array($result1)){
            $sum = $sum + $row1['amount'];        
          }         
        }
        array_push($row,$sum);
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