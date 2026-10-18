<?php
/**
 * search_prod.php
 * ค้นหาสินค้าจากชื่อหรือรหัสสินค้า (ต้นฉบับเสถียร รองรับทุกหน้าในระบบ)
 */
include "db.php";
include_once "folder_helper.php";

$data = isset($_POST['phrase']) ? trim(mysqli_real_escape_string($conn, $_POST['phrase'])) : '';
if ($data === '' && isset($_GET['phrase'])) {
    $data = trim(mysqli_real_escape_string($conn, $_GET['phrase']));
}
$output = array();

if ($data === '') {
    echo json_encode($output);
    mysqli_close($conn);
    exit;
}
  
$query = "SELECT prod_id, prod_code, prod_name, prod_cost, prod_price, prod_pricesend, min_amount,
                 CASE WHEN img LIKE 'img/%' THEN img WHEN LENGTH(img) < 200 THEN img ELSE '' END AS img 
          FROM product 
          WHERE prod_name LIKE '%$data%' OR prod_code LIKE '%$data%' 
          ORDER BY prod_code ASC";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $raw_rows = array();
    $prod_ids = array();
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $raw_rows[] = $row;
        $prod_ids[] = $row['prod_id'];
    }

    // Batch ดึงโครงสร้างโฟลเดอร์ของสินค้าทั้งหมดใน 1 คิวรี
    $folder_map = get_products_folder_paths($conn, $prod_ids);

    // ดึงสต๊อกโกดัง/หน้าร้าน หากมี ware_id ส่งมา (เช่นจาก web_import_bk.html)
    $amt_map = array();
    if (isset($_GET['ware_id']) && !empty($prod_ids)) {
        $ware_id = mysqli_real_escape_string($conn, $_GET['ware_id']);
        $type = isset($_GET['type']) ? $_GET['type'] : '';
        $pid_str = implode(',', $prod_ids);
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

    foreach ($raw_rows as $row) {
        $prod_id = $row['prod_id'];            
        if (isset($_GET['ware_id'])) {
            $row['min_amount'] = isset($amt_map[$prod_id]) ? $amt_map[$prod_id] : 0;
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