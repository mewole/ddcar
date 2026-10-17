<?php
    include "db.php";   
    $prod_id  = $_POST['prod_id'] ?? null;
    $name     = trim($_POST['name_prod'] ?? '');
    $raw_code = trim($_POST['code'] ?? '');   
    $code     = strtoupper(str_replace(' ', '', $raw_code));

    if (!$prod_id || empty($code)) {
        echo "fail";
        exit;
    }

    if (!preg_match('/^[A-Z0-9\-]+$/', $code)) {
        echo "invalid_sku";
        exit;
    }

    $cost     = $_POST['cost'] ?? '0';
    $price_w  = $_POST['price_w'] ?? '0';
    $price_s  = $_POST['price_s'] ?? '0';
    $img      = $_POST['img'] ?? '1';
    $min      = $_POST['min'] ?? '0';
    //$b64 = str_replace(" ","+",$img);
    if($img == 1){
        $sql = "UPDATE product SET prod_name = '$name',prod_code = '$code',prod_price = '$price_w',
        prod_pricesend = '$price_s',prod_cost = '$cost',min_amount = '$min' WHERE prod_id = '$prod_id'";  
    }else{
        $sql = "UPDATE product SET prod_name = '$name',prod_code = '$code',prod_price = '$price_w',
        prod_pricesend = '$price_s',prod_cost = '$cost',img = '$img',min_amount = '$min' WHERE prod_id = '$prod_id'";
    }  
    $result = mysqli_query($conn, $sql);

    if($result){
        echo "success";
    }else{
        echo "fail";
    }    
    mysqli_close($conn);
?>