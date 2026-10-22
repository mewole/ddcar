<?php
    include "db.php";
include_once "auth_guard.php";
require_login();    

    $output = array();
    $supplier_name = isset($_POST['supplier_name']) ? mysqli_real_escape_string($conn, trim($_POST['supplier_name'])) : '';

    if(isset($_POST['qr'])){
        $qr = mysqli_real_escape_string($conn, trim($_POST['qr']));    
        // 1. ค้นหาจากบาร์โค้ด / รหัสสินค้าของร้านเราก่อน
        $query = "SELECT * FROM product WHERE prod_code = '$qr'";
        $result = mysqli_query($conn, $query);

        // 2. หากไม่พบ และมีการระบุร้านค้า ให้ค้นหาจาก Supplier SKU ของร้านนั้น
        if(!$result || mysqli_num_rows($result) == 0){
            if(!empty($supplier_name)){
                $query_sup = "SELECT p.*, s.supplier_sku, s.last_cost AS supplier_last_cost 
                              FROM product p 
                              JOIN product_supplier_sku s ON p.prod_id = s.prod_id 
                              WHERE s.supplier_name = '$supplier_name' AND s.supplier_sku = '$qr' 
                              LIMIT 1";
                $res_sup = mysqli_query($conn, $query_sup);
                if($res_sup && mysqli_num_rows($res_sup) > 0){
                    $result = $res_sup;
                }
            }
        }

        // 3. หากยังไม่พบ ให้ค้นหาจาก Supplier SKU ของทุกร้านในระบบ
        if(!$result || mysqli_num_rows($result) == 0){
            $query_any = "SELECT p.*, s.supplier_sku, s.last_cost AS supplier_last_cost 
                          FROM product p 
                          JOIN product_supplier_sku s ON p.prod_id = s.prod_id 
                          WHERE s.supplier_sku = '$qr' 
                          LIMIT 1";
            $res_any = mysqli_query($conn, $query_any);
            if($res_any && mysqli_num_rows($res_any) > 0){
                $result = $res_any;
            }
        }
    }else if(isset($_POST['prod_id'])){
        $prod_id = mysqli_real_escape_string($conn, trim($_POST['prod_id'])); 
        $query = "SELECT * FROM product WHERE prod_id = '$prod_id'";
        $result = mysqli_query($conn, $query);
    }    

    if(isset($result) && $result && mysqli_num_rows($result) > 0){    
      while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){     
        $p_id = $row['prod_id'];
        
        // ถ้ายังไม่มี supplier_sku ผูกมากับ row และมี supplier_name ให้ค้นหารหัสที่เคยจับคู่ไว้
        if(!isset($row['supplier_sku']) && !empty($supplier_name)){
            $q_match = "SELECT supplier_sku, last_cost FROM product_supplier_sku WHERE prod_id = '$p_id' AND supplier_name = '$supplier_name' LIMIT 1";
            $res_match = mysqli_query($conn, $q_match);
            if($res_match && ($row_m = mysqli_fetch_assoc($res_match))){
                $row['supplier_sku'] = $row_m['supplier_sku'];
                if(empty($row['supplier_last_cost']) && floatval($row_m['last_cost']) > 0){
                    $row['supplier_last_cost'] = floatval($row_m['last_cost']);
                }
            }
        }

        if(isset($_POST['ware_id'])){
            $ware_id = intval($_POST['ware_id']);
            $type = isset($_POST['type']) ? trim($_POST['type']) : '';
            if($type == "หน้าร้าน" || $type == "shop"){
                $query1 = "SELECT amount FROM shop WHERE prod_id = '$p_id' AND shop_id = '$ware_id'";
                $result1 = mysqli_query($conn, $query1);
                if($result1 && mysqli_num_rows($result1) > 0){    
                    $row1 = mysqli_fetch_array($result1);
                    $row['min_amount'] = $row1['amount'];
                }else{
                    $row['min_amount'] = 0;
                }
            }else if($type == "โกดัง" || $type == "ware" || $type == "warehouse"){
                $query1 = "SELECT amount FROM warehouse WHERE prod_id = '$p_id' AND ware_id = '$ware_id'";
                $result1 = mysqli_query($conn, $query1);
                if($result1 && mysqli_num_rows($result1) > 0){    
                    $row1 = mysqli_fetch_array($result1);
                    $row['min_amount'] = $row1['amount'];
                }else{
                    $row['min_amount'] = 0;
                }
            }
        }             
        $output[] = $row;  
      }   
    }

    if(isset($result) && $result){
        echo json_encode($output, JSON_UNESCAPED_UNICODE);		   
    }else{
        echo "fail";
    }
	mysqli_close($conn);
?>