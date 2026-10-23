<?php
include_once "auth_guard.php";
require_login();
include "db.php";
$input  = $_POST['JSON'];
    $obj = json_decode($input,true); 
    $imp_id = $_POST['imp_id'];
    $ware_id = $_POST['ware_id'];
    $sum = $_POST['sum']; 

    $query = "SELECT * FROM stock_in WHERE stock_id = '$imp_id'";  
    $result = mysqli_query($conn, $query);
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){
            $ware_idd = $row['ware_id'];
            $summ = $row['total_stock'];           
        }   
    }

    mysqli_begin_transaction($conn);
    $query = "INSERT INTO stock_in_record(stock_id,ware_id,total_stock)VALUES ('$imp_id','$ware_idd','$summ')";  
    $result = mysqli_query($conn, $query);  
    if(!$result){
        mysqli_rollback($conn);
        echo "fail";
        exit;
    }
    $record_id = mysqli_insert_id($conn);

    $query = "UPDATE stock_in SET ware_id = '$ware_id',total_stock = '$sum' WHERE stock_id = '$imp_id'";  
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

        $query = "SELECT * FROM stock_in_item WHERE stock_id = '$imp_id' AND item_id='$no'";  
        $result = mysqli_query($conn, $query);
        if(mysqli_num_rows($result) > 0){    
            while($row = mysqli_fetch_array($result)){
                $prod_id = $row['prod_id'];
                $cost = $row['stock_cost'];
                $amount = $row['amount'];
            }   
        } 

        if($ch == 1){
            $query = "INSERT INTO stock_in_item_record(record_id,stock_id,item_id,prod_id,stock_cost,amount) 
            VALUES ('$record_id','$imp_id','$no','$prod_id','$cost','$amount')";  
            $result = mysqli_query($conn, $query); 
            if(!$result){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            } 
        }     
        
        if($ware_idd == $ware_id){
            if($ch == 1){
                if($amount > $amt){
                    $dif = $amount - $amt;            
                    $query = "UPDATE warehouse SET amount=amount-'$dif' WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";                 
                }else if($amt > $amount){
                    $dif = $amt - $amount;
                    $query = "UPDATE warehouse SET amount=amount+'$dif' WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";                  
                } 
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                } 
            }         
        }else{            
            $query = "UPDATE warehouse SET amount=amount-'$amount' WHERE ware_id = '$ware_idd' AND prod_id = '$prod_id'";
            $result = mysqli_query($conn, $query);
            if(!$result){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }    
            $query = "SELECT * FROM warehouse WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";  
            $result = mysqli_query($conn, $query);
            if(mysqli_num_rows($result) > 0){      
                $query = "UPDATE warehouse SET amount=amount+'$amt' WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                } 
            }else{
                $query = "INSERT INTO warehouse(ware_id,prod_id,amount) VALUES ('$ware_id','$prod_id','$amt')";
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                } 
            }                   
        }       

        if($ch == 1){
            $query = "UPDATE stock_in_item SET amount='$amt' WHERE stock_id = '$imp_id' AND item_id='$no'";  
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