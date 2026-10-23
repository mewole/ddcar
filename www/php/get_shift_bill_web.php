<?php
include_once "auth_guard.php";
require_login();

include "db.php";
$output = array();
    $date_f = isset($_POST['datefrom']) ? mysqli_real_escape_string($conn, trim($_POST['datefrom'])) : date('Y-m-d');
    $date_t = isset($_POST['dateto']) ? mysqli_real_escape_string($conn, trim($_POST['dateto'])) : $date_f;
    $date_from = $date_f.' 00:00:00';
    $date_to = $date_t.' 23:59:59';
    $txt = "(cancel)";    

    $query = "SELECT 
                s.order_id,
                s.order_number,
                u.user_name,
                s.total_price,
                s.date_time,
                s.customer_id,
                COALESCE(c.cus_name, 'ลูกค้าทั่วไป') AS cus_name,
                COALESCE(cost_data.total_cost, 0) AS count
              FROM sale_order s 
              INNER JOIN user u ON s.user_id = u.user_id 
              LEFT JOIN customer c ON s.customer_id = c.cus_id
              LEFT JOIN (
                  SELECT soi.order_id, SUM(COALESCE(soi.prod_amount, 0) * COALESCE(pr.prod_cost, 0)) AS total_cost
                  FROM sale_order_item soi
                  INNER JOIN product pr ON soi.prod_id = pr.prod_id
                  WHERE soi.order_id IN (
                      SELECT order_id FROM sale_order 
                      WHERE date_time BETWEEN '$date_from' AND '$date_to' 
                        AND order_number NOT LIKE '%$txt%'
                  )
                  GROUP BY soi.order_id
              ) cost_data ON s.order_id = cost_data.order_id
              WHERE s.date_time BETWEEN '$date_from' AND '$date_to' 
                AND s.order_number NOT LIKE '%$txt%'
              ORDER BY s.order_id DESC";    

    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0){    
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