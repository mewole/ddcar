<?php  
    include "db.php";
include_once "auth_guard.php";
require_login();
    $input  = $_POST['JSON'];
    $obj = json_decode($input,true);    

    mysqli_begin_transaction($conn);
    foreach ($obj as $data){
        $prod_id = $data['prod_id'];
        $id = $data['id'];
        $addr = $data['addr'];
        $type = $data['type'];

        if($type == "ware"){
            $query = "UPDATE warehouse_place SET place='$addr' WHERE ware_id = '$id' AND prod_id = '$prod_id'";           
        }else if($type == "shop"){
            $query = "UPDATE shop_place SET place='$addr' WHERE shop_id = '$id' AND prod_id = '$prod_id'";         
        }
        $result = mysqli_query($conn, $query);
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }    
      
    }
    mysqli_commit($conn);
    echo "success"; 
    mysqli_close($conn);
?>