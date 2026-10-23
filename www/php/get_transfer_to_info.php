<?php
include_once "auth_guard.php";
require_login();

include "db.php";
$imp_id = isset($_POST['imp_id']) ? intval($_POST['imp_id']) : 0;
$output = array();

$query = "SELECT 
            t.branch_id,
            t.tf_id,
            t.tf_number,
            t.direction,
            t.status,
            t.receiver_name,
            t.receive_time,
            t.reject_reason,
            t.shop_id,
            w.ware_name,
            s.shop_name,
            t.date_time,
            t.total,
            i.item_id,
            i.prod_id,
            p.prod_name,
            p.prod_code,
            i.prod_price,
            i.prod_amount,
            u.user_name,
            u.user_name AS sender_name
          FROM transfer_record t 
          LEFT JOIN warehouse_detail w ON t.branch_id = w.ware_id 
          LEFT JOIN shop_detail s ON t.shop_id = s.shop_id 
          LEFT JOIN transfer_record_item i ON t.tf_id = i.tf_id 
          LEFT JOIN product p ON i.prod_id = p.prod_id 
          LEFT JOIN user u ON t.user = u.user_id 
          WHERE t.tf_id = '$imp_id'
          ORDER BY i.item_id ASC";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {    
    while ($row = mysqli_fetch_assoc($result)) {
        if (empty($row['status'])) {
            $row['status'] = 'received';
        }
        if ($row['direction'] == 'ware_to_shop') {
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