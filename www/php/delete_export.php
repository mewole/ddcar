<?php
include_once "auth_guard.php";
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('HTTP/1.1 405 Method Not Allowed'); exit; }  
    include "db.php"; 
    $exp_id  = $_POST['exp_id'];
    $ware_id  = $_POST['ware_id'];
    $txt = "(cancel)"; 

    mysqli_begin_transaction($conn);
    $query = "SELECT amount,prod_id FROM stock_out_item WHERE stock_out_id = '$exp_id'";  
    $result = mysqli_query($conn, $query);
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){
            $amt = $row['amount']; 
            $prod_id = $row['prod_id'];           
            $query = "UPDATE warehouse SET amount=amount+'$amt' WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";                 
            $result1 = mysqli_query($conn, $query); 
            if(!$result1){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }              
        }   
    }
  
    $sql = "UPDATE stock_out SET stock_out_number = CONCAT(stock_out_number,'".$txt."') WHERE stock_out_id = '$exp_id'";  
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