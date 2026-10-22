<?php
include_once "auth_guard.php";
require_login();
include "db.php";

$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$output = array();
$txt = "(cancel)";

$baseSelect = "SELECT 
                t.tf_id,
                t.tf_number,
                t.direction,
                t.status,
                t.total,
                t.date_time,
                t.remark,
                t.receiver_name,
                t.receive_time,
                t.reject_reason,
                t.shop_id,
                t.branch_id,
                w.ware_name,
                s.shop_name,
                u.user_name AS sender_name,
                (SELECT COUNT(*) FROM transfer_record_item WHERE tf_id = t.tf_id) AS item_count
               FROM transfer_record t 
               LEFT JOIN warehouse_detail w ON t.branch_id = w.ware_id 
               LEFT JOIN shop_detail s ON t.shop_id = s.shop_id 
               LEFT JOIN user u ON t.user = u.user_id ";

if(isset($_POST['date'])){
    $date = mysqli_real_escape_string($conn, trim($_POST['date']));
    $date_to = mysqli_real_escape_string($conn, trim($_POST['date_to']));
    if (strlen($date) <= 10) $date .= ' 00:00:00';
    if (strlen($date_to) <= 10) $date_to .= ' 23:59:59';
    $query = $baseSelect . " WHERE t.date_time BETWEEN '$date' AND '$date_to' 
             AND (t.remark IS NULL OR t.remark NOT LIKE '%$txt%') 
             ORDER BY t.tf_id DESC LIMIT $start, 30";
} else if(isset($_POST['value'])){
    $val = mysqli_real_escape_string($conn, trim($_POST['value']));     
    $query = "SELECT DISTINCT 
                t.tf_id,
                t.tf_number,
                t.direction,
                t.status,
                t.total,
                t.date_time,
                t.remark,
                t.receiver_name,
                t.receive_time,
                t.reject_reason,
                t.shop_id,
                t.branch_id,
                w.ware_name,
                s.shop_name,
                u.user_name AS sender_name,
                (SELECT COUNT(*) FROM transfer_record_item WHERE tf_id = t.tf_id) AS item_count
              FROM transfer_record t 
              LEFT JOIN warehouse_detail w ON t.branch_id = w.ware_id 
              LEFT JOIN shop_detail s ON t.shop_id = s.shop_id 
              LEFT JOIN user u ON t.user = u.user_id 
              LEFT JOIN transfer_record_item i ON t.tf_id = i.tf_id 
              LEFT JOIN product p ON i.prod_id = p.prod_id 
              WHERE (w.ware_name LIKE '%$val%' OR s.shop_name LIKE '%$val%' OR p.prod_name LIKE '%$val%' OR p.prod_code LIKE '%$val%' OR t.tf_number LIKE '%$val%' OR t.receiver_name LIKE '%$val%' OR u.user_name LIKE '%$val%' OR t.remark LIKE '%$val%') 
                AND (t.remark IS NULL OR t.remark NOT LIKE '%$txt%') 
              ORDER BY t.tf_id DESC LIMIT $start, 30";
} else {
    $query = $baseSelect . " WHERE (t.remark IS NULL OR t.remark NOT LIKE '%$txt%') 
             ORDER BY t.tf_id DESC LIMIT $start, 30";
}    

$result = mysqli_query($conn, $query);

if($result && mysqli_num_rows($result) > 0){
    if(mysqli_num_rows($result) < 30){           
        $output[] = 'last';
    }else{
        $output[] = 'not last';
    }
    while($row = mysqli_fetch_assoc($result)){      
        if(empty($row['status'])){
            $row['status'] = 'received';
        }
        if($row['direction'] == 'ware_to_shop'){
            $row['source_name'] = !empty($row['ware_name']) ? $row['ware_name'] : 'โกดัง';
            $row['dest_name'] = !empty($row['shop_name']) ? $row['shop_name'] : 'หน้าร้าน';
        } else {
            $row['source_name'] = !empty($row['shop_name']) ? $row['shop_name'] : 'หน้าร้าน';
            $row['dest_name'] = !empty($row['ware_name']) ? $row['ware_name'] : 'โกดัง';
        }
        $output[] = $row;  
    }   
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>