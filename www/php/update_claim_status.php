<?php
/**
 * update_claim_status.php
 * ปรับปรุงสถานะและปิดงานเคลมซัพพลายเออร์ (ระยะ A)
 * รองรับ: ส่งเคลมแล้ว, ได้ของคืน (คืนสต๊อกขาย), ได้เงินคืน, เคลมไม่ได้ (ส่งขายเซล / ทุบทิ้ง)
 */
include_once "auth_guard.php";
require_staff_or_admin();

header('Content-Type: application/json; charset=utf-8');
include "db.php";

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?: $_POST;

$claim_id = isset($data['claim_id']) ? intval($data['claim_id']) : 0;
$action   = isset($data['action']) ? trim($data['action']) : ''; // 'mark_sent', 'resolve_received_goods', 'resolve_refund', 'resolve_unresolved'

if ($claim_id <= 0 || empty($action)) {
    echo json_encode(['success' => false, 'error' => 'พารามิเตอร์ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_name = $_SESSION['user_name'] ?? ($data['user_name'] ?? 'พนักงาน');
$user_role = $_SESSION['user_role'] ?? ($data['role'] ?? 'พนักงาน');

// ดึงข้อมูลรายการเคลมปัจจุบัน
$q_clm = mysqli_query($conn, "SELECT * FROM product_claim_supplier WHERE id = '$claim_id' LIMIT 1");
if (!$q_clm || mysqli_num_rows($q_clm) === 0) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบรายการเคลมนี้ในระบบ'], JSON_UNESCAPED_UNICODE);
    exit;
}
$claim = mysqli_fetch_assoc($q_clm);
$pid = intval($claim['prod_id']);
$qty = intval($claim['qty']);
$branch_id = intval($claim['branch_id'] ?: 1);
$branch_type = $claim['branch_type'] ?: 'หน้าร้าน';

mysqli_begin_transaction($conn);

try {
    if ($action === 'mark_sent') {
        // 1. ส่งเคลมร้านแล้ว (รอผล)
        $ref_no = trim($data['supplier_ref_no'] ?? '');
        $notes  = trim($data['notes'] ?? '');

        $stmt = mysqli_prepare($conn, "UPDATE product_claim_supplier 
            SET status = 'ส่งเคลมแล้ว', supplier_ref_no = ?, notes = ?, updated_at = NOW() 
            WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $ref_no, $notes, $claim_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'บันทึกสถานะส่งเคลมร้านแล้วเรียบร้อย'], JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'resolve_received_goods') {
        // 2. ได้ของคืน: กดรับ → ออกจากคลังเคลม + เข้าสต๊อกขายอัตโนมัติ
        $stmt = mysqli_prepare($conn, "UPDATE product_claim_supplier 
            SET status = 'ปิด', result_action = 'ได้ของคืน', closed_by = ?, closed_at = NOW(), updated_at = NOW() 
            WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $user_name, $claim_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // คืนเข้าสต๊อกขายปกติ
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

        mysqli_commit($conn);
        echo json_encode([
            'success' => true, 
            'message' => "ปิดเคลมเรียบร้อย: รับสินค้า $qty ชิ้น กลับเข้าสต๊อกขายอัตโนมัติแล้ว"
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'resolve_refund') {
        // 3. ได้เงิน/ลดหนี้แทน: ปิดเคลมพร้อมยอดเงิน
        $refund_amt = floatval($data['refund_amount'] ?? 0);
        $notes      = trim($data['notes'] ?? '');

        $stmt = mysqli_prepare($conn, "UPDATE product_claim_supplier 
            SET status = 'ปิด', result_action = 'ได้เงินคืน', refund_amount = ?, notes = ?, closed_by = ?, closed_at = NOW(), updated_at = NOW() 
            WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "dssi", $refund_amt, $notes, $user_name, $claim_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        echo json_encode([
            'success' => true, 
            'message' => "ปิดเคลมเรียบร้อย: บันทึกรับเงินคืน/ลดหนี้ ฿" . number_format($refund_amt, 2)
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'resolve_unresolved') {
        // 4. เคลมไม่ได้: ย้ายเป็น "ของเสียหาย" → เลือก ส่งไปขายเซล หรือ ทุบทิ้ง (ต้องอนุมัติโดยแอดมิน/เจ้าของร้าน)
        if ($user_role !== 'เจ้าของร้าน' && $user_role !== 'แอดมิน') {
            throw new Exception("เฉพาะแอดมินและเจ้าของร้านเท่านั้นที่มีสิทธิ์ตัดสินใจจัดการของเสียหาย");
        }

        $unres_action = trim($data['unresolved_action'] ?? 'ขายเซล'); // 'ขายเซล' หรือ 'ทุบทิ้ง'
        $notes        = trim($data['notes'] ?? '');

        if ($unres_action !== 'ขายเซล' && $unres_action !== 'ทุบทิ้ง') {
            throw new Exception("ตัวเลือกจัดการของเสียหายต้องเป็น 'ขายเซล' หรือ 'ทุบทิ้ง' เท่านั้น");
        }

        $stmt = mysqli_prepare($conn, "UPDATE product_claim_supplier 
            SET status = 'ปิด', result_action = 'ของเสียหาย_เคลมไม่ได้', unresolved_action = ?, notes = ?, closed_by = ?, closed_at = NOW(), updated_at = NOW() 
            WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssi", $unres_action, $notes, $user_name, $claim_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        $msg = ($unres_action === 'ขายเซล') 
            ? "ย้ายสินค้าไปยังคิว 'สินค้าเซล' (Phase 4) เรียบร้อยแล้ว" 
            : "บันทึกตัดเป็นของเสียหายทุบทิ้ง (ตัดจำหน่าย) เรียบร้อยแล้ว";

        echo json_encode(['success' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);

    } else {
        throw new Exception("ไม่รู้จักคำสั่ง action: $action");
    }

} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage() . ' on line ' . $e->getLine()], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conn);
?>
