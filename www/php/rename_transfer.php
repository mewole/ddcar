<?php
include_once "auth_guard.php";
require_login();
include "db.php";
$input  = $_POST['JSON'];
    $obj = json_decode($input,true); 
    $imp_id = $_POST['imp_id'];
    $ware_id = $_POST['ware_id'];
    $sum = $_POST['sum']; 

    $query = "SELECT * FROM transfer_in WHERE tran_id = '$imp_id'";  
    $result = mysqli_query($conn, $query);
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){
            $ware_idd = $row['shop_id'];
            $summ = $row['total_tran'];           
        }   
    }

    mysqli_begin_transaction($conn);
    $query = "UPDATE transfer_in SET shop_id = '$ware_id',total_tran = '$sum' WHERE tran_id = '$imp_id'";  
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

        $query = "SELECT * FROM transfer_in_item WHERE tran_id = '$imp_id' AND item_id='$no'";  
        $result = mysqli_query($conn, $query);
        if(mysqli_num_rows($result) > 0){    
            while($row = mysqli_fetch_array($result)){
                $prod_id = $row['prod_id'];
                $cost = $row['tran_cost'];
                $amount = $row['amount'];
            }   
        }    
        
        if($ware_idd == $ware_id){
            if($ch == 1){
                if($amount > $amt){
                    $dif = $amount - $amt;            
                    $query = "UPDATE shop SET amount=amount-'$dif' WHERE shop_id = '$ware_id' AND prod_id = '$prod_id'";                 
                }else if($amt > $amount){
                    $dif = $amt - $amount;
                    $query = "UPDATE shop SET amount=amount+'$dif' WHERE shop_id = '$ware_id' AND prod_id = '$prod_id'";                  
                } 
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                } 
            }         
        }else{            
            $query = "UPDATE shop SET amount=amount-'$amount' WHERE shop_id = '$ware_idd' AND prod_id = '$prod_id'";
            $result = mysqli_query($conn, $query);
            if(!$result){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }
            $query = "SELECT * FROM shop WHERE shop_id = '$ware_id' AND prod_id = '$prod_id'";  
            $result = mysqli_query($conn, $query);
            if(mysqli_num_rows($result) > 0){    
                $query = "UPDATE shop SET amount=amount+'$amt' WHERE shop_id = '$ware_id' AND prod_id = '$prod_id'";
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                } 
            }else{
                $query = "INSERT INTO shop(shop_id,prod_id,amount) VALUES ('$ware_id','$prod_id','$amt')";
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                } 
            }   
                                
        }       

        if($ch == 1){
            $query = "UPDATE transfer_in_item SET amount='$amt' WHERE tran_id = '$imp_id' AND item_id='$no'";  
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