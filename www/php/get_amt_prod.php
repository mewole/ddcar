<?php
    include "db.php";
include_once "auth_guard.php";
require_login();
    $prod_id = $_POST['prod'];   

    $query = "SELECT amount FROM warehouse WHERE prod_id = '$prod_id'";
    $result = mysqli_query($conn, $query);

    $sum_w = 0;
    if(mysqli_num_rows($result) > 0){    
      while($row = mysqli_fetch_array($result)){            
        $sum_w = $sum_w + $row['amount'];      
      }   
    }

    $query = "SELECT amount FROM shop WHERE prod_id = '$prod_id'";
    $result = mysqli_query($conn, $query);

    $sum_s = 0;
    if(mysqli_num_rows($result) > 0){    
      while($row = mysqli_fetch_array($result)){            
        $sum_s = $sum_s + $row['amount'];      
      }   
    }

    if($result){
        $sum = $sum_w + $sum_s;
        echo $sum;		   
    }else{
        echo "fail";
    }
	  mysqli_close($conn);
?>