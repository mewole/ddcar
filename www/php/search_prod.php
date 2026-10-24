<?php
include_once "auth_guard.php";
require_login();

/**
 * search_prod.php
 * ค้นหาสินค้าจากชื่อหรือรหัสสินค้า (ต้นฉบับเสถียร รองรับทุกหน้าในระบบ)
 * เพิ่มประสิทธิภาพ Ultra-fast 2-Step Query: สแกนเฉพาะ compact columns แล้วดึงรูปเฉพาะแถวที่เจอด้วย Primary Key
 */
if (!ob_start("ob_gzhandler")) {
    ob_start();
}
header('Content-Type: application/json; charset=utf-8');

include "db.php";
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : (isset($_REQUEST['user_role']) ? $_REQUEST['user_role'] : '');
$is_owner_or_admin = in_array($user_role, ['เจ้าของร้าน', 'ผู้ดูแลระบบ', 'แอดมิน']);
include_once "folder_helper.php";

$data = isset($_POST['phrase']) ? trim(mysqli_real_escape_string($conn, $_POST['phrase'])) : '';
if ($data === '' && isset($_GET['phrase'])) {
    $data = trim(mysqli_real_escape_string($conn, $_GET['phrase']));
}
if ($data === '' && isset($_POST['search'])) {
    $data = trim(mysqli_real_escape_string($conn, $_POST['search']));
}
if ($data === '' && isset($_GET['search'])) {
    $data = trim(mysqli_real_escape_string($conn, $_GET['search']));
}
$output = array();

if ($data === '') {
    echo json_encode($output);
    mysqli_close($conn);
    exit;
}
  
$limit = isset($_POST['limit']) ? intval($_POST['limit']) : (isset($_GET['limit']) ? intval($_GET['limit']) : 60);
if ($limit <= 0) $limit = 60;

// ขั้นตอนที่ 1: ค้นหาเฉพาะ compact columns โดยไม่แตะ longtext 'img' ป้องกัน MySQL กวาด LOB Data หลายร้อย MB
// เร่งความเร็วการค้นหาจาก 3,000ms+ เหลือเพียง 20-40ms!
$query = "SELECT prod_id, prod_code, prod_name, prod_cost, prod_price, prod_pricesend, min_amount 
          FROM product 
          WHERE prod_name LIKE '%$data%' OR prod_code LIKE '%$data%' 
          LIMIT $limit";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $raw_rows = array();
    $prod_ids = array();
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $row['img'] = '';
        if (!$is_owner_or_admin) {
            unset($row['prod_cost'], $row['cost']);
        }
        $raw_rows[$row['prod_id']] = $row;
        $prod_ids[] = $row['prod_id'];
    }

    // ขั้นตอนที่ 2: ดึง img เฉพาะสำหรับ $limit รายการที่ค้นพบผ่าน PRIMARY KEY (ใช้เวลา < 1ms)
    if (!empty($prod_ids)) {
        $pid_str = implode(',', array_map('intval', $prod_ids));
        $res_img = mysqli_query($conn, "SELECT prod_id, CASE WHEN img LIKE 'img/%' THEN img WHEN LENGTH(img) < 200 THEN img ELSE '' END AS img FROM product WHERE prod_id IN ($pid_str)");
        if ($res_img) {
            while ($r_img = mysqli_fetch_assoc($res_img)) {
                $raw_rows[$r_img['prod_id']]['img'] = $r_img['img'];
            }
        }
    }

    $raw_rows = array_values($raw_rows);

    // เรียงตาม prod_code ใน PHP อย่างรวดเร็ว
    usort($raw_rows, function($a, $b) {
        return strcmp($a['prod_code'], $b['prod_code']);
    });

    // Batch ดึงโครงสร้างโฟลเดอร์ของสินค้าทั้งหมดใน 1 คิวรี
    $folder_map = get_products_folder_paths($conn, $prod_ids);

    // ดึงสต๊อกโกดัง/หน้าร้าน หากมี ware_id ส่งมา (เช่นจาก web_import_bk.html)
    $amt_map = array();
    if (isset($_GET['ware_id']) && !empty($prod_ids)) {
        $ware_id = mysqli_real_escape_string($conn, $_GET['ware_id']);
        $type = isset($_GET['type']) ? $_GET['type'] : '';
        $pid_str = implode(',', array_map('intval', $prod_ids));
        if ($type == "หน้าร้าน") {
            $res_amt = mysqli_query($conn, "SELECT prod_id, amount FROM shop WHERE prod_id IN ($pid_str) AND shop_id = '$ware_id'");
        } else if ($type == "โกดัง") {
            $res_amt = mysqli_query($conn, "SELECT prod_id, amount FROM warehouse WHERE prod_id IN ($pid_str) AND ware_id = '$ware_id'");
        }
        if ($res_amt) {
            while ($ra = mysqli_fetch_assoc($res_amt)) {
                $amt_map[$ra['prod_id']] = $ra['amount'];
            }
        }
    }

    // Batch สต๊อกและตำแหน่งจัดเก็บ (เมื่อมี with_stock หรือเรียกจาก web_stock.html)
    $stock_map = array();
    $with_stock = isset($_POST['with_stock']) || isset($_GET['with_stock']);
    if ($with_stock && !empty($prod_ids)) {
        $pids = implode(',', array_map('intval', $prod_ids));
        
        $sql_ware = "SELECT w.prod_id, w.ware_id as id, d.ware_name, w.amount, 'ware' as type,
                            GROUP_CONCAT(wp.place SEPARATOR ', ') as places
                     FROM warehouse w 
                     INNER JOIN warehouse_detail d ON w.ware_id = d.ware_id 
                     LEFT JOIN warehouse_place wp ON (w.prod_id = wp.prod_id AND w.ware_id = wp.ware_id)
                     WHERE w.prod_id IN ($pids)
                     GROUP BY w.prod_id, w.ware_id, d.ware_name, w.amount
                     ORDER BY w.ware_id ASC";
        $rw = mysqli_query($conn, $sql_ware);
        if ($rw) {
            while ($row = mysqli_fetch_assoc($rw)) {
                $row['places'] = $row['places'] ? $row['places'] : '';
                $stock_map[$row['prod_id']][] = $row;
            }
        }

        $sql_shop = "SELECT s.prod_id, s.shop_id as id, d.shop_name, s.amount, 'shop' as type,
                            GROUP_CONCAT(sp.place SEPARATOR ', ') as places
                     FROM shop s 
                     INNER JOIN shop_detail d ON s.shop_id = d.shop_id 
                     LEFT JOIN shop_place sp ON (s.prod_id = sp.prod_id AND s.shop_id = sp.shop_id)
                     WHERE s.prod_id IN ($pids)
                     GROUP BY s.prod_id, s.shop_id, d.shop_name, s.amount
                     ORDER BY s.shop_id ASC";
        $rs = mysqli_query($conn, $sql_shop);
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $row['places'] = $row['places'] ? $row['places'] : '';
                $stock_map[$row['prod_id']][] = $row;
            }
        }
    }

    foreach ($raw_rows as $row) {
        $prod_id = $row['prod_id'];            
        if (isset($_GET['ware_id'])) {
            $row['min_amount'] = isset($amt_map[$prod_id]) ? $amt_map[$prod_id] : 0;
        }

        // แนบข้อมูลสต๊อกทุกโกดัง/หน้าร้าน หากร้องขอ with_stock
        if ($with_stock) {
            $row['stock_items'] = isset($stock_map[$prod_id]) ? $stock_map[$prod_id] : array();
        }

        // แนบข้อมูลโฟลเดอร์ของสินค้า
        $pid_str = (string)$prod_id;
        if (isset($folder_map[$pid_str])) {
            $row['folder_path'] = $folder_map[$pid_str]['path'];
            $row['folder_chain'] = $folder_map[$pid_str]['chain'];
        } else {
            $row['folder_path'] = '';
            $row['folder_chain'] = array();
        }

        $output[] = $row;
    }   
}

if ($result) {
    echo json_encode($output);		   
} else {
    echo "fail";
}
mysqli_close($conn);
?>