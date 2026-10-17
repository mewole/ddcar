<?php
include "db.php";

header('Content-Type: application/json; charset=utf-8');

$txtCancel = "(cancel)";
$type = isset($_GET['type']) ? trim($_GET['type']) : 'pending';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 15;

if ($type === 'recent') {
    $whereStatus = "t.status IN ('received', 'rejected')";
    $limitSql = "LIMIT " . max(1, min(50, $limit));
} else {
    $whereStatus = "t.status = 'pending'";
    $limitSql = "";
}

$query = "SELECT 
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
            s.shop_name,
            w.ware_name,
            u.user_name AS sender_name,
            (SELECT COUNT(*) FROM transfer_record_item WHERE tf_id = t.tf_id) AS item_count
          FROM transfer_record t
          LEFT JOIN shop_detail s ON t.shop_id = s.shop_id
          LEFT JOIN warehouse_detail w ON t.branch_id = w.ware_id
          LEFT JOIN user u ON t.user = u.user_id
          WHERE $whereStatus 
            AND (t.remark IS NULL OR t.remark NOT LIKE '%$txtCancel%')
          ORDER BY t.tf_id DESC $limitSql";

$result = mysqli_query($conn, $query);
$output = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['direction'] == 'shop_to_ware') {
            $row['source_name'] = !empty($row['shop_name']) ? $row['shop_name'] : 'หน้าร้าน';
            $row['dest_name'] = !empty($row['ware_name']) ? $row['ware_name'] : 'โกดัง';
        } else {
            $row['source_name'] = !empty($row['ware_name']) ? $row['ware_name'] : 'โกดัง';
            $row['dest_name'] = !empty($row['shop_name']) ? $row['shop_name'] : 'หน้าร้าน';
        }
        $output[] = $row;
    }
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
mysqli_close($conn);
?>
