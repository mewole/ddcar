<?php
    include "db.php";
    header("Content-Type: application/json; charset=UTF-8");

    $prod_id = isset($_POST['prod_id']) ? mysqli_real_escape_string($conn, trim($_POST['prod_id'])) : '';

    if (empty($prod_id)) {
        echo json_encode([
            'status' => 'empty',
            'prod_id' => '',
            'current_cost' => 0,
            'history' => [],
            'recent_orders' => [],
            'all_suppliers' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 1. ตรวจสอบว่าคอลัมน์ source_from มีใน stock_in_item หรือไม่ หากยังไม่มีให้สร้างอัตโนมัติ
    $checkCol = mysqli_query($conn, "SHOW COLUMNS FROM stock_in_item LIKE 'source_from'");
    if ($checkCol && mysqli_num_rows($checkCol) == 0) {
        @mysqli_query($conn, "ALTER TABLE stock_in_item ADD COLUMN source_from VARCHAR(50) NULL DEFAULT NULL AFTER amount");
    }

    // 2. ดึงข้อมูลต้นทุนปัจจุบันของสินค้า
    $current_cost = 0;
    $prod_name = '';
    $prod_code = '';
    $res_prod = mysqli_query($conn, "SELECT prod_name, prod_code, prod_cost FROM product WHERE prod_id = '$prod_id'");
    if ($res_prod && ($row_p = mysqli_fetch_assoc($res_prod))) {
        $current_cost = floatval($row_p['prod_cost']);
        $prod_name = $row_p['prod_name'];
        $prod_code = $row_p['prod_code'];
    }

    // 3. ดึงประวัติราคาทุนแยกตามร้านค้า (ที่มี source_from ระบุไว้)
    $history = array();
    $query = "SELECT 
                si.source_from AS supplier,
                MIN(si.stock_cost) AS min_cost,
                (SELECT s2.stock_cost FROM stock_in_item s2 JOIN stock_in in2 ON s2.stock_id = in2.stock_id WHERE s2.prod_id = '$prod_id' AND s2.source_from = si.source_from ORDER BY in2.date_time DESC, s2.item_id DESC LIMIT 1) AS last_cost,
                MAX(s.date_time) AS last_date,
                COUNT(si.item_id) AS order_count
              FROM stock_in_item si
              JOIN stock_in s ON si.stock_id = s.stock_id
              WHERE si.prod_id = '$prod_id' 
                AND si.source_from IS NOT NULL 
                AND TRIM(si.source_from) != ''
              GROUP BY si.source_from
              ORDER BY min_cost ASC, last_date DESC";

    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $rank = 1;
        while ($row = mysqli_fetch_assoc($result)) {
            $row['rank'] = $rank;
            $row['is_best'] = ($rank === 1);
            $history[] = $row;
            $rank++;
        }
    }

    // 4. ดึงประวัติการรับเข้าย้อนหลังล่าสุด (ทั้งโกดังและหน้าร้าน 5 รายการล่าสุด)
    $recent_orders = array();
    $sql_recent = "(SELECT si.stock_cost, s.date_time, si.amount, si.source_from, 'โกดัง' AS destination
                    FROM stock_in_item si
                    JOIN stock_in s ON si.stock_id = s.stock_id
                    WHERE si.prod_id = '$prod_id')
                   UNION ALL
                   (SELECT ti.tran_cost AS stock_cost, t.date_time, ti.amount, ti.source_from, 'หน้าร้าน' AS destination
                    FROM transfer_in_item ti
                    JOIN transfer_in t ON ti.tran_id = t.tran_id
                    WHERE ti.prod_id = '$prod_id')
                   ORDER BY date_time DESC
                   LIMIT 5";
    $res_recent = mysqli_query($conn, $sql_recent);
    if ($res_recent && mysqli_num_rows($res_recent) > 0) {
        while ($rr = mysqli_fetch_assoc($res_recent)) {
            $recent_orders[] = $rr;
        }
    }

    // 5. ดึงประวัติการโอนย้ายสินค้าระหว่างสาขา (หน้าร้าน ⇄ โกดัง) 5 รายการล่าสุด
    $recent_transfers = array();
    $sql_tf = "SELECT t.tf_id, t.tf_number, t.direction, t.date_time, t.receiver_name, t.receive_time,
                      ti.prod_amount, ti.received_amount,
                      w.ware_name, s.shop_name, u.user_name AS sender_name
               FROM transfer_record_item ti
               JOIN transfer_record t ON ti.tf_id = t.tf_id
               LEFT JOIN warehouse_detail w ON t.branch_id = w.ware_id
               LEFT JOIN shop_detail s ON t.shop_id = s.shop_id
               LEFT JOIN user u ON t.user = u.user_id
               WHERE ti.prod_id = '$prod_id' AND t.status = 'received'
               ORDER BY t.tf_id DESC
               LIMIT 5";
    $res_tf = mysqli_query($conn, $sql_tf);
    if ($res_tf && mysqli_num_rows($res_tf) > 0) {
        while ($rt = mysqli_fetch_assoc($res_tf)) {
            if ($rt['direction'] == 'ware_to_shop') {
                $rt['source_name'] = !empty($rt['ware_name']) ? $rt['ware_name'] : 'โกดัง';
                $rt['dest_name'] = !empty($rt['shop_name']) ? $rt['shop_name'] : 'หน้าร้าน';
            } else {
                $rt['source_name'] = !empty($rt['shop_name']) ? $rt['shop_name'] : 'หน้าร้าน';
                $rt['dest_name'] = !empty($rt['ware_name']) ? $rt['ware_name'] : 'โกดัง';
            }
            $recent_transfers[] = $rt;
        }
    }

    // 6. ดึงข้อมูล SKU ที่เคยผูกกับร้านค้าต่างๆ สำหรับสินค้านี้
    $supplier_skus = array();
    $res_skus = mysqli_query($conn, "SELECT supplier_name, supplier_sku, last_cost, updated_at FROM product_supplier_sku WHERE prod_id = '$prod_id' ORDER BY updated_at DESC");
    if ($res_skus) {
        while ($rsk = mysqli_fetch_assoc($res_skus)) {
            $supplier_skus[] = $rsk;
        }
    }

    // 7. ดึงรายชื่อร้านทั้งหมดที่เคยมีในระบบ (สำหรับทำ Autocomplete)
    $all_suppliers = array();
    $sql_all_sup = "SELECT DISTINCT name FROM (
                        SELECT supplier_name AS name FROM stock_in WHERE supplier_name IS NOT NULL AND TRIM(supplier_name) != ''
                        UNION
                        SELECT supplier_name AS name FROM transfer_in WHERE supplier_name IS NOT NULL AND TRIM(supplier_name) != ''
                        UNION
                        SELECT supplier_name AS name FROM product_supplier_sku WHERE supplier_name IS NOT NULL AND TRIM(supplier_name) != ''
                        UNION
                        SELECT source_from AS name FROM stock_in_item WHERE source_from IS NOT NULL AND TRIM(source_from) != '' 
                        UNION 
                        SELECT source_from AS name FROM transfer_in_item WHERE source_from IS NOT NULL AND TRIM(source_from) != '' 
                    ) AS u_sup ORDER BY name ASC";
    $res_supp = mysqli_query($conn, $sql_all_sup);
    if ($res_supp) {
        while ($rs = mysqli_fetch_assoc($res_supp)) {
            $sname = trim($rs['name']);
            if (!empty($sname) && !in_array($sname, $all_suppliers)) {
                $all_suppliers[] = $sname;
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'prod_id' => $prod_id,
        'prod_name' => $prod_name,
        'prod_code' => $prod_code,
        'current_cost' => $current_cost,
        'history' => $history,
        'supplier_skus' => $supplier_skus,
        'recent_orders' => $recent_orders,
        'recent_transfers' => $recent_transfers,
        'all_suppliers' => $all_suppliers
    ], JSON_UNESCAPED_UNICODE);
    mysqli_close($conn);
?>
