<?php
/**
 * get_low_stock.php
 * รายงานสินค้าใกล้หมด / ต่ำกว่าจุดสั่งซื้อซ้ำ (Reorder Point / min_amount)
 * ประสิทธิภาพสูง: ใช้ Batch Query 3 ครั้งจบ ไม่ใช้ correlated subquery
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
include "db.php";
include_once "folder_helper.php";

header('Content-Type: application/json; charset=utf-8');

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'reorder'; 

$items = array();

if ($mode === 'zero') {
    // โหมดสินค้าหมดสต๊อก (รวมทุกรายการที่มีในระบบแต่สต๊อก = 0)
    // 1. ดึงสินค้าทั้งหมดที่ active 500 รายการ
    $sql = "SELECT prod_id, prod_code, prod_name, 
            CASE WHEN img LIKE 'img/%' THEN img WHEN LENGTH(img) < 200 THEN img ELSE '' END AS img, 
            prod_cost, prod_price, prod_pricesend, CAST(min_amount AS SIGNED) AS min_amount
            FROM product 
            ORDER BY prod_id DESC 
            LIMIT 500";
    $res = mysqli_query($conn, $sql);
    $prod_list = array();
    $pids = array();
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $prod_list[] = $r;
            $pids[] = intval($r['prod_id']);
        }
    }
} else {
    // โหมดมาตรฐาน 'reorder': ดึงเฉพาะสินค้าที่ผู้ใช้ตั้งค่า min_amount > 0 ไว้
    $sql = "SELECT prod_id, prod_code, prod_name, 
            CASE WHEN img LIKE 'img/%' THEN img WHEN LENGTH(img) < 200 THEN img ELSE '' END AS img, 
            prod_cost, prod_price, prod_pricesend, CAST(min_amount AS SIGNED) AS min_amount
            FROM product 
            WHERE min_amount != '' AND min_amount != '0' AND min_amount IS NOT NULL
            ORDER BY prod_id DESC
            LIMIT 300";
    $res = mysqli_query($conn, $sql);
    $prod_list = array();
    $pids = array();
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $prod_list[] = $r;
            $pids[] = intval($r['prod_id']);
        }
    }
}

if (!empty($pids)) {
    $pid_str = implode(',', $pids);

    // 2. Batch ดึงจำนวนสต๊อกหน้าร้าน
    $shop_map = array();
    $sql_shop = "SELECT prod_id, SUM(amount) AS qty FROM shop WHERE prod_id IN ($pid_str) GROUP BY prod_id";
    $res_shop = mysqli_query($conn, $sql_shop);
    if ($res_shop) {
        while ($row = mysqli_fetch_assoc($res_shop)) {
            $shop_map[$row['prod_id']] = intval($row['qty']);
        }
    }

    // 3. Batch ดึงจำนวนสต๊อกโกดัง
    $ware_map = array();
    $sql_ware = "SELECT prod_id, SUM(amount) AS qty FROM warehouse WHERE prod_id IN ($pid_str) GROUP BY prod_id";
    $res_ware = mysqli_query($conn, $sql_ware);
    if ($res_ware) {
        while ($row = mysqli_fetch_assoc($res_ware)) {
            $ware_map[$row['prod_id']] = intval($row['qty']);
        }
    }

    // 4. คำนวณสต๊อกรวมและเปรียบเทียบกับเกณฑ์
    $low_pids = array();
    foreach ($prod_list as $p) {
        $pid = $p['prod_id'];
        $shop_qty = isset($shop_map[$pid]) ? $shop_map[$pid] : 0;
        $ware_qty = isset($ware_map[$pid]) ? $ware_map[$pid] : 0;
        $total_qty = $shop_qty + $ware_qty;
        $min_amt = intval($p['min_amount']);

        $is_low = false;
        if ($mode === 'zero') {
            if ($total_qty <= 0) $is_low = true;
        } else {
            if ($min_amt > 0 && $total_qty <= $min_amt) $is_low = true;
        }

        if ($is_low) {
            $urgency = 'warning';
            if ($total_qty <= 0) {
                $urgency = 'out';
            } else if ($min_amt > 0 && $total_qty <= ceil($min_amt / 2)) {
                $urgency = 'critical';
            }

            $items[] = array(
                'prod_id' => $pid,
                'prod_code' => $p['prod_code'],
                'prod_name' => $p['prod_name'],
                'img' => $p['img'],
                'prod_cost' => $p['prod_cost'],
                'prod_price' => $p['prod_price'],
                'prod_pricesend' => $p['prod_pricesend'],
                'min_amount' => $min_amt,
                'shop_amount' => $shop_qty,
                'ware_amount' => $ware_qty,
                'total_amount' => $total_qty,
                'diff' => $total_qty - $min_amt,
                'urgency' => $urgency
            );
            $low_pids[] = $pid;
        }
    }

    // เรียงลำดับจากวิกฤตสุด (total น้อยสุด) ขึ้นก่อน
    usort($items, function($a, $b) {
        if ($a['total_amount'] === $b['total_amount']) {
            return $b['min_amount'] - $a['min_amount'];
        }
        return $a['total_amount'] - $b['total_amount'];
    });

    // 5. ดึงโครงสร้างโฟลเดอร์สำหรับสินค้าที่ติดแจ้งเตือน
    if (!empty($low_pids)) {
        $folder_map = get_products_folder_paths($conn, $low_pids);
        for ($i = 0; $i < count($items); $i++) {
            $pid_key = (string)$items[$i]['prod_id'];
            if (isset($folder_map[$pid_key])) {
                $items[$i]['folder_path'] = $folder_map[$pid_key]['path'];
                $items[$i]['folder_chain'] = $folder_map[$pid_key]['chain'];
            } else {
                $items[$i]['folder_path'] = '';
                $items[$i]['folder_chain'] = array();
            }
        }
    }
}

echo json_encode([
    'status' => 'success',
    'total_count' => count($items),
    'mode' => $mode,
    'data' => $items
]);

mysqli_close($conn);
?>
