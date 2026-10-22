<?php
require_once "db.php";
header("Content-Type: application/json; charset=utf-8");

// Ensure Tables Exist with matching collation
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS platform_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_code VARCHAR(50) NOT NULL,
    order_sn VARCHAR(100) NOT NULL,
    order_date DATETIME NOT NULL,
    order_status VARCHAR(50) DEFAULT '',
    buyer_username VARCHAR(100) DEFAULT '',
    total_amount DECIMAL(12,2) DEFAULT 0,
    items_count INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_channel_order (channel_code, order_sn),
    KEY idx_channel_date (channel_code, order_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS platform_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_sn VARCHAR(100) NOT NULL,
    channel_code VARCHAR(50) NOT NULL,
    platform_item_id VARCHAR(100) DEFAULT '',
    platform_model_id VARCHAR(100) DEFAULT '',
    platform_sku VARCHAR(100) DEFAULT '',
    platform_name VARCHAR(255) DEFAULT '',
    qty INT DEFAULT 1,
    price DECIMAL(12,2) DEFAULT 0,
    prod_id INT DEFAULT 0,
    target_sku VARCHAR(100) DEFAULT '',
    match_status ENUM('matched', 'unmapped') DEFAULT 'unmapped',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order_sn (order_sn),
    KEY idx_channel_sku (channel_code, platform_sku),
    KEY idx_match_status (match_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS platform_sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_code VARCHAR(50) NOT NULL,
    sync_source VARCHAR(50) DEFAULT 'chrome_extension',
    orders_count INT DEFAULT 0,
    items_count INT DEFAULT 0,
    matched_count INT DEFAULT 0,
    unmapped_count INT DEFAULT 0,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_channel_time (channel_code, synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

// Read Input
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

if (!$data && isset($_POST['payload'])) {
    $data = json_decode($_POST['payload'], true);
}

if (!$data || !is_array($data)) {
    echo json_encode(["success" => false, "message" => "Invalid or empty JSON payload"]);
    exit;
}

$channel_code = isset($data['channel_code']) ? mysqli_real_escape_string($conn, trim($data['channel_code'])) : 'shopee_kingkong';
$orders = isset($data['orders']) && is_array($data['orders']) ? $data['orders'] : [];
$sync_source = isset($data['source']) ? mysqli_real_escape_string($conn, trim($data['source'])) : 'chrome_extension';

if (empty($orders)) {
    echo json_encode(["success" => true, "message" => "No orders to sync", "orders_count" => 0]);
    exit;
}

$orders_processed = 0;
$items_processed = 0;
$matched_count = 0;
$unmapped_count = 0;

foreach ($orders as $ord) {
    $order_sn = mysqli_real_escape_string($conn, trim($ord['order_sn'] ?? ''));
    if (empty($order_sn)) continue;

    $order_date = !empty($ord['order_date']) ? date("Y-m-d H:i:s", strtotime($ord['order_date'])) : date("Y-m-d H:i:s");
    $order_status = mysqli_real_escape_string($conn, trim($ord['order_status'] ?? ''));
    $buyer = mysqli_real_escape_string($conn, trim($ord['buyer_username'] ?? ''));
    $total_amount = floatval($ord['total_amount'] ?? 0);
    $items = isset($ord['items']) && is_array($ord['items']) ? $ord['items'] : [];
    $items_cnt = count($items);

    // 1. Insert/Update Order Header
    $sql_ord = "INSERT INTO platform_orders (channel_code, order_sn, order_date, order_status, buyer_username, total_amount, items_count)
                VALUES ('$channel_code', '$order_sn', '$order_date', '$order_status', '$buyer', '$total_amount', $items_cnt)
                ON DUPLICATE KEY UPDATE 
                    order_status = VALUES(order_status),
                    total_amount = VALUES(total_amount),
                    items_count = VALUES(items_count)";
    mysqli_query($conn, $sql_ord);
    $orders_processed++;

    // 2. Process Order Items
    foreach ($items as $it) {
        $p_sku = mysqli_real_escape_string($conn, trim($it['platform_sku'] ?? ''));
        $p_name = mysqli_real_escape_string($conn, trim($it['platform_name'] ?? ''));
        $p_item_id = mysqli_real_escape_string($conn, trim($it['item_id'] ?? ''));
        $p_model_id = mysqli_real_escape_string($conn, trim($it['model_id'] ?? ''));
        $qty = max(1, intval($it['qty'] ?? 1));
        $price = floatval($it['price'] ?? 0);

        // Check if SKU is mapped in platform_sku_mapping
        $prod_id = 0;
        $target_sku = '';
        $match_status = 'unmapped';

        if (!empty($p_sku)) {
            $chk_map = mysqli_query($conn, "SELECT prod_id, target_sku, status FROM platform_sku_mapping 
                                            WHERE channel_code = '$channel_code' AND platform_sku = '$p_sku' LIMIT 1");
            if ($chk_map && mysqli_num_rows($chk_map) > 0) {
                $m_row = mysqli_fetch_assoc($chk_map);
                if ($m_row['status'] === 'mapped' && intval($m_row['prod_id']) > 0) {
                    $prod_id = intval($m_row['prod_id']);
                    $target_sku = mysqli_real_escape_string($conn, $m_row['target_sku']);
                    $match_status = 'matched';
                }
                // Update platform_name if new incoming name is longer or has better variation info
                if (!empty($p_name)) {
                    mysqli_query($conn, "UPDATE platform_sku_mapping 
                                        SET platform_name = '$p_name' 
                                        WHERE channel_code = '$channel_code' AND platform_sku = '$p_sku' 
                                        AND (platform_name = '' OR CHAR_LENGTH(platform_name) < CHAR_LENGTH('$p_name') OR (platform_name LIKE '%[Not Specified]%' AND '$p_name' NOT LIKE '%[Not Specified]%'))");
                }
            } else {
                // Auto-create unmapped entry for visibility in mapping table
                // Check if SKU directly matches a DDCar product prod_code
                $chk_direct = mysqli_query($conn, "SELECT prod_id, prod_code FROM product WHERE prod_code = '$p_sku' LIMIT 1");
                if ($chk_direct && mysqli_num_rows($chk_direct) > 0) {
                    $d_row = mysqli_fetch_assoc($chk_direct);
                    $prod_id = intval($d_row['prod_id']);
                    $target_sku = mysqli_real_escape_string($conn, $d_row['prod_code']);
                    $match_status = 'mapped';
                    mysqli_query($conn, "INSERT IGNORE INTO platform_sku_mapping 
                        (channel_code, platform_sku, platform_name, mapping_type, prod_id, target_sku, status)
                        VALUES ('$channel_code', '$p_sku', '$p_name', 'single', $prod_id, '$target_sku', 'mapped')");
                } else {
                    mysqli_query($conn, "INSERT IGNORE INTO platform_sku_mapping 
                        (channel_code, platform_sku, platform_name, mapping_type, status)
                        VALUES ('$channel_code', '$p_sku', '$p_name', 'single', 'unmapped')");
                }
            }
        } elseif (!empty($p_name)) {
            // No SKU provided by platform, insert by name
            $safe_sku_placeholder = 'NO_SKU_' . substr(md5($p_name), 0, 10);
            mysqli_query($conn, "INSERT IGNORE INTO platform_sku_mapping 
                (channel_code, platform_sku, platform_name, mapping_type, status)
                VALUES ('$channel_code', '$safe_sku_placeholder', '$p_name', 'single', 'unmapped')");
            $p_sku = $safe_sku_placeholder;
        }

        if ($match_status === 'matched') {
            $matched_count++;
        } else {
            $unmapped_count++;
        }

        // Insert item record
        $sql_it = "INSERT INTO platform_order_items 
                   (order_sn, channel_code, platform_item_id, platform_model_id, platform_sku, platform_name, qty, price, prod_id, target_sku, match_status)
                   VALUES ('$order_sn', '$channel_code', '$p_item_id', '$p_model_id', '$p_sku', '$p_name', $qty, $price, $prod_id, '$target_sku', '$match_status')";
        mysqli_query($conn, $sql_it);
        $items_processed++;
    }
}

// Log Sync Execution
mysqli_query($conn, "INSERT INTO platform_sync_logs (channel_code, sync_source, orders_count, items_count, matched_count, unmapped_count)
                     VALUES ('$channel_code', '$sync_source', $orders_processed, $items_processed, $matched_count, $unmapped_count)");

echo json_encode([
    "success" => true,
    "channel_code" => $channel_code,
    "orders_processed" => $orders_processed,
    "items_processed" => $items_processed,
    "matched_count" => $matched_count,
    "unmapped_count" => $unmapped_count,
    "synced_at" => date("Y-m-d H:i:s")
]);
?>
