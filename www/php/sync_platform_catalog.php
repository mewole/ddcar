<?php
include_once "auth_guard.php";
require_staff_or_admin();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once "db.php";
$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?: $_POST;

$channel_code = isset($data['channel_code']) ? mysqli_real_escape_string($conn, trim($data['channel_code'])) : '';
$products = isset($data['products']) && is_array($data['products']) ? $data['products'] : [];
$source = isset($data['source']) ? mysqli_real_escape_string($conn, trim($data['source'])) : 'chrome_extension';

// Allow Chrome extension sync calls without requiring browser session cookies
$is_ext = (!empty($_SERVER['HTTP_X_EXTENSION_REQUEST']) || 
           strpos($source, 'extension') !== false || 
           strpos($source, 'catalog') !== false || 
           strpos($source, 'page') !== false);
if (!$is_ext && !is_logged_in()) {
    }
header("Content-Type: application/json; charset=utf-8");

if (empty($channel_code)) {
    echo json_encode(["success" => false, "message" => "กรุณาระบุ channel_code"]);
    exit;
}

if (empty($products)) {
    echo json_encode(["success" => false, "message" => "ไม่มีข้อมูลรายการสินค้า"]);
    exit;
}

$updated = 0;
$inserted = 0;

foreach ($products as $p) {
    $sku = mysqli_real_escape_string($conn, trim($p['platform_sku'] ?? ''));
    $name = mysqli_real_escape_string($conn, trim($p['platform_name'] ?? ''));
    $img = mysqli_real_escape_string($conn, trim($p['image_url'] ?? ''));
    $url = mysqli_real_escape_string($conn, trim($p['product_url'] ?? ''));
    $item_id = mysqli_real_escape_string($conn, trim($p['item_id'] ?? ''));

    if (empty($sku) && !empty($item_id)) {
        $sku = "(ไม่พบ Seller SKU) ID " . $item_id;
    }

    if (empty($sku) && empty($name)) continue;
    if (empty($sku)) {
        $sku = 'AUTO_' . substr(md5($name), 0, 10);
    }

    // Check if entry exists in mapping table
    $where_match = "(platform_sku = '$sku'";
    if (!empty($item_id)) {
        $where_match .= " OR platform_sku LIKE '%$item_id%'";
    }
    $short_name = mb_substr($name, 0, 25);
    if (mb_strlen($short_name) >= 12) {
        $esc_short = mysqli_real_escape_string($conn, $short_name);
        $where_match .= " OR platform_name LIKE '%$esc_short%'";
    }
    $where_match .= ")";

    $chk = mysqli_query($conn, "SELECT id, platform_name, image_url, product_url, status FROM platform_sku_mapping 
                                WHERE channel_code = '$channel_code' 
                                AND $where_match LIMIT 1");

    if ($chk && mysqli_num_rows($chk) > 0) {
        $row = mysqli_fetch_assoc($chk);
        $mid = intval($row['id']);

        $set_parts = [];
        if (!empty($name)) {
            $set_parts[] = "platform_name = '$name'";
        }
        if (!empty($img)) {
            $set_parts[] = "image_url = '$img'";
        }
        if (!empty($url)) {
            $set_parts[] = "product_url = '$url'";
        }

        if (!empty($set_parts)) {
            mysqli_query($conn, "UPDATE platform_sku_mapping SET " . implode(", ", $set_parts) . " WHERE id = $mid");
            $updated++;
        }
    } else {
        // Auto-match against DDCar product table
        $auto_pid = 0;
        $auto_target = '';
        $auto_status = 'unmapped';

        $p_match = mysqli_query($conn, "SELECT prod_id, prod_code, img FROM product WHERE prod_code = '$sku' LIMIT 1");
        if ($p_match && mysqli_num_rows($p_match) > 0) {
            $p_row = mysqli_fetch_assoc($p_match);
            $auto_pid = intval($p_row['prod_id']);
            $auto_target = $p_row['prod_code'];
            $auto_status = 'mapped';
            // Do NOT copy local warehouse img into platform image_url
            // Platform image_url must only store genuine marketplace CDN URLs
        }

        $ins_sql = "INSERT INTO platform_sku_mapping 
            (channel_code, platform_sku, platform_name, image_url, product_url, mapping_type, prod_id, target_sku, status)
            VALUES 
            ('$channel_code', '$sku', '$name', '$img', '$url', 'single', $auto_pid, '$auto_target', '$auto_status')";

        if (mysqli_query($conn, $ins_sql)) {
            $inserted++;
        }
    }
}

echo json_encode([
    "success" => true,
    "channel_code" => $channel_code,
    "updated_count" => $updated,
    "inserted_count" => $inserted,
    "total_processed" => count($products),
    "timestamp" => date("Y-m-d H:i:s")
], JSON_UNESCAPED_UNICODE);
?>
