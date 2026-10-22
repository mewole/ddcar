<?php 
    include "db.php";
include_once "auth_guard.php";
require_login(); 
    $input  = $_POST['JSON'];
    $obj = json_decode($input,true);
    $shop_id  = $_POST['shop_id'];
    $dt  = $_POST['date'];
    $user_id = $_POST['user_id'];
    $i = 0;  

    mysqli_begin_transaction($conn);
    $sql = "INSERT INTO transfer_in (date_time,shop_id,user_id) VALUE ('$dt','$shop_id','$user_id')"; 
    $result = mysqli_query($conn, $sql); 
    $last_id = mysqli_insert_id($conn);
    if(!$result){
        mysqli_rollback($conn);
        echo "fail";
        exit;
    }  	
   
    foreach ($obj as $data)
    {
        $i++;
        $item = $i;
        $prod_id = $data['prod_id'];         
        $amt = $data['amt']; 
        $addr = $data['addr']; 
        $amount = 0;

        $sql_item = "INSERT INTO transfer_in_item (tran_id,item_id,prod_id,amount) VALUE ('$last_id','$item','$prod_id','$amt')"; 
        $result_item = mysqli_query($conn, $sql_item);
        if(!$result_item){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }  

        $sql = "SELECT min_amount,status FROM product WHERE prod_id = '$prod_id' AND status != 'normal'"; 
        $result = mysqli_query($conn, $sql);
        if(mysqli_num_rows($result) > 0){    
            while($row = mysqli_fetch_array($result)){  
                $min = $row['min_amount'];
                $status = $row['status'];
            }
        }
        $sql = "SELECT amount FROM warehouse WHERE prod_id = '$prod_id'"; 
        $result = mysqli_query($conn, $sql); 
        if(mysqli_num_rows($result) > 0){    
            while($row = mysqli_fetch_array($result)){  
                $amount += $row['amount'];                    
            }
        } 
        $sql = "SELECT amount FROM shop WHERE prod_id = '$prod_id'"; 
        $result = mysqli_query($conn, $sql); 
        if(mysqli_num_rows($result) > 0){    
            while($row = mysqli_fetch_array($result)){  
                $amount += $row['amount'];                    
            }
        } 
        if($status == 'danger'){
            if($amount + $amt >= $min){
                $sql = "UPDATE product SET status = 'normal' WHERE prod_id = '$prod_id'";
                $result = mysqli_query($conn, $sql);                 
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                } 
            }else if($amount + $amt > 10){
                $sql = "UPDATE product SET status = 'warning' WHERE prod_id = '$prod_id'";
                $result = mysqli_query($conn, $sql);               
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }    
            }
        }else if($status == 'warning'){
            if($amount + $amt >= $min){
                $sql = "UPDATE product SET status = 'normal' WHERE prod_id = '$prod_id'";
                $result = mysqli_query($conn, $sql);               
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }    
            }
        }  
        
        $query = "SELECT prod_id FROM shop WHERE shop_id = '$shop_id' AND prod_id = '$prod_id'";
        $result_q = mysqli_query($conn, $query);
        if(mysqli_num_rows($result_q) > 0){ 
            $sql = "UPDATE shop SET amount = amount+'$amt' WHERE shop_id = '$shop_id' AND prod_id = '$prod_id'";           
        }else{
            $sql = "INSERT INTO shop (shop_id,prod_id,amount) VALUE ('$shop_id','$prod_id','$amt')";           
        }
        $result = mysqli_query($conn, $sql);
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        } 
        if($addr != ""){
            $sql = "INSERT INTO shop_place (shop_id,prod_id,place) VALUE ('$shop_id','$prod_id','$addr')"; 
            $result = mysqli_query($conn, $sql); 
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