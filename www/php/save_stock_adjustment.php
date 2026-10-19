<?php
/**
 * save_stock_adjustment.php
 * Direct stock deduction/adjustment (Write-off / Damaged / Count Correction)
 * Enforces mandatory reason and logs full audit trail.
 */

header("Content-Type: application/json; charset=UTF-8");
include "db.php";

$prod_id    = isset($_POST['prod_id']) ? intval($_POST['prod_id']) : 0;
$place_type = isset($_POST['place_type']) ? trim($_POST['place_type']) : '';
$place_id   = isset($_POST['place_id']) ? intval($_POST['place_id']) : 0;
$place_name = isset($_POST['place_name']) ? mysqli_real_escape_string($conn, trim($_POST['place_name'])) : '';
$adjust_qty = isset($_POST['adjust_qty']) ? intval($_POST['adjust_qty']) : 0;
$reason     = isset($_POST['reason']) ? trim($_POST['reason']) : '';
$user_id    = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$user_name  = isset($_POST['user_name']) ? mysqli_real_escape_string($conn, trim($_POST['user_name'])) : 'พนักงาน';

// Validation 1: Product ID
if ($prod_id <= 0) {
    echo json_encode(array("status" => "error", "message" => "ไม่พบรหัสสินค้าที่ต้องการปรับ"));
    exit;
}

// Validation 2: Place Type
if ($place_type !== 'shop' && $place_type !== 'ware') {
    echo json_encode(array("status" => "error", "message" => "กรุณาระบุสถานที่ตัดสต๊อก (หน้าร้าน หรือ โกดัง)"));
    exit;
}

// Validation 3: Deduct Qty must be > 0
if ($adjust_qty <= 0) {
    echo json_encode(array("status" => "error", "message" => "จำนวนที่ต้องการตัดออกต้องมากกว่า 0"));
    exit;
}

// Validation 4: Mandatory Reason (บังคับระบุเหตุผล)
if (empty($reason)) {
    echo json_encode(array("status" => "error", "message" => "กรุณาระบุเหตุผลในการตัดสต๊อก (บังคับ)"));
    exit;
}
$reason_clean = mysqli_real_escape_string($conn, $reason);

mysqli_begin_transaction($conn);

try {
    $before_qty = 0;

    // A. Check & Lock Current Stock
    if ($place_type === 'shop') {
        $q = mysqli_query($conn, "SELECT amount FROM shop WHERE shop_id = '$place_id' AND prod_id = '$prod_id' FOR UPDATE");
        if (!$q || mysqli_num_rows($q) === 0) {
            mysqli_rollback($conn);
            echo json_encode(array("status" => "error", "message" => "ไม่พบข้อมูลสินค้าในหน้าร้านที่เลือก"));
            exit;
        }
        $r = mysqli_fetch_assoc($q);
        $before_qty = intval($r['amount']);
        if ($adjust_qty > $before_qty) {
            mysqli_rollback($conn);
            echo json_encode(array("status" => "error", "message" => "จำนวนที่ต้องการตัด ($adjust_qty) มากกว่าสต๊อกคงเหลือจริง ($before_qty)"));
            exit;
        }
        $after_qty = $before_qty - $adjust_qty;

        $up = mysqli_query($conn, "UPDATE shop SET amount = '$after_qty' WHERE shop_id = '$place_id' AND prod_id = '$prod_id'");
        if (!$up) {
            throw new Exception("ไม่สามารถอัปเดตสต๊อกหน้าร้านได้");
        }
    } else {
        $q = mysqli_query($conn, "SELECT amount FROM warehouse WHERE ware_id = '$place_id' AND prod_id = '$prod_id' FOR UPDATE");
        if (!$q || mysqli_num_rows($q) === 0) {
            mysqli_rollback($conn);
            echo json_encode(array("status" => "error", "message" => "ไม่พบข้อมูลสินค้าในโกดังที่เลือก"));
            exit;
        }
        $r = mysqli_fetch_assoc($q);
        $before_qty = intval($r['amount']);
        if ($adjust_qty > $before_qty) {
            mysqli_rollback($conn);
            echo json_encode(array("status" => "error", "message" => "จำนวนที่ต้องการตัด ($adjust_qty) มากกว่าสต๊อกคงเหลือจริง ($before_qty)"));
            exit;
        }
        $after_qty = $before_qty - $adjust_qty;

        $up = mysqli_query($conn, "UPDATE warehouse SET amount = '$after_qty' WHERE ware_id = '$place_id' AND prod_id = '$prod_id'");
        if (!$up) {
            throw new Exception("ไม่สามารถอัปเดตสต๊อกโกดังได้");
        }
    }

    // B. Generate Document Number: ADJ{YY}-{Sequence 5 หลัก}
    $year_cur = date("y");
    $prefix = "ADJ" . $year_cur . "-";
    $seq_q = mysqli_query($conn, "SELECT adj_number FROM stock_adjustment WHERE adj_number LIKE '$prefix%' ORDER BY adj_id DESC LIMIT 1");
    $next_seq = 1;
    if ($seq_q && mysqli_num_rows($seq_q) > 0) {
        $row_seq = mysqli_fetch_assoc($seq_q);
        $parts = explode('-', $row_seq['adj_number']);
        if (count($parts) >= 2) {
            $next_seq = intval($parts[1]) + 1;
        }
    }
    $adj_number = $prefix . str_pad($next_seq, 5, "0", STR_PAD_LEFT);

    // C. Insert Audit Record
    $sql_ins = "INSERT INTO stock_adjustment (
        adj_number, prod_id, place_type, place_id, place_name, 
        before_qty, adjust_qty, after_qty, reason, user_id, user_name, date_time
    ) VALUES (
        '$adj_number', '$prod_id', '$place_type', '$place_id', '$place_name',
        '$before_qty', '$adjust_qty', '$after_qty', '$reason_clean', '$user_id', '$user_name', NOW()
    )";
    $ins_q = mysqli_query($conn, $sql_ins);
    if (!$ins_q) {
        throw new Exception("ไม่สามารถบันทึกประวัติการปรับสต๊อกได้: " . mysqli_error($conn));
    }
    $adj_id = mysqli_insert_id($conn);

    // D. Update Alert Status in product table if total drops below thresholds
    $total_stock = 0;
    $q_tot_w = mysqli_query($conn, "SELECT SUM(amount) AS sum_w FROM warehouse WHERE prod_id = '$prod_id'");
    if ($q_tot_w && $r_w = mysqli_fetch_assoc($q_tot_w)) {
        $total_stock += intval($r_w['sum_w']);
    }
    $q_tot_s = mysqli_query($conn, "SELECT SUM(amount) AS sum_s FROM shop WHERE prod_id = '$prod_id'");
    if ($q_tot_s && $r_s = mysqli_fetch_assoc($q_tot_s)) {
        $total_stock += intval($r_s['sum_s']);
    }

    $q_prod = mysqli_query($conn, "SELECT min_amount FROM product WHERE prod_id = '$prod_id'");
    if ($q_prod && $r_p = mysqli_fetch_assoc($q_prod)) {
        $min_amt = intval($r_p['min_amount']);
        $new_status = 'normal';
        if ($total_stock <= 10) {
            $new_status = 'danger';
        } else if ($total_stock <= $min_amt) {
            $new_status = 'warning';
        }
        mysqli_query($conn, "UPDATE product SET status = '$new_status' WHERE prod_id = '$prod_id'");
        if ($new_status !== 'normal') {
            @mysqli_query($conn, "UPDATE bell SET status = 'alert'");
        }
    }

    mysqli_commit($conn);

    echo json_encode(array(
        "status" => "success",
        "adj_id" => $adj_id,
        "adj_number" => $adj_number,
        "before_qty" => $before_qty,
        "adjust_qty" => $adjust_qty,
        "after_qty" => $after_qty,
        "place_name" => $place_name,
        "message" => "ตัดสต๊อกเรียบร้อยแล้ว เลขที่เอกสาร: $adj_number"
    ), JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(array("status" => "error", "message" => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}

mysqli_close($conn);
?>
