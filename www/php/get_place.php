<?php
include_once "auth_guard.php";
require_login();

include "db.php";
$prod_id = $_POST['prod_id'];
    $output = array();

    if(isset($_POST['shop_id'])){
        $shop_id = $_POST['shop_id'];    
        $query = "SELECT place FROM shop_place WHERE prod_id = '$prod_id' AND shop_id = '$shop_id'";
    }else if(isset($_POST['ware_id'])){
        $ware_id = $_POST['ware_id']; 
        $query = "SELECT place FROM warehouse_place WHERE prod_id = '$prod_id' AND ware_id = '$ware_id'";
    }    
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0){    
      while($row = mysqli_fetch_array($result)){      
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