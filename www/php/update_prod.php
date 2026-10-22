<?php
    include "db.php";
    include_once "auth_guard.php";
    require_login();

    $prod_id  = isset($_POST['prod']) ? intval($_POST['prod']) : 0;
    $cost     = isset($_POST['cost']) ? floatval($_POST['cost']) : 0.0;
    $price_s  = isset($_POST['price_s']) ? floatval($_POST['price_s']) : 0.0;
    $price_w  = isset($_POST['price_w']) ? floatval($_POST['price_w']) : 0.0;

    if ($prod_id <= 0) {
        echo "fail";
        exit;
    }

    // Step 3 Data Quality Guards:
    // 1. Retail price cannot be 0 or negative
    if ($price_w <= 0) {
        echo "error_price_zero";
        exit;
    }
    // 2. Wholesale price cannot be greater than retail price
    if ($price_s > 0 && $price_s > $price_w) {
        echo "error_wholesale_higher";
        exit;
    }
   
    $sql = "UPDATE product SET prod_cost = '$cost', prod_pricesend = '$price_s', prod_price = '$price_w' 
    WHERE prod_id = '$prod_id'"; 
    $result = mysqli_query($conn, $sql);   

    if($result){
        echo "success";              
    }else{
        echo "fail";      
    }
    mysqli_close($conn);
?>