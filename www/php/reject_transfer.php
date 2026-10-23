<?php
include_once "auth_guard.php";
require_login();
include "db.php";
header('Content-Type: application/json; charset=utf-8');

$tf_id = isset($_POST['tf_id']) ? intval($_POST['tf_id']) : 0;
$reason = isset($_POST['reason']) ? mysqli_real_escape_string($conn, trim($_POST['reason'])) : '';
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if ($tf_id <= 0) {
    echo json_encode(["status" => "error", "message" => "ไม่พบรหัสใบโอน"]);
    exit;
}

if (empty($reason)) {
    $reason = "ยอดสินค้าไม่ตรงตามใบโอน หรือสินค้าชำรุด";
}

mysqli_begin_transaction($conn);

try {
    $sqlTf = "SELECT * FROM transfer_record WHERE tf_id = '$tf_id' FOR UPDATE";
    $resTf = mysqli_query($conn, $sqlTf);
    if (!$resTf || mysqli_num_rows($resTf) == 0) {
        throw new Exception("ไม่พบข้อมูลใบโอนย้ายนี้");
    }
    $tf = mysqli_fetch_assoc($resTf);

    if ($tf['status'] != 'pending') {
        throw new Exception("ใบโอนนี้ไม่ได้อยู่ในสถานะรอตรวจรับ (สถานะปัจจุบัน: " . $tf['status'] . ")");
    }

    $direction = $tf['direction'];
    $shop_id = intval($tf['shop_id']);
    $ware_id = intval($tf['branch_id']);

    // Fetch items to return stock to origin
    $sqlItems = "SELECT * FROM transfer_record_item WHERE tf_id = '$tf_id'";
    $resItems = mysqli_query($conn, $sqlItems);
    if (!$resItems || mysqli_num_rows($resItems) == 0) {
        throw new Exception("ไม่พบรายการสินค้าในใบโอน");
    }

    while ($it = mysqli_fetch_assoc($resItems)) {
        $prod_id = mysqli_real_escape_string($conn, $it['prod_id']);
        $amt = intval($it['prod_amount']);

        if ($amt <= 0) continue;

        if ($direction == 'shop_to_ware') {
            // Origin was shop ($shop_id): return stock to shop
            $upS = mysqli_query($conn, "UPDATE shop SET amount = amount + $amt WHERE shop_id = '$shop_id' AND prod_id = '$prod_id'");
            if (!$upS) {
                throw new Exception("ไม่สามารถคืนสต๊อกกลับเข้าหน้าร้านได้: " . mysqli_error($conn));
            }
        } else { // ware_to_shop
            // Origin was warehouse ($ware_id): return stock to warehouse
            $upW = mysqli_query($conn, "UPDATE warehouse SET amount = amount + $amt WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'");
            if (!$upW) {
                throw new Exception("ไม่สามารถคืนสต๊อกกลับเข้าโกดังได้: " . mysqli_error($conn));
            }
        }
    }

    // Update transfer_record status
    $now = date("Y-m-d H:i:s");
    $upTf = mysqli_query($conn, "UPDATE transfer_record 
                                SET status = 'rejected', 
                                    reject_reason = '$reason', 
                                    receive_time = '$now', 
                                    receive_user_id = '$user_id' 
                                WHERE tf_id = '$tf_id'");
    if (!$upTf) {
        throw new Exception("ไม่สามารถอัปเดตสถานะใบโอนได้: " . mysqli_error($conn));
    }

    mysqli_commit($conn);
    echo json_encode(["status" => "success", "message" => "ปฏิเสธการรับสินค้าและคืนสต๊อกเข้าต้นทางเรียบร้อยแล้ว"], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(["status" => "error", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conn);
?>
