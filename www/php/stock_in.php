<?php
    include "db.php";
    $input  = $_POST['JSON'];
    $obj = json_decode($input,true);  	
    $ware_id  = $_POST['ware_id'];   
    $dt  = $_POST['date']; 
    $sum  = $_POST['sum']; 
    $user_id  = $_POST['user_id'];
    $type  = $_POST['type'];
    $last_id = "";
    $i = 0;

    if($type == "โกดัง"){
        mysqli_begin_transaction($conn);
        $sql = "SELECT stock_number,count FROM stock_in ORDER BY stock_id DESC LIMIT 1"; 
        $result = mysqli_query($conn, $sql);   
      
        if(mysqli_num_rows($result) > 0){    
            while($row = mysqli_fetch_array($result)){  
                $number = $row['stock_number'];
                $year_old = substr($number,3,2);
                $year_cur = date("y");
                if($year_cur == $year_old){
                    $count = $row['count']+1;
                    $year = "IMP" . $year_cur . "-";
                    $stock_number = $year . str_pad($count, 5, "0",STR_PAD_LEFT);
                    $sql2 = "INSERT INTO stock_in (stock_number,date_time,ware_id,user_id,total_stock,count) 
                    VALUES ('$stock_number','$dt','$ware_id','$user_id','$sum','$count')"; 
                    $result2 = mysqli_query($conn, $sql2);
                    $last_id = mysqli_insert_id($conn);	
                    if(!$result2){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    } 
                }else{
                    $year = "IMP" . $year_cur . "-";
                    $stock_number = $year . str_pad(1, 5, "0",STR_PAD_LEFT);
                    $sql3 = "INSERT INTO stock_in (stock_number,date_time,ware_id,user_id,total_stock,count) 
                    VALUES ('$stock_number','$dt','$ware_id','$user_id','$sum',1)"; 
                    $result3 = mysqli_query($conn, $sql3);
                    $last_id = mysqli_insert_id($conn);	
                    if(!$result3){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    } 
                }           
            }
        }else{
            $year = "IMP" . date("y") . "-";
            $stock_number = $year . str_pad(1, 5, "0",STR_PAD_LEFT);
            $sql1 = "INSERT INTO stock_in (stock_number,date_time,ware_id,user_id,total_stock,count) 
            VALUES ('$stock_number','$dt','$ware_id','$user_id','$sum',1)"; 
            $result1 = mysqli_query($conn, $sql1);
            $last_id = mysqli_insert_id($conn);	
            if(!$result1){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            } 
        }   
        foreach ($obj as $data)
        {
            $i++;
            $item = $i;
            $prod_id = $data['prod_id'];
            $cost = $data['cost'];    
            $amt = $data['amt'];  
            $addr = isset($data['addr']) ? mysqli_real_escape_string($conn, trim($data['addr'])) : ''; 
            $from = isset($data['from']) ? mysqli_real_escape_string($conn, trim($data['from'])) : '';
            $amount = 0;

            static $stockColChecked = false;
            if (!$stockColChecked) {
                $stockColChecked = true;
                $chk = mysqli_query($conn, "SHOW COLUMNS FROM stock_in_item LIKE 'source_from'");
                if ($chk && mysqli_num_rows($chk) == 0) {
                    @mysqli_query($conn, "ALTER TABLE stock_in_item ADD COLUMN source_from VARCHAR(50) NULL DEFAULT NULL AFTER amount");
                }
            }
    
            if (!empty($from)) {
                $sql_item = "INSERT INTO stock_in_item (stock_id,item_id,prod_id,stock_cost,amount,source_from) VALUE ('$last_id','$item','$prod_id','$cost','$amt','$from')"; 
            } else {
                $sql_item = "INSERT INTO stock_in_item (stock_id,item_id,prod_id,stock_cost,amount) VALUE ('$last_id','$item','$prod_id','$cost','$amt')"; 
            }
            $result_item = mysqli_query($conn, $sql_item);
            if(!$result_item){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            } 
    
            $sql = "SELECT min_amount,status FROM product WHERE prod_id = '$prod_id' AND status != 'normal'"; 
            $result = mysqli_query($conn, $sql);
            if(mysqli_num_rows($result) > 0){    
                while($row = mysqli_fetch_array($result)){  
                    $min = $row['min_amount'];
                    $status = $row['status'];
                }
            }
            $sql = "SELECT amount FROM warehouse WHERE prod_id = '$prod_id'"; 
            $result = mysqli_query($conn, $sql); 
            if(mysqli_num_rows($result) > 0){    
                while($row = mysqli_fetch_array($result)){  
                    $amount += $row['amount'];                    
                }
            } 
            $sql = "SELECT amount FROM shop WHERE prod_id = '$prod_id'"; 
            $result = mysqli_query($conn, $sql); 
            if(mysqli_num_rows($result) > 0){    
                while($row = mysqli_fetch_array($result)){  
                    $amount += $row['amount'];                    
                }
            } 
            if($status == 'danger'){
                if($amount + $amt >= $min){
                    $sql = "UPDATE product SET status = 'normal' WHERE prod_id = '$prod_id'";
                    $result = mysqli_query($conn, $sql);                 
                    if(!$result){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    } 
                }else if($amount + $amt > 10){
                    $sql = "UPDATE product SET status = 'warning' WHERE prod_id = '$prod_id'";
                    $result = mysqli_query($conn, $sql);               
                    if(!$result){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    }    
                }
            }else if($status == 'warning'){
                if($amount + $amt >= $min){
                    $sql = "UPDATE product SET status = 'normal' WHERE prod_id = '$prod_id'";
                    $result = mysqli_query($conn, $sql);               
                    if(!$result){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    }    
                }
            }    
            
            $query = "SELECT prod_id FROM warehouse WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";
            $result_q = mysqli_query($conn, $query);
            if(mysqli_num_rows($result_q) > 0){ 
                $sql = "UPDATE warehouse SET amount = amount+'$amt' WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'";          
            }else{
                $sql = "INSERT INTO warehouse (ware_id,prod_id,amount) VALUE ('$ware_id','$prod_id','$amt')";           
            }
            $result = mysqli_query($conn, $sql);
            if(!$result){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }
            if($addr != ""){
                $sql = "INSERT INTO warehouse_place (ware_id,prod_id,place) VALUE ('$ware_id','$prod_id','$addr')"; 
                $result = mysqli_query($conn, $sql); 
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }            
            }       
        }
        mysqli_commit($conn); 
        echo "success"; 
        mysqli_close($conn);
    }else if($type == "หน้าร้าน"){
        mysqli_begin_transaction($conn);
        $sql = "SELECT tran_number,count FROM transfer_in ORDER BY tran_id DESC LIMIT 1"; 
        $result = mysqli_query($conn, $sql);   
      
        if(mysqli_num_rows($result) > 0){    
            while($row = mysqli_fetch_array($result)){  
                $number = $row['tran_number'];
                $year_old = substr($number,3,2);
                $year_cur = date("y");
                if($year_cur == $year_old){
                    $count = $row['count']+1;
                    $year = "TRA" . $year_cur . "-";
                    $stock_number = $year . str_pad($count, 5, "0",STR_PAD_LEFT);
                    $sql2 = "INSERT INTO transfer_in (tran_number,date_time,shop_id,user_id,total_tran,count) 
                    VALUES ('$stock_number','$dt','$ware_id','$user_id','$sum','$count')"; 
                    $result2 = mysqli_query($conn, $sql2);
                    $last_id = mysqli_insert_id($conn);	
                    if(!$result2){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    } 
                }else{
                    $year = "TRA" . $year_cur . "-";
                    $stock_number = $year . str_pad(1, 5, "0",STR_PAD_LEFT);
                    $sql3 = "INSERT INTO transfer_in (tran_number,date_time,shop_id,user_id,total_tran,count) 
                    VALUES ('$stock_number','$dt','$ware_id','$user_id','$sum',1)"; 
                    $result3 = mysqli_query($conn, $sql3);
                    $last_id = mysqli_insert_id($conn);	
                    if(!$result3){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    } 
                }           
            }
        }else{
            $year = "TRA" . date("y") . "-";
            $stock_number = $year . str_pad(1, 5, "0",STR_PAD_LEFT);
            $sql1 = "INSERT INTO transfer_in (tran_number,date_time,shop_id,user_id,total_tran,count) 
            VALUES ('$stock_number','$dt','$ware_id','$user_id','$sum',1)"; 
            $result1 = mysqli_query($conn, $sql1);
            $last_id = mysqli_insert_id($conn);	
            if(!$result1){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            } 
        }   
        foreach ($obj as $data)
        {
            $i++;
            $item = $i;
            $prod_id = $data['prod_id'];
            $cost = $data['cost'];    
            $amt = $data['amt'];  
            $addr = isset($data['addr']) ? mysqli_real_escape_string($conn, trim($data['addr'])) : ''; 
            $from = isset($data['from']) ? mysqli_real_escape_string($conn, trim($data['from'])) : '';
            $amount = 0;

            static $tranColChecked = false;
            if (!$tranColChecked) {
                $tranColChecked = true;
                $chk = mysqli_query($conn, "SHOW COLUMNS FROM transfer_in_item LIKE 'source_from'");
                if ($chk && mysqli_num_rows($chk) == 0) {
                    @mysqli_query($conn, "ALTER TABLE transfer_in_item ADD COLUMN source_from VARCHAR(50) NULL DEFAULT NULL AFTER amount");
                }
            }
    
            if (!empty($from)) {
                $sql_item = "INSERT INTO transfer_in_item (tran_id,item_id,prod_id,tran_cost,amount,source_from) VALUE ('$last_id','$item','$prod_id','$cost','$amt','$from')"; 
            } else {
                $sql_item = "INSERT INTO transfer_in_item (tran_id,item_id,prod_id,tran_cost,amount) VALUE ('$last_id','$item','$prod_id','$cost','$amt')"; 
            }
            $result_item = mysqli_query($conn, $sql_item);
            if(!$result_item){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            } 
    
            $sql = "SELECT min_amount,status FROM product WHERE prod_id = '$prod_id' AND status != 'normal'"; 
            $result = mysqli_query($conn, $sql);
            if(mysqli_num_rows($result) > 0){    
                while($row = mysqli_fetch_array($result)){  
                    $min = $row['min_amount'];
                    $status = $row['status'];
                }
            }
            $sql = "SELECT amount FROM warehouse WHERE prod_id = '$prod_id'"; 
            $result = mysqli_query($conn, $sql); 
            if(mysqli_num_rows($result) > 0){    
                while($row = mysqli_fetch_array($result)){  
                    $amount += $row['amount'];                    
                }
            } 
            $sql = "SELECT amount FROM shop WHERE prod_id = '$prod_id'"; 
            $result = mysqli_query($conn, $sql); 
            if(mysqli_num_rows($result) > 0){    
                while($row = mysqli_fetch_array($result)){  
                    $amount += $row['amount'];                    
                }
            } 
            if($status == 'danger'){
                if($amount + $amt >= $min){
                    $sql = "UPDATE product SET status = 'normal' WHERE prod_id = '$prod_id'";
                    $result = mysqli_query($conn, $sql);                 
                    if(!$result){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    } 
                }else if($amount + $amt > 10){
                    $sql = "UPDATE product SET status = 'warning' WHERE prod_id = '$prod_id'";
                    $result = mysqli_query($conn, $sql);               
                    if(!$result){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    }    
                }
            }else if($status == 'warning'){
                if($amount + $amt >= $min){
                    $sql = "UPDATE product SET status = 'normal' WHERE prod_id = '$prod_id'";
                    $result = mysqli_query($conn, $sql);               
                    if(!$result){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    }    
                }
            }    
            
            $query = "SELECT prod_id FROM shop WHERE shop_id = '$ware_id' AND prod_id = '$prod_id'";
            $result_q = mysqli_query($conn, $query);
            if(mysqli_num_rows($result_q) > 0){ 
                $sql = "UPDATE shop SET amount = amount+'$amt' WHERE shop_id = '$ware_id' AND prod_id = '$prod_id'";           
            }else{
                $sql = "INSERT INTO shop (shop_id,prod_id,amount) VALUE ('$ware_id','$prod_id','$amt')";           
            }
            $result = mysqli_query($conn, $sql);
            if(!$result){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            } 
            if($addr != ""){
                $sql = "INSERT INTO shop_place (shop_id,prod_id,place) VALUE ('$ware_id','$prod_id','$addr')"; 
                $result = mysqli_query($conn, $sql); 
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }  
            }  
        }
        mysqli_commit($conn); 
        echo "success"; 
        mysqli_close($conn);
    }else{
        echo "fail";        
    }    
?>