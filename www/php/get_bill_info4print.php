<?php
    include "db.php";
include_once "auth_guard.php";
require_login();
    $bill_id = $_POST['bill_id'];

    $query = "SELECT s.order_number,c.cus_name,c.cus_tel,c.cus_addr,s.date_time,s.payment_type,s.sum_price,
    s.total_discount,s.total_price,s.get_price,s.chng,u.user_name,p.prod_name,p.prod_code,i.prod_price,
    i.prod_amount,i.item_id,s.comment,i.prod_id,s.type,s.branch_id,s.credit_id
    FROM sale_order s INNER JOIN customer c ON s.customer_id = c.cus_id INNER JOIN user u ON s.user_id = u.user_id 
    INNER JOIN sale_order_item i ON s.order_id = i.order_id INNER JOIN product p ON i.prod_id = p.prod_id 
    WHERE s.order_id = '$bill_id'";
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