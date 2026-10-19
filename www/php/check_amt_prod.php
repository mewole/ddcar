<?php
    include "db.php";
    include_once "folder_helper.php";
    $output = array();
    $target_prod_id = null;

    if(isset($_POST['qr'])){
        $qr = mysqli_real_escape_string($conn, $_POST['qr']); 
        $query = "SELECT d.ware_name,p.prod_name,w.amount,p.img,p.prod_cost,p.prod_pricesend,p.prod_price,w.ware_id as id,p.prod_id,
                         (SELECT GROUP_CONCAT(place SEPARATOR ', ') FROM warehouse_place wp WHERE wp.prod_id = p.prod_id AND wp.ware_id = w.ware_id) as places
                  FROM warehouse w INNER JOIN warehouse_detail d
                  ON w.ware_id = d.ware_id INNER JOIN product p ON w.prod_id = p.prod_id WHERE p.prod_code = '$qr' 
                  ORDER BY w.ware_id ASC";
        $result = mysqli_query($conn, $query);
        
        if($result && mysqli_num_rows($result) > 0){    
          while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){ 
                $target_prod_id = $row['prod_id'];
                $row['places'] = isset($row['places']) && $row['places'] !== null ? $row['places'] : '';
                $row['type'] = "ware";
                $row['place_type'] = "ware";
                $row[0] = $row['ware_name'];
                $row[1] = $row['prod_name'];
                $row[2] = $row['amount'];
                $row[3] = $row['img'];
                $row[4] = $row['prod_cost'];
                $row[5] = $row['prod_pricesend'];
                $row[6] = $row['prod_price'];
                $row[7] = $row['id'];
                $row[8] = $row['prod_id'];
                $row[9] = "ware";
                $output[] = $row;
          }   
        }
    
        $query = "SELECT d.shop_name,p.prod_name,s.amount,p.img,p.prod_cost,p.prod_pricesend,p.prod_price,s.shop_id as id,p.prod_id,
                         (SELECT GROUP_CONCAT(place SEPARATOR ', ') FROM shop_place sp WHERE sp.prod_id = p.prod_id AND sp.shop_id = s.shop_id) as places
                  FROM shop s INNER JOIN shop_detail d
                  ON s.shop_id = d.shop_id INNER JOIN product p ON s.prod_id = p.prod_id WHERE p.prod_code = '$qr' 
                  ORDER BY s.shop_id ASC";
        $result = mysqli_query($conn, $query);
        
        if($result && mysqli_num_rows($result) > 0){    
          while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){ 
                $target_prod_id = $row['prod_id'];
                $row['places'] = isset($row['places']) && $row['places'] !== null ? $row['places'] : '';
                $row['type'] = "shop";
                $row['place_type'] = "shop";
                $row[0] = $row['shop_name'];
                $row[1] = $row['prod_name'];
                $row[2] = $row['amount'];
                $row[3] = $row['img'];
                $row[4] = $row['prod_cost'];
                $row[5] = $row['prod_pricesend'];
                $row[6] = $row['prod_price'];
                $row[7] = $row['id'];
                $row[8] = $row['prod_id'];
                $row[9] = "shop";
                $output[] = $row;
          }   
        }
    }else if(isset($_POST['prod_id'])){
        $prod_id = mysqli_real_escape_string($conn, $_POST['prod_id']);
        $target_prod_id = $prod_id;
        $query = "SELECT d.ware_name,p.prod_name,p.prod_code,w.amount,p.img,p.prod_cost,p.prod_pricesend,p.prod_price,w.ware_id as id,
                         (SELECT GROUP_CONCAT(place SEPARATOR ', ') FROM warehouse_place wp WHERE wp.prod_id = p.prod_id AND wp.ware_id = w.ware_id) as places
                  FROM warehouse w INNER JOIN warehouse_detail d
                  ON w.ware_id = d.ware_id INNER JOIN product p ON w.prod_id = p.prod_id WHERE p.prod_id = '$prod_id' 
                  ORDER BY w.ware_id ASC";
        $result = mysqli_query($conn, $query);
        
        if($result && mysqli_num_rows($result) > 0){    
          while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){  
                $row['places'] = isset($row['places']) && $row['places'] !== null ? $row['places'] : '';
                $row['type'] = "ware";
                $row['place_type'] = "ware";
                $row[0] = $row['ware_name'];
                $row[1] = $row['prod_name'];
                $row[2] = $row['amount'];
                $row[3] = $row['img'];
                $row[4] = $row['prod_cost'];
                $row[5] = $row['prod_pricesend'];
                $row[6] = $row['prod_price'];
                $row[7] = $row['id'];
                $row[8] = "ware";
                $row[9] = "ware";
                $output[] = $row;
          }   
        }
    
        $query = "SELECT d.shop_name,p.prod_name,p.prod_code,s.amount,p.img,p.prod_cost,p.prod_pricesend,p.prod_price,s.shop_id as id,
                         (SELECT GROUP_CONCAT(place SEPARATOR ', ') FROM shop_place sp WHERE sp.prod_id = p.prod_id AND sp.shop_id = s.shop_id) as places
                  FROM shop s INNER JOIN shop_detail d
                  ON s.shop_id = d.shop_id INNER JOIN product p ON s.prod_id = p.prod_id WHERE p.prod_id = '$prod_id' 
                  ORDER BY s.shop_id ASC";
        $result = mysqli_query($conn, $query);
        
        if($result && mysqli_num_rows($result) > 0){    
          while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
                $row['places'] = isset($row['places']) && $row['places'] !== null ? $row['places'] : '';
                $row['type'] = "shop";
                $row['place_type'] = "shop";
                $row[0] = $row['shop_name'];
                $row[1] = $row['prod_name'];
                $row[2] = $row['amount'];
                $row[3] = $row['img'];
                $row[4] = $row['prod_cost'];
                $row[5] = $row['prod_pricesend'];
                $row[6] = $row['prod_price'];
                $row[7] = $row['id'];
                $row[8] = "shop";
                $row[9] = "shop";
                $output[] = $row;
          }   
        }
    }

    // แนบข้อมูลโครงสร้างโฟลเดอร์ของสินค้า
    if (!empty($target_prod_id) && !empty($output)) {
        $folder_info = get_product_folder_path($conn, $target_prod_id);
        for ($i = 0; $i < count($output); $i++) {
            $output[$i]['folder_path'] = $folder_info['path'];
            $output[$i]['folder_chain'] = $folder_info['chain'];
        }
    }

    if($result){
        echo json_encode($output);		   
    }else{
        echo "fail";
    }
    mysqli_close($conn);
?>