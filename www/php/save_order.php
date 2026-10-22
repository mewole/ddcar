<?php
    include "db.php";
include_once "auth_guard.php";
require_login();
    $input  = $_POST['JSON'];
    $obj = json_decode($input,true); 
    $input_d  = $_POST['JSOND'];
    $obj_d = json_decode($input_d,true); 
    $cus  = $_POST['cus_id'];
    $b  = $_POST['ware_id'];
    $dt  = $_POST['date'];
    $sum  = $_POST['net'];
    $pay  = $_POST['pay'];
    $dis  = $_POST['dis'];
    $type = $_POST['type'];
    $get  = $_POST['get_price'];
    $change  = $_POST['chng'];
    $total  = $_POST['total'];
    $comment  = $_POST['comment'];   
    $user_id = $_POST['user_id'];
    $online_name = $_POST['online'];
    $vat_type = isset($_POST['vat_type']) ? mysqli_real_escape_string($conn, trim($_POST['vat_type'])) : (isset($_POST['vat']) && $_POST['vat'] == 'vat' ? 'vat' : 'no_vat');
    $vat_amount = ($vat_type === 'vat') ? round(floatval($_POST['total'] ?? 0) * 7 / 107, 2) : 0.00;
    $b_to = $_POST['ware_id_to'];

    mysqli_begin_transaction($conn);
    if($b_to == 0){
        foreach ($obj_d as $data){
            $prod_id = $data['prod_id'];
            $price = $data['price'];   
          
            $sql = "SELECT * FROM detail_cus WHERE prod_id = '$prod_id' AND cus_id = '$cus'"; 
            $result = mysqli_query($conn, $sql);    
            if(mysqli_num_rows($result) > 0){   
                $sql = "UPDATE detail_cus SET cus_price = '$price' WHERE prod_id = '$prod_id' AND cus_id = '$cus'"; 
            }else{
                $sql = "INSERT INTO detail_cus(prod_id,cus_id,cus_price) VALUE ('$prod_id','$cus','$price')"; 
            }        
            $result = mysqli_query($conn, $sql);  
            if(!$result){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }        
        }
    
        $credit_id = 0;
        if(strpos($pay,'เครดิต') !== false){
            $lastday = date("Y-m-t", strtotime($dt));
            $firstday = date("Y-m-01", strtotime($dt));
            $sql = "SELECT * FROM credit WHERE date_time BETWEEN '$firstday' AND '$lastday' AND cus_id = '$cus'"; 
            $result = mysqli_query($conn, $sql);
            if(mysqli_num_rows($result) > 0){    
                while($row = mysqli_fetch_array($result)){  
                    $credit_id = $row['credit_id'];                
                } 
                $sql = "UPDATE credit SET credit_price = credit_price+'$total' WHERE credit_id = '$credit_id'";
                $result = mysqli_query($conn, $sql);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }    
            }else{
                $sql = "SELECT credit_num,count FROM credit ORDER BY credit_id DESC LIMIT 1"; 
                $result = mysqli_query($conn, $sql);    
            
                if(mysqli_num_rows($result) > 0){    
                    while($row = mysqli_fetch_array($result)){  
                        $number = $row['credit_num'];
                        $year_old = substr($number,1,2);
                        $year_cur = date("y");
                        if($year_cur == $year_old){
                            $count = $row['count']+1;
                            $year = "C" . $year_cur . "-";
                            $order_number = $year . str_pad($count, 5, "0",STR_PAD_LEFT);
                            $sql2 = "INSERT INTO credit (credit_num,cus_id,credit_price,date_time,count) 
                            VALUE ('$order_number','$cus','$total','$dt','$count')"; 
                            $result2 = mysqli_query($conn, $sql2);
                            if(!$result2){
                                mysqli_rollback($conn);
                                echo "fail";
                                exit;
                            }
                            $credit_id = mysqli_insert_id($conn);                
                        }else{
                            $year = "C" . $year_cur . "-";
                            $order_number = $year . str_pad(1, 5, "0",STR_PAD_LEFT);
                            $sql3 = "INSERT INTO credit (credit_num,cus_id,credit_price,date_time,count) 
                            VALUE ('$order_number','$cus','$total','$dt',1)"; 
                            $result3 = mysqli_query($conn, $sql3);
                            if(!$result3){
                                mysqli_rollback($conn);
                                echo "fail";
                                exit;
                            }
                            $credit_id = mysqli_insert_id($conn);               
                        }           
                    }
                }else{
                    $year = "C" . date("y") . "-";
                    $order_number = $year . str_pad(1, 5, "0",STR_PAD_LEFT);
                    $sql1 = "INSERT INTO credit (credit_num,cus_id,credit_price,date_time,count) 
                    VALUE ('$order_number','$cus','$total','$dt',1)"; 
                    $result1 = mysqli_query($conn, $sql1);
                    if(!$result1){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    }
                    $credit_id = mysqli_insert_id($conn);       
                }	
            }
        }
    
        $sql = "SELECT order_number,count FROM sale_order ORDER BY order_id DESC LIMIT 1"; 
        $result = mysqli_query($conn, $sql);    
       
        if(mysqli_num_rows($result) > 0){
            while($row = mysqli_fetch_array($result)){  
                $number = $row['order_number'];
                $year_old = substr($number,1,2);
                $year_cur = date("y");
                if($year_cur == $year_old){
                    $count = $row['count']+1;
                    $year = "S" . $year_cur . "-";
                    $order_number = $year . str_pad($count, 5, "0",STR_PAD_LEFT);
                    $sql2 = "INSERT INTO sale_order (order_number,customer_id,date_time,sum_price,
                    payment_type,branch_id,type,total_discount,total_price,get_price,chng,user_id,credit_id,comment,count,vat_type,vat_amount) 
                    VALUE ('$order_number','$cus','$dt','$sum','$pay','$b','$type','$dis','$total','$get','$change','$user_id','$credit_id','$comment','$count','$vat_type','$vat_amount')"; 
                    $result2 = mysqli_query($conn, $sql2);
                    if(!$result2){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    }
                    $last_id = mysqli_insert_id($conn);	
                }else{
                    $year = "S" . $year_cur . "-";
                    $order_number = $year . str_pad(1, 5, "0",STR_PAD_LEFT);
                    $sql3 = "INSERT INTO sale_order (order_number,customer_id,date_time,sum_price,
                    payment_type,branch_id,type,total_discount,total_price,get_price,chng,user_id,credit_id,comment,count,vat_type,vat_amount) 
                    VALUE ('$order_number','$cus','$dt','$sum','$pay','$b','$type','$dis','$total','$get','$change','$user_id','$credit_id','$comment',1,'$vat_type','$vat_amount')"; 
                    $result3 = mysqli_query($conn, $sql3);
                    if(!$result3){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    }
                    $last_id = mysqli_insert_id($conn);	
                }           
            }
        }else{
            $year = "S" . date("y") . "-";
            $order_number = $year . str_pad(1, 5, "0",STR_PAD_LEFT);
            $sql1 = "INSERT INTO sale_order (order_number,customer_id,date_time,sum_price,
            payment_type,branch_id,type,total_discount,total_price,get_price,chng,user_id,credit_id,comment,count,vat_type,vat_amount) 
            VALUE ('$order_number','$cus','$dt','$sum','$pay','$b','$type','$dis','$total','$get','$change','$user_id','$credit_id','$comment',1,'$vat_type','$vat_amount')"; 
            $result1 = mysqli_query($conn, $sql1);
            if(!$result1){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }
            $last_id = mysqli_insert_id($conn);	
        }  
        $item = 0;
        $ch = 'false'; 
        foreach ($obj as $data){
            $item++;
            $prod_id = $data['prod_id'];
            $price = $data['price'];    
            $amt = $data['amt'];    
            $amount = 0;
          
            $sql1 = "INSERT INTO sale_order_item (order_id,item_id,prod_id,prod_price,prod_amount)
             VALUE ('$last_id','$item','$prod_id','$price','$amt')"; 
            $result1 = mysqli_query($conn, $sql1);  
            if(!$result1){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            } 
    
            $sql = "SELECT min_amount,status FROM product WHERE prod_id = '$prod_id' AND status != 'danger'"; 
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
            if($status == 'normal'){            
                if($amount - $amt <= 10){
                    $sql = "UPDATE product SET status = 'danger' WHERE prod_id = '$prod_id'";
                    $result = mysqli_query($conn, $sql);
                    $ch = 'true';    
                    if(!$result){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    }    
                }else if($amount - $amt <= $min){
                    $sql = "UPDATE product SET status = 'warning' WHERE prod_id = '$prod_id'";
                    $result = mysqli_query($conn, $sql); 
                    $ch = 'true';    
                    if(!$result){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    } 
                }
            }else if($status == 'warning'){
                if($amount - $amt <= 10){
                    $sql = "UPDATE product SET status = 'danger' WHERE prod_id = '$prod_id'";
                    $result = mysqli_query($conn, $sql);
                    $ch = 'true';    
                    if(!$result){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    }    
                }
            }       
           
            if($type == "โกดัง"){
                $sql_up = "UPDATE warehouse SET amount = amount-'$amt' WHERE ware_id = '$b' AND prod_id = '$prod_id'"; 
            }else if($type == "หน้าร้าน"){
                $sql_up = "UPDATE shop SET amount = amount-'$amt' WHERE shop_id = '$b' AND prod_id = '$prod_id'"; 
            }       
            $result_up = mysqli_query($conn, $sql_up);    
            if(!$result_up){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }

            // DD Accessories - Auto-Deduct Bundle Child Stock & Dual VAT Buckets
            $p_info_res = mysqli_query($conn, "SELECT prod_code, prod_name, prod_cost FROM product WHERE prod_id = '$prod_id'");
            if ($p_info_res && mysqli_num_rows($p_info_res) > 0) {
                $p_info = mysqli_fetch_assoc($p_info_res);
                $cur_code = $p_info['prod_code'];
                $cur_cost = floatval($p_info['prod_cost']);

                // 1. Bundle Recipe Auto-Deduct
                $bun_q = mysqli_query($conn, "SELECT child_sku, ratio FROM bundle_recipes WHERE bundle_sku = '$cur_code'");
                if ($bun_q && mysqli_num_rows($bun_q) > 0) {
                    while ($b_part = mysqli_fetch_assoc($bun_q)) {
                        $child_sku = mysqli_real_escape_string($conn, $b_part['child_sku']);
                        $child_ratio = intval($b_part['ratio']) ?: 1;
                        $child_deduct = $amt * $child_ratio;

                        $cp_res = mysqli_query($conn, "SELECT prod_id FROM product WHERE prod_code = '$child_sku' LIMIT 1");
                        if ($cp_res && mysqli_num_rows($cp_res) > 0) {
                            $cp_row = mysqli_fetch_assoc($cp_res);
                            $cp_id = $cp_row['prod_id'];
                            if ($type == "โกดัง") {
                                mysqli_query($conn, "UPDATE warehouse SET amount = amount - $child_deduct WHERE ware_id = '$b' AND prod_id = '$cp_id'");
                            } else {
                                mysqli_query($conn, "UPDATE shop SET amount = amount - $child_deduct WHERE shop_id = '$b' AND prod_id = '$cp_id'");
                            }
                        }
                    }
                }

                // 2. Dual Tax Buckets Auto-Deduct & Pending VAT Queue
                $vat_plat_q = mysqli_query($conn, "SELECT vat_stock, non_vat_stock FROM product_platform_sku WHERE prod_id = '$prod_id'");
                if ($vat_plat_q && mysqli_num_rows($vat_plat_q) > 0) {
                    $vp_row = mysqli_fetch_assoc($vat_plat_q);
                    $v_stk = intval($vp_row['vat_stock']);
                    $nv_stk = intval($vp_row['non_vat_stock']);

                    if ($vat_type === 'vat') {
                        if ($v_stk >= $amt) {
                            mysqli_query($conn, "UPDATE product_platform_sku SET vat_stock = vat_stock - $amt WHERE prod_id = '$prod_id'");
                        } else {
                            $rem = $amt - $v_stk;
                            mysqli_query($conn, "UPDATE product_platform_sku SET vat_stock = 0, non_vat_stock = GREATEST(0, non_vat_stock - $rem) WHERE prod_id = '$prod_id'");

                            $is_online = !empty($online_name) || stripos($comment, 'shopee') !== false || stripos($comment, 'lazada') !== false || stripos($comment, 'tiktok') !== false;
                            if ($is_online && $rem > 0) {
                                $tot_cost_rem = $cur_cost * $rem;
                                $need_vat = round($tot_cost_rem * 0.07, 2);
                                $prod_nm_esc = mysqli_real_escape_string($conn, $p_info['prod_name']);
                                mysqli_query($conn, "INSERT INTO pending_input_vat 
                                    (supplier_name, ref_invoice, order_date, prod_code, prod_name, qty, cost_amount, pending_vat_amount, platform, status, notes)
                                    VALUES ('ร้านค้าส่ง (ต้องตามบิล)', '$order_number', CURDATE(), '$cur_code', '$prod_nm_esc', $rem, $tot_cost_rem, $need_vat, 'Online', 'pending_invoice', 'ขายออนไลน์แต่ตัดสต็อกไม่มี VAT ไปก่อน')");
                            }
                        }
                    } else {
                        if ($nv_stk >= $amt) {
                            mysqli_query($conn, "UPDATE product_platform_sku SET non_vat_stock = non_vat_stock - $amt WHERE prod_id = '$prod_id'");
                        } else {
                            $rem = $amt - $nv_stk;
                            mysqli_query($conn, "UPDATE product_platform_sku SET non_vat_stock = 0, vat_stock = GREATEST(0, vat_stock - $rem) WHERE prod_id = '$prod_id'");
                        }
                    }
                }
            }    
        }
        if($ch == 'true'){
            $sql = "UPDATE bell SET status = 'alert'";
            $result = mysqli_query($conn, $sql);       
            if(!$result){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }         
        }
        mysqli_commit($conn); 
        echo $order_number." ".$ch;
        mysqli_close($conn);
    }else{
        $sql1 = "INSERT INTO transfer_record (total,shop_id,branch_id,date_time,user) 
            VALUE ('$total','$b','$b_to','$dt','$user_id')"; 
            $result1 = mysqli_query($conn, $sql1);
            if(!$result1){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }
        $last_id = mysqli_insert_id($conn);	
        $item = 0;        
        foreach ($obj as $data){
            $item++;
            $prod_id = $data['prod_id'];
            $price = $data['price'];    
            $amt = $data['amt'];    
            $amount = 0;
          
            $sql1 = "INSERT INTO transfer_record_item (tf_id,item_id,prod_id,prod_price,prod_amount)
             VALUE ('$last_id','$item','$prod_id','$price','$amt')"; 
            $result1 = mysqli_query($conn, $sql1);  
            if(!$result1){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            } 
    
            $sql = "SELECT min_amount,status FROM product WHERE prod_id = '$prod_id' AND status != 'danger'"; 
            $result = mysqli_query($conn, $sql);
            if(mysqli_num_rows($result) > 0){    
                while($row = mysqli_fetch_array($result)){  
                    $min = $row['min_amount'];
                    $status = $row['status'];
                }
            }

            $sql_up1 = "UPDATE shop SET amount = amount-'$amt' WHERE shop_id = '$b' AND prod_id = '$prod_id'";                   
            $result_up1 = mysqli_query($conn, $sql_up1);    
            if(!$result_up1){
                mysqli_rollback($conn);
                echo "fail";
                exit;
            }
            $query = "SELECT * FROM warehouse WHERE ware_id = '$b_to' AND prod_id = '$prod_id'";  
            $result = mysqli_query($conn, $query);
                if(mysqli_num_rows($result) > 0){                
                    $sql_up = "UPDATE warehouse SET amount = amount+'$amt' WHERE ware_id = '$b_to' AND prod_id = '$prod_id'";
                    $result_up = mysqli_query($conn, $sql_up);    
                    if(!$result_up){
                        mysqli_rollback($conn);
                        echo "fail";
                        exit;
                    }
            }else{
                $sql_up = "INSERT INTO warehouse(amount,ware_id,prod_id) VALUE ('$amt','$b_to','$prod_id')";
                $result_up = mysqli_query($conn, $sql_up);    
                if(!$result_up){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }
            } 
          
            
        }
        mysqli_commit($conn); 
        echo "Success";
        mysqli_close($conn);
    }
    
?>