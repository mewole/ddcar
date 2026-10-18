<?php
/**
 * search_prod.php
 * ค้นหาสินค้าแบบรวดเร็วพิเศษ (Ultra-fast Two-step Indexed Search)
 * ประสิทธิภาพสูง: ค้นหาผ่าน Index ก่อน ไม่ดึง Base64 ไม่ทำให้ระบบค้าง
 */
include "db.php";
include_once "folder_helper.php";

header('Content-Type: application/json; charset=utf-8');

$phrase = isset($_POST['phrase']) ? trim(mysqli_real_escape_string($conn, $_POST['phrase'])) : '';
$output = array();

if ($phrase === '' || mb_strlen($phrase) < 2) {
    echo json_encode($output);
    mysqli_close($conn);
    exit;
}

$pids = array();
$limit = 40;

// ขั้นตอนที่ 1: ค้นหาแบบ Prefix จาก prod_code (ใช้ Index prod_code ตรงๆ เร็วระดับ 1ms)
$res1 = mysqli_query($conn, "SELECT prod_id FROM product WHERE prod_code LIKE '$phrase%' LIMIT $limit");
if ($res1) {
    while ($r = mysqli_fetch_row($res1)) {
        $pids[] = intval($r[0]);
    }
}

// ขั้นตอนที่ 2: ค้นหาแบบ Prefix จาก prod_name (ถ้ายังไม่ครบ 40 รายการ)
if (count($pids) < $limit) {
    $remain = $limit - count($pids);
    $ex = !empty($pids) ? "AND prod_id NOT IN (" . implode(',', $pids) . ")" : "";
    $res2 = mysqli_query($conn, "SELECT prod_id FROM product WHERE prod_name LIKE '$phrase%' $ex LIMIT $remain");
    if ($res2) {
        while ($r = mysqli_fetch_row($res2)) {
            $pids[] = intval($r[0]);
        }
    }
}

// ขั้นตอนที่ 3: ค้นหาแบบ Substring (%phrase%) ทั้งในรหัสและชื่อ (ถ้ายังไม่ครบ 40 รายการ)
if (count($pids) < $limit) {
    $remain = $limit - count($pids);
    $ex = !empty($pids) ? "AND prod_id NOT IN (" . implode(',', $pids) . ")" : "";
    $res3 = mysqli_query($conn, "SELECT prod_id FROM product WHERE (prod_code LIKE '%$phrase%' OR prod_name LIKE '%$phrase%') $ex LIMIT $remain");
    if ($res3) {
        while ($r = mysqli_fetch_row($res3)) {
            $pids[] = intval($r[0]);
        }
    }
}

if (!empty($pids)) {
    $pid_str = implode(',', $pids);
    
    // ดึงเฉพาะฟิลด์ที่จำเป็น และดึง img เฉพาะที่เป็น path สั้น เพื่อป้องกัน Response บวม
    $sql_full = "SELECT 
                    prod_id, 
                    prod_code, 
                    prod_name, 
                    prod_cost, 
                    prod_price, 
                    prod_pricesend, 
                    min_amount,
                    CASE WHEN img LIKE 'img/%' THEN img WHEN LENGTH(img) < 200 THEN img ELSE '' END AS img 
                 FROM product 
                 WHERE prod_id IN ($pid_str) 
                 ORDER BY prod_code ASC";
    $result = mysqli_query($conn, $sql_full);

    if ($result && mysqli_num_rows($result) > 0) {
        $raw_rows = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $raw_rows[] = $row;
        }

        // Batch ดึงโครงสร้างโฟลเดอร์ของสินค้าทั้งหมดใน 1 คิวรี
        $folder_map = get_products_folder_paths($conn, $pids);

        // Batch ดึงสต๊อกตามสาขา/โกดัง (ถ้ามี ware_id ส่งมา)
        $amt_map = array();
        if (isset($_GET['ware_id'])) {
            $ware_id = mysqli_real_escape_string($conn, $_GET['ware_id']);
            $type = isset($_GET['type']) ? $_GET['type'] : '';
            if ($type === "หน้าร้าน") {
                $res_amt = mysqli_query($conn, "SELECT prod_id, amount FROM shop WHERE prod_id IN ($pid_str) AND shop_id = '$ware_id'");
            } else {
                $res_amt = mysqli_query($conn, "SELECT prod_id, amount FROM warehouse WHERE prod_id IN ($pid_str) AND ware_id = '$ware_id'");
            }
            if ($res_amt) {
                while ($ra = mysqli_fetch_assoc($res_amt)) {
                    $amt_map[$ra['prod_id']] = $ra['amount'];
                }
            }
        }

        foreach ($raw_rows as $row) {
            $prod_id = $row['prod_id'];            
            if (isset($_GET['ware_id'])) {
                $row['min_amount'] = isset($amt_map[$prod_id]) ? $amt_map[$prod_id] : 0;
            }

            $pid_str_key = (string)$prod_id;
            if (isset($folder_map[$pid_str_key])) {
                $row['folder_path'] = $folder_map[$pid_str_key]['path'];
                $row['folder_chain'] = $folder_map[$pid_str_key]['chain'];
            } else {
                $row['folder_path'] = '';
                $row['folder_chain'] = array();
            }

            $output[] = $row;
        }
    }
}

echo json_encode($output);
mysqli_close($conn);
?>