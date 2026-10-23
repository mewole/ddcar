<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
ini_set('display_errors', 1);
    error_reporting(E_ALL);

    include "db.php";
    include_once "img_helper.php";

    $prod_id   = $_POST['prod_id'] ?? null;
    $name_prod = trim($_POST['name_prod'] ?? '');
    $raw_code  = trim($_POST['code'] ?? '');
    $code      = strtoupper(str_replace(' ', '', $raw_code));
    $img       = $_POST['img'] ?? null;

    $role         = trim($_POST['role'] ?? '');
    $cost         = (isset($_POST['cost']) && $_POST['cost'] !== '') ? floatval($_POST['cost']) : null;
    $price_s      = (isset($_POST['price_s']) && $_POST['price_s'] !== '') ? floatval($_POST['price_s']) : 0.0;
    $price_w      = (isset($_POST['price_w']) && $_POST['price_w'] !== '') ? floatval($_POST['price_w']) : 0.0;
    $sync_history = isset($_POST['sync_history']) ? intval($_POST['sync_history']) : 0;

    if (!$prod_id || empty($code)) {
        echo "fail";
        exit;
    }

    // Step 3 Data Quality Guards:
    // 1. Retail price cannot be 0 or negative
    if ($price_w <= 0) {
        echo "error_price_zero";
        exit;
    }
    // 2. Wholesale price cannot be greater than retail price
    if ($price_s > 0 && $price_s > $price_w) {
        echo "error_wholesale_higher";
        exit;
    }

    if (!preg_match('/^[A-Z0-9\-]+$/', $code)) {
        echo "invalid_sku";
        exit;
    }

    // Role check from SERVER SESSION: Only Admin and Owner can modify cost
    $server_role = $_SESSION['user_role'] ?? '';
    $can_edit_cost = ($server_role === 'แอดมิน' || $server_role === 'เจ้าของร้าน');
    if ($cost !== null && !$can_edit_cost) {
        $cost = null; // Ignore cost update for unauthorized roles
    }

    if (!empty($img)) {
        // บันทึกไฟล์ภาพ Base64 ลง disk แล้วเก็บ path
        $saved_path = save_product_image_from_base64($img, $prod_id);
        if ($saved_path) {
            $img = $saved_path;
        }

        if ($can_edit_cost && $cost !== null) {
            $sql = "UPDATE product SET prod_name = ?, prod_code = ?, img = ?, prod_cost = ?, prod_pricesend = ?, prod_price = ? WHERE prod_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssdddi", $name_prod, $code, $img, $cost, $price_s, $price_w, $prod_id);
        } else {
            $sql = "UPDATE product SET prod_name = ?, prod_code = ?, img = ? WHERE prod_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssi", $name_prod, $code, $img, $prod_id);
        }
    } else {
        if ($can_edit_cost && $cost !== null) {
            $sql = "UPDATE product SET prod_name = ?, prod_code = ?, prod_cost = ?, prod_pricesend = ?, prod_price = ? WHERE prod_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssdddi", $name_prod, $code, $cost, $price_s, $price_w, $prod_id);
        } else {
            $sql = "UPDATE product SET prod_name = ?, prod_code = ? WHERE prod_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $name_prod, $code, $prod_id);
        }
    }

    if ($stmt && mysqli_stmt_execute($stmt)) {
        // หากผู้ใช้เลือก sync ประวัติราคาทุนย้อนหลัง (เฉพาะ "เจ้าของร้าน" เท่านั้น พร้อมบันทึก Audit Log)
        $user_role = $_SESSION['user_role'] ?? '';
        $user_id = intval($_SESSION['user_id'] ?? 0);
        $user_name = $_SESSION['user_name'] ?? '';

        if ($cost !== null && $cost > 0 && $sync_history === 1 && $user_role === 'เจ้าของร้าน') {
            // ดึงราคาทุนเดิมเพื่อบันทึกประวัติ
            $q_old = mysqli_query($conn, "SELECT prod_cost, prod_code FROM product WHERE prod_id = '$prod_id'");
            $old_cost = 0.0;
            $p_code = $code;
            if ($q_old && ($r_old = mysqli_fetch_assoc($q_old))) {
                $old_cost = floatval($r_old['prod_cost'] ?? 0);
                if (!empty($r_old['prod_code'])) $p_code = $r_old['prod_code'];
            }

            // บันทึก Audit Log ลง cost_history_log
            $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $stmt_log = mysqli_prepare($conn, "INSERT INTO cost_history_log (prod_id, prod_code, old_cost, new_cost, changed_by_user_id, changed_by_name, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            if ($stmt_log) {
                mysqli_stmt_bind_param($stmt_log, "isddiss", $prod_id, $p_code, $old_cost, $cost, $user_id, $user_name, $client_ip);
                mysqli_stmt_execute($stmt_log);
                mysqli_stmt_close($stmt_log);
            }

            // ปรับราคาทุนในประวัติการรับเข้าเดิม
            @mysqli_query($conn, "UPDATE stock_in_item SET stock_cost = '$cost' WHERE prod_id = '$prod_id'");
            @mysqli_query($conn, "UPDATE transfer_in_item SET tran_cost = '$cost' WHERE prod_id = '$prod_id'");
            @mysqli_query($conn, "UPDATE product_supplier_sku SET last_cost = '$cost' WHERE prod_id = '$prod_id'");
        }
        echo "success";
    } else {
        echo "fail";
    }    

    if ($stmt) {
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
?>
