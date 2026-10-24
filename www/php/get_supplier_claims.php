<?php
/**
 * get_supplier_claims.php
 * ดึงรายการเคลมร้านค้า/ซัพพลายเออร์ และหน้า "เคลมค้างรับ"
 * คำนวณจำนวนวันค้างอัตโนมัติ และเรียงลำดับจากค้างนานสุด
 */
include_once "auth_guard.php";
require_login();

header('Content-Type: application/json; charset=utf-8');
include "db.php";

$status   = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$supplier = isset($_GET['supplier']) ? trim($_GET['supplier']) : '';
$keyword  = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$mode     = isset($_GET['mode']) ? trim($_GET['mode']) : ''; // 'pending_only' สำหรับแท็บ "เคลมค้างรับ"

// นับสรุปยอดตามสถานะ
$q_sum = mysqli_query($conn, "SELECT 
    COUNT(CASE WHEN status = 'รอส่งเคลม' THEN 1 END) as wait_send,
    COUNT(CASE WHEN status = 'ส่งเคลมแล้ว' THEN 1 END) as sent_pending,
    COUNT(CASE WHEN status = 'ปิด' THEN 1 END) as closed_count
FROM product_claim_supplier");
$summary = ['wait_send' => 0, 'sent_pending' => 0, 'closed_count' => 0];
if ($q_sum && $r_sum = mysqli_fetch_assoc($q_sum)) {
    $summary = [
        'wait_send'    => intval($r_sum['wait_send']),
        'sent_pending' => intval($r_sum['sent_pending']),
        'closed_count' => intval($r_sum['closed_count'])
    ];
}

$status_map = [
    'wait_send'    => 'รอส่งเคลม',
    'sent'         => 'ส่งเคลมแล้ว',
    'sent_pending' => 'ส่งเคลมแล้ว',
    'closed'       => 'ปิด'
];
if (isset($status_map[$status])) {
    $status = $status_map[$status];
}

$where = ["1=1"];

if ($mode === 'pending_only' || $status === 'ส่งเคลมแล้ว') {
    $where[] = "c.status = 'ส่งเคลมแล้ว'";
} elseif ($status !== 'all' && !empty($status)) {
    $clean_st = mysqli_real_escape_string($conn, $status);
    $where[] = "c.status = '$clean_st'";
}

if (!empty($supplier)) {
    $clean_sup = mysqli_real_escape_string($conn, $supplier);
    $where[] = "c.supplier_name LIKE '%$clean_sup%'";
}

if (!empty($keyword)) {
    $clean_kw = mysqli_real_escape_string($conn, $keyword);
    $where[] = "(c.claim_code LIKE '%$clean_kw%' OR c.prod_code LIKE '%$clean_kw%' OR c.prod_name LIKE '%$clean_kw%' OR c.supplier_ref_no LIKE '%$clean_kw%' OR c.return_code LIKE '%$clean_kw%')";
}

$where_sql = implode(' AND ', $where);

// ถ้าเป็นหน้า "เคลมค้างรับ" ให้เรียงจากค้างนานสุด (days_pending DESC) ตามกติกา
$order_by = ($mode === 'pending_only' || $status === 'ส่งเคลมแล้ว') 
    ? "DATEDIFF(CURDATE(), c.claim_date) DESC, c.id ASC" 
    : "c.id DESC";

$query = "SELECT c.*, 
                 DATEDIFF(CURDATE(), c.claim_date) as days_pending,
                 p.img, p.prod_cost
          FROM product_claim_supplier c
          LEFT JOIN product p ON c.prod_id = p.prod_id
          WHERE $where_sql
          ORDER BY $order_by
          LIMIT 100";

$res = mysqli_query($conn, $query);
$claims = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $row['id']           = intval($row['id']);
        $row['prod_id']      = intval($row['prod_id']);
        $row['qty']          = intval($row['qty']);
        $row['days_pending'] = max(0, intval($row['days_pending']));
        $row['img']          = $row['img'] ?: 'img/no_img.png';
        $row['refund_amount']= floatval($row['refund_amount']);
        $row['damage_images']= json_decode($row['damage_images'] ?? '[]', true) ?: [];
        $claims[] = $row;
    }
}

mysqli_close($conn);

echo json_encode([
    'success' => true,
    'summary' => $summary,
    'total'   => count($claims),
    'data'    => $claims
], JSON_UNESCAPED_UNICODE);
?>
