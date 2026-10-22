<?php
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

    if (!preg_match('/^[A-Z0-9\-]+$/', $code)) {
        echo "invalid_sku";
        exit;
    }

    // Role check: Only Admin and Owner can modify cost
    $can_edit_cost = ($role === 'แอดมิน' || $role === 'เจ้าของร้าน');
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
        // หากผู้ใช้เลือก sync ประวัติราคาทุนย้อนหลัง
        if ($cost !== null && $cost > 0 && $sync_history === 1 && $can_edit_cost) {
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
