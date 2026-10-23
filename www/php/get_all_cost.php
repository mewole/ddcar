<?php
include_once "auth_guard.php";
require_login();

include "db.php";
$cost = 0;

    $sql = "SELECT * FROM warehouse WHERE amount > '0'"; 
    $result = mysqli_query($conn, $sql);   
  
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){  
            $prod_id = $row['prod_id'];
            $amount = $row['amount'];

            $sql1 = "SELECT prod_cost FROM product WHERE prod_id = '$prod_id'"; 
            $result1 = mysqli_query($conn, $sql1);
            if(mysqli_num_rows($result1) > 0){    
                while($row1 = mysqli_fetch_array($result1)){  
                    $prod_cost = $row1['prod_cost'];
                    $cost = $cost + ($prod_cost*$amount);
                }
            }
                 
        }
    }

    $sql = "SELECT * FROM shop WHERE amount > '0'"; 
    $result = mysqli_query($conn, $sql);   
  
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){  
            $prod_id = $row['prod_id'];
            $amount = $row['amount'];

            $sql1 = "SELECT prod_cost FROM product WHERE prod_id = '$prod_id'"; 
            $result1 = mysqli_query($conn, $sql1);
            if(mysqli_num_rows($result1) > 0){    
                while($row1= mysqli_fetch_array($result1)){  
                    $prod_cost = $row1['prod_cost'];
                    $cost = $cost + ($prod_cost*$amount);
                }
            }
                 
        }
    }     
    echo $cost; 
    mysqli_close($conn);
?>