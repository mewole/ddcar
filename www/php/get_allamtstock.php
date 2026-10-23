<?php
include_once "auth_guard.php";
require_login();
include "db.php";
$sql = "SELECT SUM(amount) FROM warehouse"; 
    $result = mysqli_query($conn, $sql);
    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){            
            $amount = $row[0];
        }
    }

    $sql1 = "SELECT SUM(amount) FROM shop"; 
    $result1 = mysqli_query($conn, $sql1);
    if(mysqli_num_rows($result1) > 0){    
        while($row = mysqli_fetch_array($result1)){            
            $amount1 = $row[0];
        }
    }

    $total = $amount + $amount1;
 
    echo "Warehouse amount of stock: $amount<br/>";   
    echo "Shop amount of stock: $amount1<br/>";
    echo "Total: $total";
    mysqli_close($conn);
?>