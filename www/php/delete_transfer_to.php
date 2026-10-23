<?php
include_once "auth_guard.php";
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('HTTP/1.1 405 Method Not Allowed'); exit; }  
    include "db.php"; 
    $tf_id  = $_POST['imp_id'];
    $ware_id  = $_POST['ware_id'];
    $shop_id  = $_POST['shop_id'];
    $txt = "(cancel)"; 

    mysqli_begin_transaction($conn);
    $query = "SELECT prod_amount,prod_id FROM transfer_record_item WHERE tf_id = '$tf_id'";  
    $result = mysqli_query($conn, $query);
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){
            $amt = $row['prod_amount']; 
            $prod_id = $row['prod_id'];           
            $query = "UPDATE shop SET amount=amount+'$amt' WHERE shop_id = '$shop_id' AND prod_id = '$prod_id'";                 
            $result1 = mysqli_query($conn, $query); 
            if(!$result1){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }
            $sql_up = "UPDATE warehouse SET amount = amount-'$amt' WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";
            $result_up = mysqli_query($conn, $sql_up);    
            if(!$result_up){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }              
        }   
    }
  
    $sql = "UPDATE transfer_record SET remark = '$txt' WHERE tf_id = '$tf_id'";  
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