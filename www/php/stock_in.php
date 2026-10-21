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
    $supplier_name = isset($_POST['supplier_name']) ? mysqli_real_escape_string($conn, trim($_POST['supplier_name'])) : '';
    $ref_invoice = isset($_POST['ref_invoice']) ? mysqli_real_escape_string($conn, trim($_POST['ref_invoice'])) : '';
    $bill_vat_type = isset($_POST['bill_vat_type']) ? mysqli_real_escape_string($conn, trim($_POST['bill_vat_type'])) : 'vat_7';
    $last_id = "";
    $i = 0;

    if (empty($obj) || !is_array($obj)) {
        echo "fail: ไม่มีรายการสินค้าในบิล";
        exit;
    }

    if($type == "โกดัง" || $type == "ware" || $type == "warehouse"){
        try {
            
    // DD Accessories - Auto Migration for VAT & Bundle Tables
    @mysqli_query($conn, "ALTER TABLE stock_in ADD COLUMN bill_vat_type VARCHAR(20) DEFAULT 'vat_7'");
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS bundle_recipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bundle_sku VARCHAR(100) NOT NULL,
        bundle_name VARCHAR(255) NOT NULL,
        bundle_type VARCHAR(50) DEFAULT 'pair_lr',
        component_sku VARCHAR(100) NOT NULL,
        component_name VARCHAR(255) DEFAULT '',
        qty INT DEFAULT 1,
        is_secret TINYINT(1) DEFAULT 0,
        picking_note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (bundle_sku),
        INDEX (component_sku)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pending_input_vat (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_no VARCHAR(100) NOT NULL,
        channel VARCHAR(100) NOT NULL,
        prod_id INT DEFAULT 0,
        prod_code VARCHAR(100) NOT NULL,
        prod_name VARCHAR(255) NOT NULL,
        qty INT DEFAULT 1,
        unit_cost DECIMAL(12,2) DEFAULT 0,
        total_cost DECIMAL(12,2) DEFAULT 0,
        input_vat_to_recover DECIMAL(12,2) DEFAULT 0,
        supplier_name VARCHAR(255) DEFAULT '',
        supplier_contact VARCHAR(255) DEFAULT '',
        status VARCHAR(20) DEFAULT 'pending',
        cleared_tax_invoice_no VARCHAR(100) DEFAULT NULL,
        cleared_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (status),
        INDEX (prod_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

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
            $sql2 = "INSERT INTO stock_in (stock_number,date_time,ware_id,user_id,supplier_name,ref_invoice,total_stock,count,bill_vat_type) 
                     VALUES ('$stock_number','$dt','$ware_id','$user_id','$supplier_name','$ref_invoice','$sum','$count','$bill_vat_type')"; 
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
                // หากไม่ได้ระบุ from ในรายการ ให้ใช้ supplier_name จากหัวบิลอัตโนมัติ
                $from = (isset($data['from']) && trim($data['from']) !== '') ? mysqli_real_escape_string($conn, trim($data['from'])) : $supplier_name;
                $supplier_sku = isset($data['supplier_sku']) ? mysqli_real_escape_string($conn, trim($data['supplier_sku'])) : '';
                $amount = 0;

                $sql_item = "INSERT INTO stock_in_item (stock_id,item_id,prod_id,stock_cost,amount,source_from,supplier_sku) 
                             VALUES ('$last_id','$item','$prod_id','$cost','$amt','$from','$supplier_sku')"; 
                $result_item = mysqli_query($conn, $sql_item);
                if(!$result_item){
                    throw new Exception("ไม่สามารถบันทึกรายการสินค้าเข้าคลังได้: " . mysqli_error($conn));
                } 

                // บันทึก / อัปเดตการจับคู่ Supplier SKU ใน product_supplier_sku อัตโนมัติ
                if(!empty($supplier_sku) && !empty($from)){
                    $sql_map = "INSERT INTO product_supplier_sku (prod_id, supplier_name, supplier_sku, last_cost) 
                                VALUES ('$prod_id', '$from', '$supplier_sku', '$cost')
                                ON DUPLICATE KEY UPDATE prod_id = VALUES(prod_id), last_cost = VALUES(last_cost)";
                    @mysqli_query($conn, $sql_map);
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
            $sql2 = "INSERT INTO transfer_in (tran_number,date_time,shop_id,user_id,supplier_name,ref_invoice,total_tran,count) 
                     VALUES ('$stock_number','$dt','$ware_id','$user_id','$supplier_name','$ref_invoice','$sum','$count')"; 
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
                $from = (isset($data['from']) && trim($data['from']) !== '') ? mysqli_real_escape_string($conn, trim($data['from'])) : $supplier_name;
                $supplier_sku = isset($data['supplier_sku']) ? mysqli_real_escape_string($conn, trim($data['supplier_sku'])) : '';
                $amount = 0;

                $sql_item = "INSERT INTO transfer_in_item (tran_id,item_id,prod_id,tran_cost,amount,source_from,supplier_sku) 
                             VALUES ('$last_id','$item','$prod_id','$cost','$amt','$from','$supplier_sku')"; 
                $result_item = mysqli_query($conn, $sql_item);
                if(!$result_item){
                    throw new Exception("ไม่สามารถบันทึกรายการสินค้าหน้าร้านได้: " . mysqli_error($conn));
                } 

                // บันทึก / อัปเดตการจับคู่ Supplier SKU ใน product_supplier_sku อัตโนมัติ
                if(!empty($supplier_sku) && !empty($from)){
                    $sql_map = "INSERT INTO product_supplier_sku (prod_id, supplier_name, supplier_sku, last_cost) 
                                VALUES ('$prod_id', '$from', '$supplier_sku', '$cost')
                                ON DUPLICATE KEY UPDATE prod_id = VALUES(prod_id), last_cost = VALUES(last_cost)";
                    @mysqli_query($conn, $sql_map);
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