<?php
    include "db.php";
include_once "auth_guard.php";
require_login();
    $bill_id = $_POST['bill_id'];

    $query = "SELECT s.order_number,c.cus_name,s.date_time,s.payment_type,s.branch_id,s.type,s.sum_price,
    s.total_discount,s.total_price,s.get_price,s.chng,u.user_name,i.item_id,i.prod_id,p.prod_name,p.prod_code,i.prod_price,
    i.prod_amount,s.credit_id,s.comment
    FROM sale_order s INNER JOIN customer c ON s.customer_id = c.cus_id INNER JOIN user u ON s.user_id = u.user_id 
    INNER JOIN sale_order_item i ON s.order_id = i.order_id INNER JOIN product p ON i.prod_id = p.prod_id 
    WHERE s.order_id = '$bill_id'";
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0){    
      while($row = mysqli_fetch_array($result)){
        $type = $row['type'];
        $id = $row['branch_id'];
        if($type == "โกดัง"){
            $sql = "SELECT ware_name FROM warehouse_detail WHERE ware_id = '$id'";
        }else if($type == "หน้าร้าน"){
            $sql = "SELECT shop_name FROM shop_detail WHERE shop_id = '$id'";
        }        
        $result1 = mysqli_query($conn, $sql);
        if(mysqli_num_rows($result1) > 0){    
            while($row1 = mysqli_fetch_array($result1)){            
                array_push($row,$row1);
            }   
        }
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