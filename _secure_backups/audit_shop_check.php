<?php
include "auth_guard.php";
require_admin_or_owner();
header('Content-Type: application/json; charset=utf-8');
include "db.php";

$audit = [];

// 1. Audit shop_id rows not in shop_detail
$q1 = mysqli_query($conn, "SELECT shop_id, COUNT(*) as cnt FROM shop WHERE shop_id NOT IN (SELECT shop_id FROM shop_detail) GROUP BY shop_id");
$orphan_shops = [];
if ($q1) {
    while ($r = mysqli_fetch_assoc($q1)) {
        $orphan_shops[] = $r;
    }
}
$audit['orphan_shop_records'] = $orphan_shops;

// 2. Active branches in shop_detail
$q2 = mysqli_query($conn, "SELECT shop_id, shop_name FROM shop_detail ORDER BY shop_id ASC");
$shops = [];
if ($q2) {
    while ($r = mysqli_fetch_assoc($q2)) {
        $shops[] = $r;
    }
}
$audit['active_shops'] = $shops;

// 3. Check MBPJ20FDF (RT26-00003)
$q3 = mysqli_query($conn, "SELECT p.prod_id, p.prod_code, p.prod_name, s.shop_id, sd.shop_name, s.amount 
    FROM product p 
    LEFT JOIN shop s ON p.prod_id = s.prod_id 
    LEFT JOIN shop_detail sd ON s.shop_id = sd.shop_id 
    WHERE p.prod_code LIKE '%MBPJ20FDF%' OR p.prod_code = 'RT26-00003' OR p.prod_name LIKE '%RT26-00003%'");
$mbpj_stock = [];
if ($q3) {
    while ($r = mysqli_fetch_assoc($q3)) {
        $mbpj_stock[] = $r;
    }
}
$audit['mbpj_stock'] = $mbpj_stock;

echo json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
mysqli_close($conn);
?>
