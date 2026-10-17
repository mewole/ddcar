<?php
    // เปิด Error Log เพื่อดูสาเหตุจริงหากมีปัญหาอื่นซ่อนอยู่
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    include "db.php";   
    $prod_id   = $_POST['prod_id'] ?? null;
    $name_prod = trim($_POST['name_prod'] ?? '');
    $raw_code  = trim($_POST['code'] ?? '');
    $code      = strtoupper(str_replace(' ', '', $raw_code));
    $img       = $_POST['img'] ?? null;

    if (!$prod_id || empty($code)) {
        echo "fail";
        exit;
    }

    if (!preg_match('/^[A-Z0-9\-]+$/', $code)) {
        echo "invalid_sku";
        exit;
    }

    if (!empty($img)) {
        $sql = "UPDATE product SET prod_name = ?, prod_code = ?, img = ? WHERE prod_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $name_prod, $code, $img, $prod_id);
    } else {
        $sql = "UPDATE product SET prod_name = ?, prod_code = ? WHERE prod_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $name_prod, $code, $prod_id);
    }

    if ($stmt && mysqli_stmt_execute($stmt)) {
        echo "success";
    } else {
        echo "fail";
    }    

    if ($stmt) {
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
?>