<?php
include_once "auth_guard.php";
require_staff_or_admin();

include "db.php";
$output = array();
    $day = date("d");   
    $month = date("m"); 
    $txt = "หมดอายุ"; 
   
    mysqli_begin_transaction($conn);
    $query = "SELECT order_id,date_time FROM sale_order WHERE order_number NOT LIKE '%(cancel)%' 
    AND payment_type LIKE '%เครดิต%' AND payment_type NOT LIKE '%หมดอายุ%'";
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0){     
      while($row = mysqli_fetch_array($result)){  
        $id = $row['order_id'];
        $dt = $row['date_time']; 
        $date = explode(" ",$dt);
        $date2 = explode("-",$date[0]);
        if($date2[1] != $month && $day > 2){
            $query ="UPDATE sale_order SET payment_type = CONCAT(payment_type,'".$txt."') WHERE order_id = '$id'"; 
            $result2 = mysqli_query($conn, $query);
            if(!$result2){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }   
        }        
      }   
    }
    mysqli_commit($conn); 
    echo "success";
    mysqli_close($conn);
?>