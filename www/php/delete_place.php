<?php
include_once "auth_guard.php";
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('HTTP/1.1 405 Method Not Allowed'); exit; }
    include "db.php";
    $prod_id = $_POST['prod_id'];    

    $place = isset($_POST['place']) ? mysqli_real_escape_string($conn, trim($_POST['place'])) : '';

    if(isset($_POST['shop_id'])){
        $shop_id = $_POST['shop_id'];    
        if(!empty($place)){
            $query = "DELETE FROM shop_place WHERE prod_id = '$prod_id' AND shop_id = '$shop_id' AND place = '$place'";
        }else{
            $query = "DELETE FROM shop_place WHERE prod_id = '$prod_id' AND shop_id = '$shop_id'";
        }
    }else if(isset($_POST['ware_id'])){
        $ware_id = $_POST['ware_id']; 
        if(!empty($place)){
            $query = "DELETE FROM warehouse_place WHERE prod_id = '$prod_id' AND ware_id = '$ware_id' AND place = '$place'";
        }else{
            $query = "DELETE FROM warehouse_place WHERE prod_id = '$prod_id' AND ware_id = '$ware_id'";
        }
    }    
    $result = mysqli_query($conn, $query);   

    if($result){
        echo "success";
    }else{
        echo "fail";
    }
	mysqli_close($conn);
?>