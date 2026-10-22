<?php
include_once "auth_guard.php";
require_login();
    include "db.php";
    $output = array();    

    $query = "SELECT ware_id,ware_name FROM warehouse_detail ORDER BY ware_id ASC";
    $result = mysqli_query($conn, $query);
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){        
          array_push($row,"โกดัง");
          $output[] = $row;  
        }   
      }

    $query = "SELECT shop_id,shop_name FROM shop_detail ORDER BY shop_id ASC";
    $result = mysqli_query($conn, $query);
    if(mysqli_num_rows($result) > 0){    
      while($row = mysqli_fetch_array($result)){        
        array_push($row,"หน้าร้าน");
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