<?php
    include "db.php";
    header("Content-Type: application/json; charset=UTF-8");

    // ตาราง product_status_ext ถูกสร้างไว้แล้วในฐานข้อมูล
    $is_rich = isset($_POST['rich']) || isset($_POST['filter']) || isset($_POST['search']);
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    if ($start < 0) $start = 0;
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
    if ($limit <= 0 || $limit > 100) $limit = 50;

    $filter = isset($_POST['filter']) ? trim($_POST['filter']) : 'all';
    $search = isset($_POST['search']) ? mysqli_real_escape_string($conn, trim($_POST['search'])) : '';

    if ($is_rich) {
        // 1. คำนวณ KPI Stats ความเร็วสูง (<20ms)
        $stats = [
            'total_low' => 0,
            'danger_count' => 0,
            'warning_count' => 0,
            'not_ordered_count' => 0,
            'ordered_count' => 0,
            'preorder_count' => 0
        ];

        $q_st1 = mysqli_query($conn, "SELECT status, COUNT(*) AS c FROM product WHERE status != 'normal' GROUP BY status");
        if ($q_st1) {
            while ($rst = mysqli_fetch_assoc($q_st1)) {
                if ($rst['status'] == 'danger') $stats['danger_count'] = intval($rst['c']);
                if ($rst['status'] == 'warning') $stats['warning_count'] = intval($rst['c']);
            }
        }
        $stats['total_low'] = $stats['danger_count'] + $stats['warning_count'];

        $q_st2 = mysqli_query($conn, "SELECT 
            COUNT(CASE WHEN is_ordered = 1 THEN 1 END) AS ord, 
            COUNT(CASE WHEN is_preorder = 1 THEN 1 END) AS pre 
        FROM product_status_ext");
        if ($q_st2 && $rst2 = mysqli_fetch_assoc($q_st2)) {
            $stats['ordered_count'] = intval($rst2['ord']);
            $stats['preorder_count'] = intval($rst2['pre']);
        }
        $stats['not_ordered_count'] = max(0, $stats['total_low'] - $stats['ordered_count'] - $stats['preorder_count']);

        // 2. Query ดึงรายการสินค้าตาม Filter
        $items = [];
        $prod_ids = [];

        if (!empty($search)) {
            $q_prod = "SELECT prod_id, prod_code, prod_name, prod_cost, min_amount, status 
                       FROM product 
                       WHERE (prod_name LIKE '%$search%' OR prod_code LIKE '%$search%') 
                       ORDER BY prod_id DESC 
                       LIMIT $start, $limit";
        } else if ($filter === 'ordered') {
            $q_prod = "SELECT p.prod_id, p.prod_code, p.prod_name, p.prod_cost, p.min_amount, p.status 
                       FROM product_status_ext ext 
                       JOIN product p ON ext.prod_id = p.prod_id 
                       WHERE ext.is_ordered = 1 
                       ORDER BY p.prod_id DESC 
                       LIMIT $start, $limit";
        } else if ($filter === 'preorder') {
            $q_prod = "SELECT p.prod_id, p.prod_code, p.prod_name, p.prod_cost, p.min_amount, p.status 
                       FROM product_status_ext ext 
                       JOIN product p ON ext.prod_id = p.prod_id 
                       WHERE ext.is_preorder = 1 
                       ORDER BY p.prod_id DESC 
                       LIMIT $start, $limit";
        } else if ($filter === 'danger') {
            $q_prod = "SELECT prod_id, prod_code, prod_name, prod_cost, min_amount, status 
                       FROM product 
                       WHERE status = 'danger' 
                       ORDER BY prod_id DESC 
                       LIMIT $start, $limit";
        } else if ($filter === 'warning') {
            $q_prod = "SELECT prod_id, prod_code, prod_name, prod_cost, min_amount, status 
                       FROM product 
                       WHERE status = 'warning' 
                       ORDER BY prod_id DESC 
                       LIMIT $start, $limit";
        } else if ($filter === 'not_ordered') {
            $q_prod = "SELECT p.prod_id, p.prod_code, p.prod_name, p.prod_cost, p.min_amount, p.status 
                       FROM product p 
                       LEFT JOIN product_status_ext ext ON p.prod_id = ext.prod_id 
                       WHERE p.status != 'normal' 
                         AND (ext.is_ordered = 0 OR ext.is_ordered IS NULL) 
                         AND (ext.is_preorder = 0 OR ext.is_preorder IS NULL) 
                       ORDER BY p.prod_id DESC 
                       LIMIT $start, $limit";
        } else {
            // 'all': สินค้าที่สถานะผิดปกติทั้งหมด
            $q_prod = "SELECT prod_id, prod_code, prod_name, prod_cost, min_amount, status 
                       FROM product 
                       WHERE status != 'normal' 
                       ORDER BY prod_id DESC 
                       LIMIT $start, $limit";
        }

        $res_prod = mysqli_query($conn, $q_prod);
        if ($res_prod && mysqli_num_rows($res_prod) > 0) {
            while ($row = mysqli_fetch_assoc($res_prod)) {
                $pid = $row['prod_id'];
                $prod_ids[] = $pid;
                $items[] = [
                    'prod_id' => $pid,
                    'prod_code' => $row['prod_code'] ? $row['prod_code'] : '-',
                    'prod_name' => $row['prod_name'],
                    'prod_cost' => floatval($row['prod_cost']),
                    'img' => '',
                    'status' => $row['status'],
                    'min_amount' => intval($row['min_amount']),
                    'is_ordered' => 0,
                    'is_preorder' => 0,
                    'warehouse_amount' => 0,
                    'shop_amount' => 0,
                    'total_amount' => 0,
                    'place' => '-',
                    'best_supplier' => null,
                    'best_cost' => null
                ];
            }
        }

        // 3. Batch Queries สำหรับรายการในหน้านี้ (50 รายการ)
        if (count($prod_ids) > 0) {
            $idList = implode("','", $prod_ids);

            // A. สถานะ is_ordered & is_preorder จาก product_status_ext
            $q_ext = mysqli_query($conn, "SELECT prod_id, is_ordered, is_preorder FROM product_status_ext WHERE prod_id IN ('$idList')");
            $ext_map = [];
            if ($q_ext) {
                while ($re = mysqli_fetch_assoc($q_ext)) {
                    $ext_map[$re['prod_id']] = [
                        'is_ordered' => intval($re['is_ordered']),
                        'is_preorder' => intval($re['is_preorder'])
                    ];
                }
            }

            // B. รูปภาพสินค้าเฉพาะ 50 รายการนี้
            $q_img = mysqli_query($conn, "SELECT prod_id, img FROM product WHERE prod_id IN ('$idList')");
            $img_map = [];
            if ($q_img) {
                while ($ri = mysqli_fetch_assoc($q_img)) {
                    if (!empty($ri['img'])) {
                        $img_map[$ri['prod_id']] = $ri['img'];
                    }
                }
            }

            // C. สต๊อกในโกดัง
            $q_w = mysqli_query($conn, "SELECT prod_id, SUM(amount) AS sum_w FROM warehouse WHERE prod_id IN ('$idList') GROUP BY prod_id");
            $ware_map = [];
            if ($q_w) {
                while ($rw = mysqli_fetch_assoc($q_w)) {
                    $ware_map[$rw['prod_id']] = intval($rw['sum_w']);
                }
            }

            // D. สต๊อกหน้าร้าน
            $q_s = mysqli_query($conn, "SELECT prod_id, SUM(amount) AS sum_s FROM shop WHERE prod_id IN ('$idList') GROUP BY prod_id");
            $shop_map = [];
            if ($q_s) {
                while ($rs = mysqli_fetch_assoc($q_s)) {
                    $shop_map[$rs['prod_id']] = intval($rs['sum_s']);
                }
            }

            // E. ตำแหน่งวางในโกดัง (warehouse_place)
            $q_p = mysqli_query($conn, "SELECT prod_id, place FROM warehouse_place WHERE prod_id IN ('$idList')");
            $place_map = [];
            if ($q_p) {
                while ($rp = mysqli_fetch_assoc($q_p)) {
                    if (!isset($place_map[$rp['prod_id']]) && !empty(trim($rp['place']))) {
                        $place_map[$rp['prod_id']] = trim($rp['place']);
                    }
                }
            }

            // F. ร้านที่เคยซื้อได้ถูกที่สุด (Cheapest Supplier)
            $supplier_map = [];
            $q_sup = mysqli_query($conn, "SELECT prod_id, source_from, stock_cost 
                FROM (
                    SELECT prod_id, source_from, stock_cost 
                    FROM stock_in_item 
                    WHERE prod_id IN ('$idList') AND source_from IS NOT NULL AND TRIM(source_from) != '' AND stock_cost > 0
                    UNION ALL
                    SELECT prod_id, source_from, tran_cost AS stock_cost 
                    FROM transfer_in_item 
                    WHERE prod_id IN ('$idList') AND source_from IS NOT NULL AND TRIM(source_from) != '' AND tran_cost > 0
                ) AS combined 
                ORDER BY stock_cost ASC");
            if ($q_sup) {
                while ($rsup = mysqli_fetch_assoc($q_sup)) {
                    $pid = $rsup['prod_id'];
                    if (!isset($supplier_map[$pid])) {
                        $supplier_map[$pid] = [
                            'source_from' => $rsup['source_from'],
                            'cost' => floatval($rsup['stock_cost'])
                        ];
                    }
                }
            }

            // รวมข้อมูลเข้า items
            foreach ($items as &$it) {
                $pid = $it['prod_id'];
                if (isset($ext_map[$pid])) {
                    $it['is_ordered'] = $ext_map[$pid]['is_ordered'];
                    $it['is_preorder'] = $ext_map[$pid]['is_preorder'];
                }
                if (isset($img_map[$pid])) {
                    $it['img'] = $img_map[$pid];
                }
                $w = isset($ware_map[$pid]) ? $ware_map[$pid] : 0;
                $s = isset($shop_map[$pid]) ? $shop_map[$pid] : 0;
                $it['warehouse_amount'] = $w;
                $it['shop_amount'] = $s;
                $it['total_amount'] = $w + $s;
                if (isset($place_map[$pid])) {
                    $it['place'] = $place_map[$pid];
                }
                if (isset($supplier_map[$pid])) {
                    $it['best_supplier'] = $supplier_map[$pid]['source_from'];
                    $it['best_cost'] = $supplier_map[$pid]['cost'];
                }
            }
            unset($it);
        }

        echo json_encode([
            'status' => 'success',
            'stats' => $stats,
            'items' => $items,
            'count' => count($items)
        ], JSON_UNESCAPED_UNICODE);
        mysqli_close($conn);
        exit;
    }

    // --- โค้ดเดิมสำหรับรองรับ Mobile App (bell.html) เร็วด้วย Batch ---
    $query = "SELECT prod_name, prod_id, status FROM product WHERE status != 'normal' ORDER BY status ASC LIMIT $start, 20";
    $result = mysqli_query($conn, $query);
    $output = array();

    if ($result && mysqli_num_rows($result) > 0) {
        $legacy_pids = [];
        $rows = [];
        while ($row = mysqli_fetch_array($result)) {
            $legacy_pids[] = $row['prod_id'];
            $rows[] = $row;
        }

        $idList = implode("','", $legacy_pids);
        $w_map = [];
        $qw = mysqli_query($conn, "SELECT prod_id, SUM(amount) AS sum_w FROM warehouse WHERE prod_id IN ('$idList') GROUP BY prod_id");
        if ($qw) {
            while ($rw = mysqli_fetch_assoc($qw)) {
                $w_map[$rw['prod_id']] = intval($rw['sum_w']);
            }
        }

        $s_map = [];
        $qs = mysqli_query($conn, "SELECT prod_id, SUM(amount) AS sum_s FROM shop WHERE prod_id IN ('$idList') GROUP BY prod_id");
        if ($qs) {
            while ($rs = mysqli_fetch_assoc($qs)) {
                $s_map[$rs['prod_id']] = intval($rs['sum_s']);
            }
        }

        foreach ($rows as $row) {
            $pid = $row['prod_id'];
            $tot = (isset($w_map[$pid]) ? $w_map[$pid] : 0) + (isset($s_map[$pid]) ? $s_map[$pid] : 0);
            array_push($row, $tot);
            $output[] = $row;
        }
    }

    echo json_encode($output, JSON_UNESCAPED_UNICODE);
    mysqli_close($conn);
?>