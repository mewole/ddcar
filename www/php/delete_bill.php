<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
include "db.php";
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('HTTP/1.1 405 Method Not Allowed'); exit; } 
    $bill_id  = $_POST['bill_id'];
    $branch_id  = $_POST['b_id'];
    $type  = $_POST['type'];
    $pay  = $_POST['pay'];
    $total  = $_POST['total'];
    $credit_id  = $_POST['credit_id'];
    $remark = $_POST['remark'];
    $user_id = $_SESSION['user_id'];
    $txt = "(cancel)";
    date_default_timezone_set("Asia/Bangkok");
    $datetime = date("Y-m-d H:i:s");

    mysqli_begin_transaction($conn);
    $query = "SELECT prod_amount,prod_id FROM sale_order_item WHERE order_id = '$bill_id'";  
    $result = mysqli_query($conn, $query);
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){
            $amt = $row['prod_amount']; 
            $prod_id = $row['prod_id']; 
            if($type == "โกดัง"){
                $query1 = "UPDATE warehouse SET amount=amount+'$amt' WHERE ware_id = '$branch_id' AND prod_id = '$prod_id'";  
            }else if($type == "หน้าร้าน"){
                $query1 = "UPDATE shop SET amount=amount+'$amt' WHERE shop_id = '$branch_id' AND prod_id = '$prod_id'";  
            }            
            $result1 = mysqli_query($conn, $query1); 
            if(!$result1){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }              
        }   
    } 
  
    $sql = "UPDATE sale_order SET order_number = CONCAT(order_number,'".$txt."') WHERE order_id = '$bill_id'";  
    $result = mysqli_query($conn, $sql); 
    if(!$result){
        mysqli_rollback($conn);
        echo "fail";
        exit;
    }
    
    if($pay != "เงินสด"){
        $sql = "UPDATE credit SET credit_price = credit_price-'$total' WHERE credit_id = '$credit_id'";  
        $result = mysqli_query($conn, $sql); 
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }
    }

    $sql = "INSERT INTO sale_order_remove (order_id, user_id, date_time, remark) VALUES ('$bill_id','$user_id','$datetime','$remark')";  
    $result = mysqli_query($conn, $sql);
    if(!$result){
        mysqli_rollback($conn);
        echo "fail";
        exit;
    }
   
    mysqli_commit($conn);
    echo "success"; 
    mysqli_close($conn);
?>