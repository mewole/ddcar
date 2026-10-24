<?php
include_once "auth_guard.php";
require_staff_or_admin();

include "db.php";
header('Content-Type: application/json; charset=utf-8');

$input = isset($_POST['JSON']) ? trim($_POST['JSON']) : '';
$items = json_decode($input, true);
if (empty($items) && is_string($input)) {
    $items = json_decode(stripslashes($input), true);
}
if (is_array($items) && isset($items['prod_id'])) {
    $items = array($items);
}

$direction = isset($_POST['direction']) ? $_POST['direction'] : 'shop_to_ware';
$from_id = isset($_POST['from_id']) ? intval($_POST['from_id']) : 0;
$to_id = isset($_POST['to_id']) ? intval($_POST['to_id']) : 0;
$dt = isset($_POST['date']) ? $_POST['date'] : date("Y-m-d H:i:s");
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$comment = isset($_POST['comment']) ? mysqli_real_escape_string($conn, trim($_POST['comment'])) : '';

if (empty($items) || !is_array($items)) {
    echo json_encode(["status" => "error", "message" => "ไม่มีรายการสินค้า"], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($from_id <= 0 || $to_id <= 0) {
    echo json_encode(["status" => "error", "message" => "กรุณาระบุต้นทางและปลายทาง"]);
    exit;
}

try {
    mysqli_begin_transaction($conn);

    // Generate Transfer Number: TRyymm-xxxxx
    $prefix = "TR" . date("y");
    $sqlNum = "SELECT tf_number FROM transfer_record WHERE tf_number LIKE '$prefix%' ORDER BY tf_id DESC LIMIT 1";
    $resNum = mysqli_query($conn, $sqlNum);
    $count = 1;
    if ($resNum && mysqli_num_rows($resNum) > 0) {
        $rowNum = mysqli_fetch_assoc($resNum);
        if (!empty($rowNum['tf_number'])) {
            $parts = explode('-', $rowNum['tf_number']);
            if (isset($parts[1])) {
                $count = intval($parts[1]) + 1;
            }
        }
    }
    $tf_number = $prefix . "-" . str_pad($count, 5, "0", STR_PAD_LEFT);

    if ($direction === 'ware_to_ware' && $from_id === $to_id) {
        throw new Exception("ห้ามเลือกโกดังต้นทางและโกดังปลายทางเป็นโกดังเดียวกัน");
    }

    // Map shop_id and branch_id (ware_id)
    if ($direction == 'shop_to_ware') {
        $shop_id = $from_id;
        $branch_id = $to_id;
    } elseif ($direction == 'ware_to_ware') {
        $shop_id = $from_id; // stores source ware_id
        $branch_id = $to_id; // stores destination ware_id
    } else { // ware_to_shop
        $branch_id = $from_id;
        $shop_id = $to_id;
    }

    $totalQty = 0;
    foreach ($items as $item) {
        $totalQty += intval($item['amt']);
    }

    $sqlHeader = "INSERT INTO transfer_record (tf_number, total, shop_id, branch_id, date_time, user, remark, direction, status) 
                  VALUES ('$tf_number', '$totalQty', '$shop_id', '$branch_id', '$dt', '$user_id', '$comment', '$direction', 'pending')";
    $resHeader = mysqli_query($conn, $sqlHeader);
    if (!$resHeader) {
        throw new Exception("ไม่สามารถบันทึกข้อมูลใบโอนได้: " . mysqli_error($conn));
    }
    $tf_id = mysqli_insert_id($conn);

    $itemIndex = 0;
    foreach ($items as $it) {
        $itemIndex++;
        $prod_id = mysqli_real_escape_string($conn, $it['prod_id']);
        $amt = intval($it['amt']);
        $price = isset($it['price']) ? floatval($it['price']) : 0;

        if ($amt <= 0) continue;

        // Check stock at origin before deducting
        if ($direction == 'shop_to_ware') {
            $chkStock = mysqli_query($conn, "SELECT amount FROM shop WHERE shop_id = '$from_id' AND prod_id = '$prod_id'");
            $curAmt = 0;
            if ($chkStock && mysqli_num_rows($chkStock) > 0) {
                $curAmt = intval(mysqli_fetch_assoc($chkStock)['amount']);
            }
            if ($curAmt < $amt) {
                throw new Exception("สินค้า ID: $prod_id มีจำนวนในหน้าร้านไม่พอ (มี $curAmt, ต้องการโอน $amt)");
            }

            // Deduct immediately from origin shop
            $upOrig = mysqli_query($conn, "UPDATE shop SET amount = amount - $amt WHERE shop_id = '$from_id' AND prod_id = '$prod_id'");
            if (!$upOrig) {
                throw new Exception("ไม่สามารถตัดสต๊อกต้นทางได้: " . mysqli_error($conn));
            }
        } elseif ($direction == 'ware_to_ware') {
            $chkStock = mysqli_query($conn, "SELECT amount FROM warehouse WHERE ware_id = '$from_id' AND prod_id = '$prod_id'");
            $curAmt = 0;
            if ($chkStock && mysqli_num_rows($chkStock) > 0) {
                $curAmt = intval(mysqli_fetch_assoc($chkStock)['amount']);
            }
            if ($curAmt < $amt) {
                throw new Exception("สินค้า ID: $prod_id มีจำนวนในโกดังต้นทางไม่พอ (มี $curAmt, ต้องการโอน $amt)");
            }

            // Deduct immediately from origin warehouse
            $upOrig = mysqli_query($conn, "UPDATE warehouse SET amount = amount - $amt WHERE ware_id = '$from_id' AND prod_id = '$prod_id'");
            if (!$upOrig) {
                throw new Exception("ไม่สามารถตัดสต๊อกต้นทางได้: " . mysqli_error($conn));
            }
        } else { // ware_to_shop
            $chkStock = mysqli_query($conn, "SELECT amount FROM warehouse WHERE ware_id = '$from_id' AND prod_id = '$prod_id'");
            $curAmt = 0;
            if ($chkStock && mysqli_num_rows($chkStock) > 0) {
                $curAmt = intval(mysqli_fetch_assoc($chkStock)['amount']);
            }
            if ($curAmt < $amt) {
                throw new Exception("สินค้า ID: $prod_id มีจำนวนในโกดังไม่พอ (มี $curAmt, ต้องการโอน $amt)");
            }

            // Deduct immediately from origin warehouse
            $upOrig = mysqli_query($conn, "UPDATE warehouse SET amount = amount - $amt WHERE ware_id = '$from_id' AND prod_id = '$prod_id'");
            if (!$upOrig) {
                throw new Exception("ไม่สามารถตัดสต๊อกต้นทางได้: " . mysqli_error($conn));
            }
        }

        // Insert item into transfer_record_item
        $sqlItem = "INSERT INTO transfer_record_item (tf_id, item_id, prod_id, prod_price, prod_amount, received_amount) 
                    VALUES ('$tf_id', '$itemIndex', '$prod_id', '$price', '$amt', 0)";
        $resItem = mysqli_query($conn, $sqlItem);
        if (!$resItem) {
            throw new Exception("ไม่สามารถบันทึกรายการสินค้าได้: " . mysqli_error($conn));
        }
    }

    mysqli_commit($conn);
    echo json_encode([
        "status" => "success",
        "tf_id" => $tf_id,
        "tf_number" => $tf_number,
        "direction" => $direction,
        "total_qty" => $totalQty
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if ($conn) {
        @mysqli_rollback($conn);
        // MyISAM failsafe: delete orphaned header and any inserted items if an error occurs
        if (isset($tf_id) && $tf_id > 0) {
            @mysqli_query($conn, "DELETE FROM transfer_record_item WHERE tf_id = '$tf_id'");
            @mysqli_query($conn, "DELETE FROM transfer_record WHERE tf_id = '$tf_id'");
        }
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conn);
?>
