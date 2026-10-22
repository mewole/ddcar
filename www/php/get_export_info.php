<?php
    include "db.php";
include_once "auth_guard.php";
require_login();
    $exp_id = $_POST['exp_id'];

    $query = "SELECT s.stock_out_number,s.ware_id,w.ware_name,s.date_time,i.item_id,i.prod_id,p.prod_name,p.prod_code,i.stock_out_cost,i.amount,u.user_name 
    FROM stock_out s INNER JOIN warehouse_detail w ON s.ware_id = w.ware_id INNER JOIN stock_out_item i ON s.stock_out_id = i.stock_out_id 
    INNER JOIN product p ON i.prod_id = p.prod_id INNER JOIN user u ON s.user_id = u.user_id WHERE s.stock_out_id = '$exp_id'";
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