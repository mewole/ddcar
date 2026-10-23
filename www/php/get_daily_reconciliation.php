<?php
include_once "auth_guard.php";
require_login();

require_once "db.php";
header("Content-Type: application/json; charset=utf-8");

$date = isset($_GET['date']) && !empty($_GET['date']) ? mysqli_real_escape_string($conn, trim($_GET['date'])) : date("Y-m-d");
$channel_code = isset($_GET['channel']) ? mysqli_real_escape_string($conn, trim($_GET['channel'])) : 'shopee_kingkong';

// Channel mapping to DDCar Customer table (cus_id & cus_name)
$channel_meta = [
    'shopee_kingkong' => [
        'name' => 'Shopee (KingKong)',
        'cus_id' => 131,
        'cus_name' => 'Shopee kingkong',
        'platform' => 'shopee'
    ],
    'shopee_taengraberd' => [
        'name' => 'Shopee (แต่งระเบิด)',
        'cus_id' => 133,
        'cus_name' => 'Shoppee jet',
        'platform' => 'shopee'
    ],
    'lazada_main' => [
        'name' => 'Lazada',
        'cus_id' => 136,
        'cus_name' => 'Lazada Mig',
        'platform' => 'lazada'
    ],
    'tiktok_main' => [
        'name' => 'TikTok Shop',
        'cus_id' => 466,
        'cus_name' => 'Tiktok',
        'platform' => 'tiktok'
    ]
];

$is_all_channels = ($channel_code === 'all');
$all_cus_ids = [131, 133, 136, 466];
$all_cus_list = implode(',', $all_cus_ids);

if ($is_all_channels) {
    $cur_channel = [
        'name' => 'รวมทุกช่องทาง',
        'cus_id' => 0,
        'cus_name' => 'รวมทุกช่องทาง',
        'platform' => 'all'
    ];
    $target_cus_id = 0;
    $target_cus_name = 'รวมทุกช่องทาง';
} else {
    $cur_channel = $channel_meta[$channel_code] ?? $channel_meta['shopee_kingkong'];
    $target_cus_id = intval($cur_channel['cus_id']);
    $target_cus_name = mysqli_real_escape_string($conn, $cur_channel['cus_name']);
}

// Ensure tables exist
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

// 1. Get Platform Orders for this date
$sql_p_ord = "SELECT COUNT(*) as ord_cnt, COALESCE(SUM(items_count), 0) as item_cnt, COALESCE(SUM(total_amount), 0) as total_amt
              FROM platform_orders 
              WHERE " . ($is_all_channels ? "1=1" : "channel_code = '$channel_code'") . " 
                AND (DATE(order_date) = '$date' OR DATE(created_at) = '$date')";
$res_p_ord = mysqli_query($conn, $sql_p_ord);
$row_p_ord = $res_p_ord ? mysqli_fetch_assoc($res_p_ord) : ['ord_cnt' => 0, 'item_cnt' => 0, 'total_amt' => 0];

$platform_orders_count = intval($row_p_ord['ord_cnt'] ?? 0);
$platform_items_count = intval($row_p_ord['item_cnt'] ?? 0);
$platform_total_amount = floatval($row_p_ord['total_amt'] ?? 0);

// 2. Get Platform Items breakdown (matched vs unmapped)
$sql_p_items = "SELECT i.*, m.target_sku as mapped_sku, p.prod_id as found_prod_id, p.prod_name as matched_prod_name, p.prod_price as matched_price
                FROM platform_order_items i
                LEFT JOIN platform_sku_mapping m ON (i.channel_code = m.channel_code AND i.platform_sku = m.platform_sku)
                LEFT JOIN product p ON (m.prod_id = p.prod_id OR (i.target_sku != '' AND i.target_sku = p.prod_code))
                WHERE " . ($is_all_channels ? "1=1" : "i.channel_code = '$channel_code'") . " 
                  AND (DATE(i.created_at) = '$date' OR EXISTS (SELECT 1 FROM platform_orders o WHERE o.order_sn = i.order_sn AND DATE(o.order_date) = '$date'))
                ORDER BY i.match_status ASC, i.id DESC";
$res_p_items = mysqli_query($conn, $sql_p_items);

$matched_items = [];
$unmapped_items = [];

if ($res_p_items) {
    while ($it = mysqli_fetch_assoc($res_p_items)) {
        $is_matched = (!empty($it['mapped_sku']) || !empty($it['target_sku']) || $it['match_status'] === 'matched');
        $status = $is_matched ? 'matched' : 'unmapped';
        
        $item_data = [
            "id" => intval($it['id']),
            "order_sn" => $it['order_sn'],
            "platform_sku" => $it['platform_sku'],
            "platform_name" => $it['platform_name'],
            "qty" => intval($it['qty']),
            "price" => floatval($it['price']),
            "prod_id" => intval($it['found_prod_id'] ?? $it['prod_id']),
            "target_sku" => $it['mapped_sku'] ?: $it['target_sku'],
            "matched_prod_name" => $it['matched_prod_name'] ?: '',
            "match_status" => $status
        ];

        if ($status === 'matched') {
            $matched_items[] = $item_data;
        } else {
            // Find smart suggestion from product table
            $suggestion = null;
            $search_sku = trim($it['platform_sku']);
            if (!empty($search_sku) && strpos($search_sku, 'NO_SKU_') === false) {
                $clean_sku = mysqli_real_escape_string($conn, $search_sku);
                $q_sug = mysqli_query($conn, "SELECT prod_id, prod_code, prod_name, prod_price 
                                              FROM product 
                                              WHERE prod_code LIKE '%$clean_sku%' OR prod_name LIKE '%$clean_sku%' 
                                              LIMIT 1");
                if ($q_sug && mysqli_num_rows($q_sug) > 0) {
                    $s_row = mysqli_fetch_assoc($q_sug);
                    $suggestion = [
                        "prod_id" => intval($s_row['prod_id']),
                        "prod_code" => $s_row['prod_code'],
                        "prod_name" => $s_row['prod_name'],
                        "confidence" => "high"
                    ];
                }
            }
            if (!$suggestion && !empty($it['platform_name'])) {
                $words = preg_split('/\s+/', trim($it['platform_name']));
                $first_word = mysqli_real_escape_string($conn, $words[0] ?? '');
                if (mb_strlen($first_word, 'UTF-8') >= 3) {
                    $q_sug2 = mysqli_query($conn, "SELECT prod_id, prod_code, prod_name, prod_price 
                                                   FROM product 
                                                   WHERE prod_name LIKE '%$first_word%' 
                                                   LIMIT 1");
                    if ($q_sug2 && mysqli_num_rows($q_sug2) > 0) {
                        $s_row = mysqli_fetch_assoc($q_sug2);
                        $suggestion = [
                            "prod_id" => intval($s_row['prod_id']),
                            "prod_code" => $s_row['prod_code'],
                            "prod_name" => $s_row['prod_name'],
                            "confidence" => "medium"
                        ];
                    }
                }
            }
            $item_data['suggestion'] = $suggestion;
            $unmapped_items[] = $item_data;
        }
    }
}

// 3. Get DDCar Sales cut for this customer on this date
// Matches by cus_id OR cus_name (or all online customer IDs when channel=all)
$where_cus = $is_all_channels ? "s.customer_id IN ($all_cus_list)" : "(s.customer_id = $target_cus_id OR c.cus_name LIKE '%$target_cus_name%')";
$sql_dd_bills = "SELECT s.order_id, s.order_number, s.total_price, s.date_time,
                        COUNT(i.item_id) as items_cnt, COALESCE(SUM(i.prod_amount), 0) as total_qty
                 FROM sale_order s
                 LEFT JOIN customer c ON s.customer_id = c.cus_id
                 LEFT JOIN sale_order_item i ON s.order_id = i.order_id
                 WHERE $where_cus
                   AND DATE(s.date_time) = '$date'
                   AND s.order_number NOT LIKE '%(cancel)%'
                 GROUP BY s.order_id";
$res_dd_bills = mysqli_query($conn, $sql_dd_bills);

$ddcar_bills_count = 0;
$ddcar_total_qty = 0;
$ddcar_total_amount = 0;

if ($res_dd_bills) {
    while ($b = mysqli_fetch_assoc($res_dd_bills)) {
        $ddcar_bills_count++;
        $ddcar_total_qty += intval($b['total_qty']);
        $ddcar_total_amount += floatval($b['total_price']);
    }
}

// 4. Get Last Sync Time
$sync_where = $is_all_channels ? "" : "WHERE channel_code = '$channel_code'";
$q_last = mysqli_query($conn, "SELECT synced_at FROM platform_sync_logs $sync_where ORDER BY id DESC LIMIT 1");
$last_synced_at = ($q_last && mysqli_num_rows($q_last) > 0) ? mysqli_fetch_assoc($q_last)['synced_at'] : null;

echo json_encode([
    "success" => true,
    "date" => $date,
    "channel" => [
        "code" => $channel_code,
        "name" => $cur_channel['name'],
        "customer_id" => $target_cus_id,
        "customer_name" => $cur_channel['cus_name'],
        "platform" => $cur_channel['platform']
    ],
    "last_synced_at" => $last_synced_at,
    "summary" => [
        "platform_orders" => $platform_orders_count,
        "platform_items" => count($matched_items) + count($unmapped_items),
        "platform_total_amount" => $platform_total_amount,
        "ddcar_bills" => $ddcar_bills_count,
        "ddcar_items" => $ddcar_total_qty,
        "ddcar_total_amount" => $ddcar_total_amount,
        "matched_count" => count($matched_items),
        "unmapped_count" => count($unmapped_items)
    ],
    "unmapped_items" => $unmapped_items,
    "matched_items" => $matched_items
]);
?>
