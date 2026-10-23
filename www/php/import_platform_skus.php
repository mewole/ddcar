<?php
include_once "auth_guard.php";
require_staff_or_admin();

require_once "db.php";
header("Content-Type: application/json; charset=utf-8");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?: $_POST;

$channel_code = isset($data['channel_code']) ? mysqli_real_escape_string($conn, trim($data['channel_code'])) : '';
$raw_text = isset($data['raw_text']) ? trim($data['raw_text']) : '';
$items = isset($data['items']) ? $data['items'] : [];

if (empty($channel_code)) {
    echo json_encode(["success" => false, "message" => "กรุณาระบุช่องทางร้านค้า (Channel)"]);
    exit;
}

$imported = 0;
$skipped = 0;

// Parse raw_text if provided (tab or comma separated: SKU [TAB/COMMA] Name)
if (!empty($raw_text) && empty($items)) {
    $lines = explode("\n", $raw_text);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Try tab-delimited or comma-delimited
        if (strpos($line, "\t") !== false) {
            $parts = explode("\t", $line);
        } elseif (strpos($line, ",") !== false) {
            $parts = str_getcsv($line);
        } else {
            $parts = [$line];
        }

        $p_sku = trim($parts[0] ?? '');
        $p_name = trim($parts[1] ?? '');

        if (!empty($p_sku)) {
            $items[] = [
                'sku' => $p_sku,
                'name' => $p_name
            ];
        }
    }
}

foreach ($items as $item) {
    $sku = mysqli_real_escape_string($conn, trim($item['sku'] ?? ''));
    $name = mysqli_real_escape_string($conn, trim($item['name'] ?? ''));

    if (empty($sku)) continue;

    // Check if auto-match can be found by exact SKU in product table
    $auto_target = '';
    $auto_pid = 0;
    $status = 'unmapped';

    $chk_p = mysqli_query($conn, "SELECT prod_id, prod_code FROM product WHERE prod_code = '$sku' LIMIT 1");
    if ($chk_p && mysqli_num_rows($chk_p) > 0) {
        $p_found = mysqli_fetch_assoc($chk_p);
        $auto_target = $p_found['prod_code'];
        $auto_pid = intval($p_found['prod_id']);
        $status = 'mapped';
    }

    $sql = "INSERT INTO platform_sku_mapping 
            (channel_code, platform_sku, platform_name, mapping_type, prod_id, target_sku, status)
            VALUES 
            ('$channel_code', '$sku', '$name', 'single', $auto_pid, '$auto_target', '$status')
            ON DUPLICATE KEY UPDATE 
            platform_name = CASE WHEN platform_name = '' THEN '$name' ELSE platform_name END";

    if (mysqli_query($conn, $sql)) {
        $imported++;
    } else {
        $skipped++;
    }
}

echo json_encode([
    "success" => true,
    "imported_count" => $imported,
    "skipped_count" => $skipped,
    "message" => "นำเข้ารายการสินค้าออนไลน์เรียบร้อย $imported รายการ"
], JSON_UNESCAPED_UNICODE);
?>
