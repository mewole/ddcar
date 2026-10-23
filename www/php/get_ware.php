<?php
include_once "auth_guard.php";
require_login();
include "db.php";
$output = array();   

    $query = "SELECT ware_id,ware_name FROM warehouse_detail ORDER BY ware_id ASC";
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0){    
      while($row = mysqli_fetch_array($result)){
        $ware_id = $row['ware_id'];    
      
        $query = "SELECT amount FROM warehouse WHERE ware_id = '$ware_id'";
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