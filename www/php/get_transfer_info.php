<?php
    include "db.php";
    $imp_id = isset($_POST['imp_id']) ? mysqli_real_escape_string($conn, $_POST['imp_id']) : '';
    $output = array();

    $query = "SELECT s.tran_number, s.shop_id, w.shop_name, s.date_time, s.total_tran,
                     s.supplier_name, s.ref_invoice,
                     i.item_id, i.prod_id, p.prod_name, i.tran_cost, i.amount, i.source_from, i.supplier_sku,
                     u.user_name, p.prod_code, p.prod_price
              FROM transfer_in s 
              INNER JOIN shop_detail w ON s.shop_id = w.shop_id 
              INNER JOIN transfer_in_item i ON s.tran_id = i.tran_id 
              INNER JOIN product p ON i.prod_id = p.prod_id 
              INNER JOIN user u ON s.user_id = u.user_id 
              WHERE s.tran_id = '$imp_id'";
    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0){    
      while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){      
        $output[] = $row;  
      }   
    }
    if($result){
        echo json_encode($output, JSON_UNESCAPED_UNICODE);		   
    }else{
        echo "fail";
    }
	mysqli_close($conn);
?>