<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('HTTP/1.1 405 Method Not Allowed'); exit; }
    include "db.php";
    $bill_id = $_POST['bill_id'];
    $prod_id = $_POST['prod_id'];
    $sum = $_POST['sum'];   
    $branch_id  = $_POST['b_id'];
    $type  = $_POST['type'];
    $pay  = $_POST['pay'];    
    $credit_id  = $_POST['credit_id'];
    $amt = $_POST['amt'];

    mysqli_begin_transaction($conn);
    $query = "UPDATE sale_order_item SET prod_amount = prod_amount-'$amt' WHERE order_id = '$bill_id' AND prod_id = '$prod_id'";
    $result = mysqli_query($conn, $query);
    if(!$result){
        mysqli_rollback($conn);
        echo "fail";
        exit;
    }

    $sql_up = "UPDATE sale_order SET sum_price = sum_price-'$sum',total_price = total_price-'$sum' WHERE order_id = '$bill_id'"; 
    $result_up = mysqli_query($conn, $sql_up);
    if(!$result_up){
        mysqli_rollback($conn);
        echo "fail";
        exit;
    }

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

    if($pay != "เงินสด"){
        $sql = "UPDATE credit SET credit_price = credit_price-'$sum' WHERE credit_id = '$credit_id'";  
        $results = mysqli_query($conn, $sql); 
        if(!$results){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }
    }

    mysqli_commit($conn); 
    echo "success";
    mysqli_close($conn);
?>