<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
include "db.php";
$credit_id = $_POST['credit_id'];
    $txt = "(cancel)";
    
    $query = "SELECT s.order_id,s.order_number,c.cus_name,s.total_price,s.date_time,s.payment_type,d.credit_num 
    FROM sale_order s INNER JOIN customer c ON s.customer_id = c.cus_id INNER JOIN credit d ON s.credit_id = d.credit_id
    WHERE s.credit_id = '$credit_id' AND s.order_number NOT LIKE '%$txt%'";
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