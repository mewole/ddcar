<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('HTTP/1.1 405 Method Not Allowed'); exit; }  
    include "db.php"; 
    $imp_id  = $_POST['imp_id'];
    $ware_id  = $_POST['ware_id'];
    $txt = "(cancel)"; 

    mysqli_begin_transaction($conn);
    $query = "SELECT amount,prod_id FROM transfer_in_item WHERE tran_id = '$imp_id'";  
    $result = mysqli_query($conn, $query);
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){
            $amt = $row['amount']; 
            $prod_id = $row['prod_id'];           
            $query = "UPDATE shop SET amount=amount-'$amt' WHERE shop_id = '$ware_id' AND prod_id = '$prod_id'";                 
            $result1 = mysqli_query($conn, $query); 
            if(!$result1){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }              
        }   
    }
  
    $sql = "UPDATE transfer_in SET tran_number = CONCAT(tran_number,'".$txt."') WHERE tran_id = '$imp_id'";  
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