<?php
    include "db.php";

    $input = isset($_POST['JSON']) ? trim($_POST['JSON']) : '';
    $obj = json_decode($input, true);  	
    if (empty($obj) && is_string($input)) {
        $obj = json_decode(stripslashes($input), true);
    }
    $ware_id = isset($_POST['ware_id']) ? intval($_POST['ware_id']) : 0;   
    $dt = isset($_POST['date']) ? $_POST['date'] : date("Y-m-d H:i:s"); 
    $sum = isset($_POST['sum']) ? floatval($_POST['sum']) : 0; 
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';
    $last_id = "";
    $i = 0;

    if (empty($obj) || !is_array($obj)) {
        echo "fail: ไม่มีรายการสินค้าในบิล";
        exit;
    }

    if($type == "โกดัง" || $type == "ware" || $type == "warehouse"){
        try {
            mysqli_begin_transaction($conn);
            $sql = "SELECT stock_number,count FROM stock_in ORDER BY stock_id DESC LIMIT 1"; 
            $result = mysqli_query($conn, $sql);   
            $count = 1;
            $year_cur = date("y");
            if($result && mysqli_num_rows($result) > 0){    
                $row = mysqli_fetch_array($result);
                $number = $row['stock_number'];
                $year_old = substr($number, 3, 2);
                if($year_cur == $year_old){
                    $count = intval($row['count']) + 1;
                }
            }
            $stock_number = "IMP" . $year_cur . "-" . str_pad($count, 5, "0", STR_PAD_LEFT);
            $sql2 = "INSERT INTO stock_in (stock_number,date_time,ware_id,user_id,total_stock,count) 
                     VALUES ('$stock_number','$dt','$ware_id','$user_id','$sum','$count')"; 
            $result2 = mysqli_query($conn, $sql2);
            if(!$result2){
                throw new Exception("ไม่สามารถบันทึกข้อมูลใบรับเข้าคลังได้: " . mysqli_error($conn));
            }
            $last_id = mysqli_insert_id($conn);	

            foreach ($obj as $data) {
                $i++;
                $item = $i;
                $prod_id = mysqli_real_escape_string($conn, $data['prod_id']);
                $cost = floatval(str_replace(',', '', $data['cost']));    
                $amt = intval($data['amt']);  
                $addr = isset($data['addr']) ? mysqli_real_escape_string($conn, trim($data['addr'])) : ''; 
                $from = isset($data['from']) ? mysqli_real_escape_string($conn, trim($data['from'])) : '';
                $amount = 0;

                if (!empty($from)) {
                    $sql_item = "INSERT INTO stock_in_item (stock_id,item_id,prod_id,stock_cost,amount,source_from) VALUES ('$last_id','$item','$prod_id','$cost','$amt','$from')"; 
                } else {
                    $sql_item = "INSERT INTO stock_in_item (stock_id,item_id,prod_id,stock_cost,amount) VALUES ('$last_id','$item','$prod_id','$cost','$amt')"; 
                }
                $result_item = mysqli_query($conn, $sql_item);
                if(!$result_item){
                    throw new Exception("ไม่สามารถบันทึกรายการสินค้าเข้าคลังได้: " . mysqli_error($conn));
                } 

                $status = 'normal';
                $min = 0;
                $sql = "SELECT min_amount,status FROM product WHERE prod_id = '$prod_id' AND status != 'normal'"; 
                $result = mysqli_query($conn, $sql);
                if($result && mysqli_num_rows($result) > 0){    
                    $row = mysqli_fetch_array($result);
                    $min = intval($row['min_amount']);
                    $status = $row['status'];
                }
                $sql = "SELECT amount FROM warehouse WHERE prod_id = '$prod_id'"; 
                $result = mysqli_query($conn, $sql); 
                if($result && mysqli_num_rows($result) > 0){    
                    while($row = mysqli_fetch_array($result)){  
                        $amount += intval($row['amount']);                    
                    }
                } 
                $sql = "SELECT amount FROM shop WHERE prod_id = '$prod_id'"; 
                $result = mysqli_query($conn, $sql); 
                if($result && mysqli_num_rows($result) > 0){    
                    while($row = mysqli_fetch_array($result)){  
                        $amount += intval($row['amount']);                    
                    }
                } 
                if($status == 'danger'){
                    if($amount + $amt >= $min){
                        mysqli_query($conn, "UPDATE product SET status = 'normal' WHERE prod_id = '$prod_id'");
                    } else if($amount + $amt > 10){
                        mysqli_query($conn, "UPDATE product SET status = 'warning' WHERE prod_id = '$prod_id'");
                    }
                } else if($status == 'warning'){
                    if($amount + $amt >= $min){
                        mysqli_query($conn, "UPDATE product SET status = 'normal' WHERE prod_id = '$prod_id'");
                    }
                }    
                
                $query = "SELECT prod_id FROM warehouse WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";
                $result_q = mysqli_query($conn, $query);
                if($result_q && mysqli_num_rows($result_q) > 0){ 
                    $sql = "UPDATE warehouse SET amount = amount + $amt WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";          
                } else {
                    $sql = "INSERT INTO warehouse (ware_id,prod_id,amount) VALUES ('$ware_id','$prod_id','$amt')";           
                }
                $result = mysqli_query($conn, $sql);
                if(!$result){
                    throw new Exception("ไม่สามารถอัปเดตสต๊อกโกดังได้: " . mysqli_error($conn));
                }

                if($addr != ""){
                    $sql = "INSERT IGNORE INTO warehouse_place (ware_id,prod_id,place) VALUES ('$ware_id','$prod_id','$addr')"; 
                    mysqli_query($conn, $sql); 
                }
                @mysqli_query($conn, "UPDATE product_status_ext SET is_ordered = 0 WHERE prod_id = '$prod_id'");
            }
            mysqli_commit($conn); 
            echo "success"; 
        } catch (Throwable $e) {
            if ($conn) @mysqli_rollback($conn);
            echo "fail: " . $e->getMessage();
        }
        mysqli_close($conn);

    } else if($type == "หน้าร้าน" || $type == "shop"){
        try {
            mysqli_begin_transaction($conn);
            $sql = "SELECT tran_number,count FROM transfer_in ORDER BY tran_id DESC LIMIT 1"; 
            $result = mysqli_query($conn, $sql);   
            $count = 1;
            $year_cur = date("y");
            if($result && mysqli_num_rows($result) > 0){    
                $row = mysqli_fetch_array($result);
                $number = $row['tran_number'];
                $year_old = substr($number, 3, 2);
                if($year_cur == $year_old){
                    $count = intval($row['count']) + 1;
                }
            }
            $stock_number = "TRA" . $year_cur . "-" . str_pad($count, 5, "0", STR_PAD_LEFT);
            $sql2 = "INSERT INTO transfer_in (tran_number,date_time,shop_id,user_id,total_tran,count) 
                     VALUES ('$stock_number','$dt','$ware_id','$user_id','$sum','$count')"; 
            $result2 = mysqli_query($conn, $sql2);
            if(!$result2){
                throw new Exception("ไม่สามารถบันทึกข้อมูลใบรับเข้าหน้าร้านได้: " . mysqli_error($conn));
            }
            $last_id = mysqli_insert_id($conn);	

            foreach ($obj as $data) {
                $i++;
                $item = $i;
                $prod_id = mysqli_real_escape_string($conn, $data['prod_id']);
                $cost = floatval(str_replace(',', '', $data['cost']));    
                $amt = intval($data['amt']);  
                $addr = isset($data['addr']) ? mysqli_real_escape_string($conn, trim($data['addr'])) : ''; 
                $from = isset($data['from']) ? mysqli_real_escape_string($conn, trim($data['from'])) : '';
                $amount = 0;

                if (!empty($from)) {
                    $sql_item = "INSERT INTO transfer_in_item (tran_id,item_id,prod_id,tran_cost,amount,source_from) VALUES ('$last_id','$item','$prod_id','$cost','$amt','$from')"; 
                } else {
                    $sql_item = "INSERT INTO transfer_in_item (tran_id,item_id,prod_id,tran_cost,amount) VALUES ('$last_id','$item','$prod_id','$cost','$amt')"; 
                }
                $result_item = mysqli_query($conn, $sql_item);
                if(!$result_item){
                    throw new Exception("ไม่สามารถบันทึกรายการสินค้าหน้าร้านได้: " . mysqli_error($conn));
                } 

                $status = 'normal';
                $min = 0;
                $sql = "SELECT min_amount,status FROM product WHERE prod_id = '$prod_id' AND status != 'normal'"; 
                $result = mysqli_query($conn, $sql);
                if($result && mysqli_num_rows($result) > 0){    
                    $row = mysqli_fetch_array($result);
                    $min = intval($row['min_amount']);
                    $status = $row['status'];
                }
                $sql = "SELECT amount FROM warehouse WHERE prod_id = '$prod_id'"; 
                $result = mysqli_query($conn, $sql); 
                if($result && mysqli_num_rows($result) > 0){    
                    while($row = mysqli_fetch_array($result)){  
                        $amount += intval($row['amount']);                    
                    }
                } 
                $sql = "SELECT amount FROM shop WHERE prod_id = '$prod_id'"; 
                $result = mysqli_query($conn, $sql); 
                if($result && mysqli_num_rows($result) > 0){    
                    while($row = mysqli_fetch_array($result)){  
                        $amount += intval($row['amount']);                    
                    }
                } 
                if($status == 'danger'){
                    if($amount + $amt >= $min){
                        mysqli_query($conn, "UPDATE product SET status = 'normal' WHERE prod_id = '$prod_id'");
                    } else if($amount + $amt > 10){
                        mysqli_query($conn, "UPDATE product SET status = 'warning' WHERE prod_id = '$prod_id'");
                    }
                } else if($status == 'warning'){
                    if($amount + $amt >= $min){
                        mysqli_query($conn, "UPDATE product SET status = 'normal' WHERE prod_id = '$prod_id'");
                    }
                }    
                
                $query = "SELECT prod_id FROM shop WHERE shop_id = '$ware_id' AND prod_id = '$prod_id'";
                $result_q = mysqli_query($conn, $query);
                if($result_q && mysqli_num_rows($result_q) > 0){ 
                    $sql = "UPDATE shop SET amount = amount + $amt WHERE shop_id = '$ware_id' AND prod_id = '$prod_id'";          
                } else {
                    $sql = "INSERT INTO shop (shop_id,prod_id,amount) VALUES ('$ware_id','$prod_id','$amt')";           
                }
                $result = mysqli_query($conn, $sql);
                if(!$result){
                    throw new Exception("ไม่สามารถอัปเดตสต๊อกหน้าร้านได้: " . mysqli_error($conn));
                }

                if($addr != ""){
                    $sql = "INSERT IGNORE INTO shop_place (shop_id,prod_id,place) VALUES ('$ware_id','$prod_id','$addr')"; 
                    mysqli_query($conn, $sql); 
                }
                @mysqli_query($conn, "UPDATE product_status_ext SET is_ordered = 0 WHERE prod_id = '$prod_id'");
            }
            mysqli_commit($conn); 
            echo "success"; 
        } catch (Throwable $e) {
            if ($conn) @mysqli_rollback($conn);
            echo "fail: " . $e->getMessage();
        }
        mysqli_close($conn);

    } else {
        echo "fail: ประเภทสถานที่ไม่ถูกต้อง ($type)";
    }
?>