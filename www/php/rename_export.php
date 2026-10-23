<?php
include_once "auth_guard.php";
require_staff_or_admin();

include "db.php";
$input  = $_POST['JSON'];
    $obj = json_decode($input,true); 
    $exp_id = $_POST['exp_id'];
    $ware_id = $_POST['ware_id'];   

    $query = "SELECT * FROM stock_out WHERE stock_out_id = '$exp_id'";  
    $result = mysqli_query($conn, $query);
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){
            $ware_idd = $row['ware_id'];           
        }   
    }

    mysqli_begin_transaction($conn);
    $query = "UPDATE stock_out SET ware_id = '$ware_id' WHERE stock_out_id = '$exp_id'";  
    $result = mysqli_query($conn, $query);
    if(!$result){
        mysqli_rollback($conn);
        echo "fail";
        exit;
    }

    foreach ($obj as $data){
        $no = $data['item_id'];
        $amt = $data['amt'];
        $ch = $data['ch'];

        $query = "SELECT * FROM stock_out_item WHERE stock_out_id = '$exp_id' AND item_id='$no'";  
        $result = mysqli_query($conn, $query);
        if(mysqli_num_rows($result) > 0){    
            while($row = mysqli_fetch_array($result)){
                $prod_id = $row['prod_id'];
                $cost = $row['stock_out_cost'];
                $amount = $row['amount'];
            }   
        }        
        if($ware_idd == $ware_id){
            if($ch == 1){
                if($amount > $amt){
                    $dif = $amount - $amt;            
                    $query = "UPDATE warehouse SET amount=amount+'$dif' WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";                 
                }else if($amt > $amount){
                    $dif = $amt - $amount;
                    $query = "UPDATE warehouse SET amount=amount-'$dif' WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";                  
                } 
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                } 
            }         
        }else{            
            $query = "UPDATE warehouse SET amount=amount+'$amount' WHERE ware_id = '$ware_idd' AND prod_id = '$prod_id'";
            $result = mysqli_query($conn, $query);
            if(!$result){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }       
            $query = "UPDATE warehouse SET amount=amount-'$amt' WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";
            $result = mysqli_query($conn, $query);
            if(!$result){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }                     
        }
    }
    mysqli_commit($conn);
    echo "success"; 
    mysqli_close($conn);
?>