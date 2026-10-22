<?php
    include "db.php";
include_once "auth_guard.php";
require_login();   
    $cus_id  = $_POST['cus_id'];
    $total  = $_POST['total']; 
    $dt  = $_POST['dt'];  
    $user_id  = $_POST['user_id'];   
    $txt = "หมดอายุ";

    mysqli_begin_transaction($conn);
    $query = "SELECT order_id FROM sale_order WHERE order_number NOT LIKE '%(cancel)%'
        AND payment_type LIKE '%เครดิต%' AND payment_type NOT LIKE '%หมดอายุ%' AND customer_id = '$cus_id'";
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0){     
        while($row = mysqli_fetch_array($result)){
            $order_id = $row['order_id'];  
            $query ="UPDATE sale_order SET payment_type = CONCAT(payment_type,'".$txt."') WHERE order_id = '$order_id'"; 
            $result2 = mysqli_query($conn, $query);   
            if(!$result2){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }         
        }   
    }   
    $sql = "SELECT credit_num,count FROM credit ORDER BY credit_id DESC LIMIT 1"; 
    $result = mysqli_query($conn, $sql);    
   
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){  
            $number = $row['credit_num'];
            $year_old = substr($number,1,2);
            $year_cur = date("y");
            if($year_cur == $year_old){
                $count = $row['count']+1;
                $year = "C" . $year_cur . "-";
                $order_number = $year . str_pad($count, 5, "0",STR_PAD_LEFT);
                $sql2 = "INSERT INTO credit (credit_num,cus_id,credit_price,date_time,user_id,count) 
                VALUE ('$order_number','$cus_id','$total','$dt','$user_id','$count')"; 
                $result2 = mysqli_query($conn, $sql2);
                if(!$result2){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }                
            }else{
                $year = "C" . $year_cur . "-";
                $order_number = $year . str_pad(1, 5, "0",STR_PAD_LEFT);
                $sql3 = "INSERT INTO credit (credit_num,cus_id,credit_price,date_time,user_id,count) 
                VALUE ('$order_number','$cus_id','$total','$dt','$user_id',1)"; 
                $result3 = mysqli_query($conn, $sql3);
                if(!$result3){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }               
            }           
        }
    }else{
        $year = "C" . date("y") . "-";
        $order_number = $year . str_pad(1, 5, "0",STR_PAD_LEFT);
        $sql1 = "INSERT INTO credit (credit_num,cus_id,credit_price,date_time,user_id,count) 
        VALUE ('$order_number','$cus_id','$total','$dt','$user_id',1)"; 
        $result1 = mysqli_query($conn, $sql1);
        if(!$result1){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }       
    } 
    mysqli_commit($conn); 
    echo $order_number;
    mysqli_close($conn);
?>