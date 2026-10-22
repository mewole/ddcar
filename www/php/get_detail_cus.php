<?php
    include "db.php";
include_once "auth_guard.php";
require_login();
    $cus_id = $_POST['cus_id'];  

    $query = "SELECT cus_name,cus_tel,cus_addr,cus_type FROM customer WHERE cus_id = '$cus_id'";
    $result = mysqli_query($conn, $query);
    if(mysqli_num_rows($result) > 0){    
      while($row = mysqli_fetch_array($result)){        
        $output[] = $row;  
      }   
    }

    $query = "SELECT d.cus_price,p.prod_name FROM detail_cus d INNER JOIN product p ON d.prod_id = p.prod_id
    WHERE cus_id = '$cus_id'";
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