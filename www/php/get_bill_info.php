<?php
include_once "auth_guard.php";
require_login();

include "db.php";
header("Content-Type: text/html; charset=UTF-8");
mysqli_set_charset($conn, "utf8");

$bill_id = isset($_REQUEST['bill_id']) ? mysqli_real_escape_string($conn, $_REQUEST['bill_id']) : '';
$output = array();

if (!empty($bill_id)) {
    // Use LEFT JOIN so bills with deleted customer or deleted product or deleted user still return!
    $query = "SELECT s.order_number, 
                     COALESCE(c.cus_name, 'ลูกค้าทั่วไป') AS cus_name, 
                     s.date_time, s.payment_type, s.branch_id, s.type, s.sum_price,
                     s.total_discount, s.total_price, s.get_price, s.chng, 
                     COALESCE(u.user_name, 'พนักงาน') AS user_name, 
                     COALESCE(i.item_id, 0) AS item_id, 
                     COALESCE(i.prod_id, 0) AS prod_id,
                     COALESCE(p.prod_name, 'สินค้า (ลบแล้ว)') AS prod_name, 
                     COALESCE(p.prod_code, '') AS prod_code, 
                     COALESCE(i.prod_price, 0) AS prod_price, 
                     COALESCE(i.prod_amount, 0) AS prod_amount, 
                     s.credit_id, s.comment
              FROM sale_order s 
              LEFT JOIN customer c ON s.customer_id = c.cus_id 
              LEFT JOIN user u ON s.user_id = u.user_id 
              LEFT JOIN sale_order_item i ON s.order_id = i.order_id 
              LEFT JOIN product p ON i.prod_id = p.prod_id 
              WHERE s.order_id = '$bill_id'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {    
        while ($row = mysqli_fetch_array($result)) {
            $type = isset($row['type']) ? trim($row['type']) : '';
            $id = isset($row['branch_id']) ? mysqli_real_escape_string($conn, $row['branch_id']) : '';

            $ware_name = '';
            $shop_name = '';

            // Query warehouse or shop details
            if (!empty($id)) {
                $res_w = mysqli_query($conn, "SELECT ware_name FROM warehouse_detail WHERE ware_id = '$id' LIMIT 1");
                if ($res_w && ($rw = mysqli_fetch_assoc($res_w))) {
                    $ware_name = $rw['ware_name'];
                }
                $res_s = mysqli_query($conn, "SELECT shop_name FROM shop_detail WHERE shop_id = '$id' LIMIT 1");
                if ($res_s && ($rs = mysqli_fetch_assoc($res_s))) {
                    $shop_name = $rs['shop_name'];
                }
            }

            $branch_name = !empty($ware_name) ? $ware_name : (!empty($shop_name) ? $shop_name : ($type ?: '-'));
            $branch_info = array(
                0 => $branch_name,
                'ware_name' => !empty($ware_name) ? $ware_name : $branch_name,
                'shop_name' => !empty($shop_name) ? $shop_name : $branch_name
            );

            // Populate index 19 and 20, as well as associative keys
            $row['ware_name'] = $branch_info['ware_name'];
            $row['shop_name'] = $branch_info['shop_name'];
            $row['branch_info'] = $branch_info;
            $row[19] = $branch_info;
            $row[20] = $branch_info;

            $output[] = $row;  
        }   
    }
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>
