<?php
    include "db.php";
include_once "auth_guard.php";
require_login();   
    $cus_id = $_POST['cus_id'];   
    $ware_id = $_POST['ware_id'];
    $type = $_POST['type'];

    if(isset($_POST['qr'])){
        $qr = $_POST['qr'];    
        $query = "SELECT * FROM product WHERE prod_code = '$qr'";
    }else if(isset($_POST['prod_id'])){
        $prod_id = $_POST['prod_id']; 
        $query = "SELECT * FROM product WHERE prod_id = '$prod_id'";
    }    
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0){    
      while($row = mysqli_fetch_array($result)){ 
        $prod_id = $row['prod_id']; 
        $query = "SELECT cus_price FROM detail_cus WHERE prod_id = '$prod_id' AND cus_id = '$cus_id'"; 
        $result1 = mysqli_query($conn, $query);   
        if(mysqli_num_rows($result1) > 0){    
            while($row1 = mysqli_fetch_array($result1)){ 
                $cus_price = $row1['cus_price'];
                array_push($row,$cus_price);              
            }   
        }
        if($type == "โกดัง" || $type == "ware" || $type == "warehouse"){
            $query = "SELECT amount FROM warehouse WHERE prod_id = '$prod_id' AND ware_id = '$ware_id'";
        }else if($type == "หน้าร้าน" || $type == "shop"){
            $query = "SELECT amount FROM shop WHERE prod_id = '$prod_id' AND shop_id = '$ware_id'";
        }        
        $result2 = mysqli_query($conn, $query);
        if(mysqli_num_rows($result2) > 0){    
            while($row2 = mysqli_fetch_array($result2)){      
                $row['min_amount'] = $row2['amount'];
            }   
        }else{
            $row['min_amount'] = 0;
        } 
        $output[] = $row;  
      }   
    }

    if($result){
        echo json_encode($output);		   
    }else{
        echo "fail";
    }
	mysqli_close($conn);
?>