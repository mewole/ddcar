<?php
/**
 * approve_product_return.php
 * อนุมัติ หรือ ไม่อนุมัติ คำขอรับคืนสินค้า (สำหรับแอดมินหรือเจ้าของร้านเท่านั้น)
 * - action = 'approve': ปรับสถานะเป็น 'สมบูรณ์', เพิ่มสต๊อกสินค้า (ถ้า destination = 'stock'), สร้างรายการเคลม (ถ้า destination = 'quarantine')
 * - action = 'reject': ปรับสถานะเป็น 'ยกเลิก_ไม่อนุมัติ', ไม่เพิ่มสต๊อก คืนสิทธิ์การทำรายการให้บิลเดิม
 */
include_once "auth_guard.php";
require_admin_or_owner();

header('Content-Type: application/json; charset=utf-8');
include "db.php";

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?: $_POST;

$return_id = !empty($data['return_id']) ? intval($data['return_id']) : 0;
$action    = trim($data['action'] ?? ''); // 'approve' or 'reject'
$admin_note = trim($data['note'] ?? '');

if ($return_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ไม่ระบุรหัสเอกสารรับคืน (return_id)'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'error' => 'รูปแบบคำสั่งไม่ถูกต้อง (action ต้องเป็น approve หรือ reject)'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'ผู้ดูแลระบบ';

mysqli_begin_transaction($conn);

try {
    // 1. ตรวจสอบเอกสารรับคืน
    $q_ret = mysqli_query($conn, "SELECT * FROM product_return WHERE id = '$return_id' FOR UPDATE");
    if (!$q_ret || mysqli_num_rows($q_ret) === 0) {
        throw new Exception("ไม่พบเอกสารรับคืนรหัส $return_id");
    }
    $ret = mysqli_fetch_assoc($q_ret);

    if ($ret['status'] !== 'รออนุมัติ') {
        throw new Exception("เอกสารนี้ไม่อยู่ในสถานะ 'รออนุมัติ' (สถานะปัจจุบัน: {$ret['status']})");
    }

    $ret_code = $ret['return_code'];

    if ($action === 'reject') {
        // กรณีปฏิเสธ/ไม่อนุมัติ: เปลี่ยนสถานะเป็น 'ยกเลิก_ไม่อนุมัติ' ไม่แตะต้องสต๊อก
        $new_reason = $ret['reason'];
        if (!empty($admin_note)) {
            $new_reason .= " [ไม่อนุมัติ: $admin_note โดย $user_name]";
        }
        $stmt_upd = mysqli_prepare($conn, "UPDATE product_return SET status = 'ยกเลิก_ไม่อนุมัติ', approved_by = ?, reason = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_upd, "ssi", $user_name, $new_reason, $return_id);
        if (!mysqli_stmt_execute($stmt_upd)) {
            throw new Exception("ปรับปรุงสถานะไม่สำเร็จ: " . mysqli_stmt_error($stmt_upd));
        }
        mysqli_stmt_close($stmt_upd);

        mysqli_commit($conn);
        echo json_encode([
            'success' => true,
            'action'  => 'reject',
            'status'  => 'ยกเลิก_ไม่อนุมัติ',
            'message' => "ปฏิเสธคำขอรับคืน $ret_code เรียบร้อยแล้ว (ยกเลิกรายการ ไม่เพิ่มสต๊อก)"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // กรณีอนุมัติ (action === 'approve'):
    // 2. ดึงรายการสินค้าทั้งหมดของเอกสารนี้
    $q_items = mysqli_query($conn, "SELECT * FROM product_return_item WHERE return_id = '$return_id' FOR UPDATE");
    $items = [];
    while ($r_it = mysqli_fetch_assoc($q_items)) {
        $items[] = $r_it;
    }

    $claim_created_count = 0;
    $claim_prefix = 'CLM' . date('y') . '-';

    foreach ($items as $item) {
        $pid         = intval($item['prod_id']);
        $pcode       = trim($item['prod_code']);
        $pname       = trim($item['prod_name']);
        $qty         = intval($item['qty']);
        $destination = trim($item['destination']);
        $branch_type = trim($item['branch_type']);
        $branch_id   = intval($item['branch_id']);
        $item_reason = trim($item['damage_reason']);
        $images_json = $item['damage_images'];

        if ($qty <= 0) continue;

        if ($branch_type === 'หน้าร้าน' && ($branch_id === 1 || $branch_id === 0)) {
            $branch_id = 6;
        }

        if ($destination === 'stock') {
            // คืนเข้าสต๊อกขาย
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
        } elseif ($destination === 'quarantine') {
            // เข้าคลังรอเคลม และสร้างใบค้างเคลมซัพพลายเออร์
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

            $stmt_clm = mysqli_prepare($conn, "INSERT INTO product_claim_supplier
                (claim_code, return_id, return_code, prod_id, prod_code, prod_name, qty, supplier_name, claim_date, status, result_action, damage_images, notes, branch_id, branch_type, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'ไม่ระบุซัพพลายเออร์', ?, 'รอส่งเคลม', 'รอผล', ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt_clm, "sisississssiss",
                $claim_code, $return_id, $ret_code, $pid, $pcode, $pname, $qty, $ret['actual_return_date'], $images_json, $item_reason, $branch_id, $branch_type, $user_name
            );
            if (!mysqli_stmt_execute($stmt_clm)) {
                throw new Exception("บันทึกรายการเคลมซัพพลายเออร์ไม่สำเร็จ: " . mysqli_stmt_error($stmt_clm));
            }
            mysqli_stmt_close($stmt_clm);
            $claim_created_count++;
        }
    }

    // 3. ปรับสถานะเอกสารเป็น 'สมบูรณ์'
    $stmt_upd = mysqli_prepare($conn, "UPDATE product_return SET status = 'สมบูรณ์', approved_by = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_upd, "si", $user_name, $return_id);
    if (!mysqli_stmt_execute($stmt_upd)) {
        throw new Exception("ปรับปรุงสถานะไม่สำเร็จ: " . mysqli_stmt_error($stmt_upd));
    }
    mysqli_stmt_close($stmt_upd);

    mysqli_commit($conn);

    echo json_encode([
        'success'     => true,
        'action'      => 'approve',
        'status'      => 'สมบูรณ์',
        'claim_count' => $claim_created_count,
        'message'     => "อนุมัติเอกสารรับคืนสินค้า $ret_code สำเร็จเรียบร้อยแล้ว เพิ่มสต๊อกขายเข้าสู่ระบบเรียบร้อย"
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
