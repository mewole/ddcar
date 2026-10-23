<?php
include_once "auth_guard.php";
require_admin_or_owner();

include "db.php";
$input  = $_POST['JSON'];
    $obj = json_decode($input,true); 
    $bill_id = $_POST['bill_id'];
    $pay = $_POST['pay'];
    $sum = $_POST['sum'];
    $dis = $_POST['dis'];
    $total = $_POST['total'];
    $get = $_POST['get'];
    $chng = $_POST['chng'];
    $b_id = $_POST['b_id'];
    $type = $_POST['type'];
    $credit_id = $_POST['credit_id'];
    $dif = 0;

    $query = "SELECT * FROM sale_order WHERE order_id = '$bill_id'";  
    $result = mysqli_query($conn, $query);
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){
            $summ = $row['sum_price'];
            $payy = $row['payment_type'];
            $diss = $row['total_discount'];
            $totall = $row['total_price'];
            $gett = $row['get_price'];
            $chngg = $row['chng'];
            $b_idd = $row['branch_id'];
            $type_b = $row['type'];
            $credit_idd = $row['credit_id'];
        }   
    }

    mysqli_begin_transaction($conn);
    $query = "INSERT INTO order_record(order_id,sum_price,total_discount,total_price,get_price,chng,payment_type,branch_id,type,credit_id) 
    VALUES ('$bill_id','$summ','$diss','$totall','$gett','$chngg','$payy','$b_idd','$type_b','$credit_idd')";  
    $result = mysqli_query($conn, $query);  
    if(!$result){
        mysqli_rollback($conn);
        echo "fail";
        exit;
    }
    $record_id = mysqli_insert_id($conn);

    $query = "UPDATE sale_order SET sum_price = '$sum',payment_type = '$pay',total_discount = '$dis',
    total_price = '$total',get_price = '$get',chng = '$chng',branch_id = '$b_id',type = '$type',credit_id = '$credit_id' 
    WHERE order_id = '$bill_id'";  
    $result = mysqli_query($conn, $query);
    if(!$result){
        mysqli_rollback($conn);
        echo "fail";
        exit;
    }

    if($payy == "เงินสด" && $pay != "เงินสด"){
        $sql = "UPDATE credit SET credit_price = credit_price+'$totall' WHERE credit_id = '$credit_id'";  
        $result = mysqli_query($conn, $sql); 
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }
    } 
    if($payy != "เงินสด" && $pay == "เงินสด"){
        $sql = "UPDATE credit SET credit_price = credit_price-'$totall' WHERE credit_id = '$credit_idd'";  
        $result = mysqli_query($conn, $sql); 
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }
    }

    foreach ($obj as $data){
        $no = $data['item_id'];
        $amt = $data['amt'];
        $ch = $data['ch'];
       
        $query = "SELECT * FROM sale_order_item WHERE order_id = '$bill_id' AND item_id='$no'";  
        $result = mysqli_query($conn, $query);
        if(mysqli_num_rows($result) > 0){    
            while($row = mysqli_fetch_array($result)){
                $prod_id = $row['prod_id'];
                $price = $row['prod_price'];
                $amount = $row['prod_amount'];
            }   
        } 

        if($ch == 1){
            $query = "INSERT INTO order_record_item(record_id,order_id,item_id,prod_id,prod_price,prod_amount) 
            VALUES ('$record_id','$bill_id','$no','$prod_id','$price','$amount')";  
            $result = mysqli_query($conn, $query); 
            if(!$result){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }     
        }
         
        $dif = $amount - $amt;
        if($type == $type_b && $b_id == $b_idd){
            if($ch == 1){
               if($type_b == "โกดัง"){                    
                    $query = "UPDATE warehouse SET amount=amount+'$dif' WHERE ware_id = '$b_id' AND prod_id = '$prod_id'";
                    $result = mysqli_query($conn, $query);
                    if(!$result){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    }
                }else if($type_b == "หน้าร้าน"){
                    $query = "UPDATE shop SET amount=amount+'$dif' WHERE shop_id = '$b_id' AND prod_id = '$prod_id'";
                    $result = mysqli_query($conn, $query);
                    if(!$result){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    }     
                }
            }                  
        }else if($type == $type_b && $b_id != $b_idd){
            if($type_b == "โกดัง"){                
                $query = "UPDATE warehouse SET amount=amount+'$amount' WHERE ware_id = '$b_idd' AND prod_id = '$prod_id'";
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }       
                $query = "UPDATE warehouse SET amount=amount-'$amt' WHERE ware_id = '$b_id' AND prod_id = '$prod_id'";
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }         
            }else if($type_b == "หน้าร้าน"){
                $query = "UPDATE shop SET amount=amount+'$amount' WHERE shop_id = '$b_idd' AND prod_id = '$prod_id'";
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }       
                $query = "UPDATE shop SET amount=amount-'$amt' WHERE shop_id = '$b_id' AND prod_id = '$prod_id'";
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }           
            }         
        }else if($type != $type_b){
            if($type_b == "โกดัง"){
                $query = "UPDATE warehouse SET amount=amount+'$amount' WHERE ware_id = '$b_idd' AND prod_id = '$prod_id'";
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                } 
                $query = "UPDATE shop SET amount=amount-'$amt' WHERE shop_id = '$b_id' AND prod_id = '$prod_id'";
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }                     
            }else if($type_b == "หน้าร้าน"){
                $query = "UPDATE shop SET amount=amount+'$amount' WHERE shop_id = '$b_idd' AND prod_id = '$prod_id'";
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }
                $query = "UPDATE warehouse SET amount=amount-'$amt' WHERE ware_id = '$b_id' AND prod_id = '$prod_id'";
                $result = mysqli_query($conn, $query);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }               
            }         
        }       

        if($ch == 1){
            $query = "UPDATE sale_order_item SET prod_amount='$amt' WHERE order_id = '$bill_id' AND item_id='$no'";  
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