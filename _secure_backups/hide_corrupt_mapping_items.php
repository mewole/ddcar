<?php
include "auth_guard.php";
require_admin_or_owner();
header('Content-Type: application/json; charset=utf-8');
include "db.php";

$ids_to_hide = [];
$details = [];

$q = mysqli_query($conn, "SELECT id, channel_code, platform_sku, platform_name, status FROM platform_sku_mapping WHERE status != 'hidden'");
while ($r = mysqli_fetch_assoc($q)) {
    $sku = trim($r['platform_sku'] ?? '');
    $name = trim($r['platform_name'] ?? '');
    
    $is_json = (strpos($sku, '{') === 0 || strpos($name, '{') === 0);
    $is_question = (strpos($sku, '????') !== false || strpos($name, '????') !== false);
    $is_empty = (empty($sku) && empty($name)) || ($sku === '' && $name === '');
    
    if ($is_json || $is_question || $is_empty) {
        $ids_to_hide[] = intval($r['id']);
        $details[] = [
            'id' => intval($r['id']),
            'channel' => $r['channel_code'],
            'platform_sku' => $sku,
            'platform_name' => $name,
            'previous_status' => $r['status'],
            'reason' => $is_json ? 'JSON string' : ($is_question ? 'Corrupt ????' : 'Empty SKU and Name')
        ];
    }
}

$updated_count = 0;
if (!empty($ids_to_hide)) {
    $id_str = implode(',', $ids_to_hide);
    $update_res = mysqli_query($conn, "UPDATE platform_sku_mapping SET status = 'hidden' WHERE id IN ($id_str)");
    if ($update_res) {
        $updated_count = mysqli_affected_rows($conn);
        // Log to file for safety/audit
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'updated_count' => $updated_count,
            'hidden_items' => $details
        ];
        @file_put_contents(__DIR__ . '/hidden_corrupt_items_log.json', json_encode($log_entry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

echo json_encode([
    'success' => true,
    'hidden_count' => $updated_count,
    'details' => $details
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
