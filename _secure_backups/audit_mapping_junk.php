<?php
include "auth_guard.php";
require_admin_or_owner();
header('Content-Type: application/json; charset=utf-8');
include "db.php";

$total = 0;
$corrupt = [];
$valid = [];

$q = mysqli_query($conn, "SELECT id, channel_code, platform_sku, platform_name, status FROM platform_sku_mapping");
while ($r = mysqli_fetch_assoc($q)) {
    $total++;
    $sku = trim($r['platform_sku'] ?? '');
    $name = trim($r['platform_name'] ?? '');
    
    // Check corrupt criteria:
    // 1. Starts with '{' (raw JSON)
    // 2. Contains '????' (corrupted encoding)
    // 3. Both empty or no valid SKU and Name
    $is_json = (strpos($sku, '{') === 0 || strpos($name, '{') === 0);
    $is_question = (strpos($sku, '????') !== false || strpos($name, '????') !== false);
    $is_empty = (empty($sku) && empty($name)) || ($sku === '' && $name === '');
    
    if ($is_json || $is_question || $is_empty) {
        $corrupt[] = [
            'id' => $r['id'],
            'sku' => mb_substr($sku, 0, 40),
            'name' => mb_substr($name, 0, 40),
            'reason' => $is_json ? 'JSON' : ($is_question ? '????' : 'EMPTY')
        ];
    } else {
        $valid[] = $r['id'];
    }
}

echo json_encode([
    'total' => $total,
    'corrupt_count' => count($corrupt),
    'valid_count' => count($valid),
    'sample_corrupt' => array_slice($corrupt, 0, 10),
    'sample_valid_ids' => array_slice($valid, 0, 10)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
