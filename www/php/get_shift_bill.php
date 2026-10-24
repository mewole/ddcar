<?php
include_once "auth_guard.php";
require_login();

include "db.php";
$output = array();   
    $date = isset($_POST['date']) ? mysqli_real_escape_string($conn, trim($_POST['date'])) : date('Y-m-d');
    $date_from = $date.' 00:00:00';
    $date_to = $date.' 23:59:59';
    $txt = "(cancel)";    

    $query = "SELECT s.order_id, s.order_number, u.user_name, s.total_price, s.count 
              FROM sale_order s 
              INNER JOIN user u ON s.user_id = u.user_id 
              WHERE s.date_time BETWEEN '$date_from' AND '$date_to' 
                AND s.order_number NOT LIKE '%$txt%'
              ORDER BY s.order_id DESC";    
    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0){    
        $orders = array();
        $order_ids = array();
        while($row = mysqli_fetch_assoc($result)){
            $row['count'] = 0; // will store total cost
            $orders[$row['order_id']] = $row;
            $order_ids[] = intval($row['order_id']);
        }

        if(!empty($order_ids)){
            $idList = implode(',', $order_ids);
            // Batch calculate total cost per order in ONE single query instead of N*M queries
            $cost_query = "SELECT soi.order_id, SUM(soi.prod_amount * p.prod_cost) AS total_cost
                           FROM sale_order_item soi
                           INNER JOIN product p ON soi.prod_id = p.prod_id
                           WHERE soi.order_id IN ($idList)
                           GROUP BY soi.order_id";
            $cost_res = mysqli_query($conn, $cost_query);
            if($cost_res){
                while($crow = mysqli_fetch_assoc($cost_res)){
                    $oid = $crow['order_id'];
                    if(isset($orders[$oid])){
                        $orders[$oid]['count'] = floatval($crow['total_cost']);
                    }
                }
            }
        }
        $can_view_cost = in_array(get_current_user_role(), ['เจ้าของร้าน', 'ผู้ดูแลระบบ', 'แอดมิน']);
        if (!$can_view_cost) {
            foreach ($orders as &$ord) {
                unset($ord['count']);
            }
            unset($ord);
        }
        $output = array_values($orders);
    }
    
    if($result){
        echo json_encode($output);		   
    }else{
        echo "fail";
    }
    mysqli_close($conn);
?>