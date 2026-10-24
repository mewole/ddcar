<?php
/**
 * get_bill_details_for_return.php
 * ค้นหาข้อมูลบิลขายเดิมเพื่อนำมาสร้างเอกสารรับคืนสินค้า (RT26-xxxxx)
 * รองรับทั้งค้นหาด้วยเลขที่บิล (order_number / order_id) และเลขออเดอร์แพลตฟอร์ม (order_sn)
 */
include_once "auth_guard.php";
require_login();

header('Content-Type: application/json; charset=utf-8');
include "db.php";

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : (isset($_POST['keyword']) ? trim($_POST['keyword']) : '');
if (empty($keyword)) {
    echo json_encode(['success' => false, 'error' => 'กรุณาระบุเลขที่บิลหรือเลขอ้างอิงคำสั่งซื้อ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$clean_kw = mysqli_real_escape_string($conn, $keyword);

// 1. ค้นหาใน sale_order ก่อน
$bill_query = "SELECT s.order_id, s.order_number, s.customer_id, s.date_time, s.sum_price, 
                      s.total_discount, s.total_price, s.payment_type, s.branch_id, s.type as branch_type,
                      s.comment,
                      c.cus_name, c.cus_tel, c.cus_addr
               FROM sale_order s
               LEFT JOIN customer c ON s.customer_id = c.cus_id
               WHERE s.order_number = '$clean_kw' OR s.order_id = '$clean_kw'
               LIMIT 1";
$res_bill = mysqli_query($conn, $bill_query);

if ($res_bill && mysqli_num_rows($res_bill) > 0) {
    $bill = mysqli_fetch_assoc($res_bill);
    $order_id = intval($bill['order_id']);

    // ดึงรายการสินค้าในบิลขาย
    $items_query = "SELECT i.item_id, i.prod_id, i.prod_price, i.prod_amount,
                           p.prod_code, p.prod_name, p.img, p.prod_cost
                    FROM sale_order_item i
                    JOIN product p ON i.prod_id = p.prod_id
                    WHERE i.order_id = '$order_id'
                    ORDER BY i.item_id ASC";
    $res_items = mysqli_query($conn, $items_query);
    $items = [];

    while ($item = mysqli_fetch_assoc($res_items)) {
        $pid = intval($item['prod_id']);

        // ตรวจสอบจำนวนที่เคยรับคืนไปแล้วในอดีต (ไม่รวมที่ยกเลิก)
        $q_prev = mysqli_query($conn, "SELECT COALESCE(SUM(ri.qty), 0) as returned_qty 
                                       FROM product_return_item ri
                                       JOIN product_return r ON ri.return_id = r.id
                                       WHERE r.bill_id = '$order_id' 
                                         AND ri.prod_id = '$pid'
                                         AND r.status NOT LIKE 'ยกเลิก%'");
        $prev_returned = 0;
        if ($q_prev && $r_prev = mysqli_fetch_assoc($q_prev)) {
            $prev_returned = intval($r_prev['returned_qty']);
        }

        $qty_sold = intval($item['prod_amount']);
        $qty_available = max(0, $qty_sold - $prev_returned);

        // ค้นหาซัพพลายเออร์ที่นำเข้าล่าสุดของสินค้านี้ (เพื่อแนะนำให้อัตโนมัติกรณีสินค้าแตกหักต้องเคลม)
        $last_supplier = '';
        $q_sup = mysqli_query($conn, "SELECT si.source_from 
                                      FROM stock_in_item si
                                      JOIN stock_in s ON si.stock_id = s.stock_id
                                      WHERE si.prod_id = '$pid' AND si.source_from IS NOT NULL AND si.source_from != ''
                                      ORDER BY s.date_time DESC, si.item_id DESC LIMIT 1");
        if ($q_sup && $r_sup = mysqli_fetch_assoc($q_sup)) {
            $last_supplier = $r_sup['source_from'];
        }

        $items[] = [
            'item_id'             => intval($item['item_id']),
            'prod_id'             => $pid,
            'prod_code'           => $item['prod_code'],
            'prod_name'           => $item['prod_name'],
            'img'                 => $item['img'] ?: 'img/no_img.png',
            'unit_price'          => floatval($item['prod_price']),
            'cost'                => floatval($item['prod_cost']),
            'qty_sold'            => $qty_sold,
            'qty_returned_so_far' => $prev_returned,
            'qty_available'       => $qty_available,
            'suggested_supplier'  => $last_supplier
        ];
    }

    // ตรวจสอบ VAT จากรูปแบบเลขที่บิล หรือหมายเหตุ
    $is_vat = (stripos($bill['order_number'], 'TAX') !== false || stripos($bill['order_number'], 'INV') !== false || stripos($bill['comment'] ?? '', 'vat') !== false);

    echo json_encode([
        'success'      => true,
        'found'        => true,
        'source'       => 'sale_order',
        'bill'         => [
            'bill_id'              => $order_id,
            'bill_number'          => $bill['order_number'],
            'order_sn'             => '',
            'date_time'            => $bill['date_time'],
            'customer_id'          => intval($bill['customer_id']),
            'customer_name'        => $bill['cus_name'] ?: 'ลูกค้าทั่วไป',
            'customer_tel'         => $bill['cus_tel'] ?: '',
            'customer_addr'        => $bill['cus_addr'] ?: '',
            'payment_type'         => $bill['payment_type'],
            'branch_id'            => intval($bill['branch_id']),
            'branch_type'          => $bill['branch_type'] ?: 'หน้าร้าน',
            'sum_price'            => floatval($bill['sum_price']),
            'total_discount'       => floatval($bill['total_discount']),
            'total_price'          => floatval($bill['total_price']),
            'is_vat'               => $is_vat,
            'vat_type'             => $is_vat ? 'vat' : 'no_vat',
            'vat_amount'           => $is_vat ? round(floatval($bill['total_price']) * 7 / 107, 2) : 0,
            'items'                => $items
        ]
    ], JSON_UNESCAPED_UNICODE);
    mysqli_close($conn);
    exit;
}

// 2. ถ้าไม่พบใน sale_order ให้ค้นหาในตารางคำสั่งซื้อแพลตฟอร์ม (platform_orders) ถ้ามีตารางนี้อยู่
$chk_tbl = mysqli_query($conn, "SHOW TABLES LIKE 'platform_orders'");
if ($chk_tbl && mysqli_num_rows($chk_tbl) > 0) {
    $q_plat = mysqli_query($conn, "SELECT order_sn, channel_code, buyer_username, order_date, total_amount 
                                   FROM platform_orders 
                                   WHERE order_sn = '$clean_kw' 
                                   LIMIT 1");

    if ($q_plat && mysqli_num_rows($q_plat) > 0) {
        $plat = mysqli_fetch_assoc($q_plat);
        $sn = $plat['order_sn'];

        $items_query = "SELECT poi.id as item_id, poi.platform_sku, poi.platform_name, poi.qty, poi.price,
                               poi.prod_id, poi.target_sku, p.prod_name, p.img, p.prod_cost
                        FROM platform_order_items poi
                        LEFT JOIN product p ON (poi.prod_id = p.prod_id OR poi.target_sku = p.prod_code)
                        WHERE poi.order_sn = '$sn'";
        $res_items = mysqli_query($conn, $items_query);
        $items = [];

        if ($res_items) {
            while ($item = mysqli_fetch_assoc($res_items)) {
                $pid = intval($item['prod_id'] ?? 0);
                $qty_sold = intval($item['qty'] ?? 1);

                $last_supplier = '';
                if ($pid > 0) {
                    $q_sup = mysqli_query($conn, "SELECT si.source_from 
                                                  FROM stock_in_item si
                                                  JOIN stock_in s ON si.stock_id = s.stock_id
                                                  WHERE si.prod_id = '$pid' AND si.source_from != ''
                                                  ORDER BY s.date_time DESC, si.item_id DESC LIMIT 1");
                    if ($q_sup && $r_sup = mysqli_fetch_assoc($q_sup)) {
                        $last_supplier = $r_sup['source_from'];
                    }
                }

                // Check previous returns for platform orders
                $target_sku_esc = mysqli_real_escape_string($conn, $item['target_sku'] ?: $item['platform_sku']);
                $q_prev_p = mysqli_query($conn, "SELECT COALESCE(SUM(ri.qty), 0) as returned_qty 
                                                FROM product_return_item ri
                                                JOIN product_return r ON ri.return_id = r.id
                                                WHERE r.order_sn = '$sn' 
                                                  AND (ri.prod_id = '$pid' OR ri.prod_code = '$target_sku_esc')
                                                  AND r.status NOT LIKE 'ยกเลิก%'");
                $prev_plat_ret = 0;
                if ($q_prev_p && $r_pp = mysqli_fetch_assoc($q_prev_p)) {
                    $prev_plat_ret = intval($r_pp['returned_qty']);
                }
                $qty_avail_plat = max(0, $qty_sold - $prev_plat_ret);

                $items[] = [
                    'item_id'             => intval($item['item_id']),
                    'prod_id'             => $pid,
                    'prod_code'           => $item['target_sku'] ?: ($item['platform_sku'] ?: 'UNKNOWN'),
                    'prod_name'           => $item['prod_name'] ?: ($item['platform_name'] ?: 'สินค้าแพลตฟอร์ม'),
                    'img'                 => $item['img'] ?: 'img/no_img.png',
                    'unit_price'          => floatval($item['price']),
                    'cost'                => floatval($item['prod_cost'] ?? 0),
                    'qty_sold'            => $qty_sold,
                    'qty_returned_so_far' => $prev_plat_ret,
                    'qty_available'       => $qty_avail_plat,
                    'suggested_supplier'  => $last_supplier
                ];
            }
        }

        echo json_encode([
            'success'      => true,
            'found'        => true,
            'source'       => 'platform_order',
            'bill'         => [
                'bill_id'              => null,
                'bill_number'          => '',
                'order_sn'             => $sn,
                'date_time'            => $plat['order_date'],
                'customer_id'          => 0,
                'customer_name'        => $plat['buyer_username'] ?: ('ลูกค้า ' . $plat['channel_code']),
                'customer_tel'         => '',
                'customer_addr'        => '',
                'payment_type'         => 'แพลตฟอร์ม (' . $plat['channel_code'] . ')',
                'branch_id'            => 1,
                'branch_type'          => 'หน้าร้าน',
                'sum_price'            => floatval($plat['total_amount']),
            'total_discount'       => 0,
            'total_price'          => floatval($plat['total_amount']),
            'is_vat'               => false,
            'vat_type'             => 'no_vat',
            'vat_amount'           => 0,
            'items'                => $items
        ]
    ], JSON_UNESCAPED_UNICODE);
    mysqli_close($conn);
    exit;
    }
}

// 3. ไม่พบบิล
mysqli_close($conn);
echo json_encode([
    'success' => true,
    'found'   => false,
    'message' => 'ไม่พบข้อมูลบิลขายหรือคำสั่งซื้อตามที่ค้นหา'
], JSON_UNESCAPED_UNICODE);
?>
