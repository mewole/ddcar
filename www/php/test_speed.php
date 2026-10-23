<?php
include_once "auth_guard.php";
require_login();

include "db.php";

$t0 = microtime(true);

// 1. Fetch 50 items
$query = "SELECT prod_id, prod_code, prod_name, prod_cost, min_amount, status 
          FROM product 
          WHERE status != 'normal' 
          ORDER BY prod_id DESC 
          LIMIT 0, 50";
$res = mysqli_query($conn, $query);
$items = [];
$pids = [];
while ($r = mysqli_fetch_assoc($res)) {
    $pids[] = $r['prod_id'];
    $items[] = $r;
}
$t1 = microtime(true);

// 2. Batch fetch ext
$idList = implode("','", $pids);
$q_ext = mysqli_query($conn, "SELECT prod_id, is_ordered, is_preorder FROM product_status_ext WHERE prod_id IN ('$idList')");
$t2 = microtime(true);

// 3. Batch fetch warehouse
$q_w = mysqli_query($conn, "SELECT prod_id, SUM(amount) as s FROM warehouse WHERE prod_id IN ('$idList') GROUP BY prod_id");
$t3 = microtime(true);

// 4. Batch fetch best supplier
$q_sup = mysqli_query($conn, "SELECT prod_id, source_from, stock_cost 
    FROM (
        SELECT prod_id, source_from, stock_cost FROM stock_in_item WHERE prod_id IN ('$idList') AND source_from IS NOT NULL AND stock_cost > 0
        UNION ALL
        SELECT prod_id, source_from, tran_cost AS stock_cost FROM transfer_in_item WHERE prod_id IN ('$idList') AND source_from IS NOT NULL AND tran_cost > 0
    ) t ORDER BY stock_cost ASC");
$t4 = microtime(true);

echo json_encode([
    'query_time_ms' => round(($t1 - $t0) * 1000, 2),
    'ext_time_ms' => round(($t2 - $t1) * 1000, 2),
    'ware_time_ms' => round(($t3 - $t2) * 1000, 2),
    'sup_time_ms' => round(($t4 - $t3) * 1000, 2),
    'total_time_ms' => round(($t4 - $t0) * 1000, 2),
    'item_count' => count($items)
], JSON_PRETTY_PRINT);
?>
