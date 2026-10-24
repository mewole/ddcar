<?php
include "auth_guard.php";
require_admin_or_owner();
header('Content-Type: application/json; charset=utf-8');
include "db.php";

// 1. Alter table to support 'hidden'
$alter_res = mysqli_query($conn, "ALTER TABLE platform_sku_mapping MODIFY COLUMN status ENUM('mapped', 'unmapped', 'hidden') DEFAULT 'unmapped'");

// 2. Update the 10 corrupt records to 'hidden'
$ids = [497, 503, 504, 505, 506, 507, 508, 510, 511, 513];
$id_str = implode(',', $ids);
$upd_res = mysqli_query($conn, "UPDATE platform_sku_mapping SET status = 'hidden' WHERE id IN ($id_str)");

// 3. Verify
$check_res = mysqli_query($conn, "SELECT id, status FROM platform_sku_mapping WHERE id IN ($id_str)");
$items = [];
while ($r = mysqli_fetch_assoc($check_res)) {
    $items[] = $r;
}

echo json_encode([
    'alter_success' => (bool)$alter_res,
    'update_success' => (bool)$upd_res,
    'verified_items' => $items
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
