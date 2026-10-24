<?php
include_once "auth_guard.php";
require_staff_or_admin();

include "db.php";
include_once "img_helper.php";

$prod_id   = $_POST['prod_id'] ?? null;
$name_prod = trim($_POST['name_prod'] ?? '');
$raw_code  = trim($_POST['code'] ?? '');
$code      = strtoupper(str_replace(' ', '', $raw_code));
$img       = $_POST['img'] ?? null;

$server_role = $_SESSION['user_role'] ?? 'พนักงาน';
$can_edit_cost = in_array($server_role, ['แอดมิน', 'เจ้าของร้าน', 'ผู้ดูแลระบบ']);
$can_edit_sku  = in_array($server_role, ['แอดมิน', 'เจ้าของร้าน', 'ผู้ดูแลระบบ']);
$cost         = (isset($_POST['cost']) && $_POST['cost'] !== '') ? floatval($_POST['cost']) : null;
$price_s      = (isset($_POST['price_s']) && $_POST['price_s'] !== '') ? floatval($_POST['price_s']) : null;
$price_w      = (isset($_POST['price_w']) && $_POST['price_w'] !== '') ? floatval($_POST['price_w']) : null;
$sync_history = isset($_POST['sync_history']) ? intval($_POST['sync_history']) : 0;

if (!$prod_id) {
    echo "fail";
    exit;
}

if (empty($name_prod)) {
    $q_name = mysqli_query($conn, "SELECT prod_name FROM product WHERE prod_id = '$prod_id' LIMIT 1");
    if ($q_name && $r_name = mysqli_fetch_assoc($q_name)) {
        $name_prod = $r_name['prod_name'] ?? '';
    }
}

// ตรวจสอบรหัสสินค้าเดิม
$old_code = '';
$q_cur = mysqli_query($conn, "SELECT prod_code, prod_cost FROM product WHERE prod_id = '$prod_id' LIMIT 1");
if ($q_cur && $r_cur = mysqli_fetch_assoc($q_cur)) {
    $old_code = $r_cur['prod_code'] ?? '';
}

if (empty($code)) {
    $code = !empty($old_code) ? $old_code : ('SKU-' . $prod_id);
}

// มติเจ้าของร้าน 24/10/2026: แก้รหัสสินค้าได้เฉพาะแอดมินและเจ้าของร้านเท่านั้น
if ($old_code !== '' && $code !== $old_code) {
    if (!$can_edit_sku) {
        // พนักงานไม่มีสิทธิ์แก้รหัสสินค้า ให้คงรหัสเดิมไว้
        $code = $old_code;
    } else {
        // ตรวจสอบรูปแบบรหัสสินค้าใหม่: เฉพาะ A-Z, 0-9 และขีด (-) เท่านั้น ห้ามมีเว้นวรรค
        if (!preg_match('/^[A-Z0-9\-]+$/', $code)) {
            echo "invalid_sku";
            exit;
        }
        // ตรวจสอบรหัสซ้ำกับสินค้าอื่น
        $clean_c = mysqli_real_escape_string($conn, $code);
        $q_dup = mysqli_query($conn, "SELECT prod_id FROM product WHERE prod_code = '$clean_c' AND prod_id != '$prod_id' LIMIT 1");
        if ($q_dup && mysqli_num_rows($q_dup) > 0) {
            echo "duplicate_sku";
            exit;
        }
    }
}

// Data Quality Validation: Negative prices are rejected
if ($price_w !== null && $price_w < 0) {
    echo "error_price_negative";
    exit;
}
if ($price_s !== null && $price_s < 0) {
    echo "error_price_negative";
    exit;
}
// Wholesale price cannot be greater than retail price if BOTH are positive
if ($price_s !== null && $price_w !== null && $price_s > 0 && $price_w > 0 && $price_s > $price_w) {
    echo "error_wholesale_higher";
    exit;
}

if ($cost !== null && !$can_edit_cost) {
    $cost = null; // Ignore cost update for unauthorized roles
}

// Save image if new base64 provided
if (!empty($img)) {
    $saved_path = save_product_image_from_base64($img, $prod_id);
    if ($saved_path) {
        $img = $saved_path;
    }
}

// Build dynamic UPDATE query based on fields provided
$fields = ["prod_name = ?", "prod_code = ?"];
$types = "ss";
$params = [$name_prod, $code];

if (!empty($img)) {
    $fields[] = "img = ?";
    $types .= "s";
    $params[] = $img;
}

if ($can_edit_cost && $cost !== null) {
    $fields[] = "prod_cost = ?";
    $types .= "d";
    $params[] = $cost;
}

if ($price_s !== null) {
    $fields[] = "prod_pricesend = ?";
    $types .= "d";
    $params[] = $price_s;
}

if ($price_w !== null) {
    $fields[] = "prod_price = ?";
    $types .= "d";
    $params[] = $price_w;
}

$types .= "i";
$params[] = $prod_id;

$sql = "UPDATE product SET " . implode(", ", $fields) . " WHERE prod_id = ?";
$stmt = mysqli_prepare($conn, $sql);
$executed = false;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    $executed = mysqli_stmt_execute($stmt);
}

if ($executed) {
    // If sync_history is enabled by store owner
    if ($cost !== null && $cost > 0 && $sync_history === 1 && $server_role === 'เจ้าของร้าน') {
        $user_id = intval($_SESSION['user_id'] ?? 33);
        $user_name = $_SESSION['user_name'] ?? 'เจ้าของร้าน';
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $q_old = mysqli_query($conn, "SELECT prod_cost, prod_code FROM product WHERE prod_id = '$prod_id'");
        $old_cost = 0.0;
        $p_code = $code;
        if ($q_old && ($r_old = mysqli_fetch_assoc($q_old))) {
            $old_cost = floatval($r_old['prod_cost'] ?? 0);
            if (!empty($r_old['prod_code'])) $p_code = $r_old['prod_code'];
        }

        $stmt_log = mysqli_prepare($conn, "INSERT INTO cost_history_log (prod_id, prod_code, old_cost, new_cost, changed_by_user_id, changed_by_name, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt_log) {
            mysqli_stmt_bind_param($stmt_log, "isddiss", $prod_id, $p_code, $old_cost, $cost, $user_id, $user_name, $client_ip);
            mysqli_stmt_execute($stmt_log);
            mysqli_stmt_close($stmt_log);
        }

        @mysqli_query($conn, "UPDATE stock_in_item SET stock_cost = '$cost' WHERE prod_id = '$prod_id'");
        @mysqli_query($conn, "UPDATE transfer_in_item SET tran_cost = '$cost' WHERE prod_id = '$prod_id'");
        @mysqli_query($conn, "UPDATE product_supplier_sku SET last_cost = '$cost' WHERE prod_id = '$prod_id'");
    }

    // มติเจ้าของร้าน: เมื่อมีการเปลี่ยนรหัสสินค้า
    if ($old_code !== '' && $code !== $old_code) {
        $clean_old = mysqli_real_escape_string($conn, $old_code);
        $clean_new = mysqli_real_escape_string($conn, $code);
        $user_name_log = mysqli_real_escape_string($conn, $_SESSION['user_name'] ?? 'แอดมิน');

        // 1. อัปเดตตารางจับคู่ SKU แพลตฟอร์มอัตโนมัติ
        @mysqli_query($conn, "UPDATE platform_sku_mapping SET target_sku = '$clean_new' WHERE prod_id = '$prod_id' OR target_sku = '$clean_old'");

        // 2. บันทึกประวัติการเปลี่ยนรหัสเดิม -> ใหม่ เพื่อให้ค้นหาย้อนหลังได้
        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS sku_change_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            prod_id INT NOT NULL,
            old_sku VARCHAR(100) NOT NULL,
            new_sku VARCHAR(100) NOT NULL,
            changed_by VARCHAR(100) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_old_sku (old_sku),
            KEY idx_new_sku (new_sku)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

        @mysqli_query($conn, "INSERT INTO sku_change_history (prod_id, old_sku, new_sku, changed_by) VALUES ('$prod_id', '$clean_old', '$clean_new', '$user_name_log')");
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
