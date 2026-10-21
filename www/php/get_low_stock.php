<?php
/**
 * get_low_stock.php
 * รายงานสินค้าใกล้หมด / ต่ำกว่าจุดสั่งซื้อซ้ำ (Reorder Point / min_amount)
 * ประสิทธิภาพสูง: ใช้ Index Query + Batch Stock Lookups + Lazy Attribute Fetching
 */
if (!ob_start("ob_gzhandler")) {
    ob_start();
}
ini_set('display_errors', 0);
error_reporting(0);
include "db.php";
include_once "folder_helper.php";

header('Content-Type: application/json; charset=utf-8');

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'reorder'; 
$items = array();
$prod_list = array();
$pids = array();

if ($mode === 'zero') {
    // โหมดสินค้าหมดสต๊อก: ดึงสินค้า 200 รายการล่าสุด
    $sql = "SELECT prod_id, prod_code, prod_name, 
            prod_cost, prod_price, prod_pricesend, CAST(min_amount AS SIGNED) AS min_amount
            FROM product 
            ORDER BY prod_id DESC 
            LIMIT 200";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $prod_list[] = $r;
            $pids[] = intval($r['prod_id']);
        }
    }
} else {
    // โหมดมาตรฐาน 'reorder': ดึงเฉพาะสินค้าที่ตั้งค่า min_amount > 0 โดยใช้ Index idx_prod_min_amount
    $sql = "SELECT prod_id, prod_code, prod_name, 
            prod_cost, prod_price, prod_pricesend, CAST(min_amount AS SIGNED) AS min_amount
            FROM product 
            WHERE min_amount > 0
            ORDER BY prod_id DESC
            LIMIT 200";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $prod_list[] = $r;
            $pids[] = intval($r['prod_id']);
        }
    }
}

if (!empty($pids)) {
    $pid_str = implode(',', $pids);

    // 1. Batch ดึงจำนวนสต๊อกหน้าร้าน (มี Index idx_sh_prod_shop)
    $shop_map = array();
    $sql_shop = "SELECT prod_id, SUM(amount) AS qty FROM shop WHERE prod_id IN ($pid_str) GROUP BY prod_id";
    $res_shop = mysqli_query($conn, $sql_shop);
    if ($res_shop) {
        while ($row = mysqli_fetch_assoc($res_shop)) {
            $shop_map[$row['prod_id']] = intval($row['qty']);
        }
    }

    // 2. Batch ดึงจำนวนสต๊อกโกดัง (มี Index idx_wh_prod_ware)
    $ware_map = array();
    $sql_ware = "SELECT prod_id, SUM(amount) AS qty FROM warehouse WHERE prod_id IN ($pid_str) GROUP BY prod_id";
    $res_ware = mysqli_query($conn, $sql_ware);
    if ($res_ware) {
        while ($row = mysqli_fetch_assoc($res_ware)) {
            $ware_map[$row['prod_id']] = intval($row['qty']);
        }
    }

    // 3. คำนวณสต๊อกรวมและเปรียบเทียบกับเกณฑ์
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
                'img' => '',
                'prod_cost' => $p['prod_cost'],
                'prod_price' => $p['prod_price'],
                'prod_pricesend' => $p['prod_pricesend'],
                'min_amount' => $min_amt,
                'shop_amount' => $shop_qty,
                'ware_amount' => $ware_qty,
                'total_amount' => $total_qty,
                'diff' => $total_qty - $min_amt,
                'urgency' => $urgency,
                'folder_path' => '',
                'folder_chain' => array()
            );
        }
    }

    // เรียงลำดับจากวิกฤตสุด (total น้อยสุด) ขึ้นก่อน
    usort($items, function($a, $b) {
        if ($a['total_amount'] === $b['total_amount']) {
            return $b['min_amount'] - $a['min_amount'];
        }
        return $a['total_amount'] - $b['total_amount'];
    });

    if (count($items) > 150) {
        $items = array_slice($items, 0, 150);
    }

    // 4. ดึงรูปภาพและโฟลเดอร์เฉพาะสินค้าที่ติดเกณฑ์จริง (ประหยัด memory และเวลาหลายร้อยเท่า)
    $low_pids = array_map(function($it) { return $it['prod_id']; }, $items);
    if (!empty($low_pids)) {
        $low_pid_str = implode(',', array_map('intval', $low_pids));
        
        $res_img = mysqli_query($conn, "SELECT prod_id, CASE WHEN img LIKE 'img/%' THEN img WHEN LENGTH(img) < 200 THEN img ELSE '' END AS img FROM product WHERE prod_id IN ($low_pid_str)");
        $img_map = array();
        if ($res_img) {
            while ($ri = mysqli_fetch_assoc($res_img)) {
                $img_map[$ri['prod_id']] = $ri['img'];
            }
        }

        $folder_map = get_products_folder_paths($conn, $low_pids);

        for ($i = 0; $i < count($items); $i++) {
            $pid = $items[$i]['prod_id'];
            $items[$i]['img'] = isset($img_map[$pid]) ? $img_map[$pid] : '';

            $pid_key = (string)$pid;
            if (isset($folder_map[$pid_key])) {
                $items[$i]['folder_path'] = $folder_map[$pid_key]['path'];
                $items[$i]['folder_chain'] = $folder_map[$pid_key]['chain'];
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
