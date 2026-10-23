<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
include "db.php";    

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
        if(isset($_POST['ware_id'])){
            $ware_id = $_POST['ware_id'];
            $query = "SELECT amount FROM warehouse WHERE prod_id = '$prod_id' AND ware_id = '$ware_id'";
            $result1 = mysqli_query($conn, $query);
            if(mysqli_num_rows($result1) > 0){    
                while($row1 = mysqli_fetch_array($result1)){      
                    $row['min_amount'] = $row1['amount'];
                }   
            }else{
                $row['min_amount'] = 0;
            }
            
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