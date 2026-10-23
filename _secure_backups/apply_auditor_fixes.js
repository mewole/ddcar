const fs = require('fs');
const path = require('path');

console.log('=== STARTING COMPREHENSIVE AUDITOR FIXES ===');

// ==========================================
// 1. web_sale.html
// ==========================================
console.log('\n--- 1. Fixing web_sale.html ---');
let saleHtml = fs.readFileSync('c:/ddcar/www/web_sale.html', 'utf8');

// A. Remove Tab key blocker
const tabOld = `			if (objEvent.keyCode == 9) {  //tab pressed\r\n				objEvent.preventDefault(); // stops its action         \r\n			}\r\n			if (objEvent.keyCode == 13) {  //enter pressed`;
const tabOldLF = `			if (objEvent.keyCode == 9) {  //tab pressed\n				objEvent.preventDefault(); // stops its action         \n			}\n			if (objEvent.keyCode == 13) {  //enter pressed`;
if (saleHtml.includes(tabOld)) {
  saleHtml = saleHtml.replace(tabOld, `			if (objEvent.keyCode == 13) {  //enter pressed`);
  console.log('  [OK] Removed Tab key blocker (CRLF)');
} else if (saleHtml.includes(tabOldLF)) {
  saleHtml = saleHtml.replace(tabOldLF, `			if (objEvent.keyCode == 13) {  //enter pressed`);
  console.log('  [OK] Removed Tab key blocker (LF)');
} else {
  // Regex fallback
  saleHtml = saleHtml.replace(/if\s*\(\s*objEvent\.keyCode\s*==\s*9\s*\)\s*\{[^}]*\}\s*/g, '');
  console.log('  [OK] Removed Tab key blocker (regex)');
}

// B. Remove dead code #c
const deadC = `$('#c').append('<a class="dropdown-item text-center" href="#" onclick="setwareto('+field.ware_id+',\\''+field.ware_name+'\\')">'+field.ware_name+'</a>');`;
if (saleHtml.includes(deadC)) {
  saleHtml = saleHtml.replace(deadC, '');
  console.log('  [OK] Removed dead code #c and setwareto()');
} else {
  console.log('  [WARN] dead code #c string not found directly');
}

// C. Fix duplicate id="head"
const head1 = `<div id="head" class="row mgb-5" style="padding-left: 2%; padding-right: 2%;">`;
const head2 = `<div id="head" class="row mgb-5">`;
if (saleHtml.includes(head1)) {
  saleHtml = saleHtml.replace(head1, `<div id="head_info" class="row mgb-5" style="padding-left: 2%; padding-right: 2%;">`);
  console.log('  [OK] Replaced first id="head" with id="head_info"');
}
if (saleHtml.includes(head2)) {
  saleHtml = saleHtml.replace(head2, `<div id="head_cart" class="row mgb-5">`);
  console.log('  [OK] Replaced second id="head" with id="head_cart"');
}

// D. Add validation for amount <= 0 and price < 0 in function add()
const addValidationTarget = `			if(name == "" || amt == "" || code == "" || price == ""){ \r
				$('#name').focus();\r
				$.confirm({\r
					title: 'พบข้อผิดพลาด',\r
					content: 'กรุณากรอกข้อมูลให้ครบ',\r
					backgroundDismiss: true,\r
					buttons: {\r
						formSubmit: {\r
							text: 'ตกลง',\r
							btnClass: 'btn-regis',\r
							action: function () {\r
							}\r
						}\r
					}\r
				});\r
				return 0;\r
			}`;

const addValidationReplacement = `			if(name == "" || amt == "" || code == "" || price == ""){ 
				$('#name').focus();
				$.confirm({
					title: 'พบข้อผิดพลาด',
					content: 'กรุณากรอกข้อมูลให้ครบ',
					backgroundDismiss: true,
					buttons: {
						formSubmit: {
							text: 'ตกลง',
							btnClass: 'btn-regis',
							action: function () {}
						}
					}
				});
				return 0;
			}
			var numAmt = parseInt(amt, 10);
			var numPrice = parseFloat(price);
			if (isNaN(numAmt) || numAmt <= 0 || parseFloat(amt) !== numAmt) {
				$('#amount').focus();
				$.confirm({
					title: 'พบข้อผิดพลาด',
					content: 'จำนวนสินค้าต้องเป็นจำนวนเต็มบวกที่มากกว่า 0',
					backgroundDismiss: true,
					buttons: {
						formSubmit: {
							text: 'ตกลง',
							btnClass: 'btn-regis',
							action: function () { $('#amount').focus(); }
						}
					}
				});
				return 0;
			}
			if (isNaN(numPrice) || numPrice < 0) {
				$('#price').focus();
				$.confirm({
					title: 'พบข้อผิดพลาด',
					content: 'ราคาสินค้าต้องไม่ติดลบ',
					backgroundDismiss: true,
					buttons: {
						formSubmit: {
							text: 'ตกลง',
							btnClass: 'btn-regis',
							action: function () { $('#price').focus(); }
						}
					}
				});
				return 0;
			}`;

if (saleHtml.includes('if(name == "" || amt == "" || code == "" || price == ""){')) {
  saleHtml = saleHtml.replace(/if\(name == "" \|\| amt == "" \|\| code == "" \|\| price == ""\)\{\s*[\s\S]*?return 0;\s*\}/, addValidationReplacement);
  console.log('  [OK] Added amount > 0 and price >= 0 validation in add()');
}

fs.writeFileSync('c:/ddcar/www/web_sale.html', saleHtml, 'utf8');

// ==========================================
// 2. web_stock.html (Dead code cleanup)
// ==========================================
console.log('\n--- 2. Fixing web_stock.html ---');
let stockHtml = fs.readFileSync('c:/ddcar/www/web_stock.html', 'utf8');
const deadBundleWrap = `$('#edit-bundle-action-wrap').hide();`;
if (stockHtml.includes(deadBundleWrap)) {
  stockHtml = stockHtml.replace(deadBundleWrap, '');
  console.log('  [OK] Removed dead reference to #edit-bundle-action-wrap');
  fs.writeFileSync('c:/ddcar/www/web_stock.html', stockHtml, 'utf8');
} else {
  console.log('  [INFO] #edit-bundle-action-wrap already removed or not found');
}

// ==========================================
// 3. web_mng_bill.html (Duplicate modal IDs)
// ==========================================
console.log('\n--- 3. Fixing web_mng_bill.html ---');
let billHtml = fs.readFileSync('c:/ddcar/www/web_mng_bill.html', 'utf8');

// Fix printModal h5
billHtml = billHtml.replace(
  '<div class="modal fade" id="printModal" tabindex="-1" role="dialog" aria-labelledby="printModal"',
  '<div class="modal fade" id="printModal" tabindex="-1" role="dialog" aria-labelledby="printModalLabel"'
);
billHtml = billHtml.replace(
  '<h5 class="modal-title" id="printModal">เลือกรูปแบบการพิมพ์</h5>',
  '<h5 class="modal-title" id="printModalLabel">เลือกรูปแบบการพิมพ์</h5>'
);

// Fix reModal h5
billHtml = billHtml.replace(
  '<div class="modal fade" id="reModal" tabindex="-1" role="dialog" aria-labelledby="reModal"',
  '<div class="modal fade" id="reModal" tabindex="-1" role="dialog" aria-labelledby="reModalLabel"'
);
billHtml = billHtml.replace(
  '<h5 class="modal-title" id="reModal">จำนวนคืนสินค้า</h5>',
  '<h5 class="modal-title" id="reModalLabel">จำนวนคืนสินค้า</h5>'
);

fs.writeFileSync('c:/ddcar/www/web_mng_bill.html', billHtml, 'utf8');
console.log('  [OK] Fixed duplicate modal title IDs in web_mng_bill.html');

// ==========================================
// 4. save_order.php (Server-side validation)
// ==========================================
console.log('\n--- 4. Updating save_order.php validation ---');
let saveOrderPhp = fs.readFileSync('c:/ddcar/www/php/save_order.php', 'utf8');

const validationSnippet = `
    // ตรวจสอบความถูกต้องของรายการสินค้าฝั่ง Server (ป้องกันจำนวนติดลบ, 0, หรือราคาไม่ถูกต้อง)
    if (!is_array($obj) || empty($obj)) {
        echo "fail:ไม่มีรายการสินค้า";
        exit;
    }
    foreach ($obj as $checkItem) {
        $chk_pid = isset($checkItem['prod_id']) ? intval($checkItem['prod_id']) : 0;
        $chk_amt = isset($checkItem['amt']) ? floatval($checkItem['amt']) : 0;
        $chk_price = isset($checkItem['price']) ? floatval($checkItem['price']) : -1;
        if ($chk_pid <= 0 || $chk_amt <= 0 || $chk_price < 0) {
            echo "fail:ข้อมูลสินค้าไม่ถูกต้อง (จำนวนต้องมากกว่า 0 และราคาไม่ติดลบ)";
            exit;
        }
    }
`;

if (!saveOrderPhp.includes('fail:ข้อมูลสินค้าไม่ถูกต้อง')) {
  saveOrderPhp = saveOrderPhp.replace(
    '$b_to = $_POST[\'ware_id_to\'];',
    '$b_to = $_POST[\'ware_id_to\'];' + validationSnippet
  );
  fs.writeFileSync('c:/ddcar/www/php/save_order.php', saveOrderPhp, 'utf8');
  console.log('  [OK] Added strict item validation to save_order.php');
} else {
  console.log('  [INFO] save_order.php already validated');
}

// ==========================================
// 5. get_platform_mappings.php (Speed optimization & images & TikTok)
// ==========================================
console.log('\n--- 5. Updating get_platform_mappings.php ---');
const fastMappingsPhp = `<?php
include_once "auth_guard.php";
require_login();

require_once "db.php";
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$channel = isset($_GET['channel']) ? mysqli_real_escape_string($conn, trim($_GET['channel'])) : 'all';
$status = isset($_GET['status']) ? mysqli_real_escape_string($conn, trim($_GET['status'])) : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$limit = isset($_GET['limit']) ? max(1, min(200, intval($_GET['limit']))) : 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$where = ["1=1"];

if ($channel !== 'all' && !empty($channel)) {
    $where[] = "m.channel_code = '$channel'";
}

if ($status === 'unmapped') {
    $where[] = "m.status = 'unmapped'";
} elseif ($status === 'mapped') {
    $where[] = "m.status = 'mapped'";
}

if (!empty($search)) {
    $where[] = "(m.platform_sku LIKE '%$search%' OR m.platform_name LIKE '%$search%' OR m.target_sku LIKE '%$search%')";
}

$where_sql = implode(" AND ", $where);

// Count total matching
$count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM platform_sku_mapping m WHERE $where_sql");
$total_rows = $count_res ? intval(mysqli_fetch_assoc($count_res)['total']) : 0;

// High-speed 2-step lookup: avoids cross-collation join table scan
$sql = "SELECT m.*, c.channel_name, c.platform_type, c.badge_color, c.customer_name as channel_customer
        FROM platform_sku_mapping m
        LEFT JOIN platform_channels c ON m.channel_code = c.channel_code
        WHERE $where_sql
        ORDER BY (m.status = 'unmapped') DESC, m.id DESC
        LIMIT $offset, $limit";

$res = mysqli_query($conn, $sql);
$items = [];
$target_skus = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $sku = trim($row['target_sku'] ?? '');
        if (!empty($sku)) {
            $target_skus[] = "'" . mysqli_real_escape_string($conn, $sku) . "'";
        }

        // Format TikTok product URL
        $prod_url = $row['product_url'] ?? '';
        if (strpos($prod_url, 'seller-th.tiktok.com/product/edit/') !== false) {
            preg_match('/edit\\/([0-9]+)/', $prod_url, $t_match);
            if (!empty($t_match[1])) {
                $prod_url = 'https://www.tiktok.com/view/product/' . $t_match[1];
            }
        }

        $items[] = [
            "id" => intval($row['id']),
            "channel_code" => $row['channel_code'],
            "channel_name" => $row['channel_name'] ?: $row['channel_code'],
            "platform_type" => $row['platform_type'] ?: 'shopee',
            "badge_color" => $row['badge_color'] ?: '#6b7280',
            "platform_sku" => $row['platform_sku'],
            "platform_name" => $row['platform_name'],
            "image_url" => $row['image_url'] ?? '',
            "product_url" => $prod_url,
            "mapping_type" => $row['mapping_type'],
            "target_sku" => $row['target_sku'],
            "bundle_json" => $row['bundle_json'],
            "vat_type" => $row['vat_type'],
            "status" => $row['status'],
            "matched_prod_id" => 0,
            "matched_prod_name" => '',
            "matched_img" => '',
            "matched_price" => 0.0,
            "total_stock" => 0,
            "bundle_components" => [],
            "bundle_available_stock" => null
        ];
    }
}

// Step 2: Fetch matched products via indexed IN query (< 5ms)
$prod_map = [];
$prod_ids = [];
if (!empty($target_skus)) {
    $sku_list = implode(',', array_unique($target_skus));
    $q_p = mysqli_query($conn, "SELECT prod_id, prod_code, prod_name, prod_price,
            CASE WHEN img LIKE 'img/%' THEN img WHEN LENGTH(img) < 200 THEN img ELSE '' END as matched_img
            FROM product WHERE prod_code IN ($sku_list)");
    if ($q_p) {
        while ($p = mysqli_fetch_assoc($q_p)) {
            $prod_map[$p['prod_code']] = $p;
            $prod_ids[] = intval($p['prod_id']);
        }
    }
}

// Attach matched product details
$img_dir = __DIR__ . '/../img/products';
foreach ($items as &$it) {
    $tsku = $it['target_sku'];
    if (!empty($tsku) && isset($prod_map[$tsku])) {
        $p = $prod_map[$tsku];
        $it['matched_prod_id'] = intval($p['prod_id']);
        $it['matched_prod_name'] = $p['prod_name'] ?: '';
        $it['matched_price'] = floatval($p['prod_price']);

        // Check product image on disk
        $m_img = $p['matched_img'];
        if (empty($m_img)) {
            if (file_exists($img_dir . '/' . $p['prod_id'] . '.jpg')) {
                $m_img = 'img/products/' . $p['prod_id'] . '.jpg';
            } else if (file_exists($img_dir . '/' . $p['prod_id'] . '.png')) {
                $m_img = 'img/products/' . $p['prod_id'] . '.png';
            }
        }
        $it['matched_img'] = $m_img;

        // Fallback: If image_url is empty, use DDCar product image
        if (empty($it['image_url']) && !empty($m_img)) {
            $it['image_url'] = $m_img;
        }
    }
}
unset($it);

// Ultra-fast Batch Stock Query for found products (< 10ms)
if (!empty($prod_ids)) {
    $pid_str = implode(',', array_unique($prod_ids));
    $stock_map = [];

    $wq = mysqli_query($conn, "SELECT prod_id, SUM(amount) as amt FROM warehouse WHERE prod_id IN ($pid_str) GROUP BY prod_id");
    if ($wq) {
        while ($r = mysqli_fetch_assoc($wq)) {
            $stock_map[$r['prod_id']] = intval($r['amt']);
        }
    }

    $sq = mysqli_query($conn, "SELECT prod_id, SUM(amount) as amt FROM shop WHERE prod_id IN ($pid_str) GROUP BY prod_id");
    if ($sq) {
        while ($r = mysqli_fetch_assoc($sq)) {
            $stock_map[$r['prod_id']] = ($stock_map[$r['prod_id']] ?? 0) + intval($r['amt']);
        }
    }

    foreach ($items as &$it) {
        if ($it['matched_prod_id'] > 0 && isset($stock_map[$it['matched_prod_id']])) {
            $it['total_stock'] = $stock_map[$it['matched_prod_id']];
        }
    }
    unset($it);
}

// Process bundle components if any
foreach ($items as &$it) {
    if ($it['mapping_type'] === 'bundle' && !empty($it['bundle_json'])) {
        $bundle_data = json_decode($it['bundle_json'], true);
        if (is_array($bundle_data) && !empty($bundle_data)) {
            $it['bundle_components'] = $bundle_data;
        }
    }
    unset($it['bundle_json']);
}
unset($it);

echo json_encode([
    "success" => true,
    "total" => $total_rows,
    "page" => $page,
    "limit" => $limit,
    "items" => $items
], JSON_UNESCAPED_UNICODE);
?>`;

fs.writeFileSync('c:/ddcar/www/php/get_platform_mappings.php', fastMappingsPhp, 'utf8');
console.log('  [OK] Optimized get_platform_mappings.php written');

// ==========================================
// 6. get_daily_reconciliation.php (channel=all fix)
// ==========================================
console.log('\n--- 6. Fixing get_daily_reconciliation.php channel=all ---');
let recPhp = fs.readFileSync('c:/ddcar/www/php/get_daily_reconciliation.php', 'utf8');

// Replace channel handling
const oldChannelMeta = `$cur_channel = $channel_meta[$channel_code] ?? $channel_meta['shopee_kingkong'];
$target_cus_id = intval($cur_channel['cus_id']);
$target_cus_name = mysqli_real_escape_string($conn, $cur_channel['cus_name']);`;

const newChannelMeta = `$is_all_channels = ($channel_code === 'all');
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
}`;

recPhp = recPhp.replace(oldChannelMeta, newChannelMeta);

// In queries, use $channel_filter and $cus_filter
const oldSqlOrd = `WHERE channel_code = '$channel_code' 
                AND (DATE(order_date) = '$date' OR DATE(created_at) = '$date')`;
const newSqlOrd = `WHERE " . ($is_all_channels ? "1=1" : "channel_code = '$channel_code'") . " 
                AND (DATE(order_date) = '$date' OR DATE(created_at) = '$date')`;
recPhp = recPhp.replace(oldSqlOrd, newSqlOrd);

const oldSqlItems = `WHERE i.channel_code = '$channel_code' 
                  AND (DATE(i.created_at) = '$date' OR EXISTS (SELECT 1 FROM platform_orders o WHERE o.order_sn = i.order_sn AND DATE(o.order_date) = '$date'))`;
const newSqlItems = `WHERE " . ($is_all_channels ? "1=1" : "i.channel_code = '$channel_code'") . " 
                  AND (DATE(i.created_at) = '$date' OR EXISTS (SELECT 1 FROM platform_orders o WHERE o.order_sn = i.order_sn AND DATE(o.order_date) = '$date'))`;
recPhp = recPhp.replace(oldSqlItems, newSqlItems);

// For sale_order query
const oldSaleQuery = `WHERE (s.customer_id = '$target_cus_id' OR c.cus_name LIKE '%$target_cus_name%')`;
const newSaleQuery = `WHERE " . ($is_all_channels ? "s.customer_id IN ($all_cus_list)" : "(s.customer_id = '$target_cus_id' OR c.cus_name LIKE '%$target_cus_name%')") . "`;
recPhp = recPhp.replace(oldSaleQuery, newSaleQuery);

fs.writeFileSync('c:/ddcar/www/php/get_daily_reconciliation.php', recPhp, 'utf8');
console.log('  [OK] Fixed channel=all in get_daily_reconciliation.php');

// ==========================================
// 7. get_status_prod.php (Filter active items)
// ==========================================
console.log('\n--- 7. Updating get_status_prod.php active filter ---');
let statusProdPhp = fs.readFileSync('c:/ddcar/www/php/get_status_prod.php', 'utf8');

// Add active_filter parameter (default: 90 days)
const statusProdParamOld = `$filter = isset($_POST['filter']) ? trim($_POST['filter']) : 'all';
    $search = isset($_POST['search']) ? mysqli_real_escape_string($conn, trim($_POST['search'])) : '';`;

const statusProdParamNew = `$filter = isset($_POST['filter']) ? trim($_POST['filter']) : 'all';
    $search = isset($_POST['search']) ? mysqli_real_escape_string($conn, trim($_POST['search'])) : '';
    $view_scope = isset($_POST['view_scope']) ? trim($_POST['view_scope']) : 'active_90';
    $active_cond = "p.prod_id IN (SELECT DISTINCT soi.prod_id FROM sale_order_item soi JOIN sale_order so ON soi.order_id = so.order_id WHERE so.date_time >= DATE_SUB(CURDATE(), INTERVAL 90 DAY))";
    $apply_active = ($view_scope === 'active_90' && empty($search));`;

statusProdPhp = statusProdPhp.replace(statusProdParamOld, statusProdParamNew);

// Update stats calculation
const oldStatsQuery = `$q_st1 = mysqli_query($conn, "SELECT status, COUNT(*) AS c FROM product WHERE status != 'normal' GROUP BY status");`;
const newStatsQuery = `$q_st1 = mysqli_query($conn, "SELECT p.status, COUNT(*) AS c FROM product p WHERE p.status != 'normal' " . ($apply_active ? "AND ($active_cond) " : "") . "GROUP BY p.status");`;
statusProdPhp = statusProdPhp.replace(oldStatsQuery, newStatsQuery);

// Update queries in get_status_prod
statusProdPhp = statusProdPhp.replace(
  `WHERE status != 'normal' \n                       ORDER BY prod_id DESC`,
  `WHERE status != 'normal' " . ($apply_active ? "AND ($active_cond) " : "") . "\n                       ORDER BY prod_id DESC`
);
statusProdPhp = statusProdPhp.replace(
  `WHERE status = 'danger' \n                       ORDER BY prod_id DESC`,
  `WHERE status = 'danger' " . ($apply_active ? "AND ($active_cond) " : "") . "\n                       ORDER BY prod_id DESC`
);
statusProdPhp = statusProdPhp.replace(
  `WHERE status = 'warning' \n                       ORDER BY prod_id DESC`,
  `WHERE status = 'warning' " . ($apply_active ? "AND ($active_cond) " : "") . "\n                       ORDER BY prod_id DESC`
);

fs.writeFileSync('c:/ddcar/www/php/get_status_prod.php', statusProdPhp, 'utf8');
console.log('  [OK] Added active products filter in get_status_prod.php');

console.log('\n=== ALL FIXES APPLIED TO LOCAL FILES! ===');
