<?php
include_once "auth_guard.php";
require_staff_or_admin();

include "db.php";
header('Content-Type: application/json; charset=utf-8');

$tf_id = isset($_POST['tf_id']) ? intval($_POST['tf_id']) : 0;
$receiver_name = isset($_POST['receiver_name']) ? mysqli_real_escape_string($conn, trim($_POST['receiver_name'])) : '';
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if ($tf_id <= 0) {
    echo json_encode(["status" => "error", "message" => "ไม่พบรหัสใบโอน"]);
    exit;
}

if (empty($receiver_name)) {
    echo json_encode(["status" => "error", "message" => "กรุณาระบุชื่อผู้ตรวจรับสินค้า"]);
    exit;
}

try {
    mysqli_begin_transaction($conn);

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

    // Fetch items
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
            // Destination is warehouse ($ware_id)
            $checkW = mysqli_query($conn, "SELECT amount FROM warehouse WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'");
            if ($checkW && mysqli_num_rows($checkW) > 0) {
                $upW = mysqli_query($conn, "UPDATE warehouse SET amount = amount + $amt WHERE ware_id = '$ware_id' AND prod_id = '$prod_id'");
            } else {
                $upW = mysqli_query($conn, "INSERT INTO warehouse (amount, ware_id, prod_id) VALUES ('$amt', '$ware_id', '$prod_id')");
            }
            if (!$upW) {
                throw new Exception("ไม่สามารถเพิ่มสต๊อกเข้าโกดังได้: " . mysqli_error($conn));
            }
        } else { // ware_to_shop
            // Destination is shop ($shop_id)
            $checkS = mysqli_query($conn, "SELECT amount FROM shop WHERE shop_id = '$shop_id' AND prod_id = '$prod_id'");
            if ($checkS && mysqli_num_rows($checkS) > 0) {
                $upS = mysqli_query($conn, "UPDATE shop SET amount = amount + $amt WHERE shop_id = '$shop_id' AND prod_id = '$prod_id'");
            } else {
                $upS = mysqli_query($conn, "INSERT INTO shop (shop_id, prod_id, amount) VALUES ('$shop_id', '$prod_id', '$amt')");
            }
            if (!$upS) {
                throw new Exception("ไม่สามารถเพิ่มสต๊อกเข้าหน้าร้านได้: " . mysqli_error($conn));
            }
        }
    }

    // Update received_amount in items
    mysqli_query($conn, "UPDATE transfer_record_item SET received_amount = prod_amount WHERE tf_id = '$tf_id'");

    // Update transfer_record status
    $now = date("Y-m-d H:i:s");
    $upTf = mysqli_query($conn, "UPDATE transfer_record 
                                SET status = 'received', 
                                    receiver_name = '$receiver_name', 
                                    receive_time = '$now', 
                                    receive_user_id = '$user_id' 
                                WHERE tf_id = '$tf_id'");
    if (!$upTf) {
        throw new Exception("ไม่สามารถอัปเดตสถานะใบโอนได้: " . mysqli_error($conn));
    }

    mysqli_commit($conn);
    echo json_encode(["status" => "success", "message" => "ยืนยันการรับสินค้าเข้าสต๊อกเรียบร้อยแล้ว"], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if ($conn) @mysqli_rollback($conn);
    echo json_encode(["status" => "error", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conn);
?>
