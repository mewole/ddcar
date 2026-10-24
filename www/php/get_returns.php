<?php
/**
 * get_returns.php
 * ดึงรายการเอกสารรับคืนสินค้า (RT26-xxxxx) ทั้งหมด
 */
include_once "auth_guard.php";
require_login();

header('Content-Type: application/json; charset=utf-8');
include "db.php";

$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date   = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$keyword    = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$limit      = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

$where = ["1=1"];

if (!empty($start_date)) {
    $clean_sd = mysqli_real_escape_string($conn, $start_date);
    $where[] = "r.actual_return_date >= '$clean_sd'";
}
if (!empty($end_date)) {
    $clean_ed = mysqli_real_escape_string($conn, $end_date);
    $where[] = "r.actual_return_date <= '$clean_ed'";
}
if (!empty($keyword)) {
    $clean_kw = mysqli_real_escape_string($conn, $keyword);
    $where[] = "(r.return_code LIKE '%$clean_kw%' OR r.bill_number LIKE '%$clean_kw%' OR r.order_sn LIKE '%$clean_kw%' OR r.customer_name LIKE '%$clean_kw%')";
}

$where_sql = implode(' AND ', $where);

$query = "SELECT r.*, 
                 (SELECT COUNT(*) FROM product_return_item WHERE return_id = r.id) as item_count,
                 (SELECT COALESCE(SUM(qty), 0) FROM product_return_item WHERE return_id = r.id) as total_qty
          FROM product_return r
          WHERE $where_sql
          ORDER BY r.id DESC
          LIMIT $limit";

$res = mysqli_query($conn, $query);
$returns = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $ret_id = intval($row['id']);
        
        // ดึงรายการสินค้าของเอกสารนี้
        $q_items = mysqli_query($conn, "SELECT ri.*, p.img 
                                        FROM product_return_item ri
                                        LEFT JOIN product p ON ri.prod_id = p.prod_id
                                        WHERE ri.return_id = '$ret_id'
                                        ORDER BY ri.id ASC");
        $items = [];
        if ($q_items) {
            while ($it = mysqli_fetch_assoc($q_items)) {
                $items[] = [
                    'id'            => intval($it['id']),
                    'prod_id'       => intval($it['prod_id']),
                    'prod_code'     => $it['prod_code'],
                    'prod_name'     => $it['prod_name'],
                    'img'           => $it['img'] ?: 'img/no_img.png',
                    'qty'           => intval($it['qty']),
                    'unit_price'    => floatval($it['unit_price']),
                    'cost'          => floatval($it['cost']),
                    'destination'   => $it['destination'],
                    'branch_type'   => $it['branch_type'],
                    'damage_reason' => $it['damage_reason'],
                    'damage_images' => json_decode($it['damage_images'] ?? '[]', true)
                ];
            }
        }
        $row['items'] = $items;
        $row['total_refund_amount'] = floatval($row['total_refund_amount']);
        $returns[] = $row;
    }
}

mysqli_close($conn);

echo json_encode([
    'success' => true,
    'total'   => count($returns),
    'data'    => $returns
], JSON_UNESCAPED_UNICODE);
?>
