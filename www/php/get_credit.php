<?php
include_once "auth_guard.php";
require_login();
    include "db.php";
    $output = array();   
    $dt = date('Y-m-d');
    $lastday = date("Y-m-t 23:59:59", strtotime($dt));
    $firstday = date("Y-m-01 00:00:00", strtotime($dt)); 
   
    if(isset($_POST['value']) && trim($_POST['value']) !== ''){
      $val = mysqli_real_escape_string($conn, trim($_POST['value']));
      $query = "SELECT d.credit_id, d.cus_id, c.cus_name, d.credit_price, d.date_time 
                FROM credit d 
                INNER JOIN customer c ON d.cus_id = c.cus_id 
                WHERE c.cus_name LIKE '%$val%' OR d.date_time LIKE '%$val%' 
                ORDER BY (d.credit_price > 0) DESC, d.date_time DESC";
    }else{
      // โชว์รายการที่ยังค้างชำระทั้งหมด (credit_price > 0) เป็นอันดับแรก
      // และรวมรายการเครดิตทั้งหมดในเดือนปัจจุบันด้วย เรียงจากล่าสุด
      $query = "SELECT d.credit_id, d.cus_id, c.cus_name, d.credit_price, d.date_time 
                FROM credit d 
                INNER JOIN customer c ON d.cus_id = c.cus_id 
                WHERE d.credit_price > 0 
                   OR d.date_time BETWEEN '$firstday' AND '$lastday'
                ORDER BY (d.credit_price > 0) DESC, d.date_time DESC";     
    }
    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0){     
      while($row = mysqli_fetch_assoc($result)){      
        $output[] = $row;  
      }   
    }
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
	mysqli_close($conn);
?>