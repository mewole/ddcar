<?php
/**
 * save_product_return.php
 * บันทึกเอกสารรับคืนสินค้า (RT26-xxxxx) และระบบเคลมร้านค้า (ระยะ A)
 * เป็นเอกสารใหม่แยกต่างหาก ไม่แก้ไขบิลเดิม 100% ตามข้อกำหนดเจ้าของร้าน
 */
include_once "auth_guard.php";
require_staff_or_admin();

header('Content-Type: application/json; charset=utf-8');
include "db.php";

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

if (!$data) {
    $data = $_POST;
    if (isset($data['items']) && is_string($data['items'])) {
        $data['items'] = json_decode($data['items'], true);
    }
}

if (empty($data['items']) || !is_array($data['items'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีรายการสินค้าที่รับคืน'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'พนักงาน';
$user_role = $_SESSION['user_role'] ?? 'พนักงาน';
$is_admin_user = in_array($user_role, ['เจ้าของร้าน', 'แอดมิน', 'ผู้ดูแลระบบ']);

$bill_id            = !empty($data['bill_id']) ? intval($data['bill_id']) : null;
$bill_number        = trim($data['bill_number'] ?? '');
$order_sn           = trim($data['order_sn'] ?? '');
$customer_id        = !empty($data['customer_id']) ? intval($data['customer_id']) : null;
$customer_name      = trim($data['customer_name'] ?? 'ลูกค้าทั่วไป');
$actual_return_date = trim($data['actual_return_date'] ?? date('Y-m-d'));
$return_date        = date('Y-m-d');
$refund_type        = trim($data['refund_type'] ?? 'คืนเงินสด');
$total_refund       = floatval($data['total_refund_amount'] ?? 0);
$reason             = trim($data['reason'] ?? 'ลูกค้านำสินค้ามาคืน');
$platform_refund_id = trim($data['platform_refund_id'] ?? '');
if (!empty($platform_refund_id) && strpos($reason, $platform_refund_id) === false) {
    $reason .= " [Ref แพลตฟอร์ม: " . $platform_refund_id . "]";
}
$is_vat             = !empty($data['is_vat']) ? 1 : 0;
$tax_id             = trim($data['tax_id'] ?? '');
$tax_address        = trim($data['tax_address'] ?? '');
$orig_bill_date     = trim($data['original_bill_date'] ?? '');
$orig_bill_amt      = floatval($data['original_bill_amount'] ?? 0);
$correct_amt        = floatval($data['correct_amount'] ?? 0);
$diff_amt           = floatval($data['diff_amount'] ?? 0);
$diff_vat           = floatval($data['diff_vat'] ?? 0);

// กฎเหล็ก Task 5: การคืนเงินผ่านแพลตฟอร์ม ต้องมี Order SN หรือ Platform Refund ID
if ($refund_type === 'คืนเงินผ่านแพลตฟอร์ม') {
    if (empty($order_sn) && empty($platform_refund_id)) {
        echo json_encode([
            'success' => false,
            'error'   => 'การคืนเงินผ่านแพลตฟอร์ม จำเป็นต้องระบุเลขคำสั่งซื้อ (Order SN) หรือรหัสคำขอคืนเงินของแพลตฟอร์ม (Platform Refund ID)'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// กฎเหล็ก Phase 1: ถ้าไม่มีเลขอ้างอิงบิลเดิม บันทึกสต๊อกได้อย่างเดียว แต่ห้ามคืนเงินและห้ามออกใบลดหนี้
if (empty($bill_id) && empty($bill_number) && empty($order_sn)) {
    if ($total_refund > 0 && $refund_type !== 'ไม่คืนเงิน') {
        echo json_encode([
            'success' => false,
            'error'   => 'ไม่มีเลขอ้างอิงบิลเดิม: สามารถบันทึกรับคืนเข้าสต๊อกได้เท่านั้น แต่ไม่สามารถทำรายการคืนเงินหรือออกใบลดหนี้ได้'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $is_vat = 0;
}

mysqli_begin_transaction($conn);

try {
    $cur_year_prefix = 'RT' . date('y') . '-';
    
    // 1. สร้างเลขที่เอกสารรับคืนสินค้า RT26-xxxxx แบบเรียงต่อเนื่อง ห้ามซ้ำ
    $q_seq = mysqli_query($conn, "SELECT return_code FROM product_return 
                                  WHERE return_code LIKE '$cur_year_prefix%' 
                                  ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $next_seq = 1;
    if ($q_seq && $r_seq = mysqli_fetch_assoc($q_seq)) {
        $last_code = $r_seq['return_code'];
        $parts = explode('-', $last_code);
        if (isset($parts[1])) {
            $next_seq = intval($parts[1]) + 1;
        }
    }
    $return_code = sprintf("%s%05d", $cur_year_prefix, $next_seq);

    // 2. ถ้าเป็นบิล VAT สร้างเลขที่ใบลดหนี้ CN26-xxxxx แบบเรียงต่อเนื่อง
    $cn_number = null;
    if ($is_vat) {
        $cn_prefix = 'CN' . date('y') . '-';
        $q_cn = mysqli_query($conn, "SELECT credit_note_number FROM product_return 
                                    WHERE credit_note_number LIKE '$cn_prefix%' 
                                    ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $next_cn = 1;
        if ($q_cn && $r_cn = mysqli_fetch_assoc($q_cn)) {
            $last_cn = $r_cn['credit_note_number'];
            $cn_parts = explode('-', $last_cn);
            if (isset($cn_parts[1])) {
                $next_cn = intval($cn_parts[1]) + 1;
            }
        }
        $cn_number = sprintf("%s%05d", $cn_prefix, $next_cn);
    }

    // วิเคราะห์ประเภทการรับคืน (ขายได้ / แตกหัก / ผสม)
    $has_stock = false;
    $has_quarantine = false;
    foreach ($data['items'] as $it) {
        if (($it['destination'] ?? 'stock') === 'quarantine') {
            $has_quarantine = true;
        } else {
            $has_stock = true;
        }
    }
    $return_type_category = ($has_stock && $has_quarantine) ? 'ผสม' : ($has_quarantine ? 'แตกหัก_เสีย' : 'ขายได้');

    $return_status = $is_admin_user ? 'สมบูรณ์' : 'รออนุมัติ';
    $approved_by_val = $is_admin_user ? $user_name : null;

    // 3. บันทึกหัวเอกสาร product_return
    $stmt_ret = mysqli_prepare($conn, "INSERT INTO product_return 
        (return_code, bill_id, bill_number, order_sn, customer_id, customer_name, return_date, 
         actual_return_date, return_type, refund_type, total_refund_amount, is_vat, tax_id, 
         tax_address, original_bill_date, original_bill_amount, correct_amount, diff_amount, 
         diff_vat, credit_note_number, reason, created_by, approved_by, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    mysqli_stmt_bind_param($stmt_ret, "sissssssssdssssddddsssss",
        $return_code, $bill_id, $bill_number, $order_sn, $customer_id, $customer_name, $return_date,
        $actual_return_date, $return_type_category, $refund_type, $total_refund, $is_vat, $tax_id,
        $tax_address, $orig_bill_date, $orig_bill_amt, $correct_amt, $diff_amt,
        $diff_vat, $cn_number, $reason, $user_name, $approved_by_val, $return_status
    );

    if (!mysqli_stmt_execute($stmt_ret)) {
        throw new Exception("บันทึกหัวเอกสารรับคืนไม่สำเร็จ: " . mysqli_stmt_error($stmt_ret));
    }
    $return_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt_ret);

    $claim_created_count = 0;
    $claim_prefix = 'CLM' . date('y') . '-';

    // 4. บันทึกรายการสินค้าแต่ละตัว
    foreach ($data['items'] as $item) {
        $pid         = intval($item['prod_id']);
        $pcode       = trim($item['prod_code'] ?? '');
        $pname       = trim($item['prod_name'] ?? '');
        $qty         = intval($item['qty'] ?? 0);

        // N5 Validation: ตรวจสอบจำนวนที่ขอคืน ต้องไม่เกินจำนวนที่สามารถคืนได้จริง
        if (!empty($bill_id) && $bill_id > 0 && $pid > 0) {
            $q_orig = mysqli_query($conn, "SELECT prod_amount FROM sale_order_item WHERE order_id = '$bill_id' AND prod_id = '$pid' LIMIT 1");
            if ($q_orig && $r_orig = mysqli_fetch_assoc($q_orig)) {
                $orig_qty = intval($r_orig['prod_amount']);
                $q_done = mysqli_query($conn, "SELECT COALESCE(SUM(ri.qty), 0) as done_qty 
                                               FROM product_return_item ri
                                               JOIN product_return r ON ri.return_id = r.id
                                               WHERE r.bill_id = '$bill_id' AND ri.prod_id = '$pid' AND r.status NOT LIKE 'ยกเลิก%'");
                $already_returned = 0;
                if ($q_done && $r_done = mysqli_fetch_assoc($q_done)) {
                    $already_returned = intval($r_done['done_qty']);
                }
                $allowed_qty = max(0, $orig_qty - $already_returned);
                if ($qty > $allowed_qty) {
                    throw new Exception("สินค้า {$pcode} ({$pname}) ขอคืน {$qty} ชิ้น เกินกว่าจำนวนที่คืนได้ (สามารถคืนได้อีก {$allowed_qty} จากทั้งหมด {$orig_qty} ชิ้น)");
                }
            }
        }
        $price       = floatval($item['unit_price'] ?? 0);
        $cost        = floatval($item['cost'] ?? 0);
        $destination = trim($item['destination'] ?? 'stock'); // 'stock' = คืนเข้าสต๊อกขาย, 'quarantine' = เข้าคลังรอเคลม
        $branch_type = trim($item['branch_type'] ?? 'หน้าร้าน');
        $branch_id   = intval($item['branch_id'] ?? 1);
        $item_reason = trim($item['damage_reason'] ?? $reason);
        $images_json = is_array($item['damage_images'] ?? null) ? json_encode($item['damage_images'], JSON_UNESCAPED_UNICODE) : null;
        $sup_name    = trim($item['supplier_name'] ?? '');

        if ($qty <= 0) continue;

        // 4.1 บันทึก product_return_item
        $stmt_item = mysqli_prepare($conn, "INSERT INTO product_return_item
            (return_id, return_code, prod_id, prod_code, prod_name, qty, unit_price, cost, destination, branch_type, branch_id, damage_reason, damage_images, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt_item, "isissiddssiss",
            $return_id, $return_code, $pid, $pcode, $pname, $qty, $price, $cost, $destination, $branch_type, $branch_id, $item_reason, $images_json
        );
        if (!mysqli_stmt_execute($stmt_item)) {
            throw new Exception("บันทึกรายการสินค้าคืนไม่สำเร็จ: " . mysqli_stmt_error($stmt_item));
        }
        mysqli_stmt_close($stmt_item);

        // 4.2 จัดการสต๊อกตามการตัดสินใจ 2 ทาง
        if ($branch_type === 'หน้าร้าน' && ($branch_id === 1 || $branch_id === 0)) {
            $branch_id = 6; // Shop ID for หน้าร้าน is 6 in shop_detail
        }

        if ($destination === 'stock') {
            // ทางที่ 1: ขายได้ -> ถ้าเป็นแอดมิน คืนเข้าสต๊อกขายปกติทันที (ถ้าเป็นพนักงาน กักสต๊อกไว้จนกว่าแอดมินจะอนุมัติ)
            if ($is_admin_user) {
                if ($branch_type === 'โกดัง') {
                    $q_stk = mysqli_query($conn, "SELECT amount FROM warehouse WHERE ware_id = '$branch_id' AND prod_id = '$pid' LIMIT 1");
                    if ($q_stk && mysqli_num_rows($q_stk) > 0) {
                        mysqli_query($conn, "UPDATE warehouse SET amount = amount + $qty WHERE ware_id = '$branch_id' AND prod_id = '$pid'");
                    } else {
                        mysqli_query($conn, "INSERT INTO warehouse (ware_id, prod_id, amount) VALUES ('$branch_id', '$pid', '$qty')");
                    }
                } else {
                    $q_stk = mysqli_query($conn, "SELECT amount FROM shop WHERE shop_id = '$branch_id' AND prod_id = '$pid' LIMIT 1");
                    if ($q_stk && mysqli_num_rows($q_stk) > 0) {
                        mysqli_query($conn, "UPDATE shop SET amount = amount + $qty WHERE shop_id = '$branch_id' AND prod_id = '$pid'");
                    } else {
                        mysqli_query($conn, "INSERT INTO shop (shop_id, prod_id, amount) VALUES ('$branch_id', '$pid', '$qty')");
                    }
                }
            }

        } elseif ($destination === 'quarantine') {
            // ทางที่ 2: แตกหัก/เสีย -> ถ้าเป็นแอดมิน เข้าคลังรอเคลมทันที (ถ้าเป็นพนักงาน รอแอดมินอนุมัติ)
            if ($is_admin_user) {
                $q_cseq = mysqli_query($conn, "SELECT claim_code FROM product_claim_supplier 
                                               WHERE claim_code LIKE '$claim_prefix%' 
                                               ORDER BY id DESC LIMIT 1 FOR UPDATE");
                $next_cseq = 1;
                if ($q_cseq && $r_cseq = mysqli_fetch_assoc($q_cseq)) {
                    $last_c = $r_cseq['claim_code'];
                    $cparts = explode('-', $last_c);
                    if (isset($cparts[1])) {
                        $next_cseq = intval($cparts[1]) + 1;
                    }
                }
                $claim_code = sprintf("%s%05d", $claim_prefix, $next_cseq + $claim_created_count);

                if (empty($sup_name)) {
                    $sup_name = 'ไม่ระบุซัพพลายเออร์';
                }

                $stmt_clm = mysqli_prepare($conn, "INSERT INTO product_claim_supplier
                    (claim_code, return_id, return_code, prod_id, prod_code, prod_name, qty, supplier_name, claim_date, status, result_action, damage_images, notes, branch_id, branch_type, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'รอส่งเคลม', 'รอผล', ?, ?, ?, ?, ?, NOW())");
                mysqli_stmt_bind_param($stmt_clm, "sisississssiss",
                    $claim_code, $return_id, $return_code, $pid, $pcode, $pname, $qty, $sup_name, $actual_return_date, $images_json, $item_reason, $branch_id, $branch_type, $user_name
                );
                if (!mysqli_stmt_execute($stmt_clm)) {
                    throw new Exception("บันทึกรายการเคลมซัพพลายเออร์ไม่สำเร็จ: " . mysqli_stmt_error($stmt_clm));
                }
                mysqli_stmt_close($stmt_clm);
                $claim_created_count++;
            }
        }
    }

    mysqli_commit($conn);

    $msg = $is_admin_user 
        ? "บันทึกเอกสารรับคืนสินค้า $return_code สำเร็จเรียบร้อย" . ($claim_created_count > 0 ? " (ส่งเข้าคลังรอเคลม $claim_created_count รายการ)" : "")
        : "บันทึกคำขอรับคืนสินค้า $return_code เรียบร้อยแล้ว (สถานะ: รออนุมัติจากผู้ดูแลระบบ)";

    echo json_encode([
        'success'            => true,
        'return_code'        => $return_code,
        'status'             => $return_status,
        'credit_note_number' => $cn_number,
        'claim_count'        => $claim_created_count,
        'message'            => $msg
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conn);
?>
