<?php
include_once __DIR__ . "/auth_guard.php";
require_admin_or_owner();
include __DIR__ . "/db.php";

header('Content-Type: application/json; charset=utf-8');

// 1. Check shop_detail valid shop_ids
$valid_shops = [];
$q_sd = mysqli_query($conn, "SELECT * FROM shop_detail");
if ($q_sd) {
    while ($r = mysqli_fetch_assoc($q_sd)) {
        $valid_shops[] = $r;
    }
}

// 2. Select rows in shop where shop_id NOT IN (SELECT shop_id FROM shop_detail)
$invalid_rows = [];
$q_inv = mysqli_query($conn, "
    SELECT s.shop_id, s.prod_id, p.prod_code, p.prod_name, s.amount 
    FROM shop s 
    LEFT JOIN product p ON s.prod_id = p.prod_id 
    WHERE s.shop_id NOT IN (SELECT shop_id FROM shop_detail)
");
if ($q_inv) {
    while ($r = mysqli_fetch_assoc($q_inv)) {
        $invalid_rows[] = $r;
    }
}

// 3. Check MBPJ20FDF stock in shop and warehouse
$mbpj_info = [];
$q_mbpj = mysqli_query($conn, "
    SELECT p.prod_id, p.prod_code, p.prod_name,
           (SELECT amount FROM shop WHERE shop_id = 6 AND prod_id = p.prod_id) as shop_stock_id6,
           (SELECT COALESCE(SUM(amount), 0) FROM warehouse WHERE prod_id = p.prod_id) as total_warehouse_stock
    FROM product p 
    WHERE p.prod_code = 'MBPJ20FDF'
");
if ($q_mbpj && $r_m = mysqli_fetch_assoc($q_mbpj)) {
    $mbpj_info = $r_m;
}

// 4. Check user passwords hash status
$user_status = [];
$q_u = mysqli_query($conn, "SELECT user_id, user_name, user_role, pass FROM user");
if ($q_u) {
    while ($u = mysqli_fetch_assoc($q_u)) {
        $is_bcrypt = (strpos($u['pass'], '$2y$') === 0 || strpos($u['pass'], '$2a$') === 0 || strpos($u['pass'], '$2b$') === 0);
        $user_status[] = [
            'user_id' => $u['user_id'],
            'user_name' => $u['user_name'],
            'user_role' => $u['user_role'],
            'has_hash' => $is_bcrypt
        ];
    }
}

// 5. Check platform_sku_mapping corrupt items
$corrupt_count = 0;
$good_count = 0;
$sample_corrupt = [];
$q_map = mysqli_query($conn, "SELECT id, channel_code, platform_sku, platform_product_name, status FROM platform_sku_mapping");
if ($q_map) {
    while ($m = mysqli_fetch_assoc($q_map)) {
        $name = trim($m['platform_product_name'] ?? '');
        $sku  = trim($m['platform_sku'] ?? '');
        $is_corrupt = (strpos($name, '{') === 0 || strpos($name, '????') !== false || (empty($sku) && empty($name)));
        if ($is_corrupt) {
            $corrupt_count++;
            if (count($sample_corrupt) < 5) $sample_corrupt[] = $m;
        } else {
            $good_count++;
        }
    }
}

echo json_encode([
    'success' => true,
    'valid_shops' => $valid_shops,
    'invalid_shop_rows' => $invalid_rows,
    'mbpj_stock' => $mbpj_info,
    'user_pass_audit' => $user_status,
    'mapping_audit' => [
        'total' => $corrupt_count + $good_count,
        'corrupt' => $corrupt_count,
        'good' => $good_count,
        'sample_corrupt' => $sample_corrupt
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
