<?php
include_once "auth_guard.php";
require_login();
include "db.php";
header("Content-Type: application/json; charset=UTF-8");

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $prod_id = isset($_POST['prod_id']) ? mysqli_real_escape_string($conn, trim($_POST['prod_id'])) : '';

    if (empty($prod_id)) {
        echo json_encode(['status' => 'fail', 'message' => 'Missing prod_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'update_min') {
        $min = isset($_POST['min_amount']) ? intval($_POST['min_amount']) : 0;
        
        // คำนวณสต๊อกคงเหลือจริงปัจจุบันจาก โกดัง + หน้าร้าน
        $total_amt = 0;
        $q_w = mysqli_query($conn, "SELECT SUM(amount) AS sum_w FROM warehouse WHERE prod_id = '$prod_id'");
        if ($q_w && $rw = mysqli_fetch_assoc($q_w)) {
            $total_amt += intval($rw['sum_w']);
        }
        $q_s = mysqli_query($conn, "SELECT SUM(amount) AS sum_s FROM shop WHERE prod_id = '$prod_id'");
        if ($q_s && $rs = mysqli_fetch_assoc($q_s)) {
            $total_amt += intval($rs['sum_s']);
        }

        // คำนวณสถานะใหม่ตาม min_amount อย่างสมเหตุสมผล
        $new_status = 'normal';
        if ($total_amt <= 0) {
            $new_status = 'danger';
        } else if ($min > 0) {
            if ($total_amt <= $min) {
                // หากสต๊อกเหลือ <= กึ่งหนึ่งของเกณฑ์ขั้นต่ำ หรือ <= 5 ชิ้น ให้เป็นระดับวิกฤต (danger)
                $danger_threshold = max(1, min(10, ceil($min / 2)));
                if ($total_amt <= $danger_threshold) {
                    $new_status = 'danger';
                } else {
                    $new_status = 'warning';
                }
            } else {
                $new_status = 'normal';
            }
        } else {
            // กรณีไม่ได้ตั้งเกณฑ์ขั้นต่ำ (min = 0) ใช้เกณฑ์มาตรฐานเดิม <= 10 เป็น danger
            if ($total_amt <= 10) {
                $new_status = 'danger';
            } else {
                $new_status = 'normal';
            }
        }

        $sql = "UPDATE product SET min_amount = '$min', status = '$new_status' WHERE prod_id = '$prod_id'";
        if (mysqli_query($conn, $sql)) {
            echo json_encode([
                'status' => 'success',
                'prod_id' => $prod_id,
                'min_amount' => $min,
                'new_status' => $new_status,
                'total_amount' => $total_amt
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['status' => 'fail', 'message' => mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if ($action === 'toggle_ordered') {
        $is_ordered = isset($_POST['is_ordered']) ? intval($_POST['is_ordered']) : 0;
        $sql = "INSERT INTO product_status_ext (prod_id, is_ordered) VALUES ('$prod_id', '$is_ordered') 
                ON DUPLICATE KEY UPDATE is_ordered = '$is_ordered'";
        if (mysqli_query($conn, $sql)) {
            echo json_encode([
                'status' => 'success',
                'prod_id' => $prod_id,
                'is_ordered' => $is_ordered
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['status' => 'fail', 'message' => mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if ($action === 'toggle_preorder') {
        $is_preorder = isset($_POST['is_preorder']) ? intval($_POST['is_preorder']) : 0;
        $sql = "INSERT INTO product_status_ext (prod_id, is_preorder) VALUES ('$prod_id', '$is_preorder') 
                ON DUPLICATE KEY UPDATE is_preorder = '$is_preorder'";
        if (mysqli_query($conn, $sql)) {
            echo json_encode([
                'status' => 'success',
                'prod_id' => $prod_id,
                'is_preorder' => $is_preorder
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['status' => 'fail', 'message' => mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    echo json_encode(['status' => 'fail', 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
    mysqli_close($conn);
?>
