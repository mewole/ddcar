<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
/**
 * get_sales_analytics.php
 * วิเคราะห์สินค้าขายดี (Top 20 Best Sellers) และ สินค้าค้างสต๊อก/ไม่เคลื่อนไหว (Dead Stock)
 * สถาปัตยกรรม Pre-aggregation ประสิทธิภาพสูง: ตอบสนองในระดับมิลลิวินาที ไม่ล็อกฐานข้อมูล
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ini_set('memory_limit', '512M');
include "db.php";
include_once "folder_helper.php";

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : 'top_sellers');
$days = isset($_POST['days']) ? intval($_POST['days']) : (isset($_GET['days']) ? intval($_GET['days']) : 30);
if ($days <= 0) $days = 30;

$txt_cancel = "(cancel)";
$date_cutoff = date('Y-m-d 00:00:00', strtotime("-$days days"));

if ($action === 'top_sellers') {
    $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : (isset($_POST['sort_by']) ? $_POST['sort_by'] : 'qty');
    $order_sql = ($sort_by === 'revenue') ? "total_revenue DESC, total_qty DESC" : "total_qty DESC, total_revenue DESC";

    // 1. Group sales by prod_id directly from indexed sale_order & sale_order_item
    $sql = "SELECT 
                soi.prod_id,
                SUM(soi.prod_amount) AS total_qty,
                SUM(soi.prod_amount * soi.prod_price) AS total_revenue
            FROM sale_order s
            STRAIGHT_JOIN sale_order_item soi ON s.order_id = soi.order_id
            WHERE s.date_time >= '$date_cutoff'
              AND s.order_number NOT LIKE '%$txt_cancel%'
            GROUP BY soi.prod_id
            ORDER BY $order_sql
            LIMIT 20";

    $res = mysqli_query($conn, $sql);
    $sales_data = array();
    $prod_ids = array();
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $pid = intval($row['prod_id']);
            $sales_data[$pid] = $row;
            $prod_ids[] = $pid;
        }
    }

    $items = array();
    $grand_qty = 0;
    $grand_rev = 0;
    $grand_profit = 0;

    if (!empty($prod_ids)) {
        $id_str = implode(',', $prod_ids);

        // 2. Fetch product details only for these 20 products
        $prod_res = mysqli_query($conn, "SELECT prod_id, prod_code, prod_name, 
            CASE WHEN img LIKE 'img/%' THEN img WHEN LENGTH(img) < 200 THEN img ELSE '' END AS img,
            prod_cost, prod_price, prod_pricesend 
            FROM product WHERE prod_id IN ($id_str)");
        $prod_info = array();
        if ($prod_res) {
            while ($p = mysqli_fetch_assoc($prod_res)) {
                $prod_info[intval($p['prod_id'])] = $p;
            }
        }

        // 3. Batch fetch current stock from shop and warehouse
        $stock_map = array();
        $res_s = mysqli_query($conn, "SELECT prod_id, SUM(amount) AS qty FROM shop WHERE prod_id IN ($id_str) GROUP BY prod_id");
        if ($res_s) {
            while ($r = mysqli_fetch_assoc($res_s)) {
                $stock_map[$r['prod_id']] = intval($r['qty']);
            }
        }
        $res_w = mysqli_query($conn, "SELECT prod_id, SUM(amount) AS qty FROM warehouse WHERE prod_id IN ($id_str) GROUP BY prod_id");
        if ($res_w) {
            while ($r = mysqli_fetch_assoc($res_w)) {
                $stock_map[$r['prod_id']] = (isset($stock_map[$r['prod_id']]) ? $stock_map[$r['prod_id']] : 0) + intval($r['qty']);
            }
        }

        // 4. Folder paths
        $folder_map = get_products_folder_paths($conn, $prod_ids);

        $rank = 1;
        foreach ($prod_ids as $pid) {
            if (!isset($prod_info[$pid])) continue;
            $p = $prod_info[$pid];
            $s = $sales_data[$pid];
            $qty = intval($s['total_qty']);
            $rev = round(floatval($s['total_revenue']), 2);
            $cost = floatval($p['prod_cost']);
            $profit = round($rev - ($qty * $cost), 2);

            $grand_qty += $qty;
            $grand_rev += $rev;
            $grand_profit += $profit;

            $pid_str = (string)$pid;
            $items[] = array(
                'rank' => $rank++,
                'prod_id' => $pid,
                'prod_code' => $p['prod_code'],
                'prod_name' => $p['prod_name'],
                'img' => $p['img'],
                'prod_cost' => $cost,
                'prod_price' => floatval($p['prod_price']),
                'prod_pricesend' => floatval($p['prod_pricesend']),
                'total_qty' => $qty,
                'total_revenue' => $rev,
                'total_profit' => $profit,
                'current_stock' => isset($stock_map[$pid]) ? $stock_map[$pid] : 0,
                'folder_path' => isset($folder_map[$pid_str]) ? $folder_map[$pid_str]['path'] : '',
                'folder_chain' => isset($folder_map[$pid_str]) ? $folder_map[$pid_str]['chain'] : array()
            );
        }
    }

    echo json_encode([
        'success' => true,
        'action' => 'top_sellers',
        'days' => $days,
        'sort_by' => $sort_by,
        'date_cutoff' => $date_cutoff,
        'summary' => [
            'total_qty' => $grand_qty,
            'total_revenue' => $grand_rev,
            'total_profit' => $grand_profit,
            'item_count' => count($items)
        ],
        'items' => $items
    ]);

} else if ($action === 'dead_stock') {
    // 1. Gather all product IDs that were sold since cutoff date
    $sold_res = mysqli_query($conn, "SELECT DISTINCT soi.prod_id 
        FROM sale_order so 
        STRAIGHT_JOIN sale_order_item soi ON so.order_id = soi.order_id 
        WHERE so.date_time >= '$date_cutoff' AND so.order_number NOT LIKE '%$txt_cancel%'");
    $sold_map = array();
    if ($sold_res) {
        while ($sr = mysqli_fetch_assoc($sold_res)) {
            $sold_map[intval($sr['prod_id'])] = true;
        }
    }

    // 2. Fetch products with active stock in shop or warehouse
    $shop_res = mysqli_query($conn, "SELECT prod_id, SUM(amount) AS qty FROM shop WHERE amount > 0 GROUP BY prod_id");
    $stock_map = array();
    if ($shop_res) {
        while ($shr = mysqli_fetch_assoc($shop_res)) {
            $pid = intval($shr['prod_id']);
            if (!isset($sold_map[$pid])) {
                $stock_map[$pid] = ['shop' => intval($shr['qty']), 'ware' => 0];
            }
        }
    }

    $ware_res = mysqli_query($conn, "SELECT prod_id, SUM(amount) AS qty FROM warehouse WHERE amount > 0 GROUP BY prod_id");
    if ($ware_res) {
        while ($wr = mysqli_fetch_assoc($ware_res)) {
            $pid = intval($wr['prod_id']);
            if (!isset($sold_map[$pid])) {
                if (!isset($stock_map[$pid])) {
                    $stock_map[$pid] = ['shop' => 0, 'ware' => intval($wr['qty'])];
                } else {
                    $stock_map[$pid]['ware'] = intval($wr['qty']);
                }
            }
        }
    }

    $items = array();
    $total_dead_stock_qty = 0;
    $total_frozen_capital = 0;

    if (!empty($stock_map)) {
        $pids = array_keys($stock_map);
        $pid_chunks = array_slice($pids, 0, 150);
        $id_str = implode(',', $pid_chunks);

        $prod_res = mysqli_query($conn, "SELECT prod_id, prod_code, prod_name, 
            CASE WHEN img LIKE 'img/%' THEN img WHEN LENGTH(img) < 200 THEN img ELSE '' END AS img,
            prod_cost, prod_price, prod_pricesend 
            FROM product WHERE prod_id IN ($id_str)");

        $prod_info = array();
        if ($prod_res) {
            while ($p = mysqli_fetch_assoc($prod_res)) {
                $prod_info[intval($p['prod_id'])] = $p;
            }
        }

        $folder_map = get_products_folder_paths($conn, $pid_chunks);

        foreach ($pid_chunks as $pid) {
            if (!isset($prod_info[$pid])) continue;
            $p = $prod_info[$pid];
            $st = $stock_map[$pid];
            $total_stock = $st['shop'] + $st['ware'];
            $cost = floatval($p['prod_cost']);
            $frozen = round($total_stock * $cost, 2);

            $total_dead_stock_qty += $total_stock;
            $total_frozen_capital += $frozen;

            $pid_str = (string)$pid;
            $items[] = array(
                'prod_id' => $pid,
                'prod_code' => $p['prod_code'],
                'prod_name' => $p['prod_name'],
                'img' => $p['img'],
                'prod_cost' => $cost,
                'prod_price' => floatval($p['prod_price']),
                'prod_pricesend' => floatval($p['prod_pricesend']),
                'shop_amount' => $st['shop'],
                'ware_amount' => $st['ware'],
                'total_stock' => $total_stock,
                'frozen_capital' => $frozen,
                'last_sale_date' => null,
                'folder_path' => isset($folder_map[$pid_str]) ? $folder_map[$pid_str]['path'] : '',
                'folder_chain' => isset($folder_map[$pid_str]) ? $folder_map[$pid_str]['chain'] : array()
            );
        }

        usort($items, function($a, $b) {
            return $b['frozen_capital'] <=> $a['frozen_capital'];
        });
    }

    echo json_encode([
        'success' => true,
        'action' => 'dead_stock',
        'days' => $days,
        'date_cutoff' => $date_cutoff,
        'summary' => [
            'item_count' => count($items),
            'total_stock_qty' => $total_dead_stock_qty,
            'total_frozen_capital' => round($total_frozen_capital, 2)
        ],
        'items' => $items
    ]);
}

mysqli_close($conn);
?>
