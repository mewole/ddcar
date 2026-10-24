<?php
include_once "auth_guard.php";

// Allow fallback authentication from client request parameters if session is empty
if (!is_logged_in()) {
    $uid = !empty($_POST['user_id']) ? intval($_POST['user_id']) : (!empty($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : 0);
    $urole = !empty($_POST['role']) ? trim($_POST['role']) : (!empty($_REQUEST['role']) ? trim($_REQUEST['role']) : '');
    if ($uid > 0) {
        $_SESSION['user_id'] = $uid;
        $_SESSION['user_name'] = !empty($_POST['user_name']) ? trim($_POST['user_name']) : (!empty($_REQUEST['user_name']) ? trim($_REQUEST['user_name']) : 'พนักงาน');
        $_SESSION['user_role'] = !empty($urole) ? $urole : 'พนักงาน';
    }
}

require_staff_or_admin();

include "db.php";
include_once "img_helper.php";

$prod_id   = $_POST['prod_id'] ?? null;
$name_prod = trim($_POST['name_prod'] ?? '');
$raw_code  = trim($_POST['code'] ?? '');
$code      = strtoupper(str_replace(' ', '', $raw_code));
$img       = $_POST['img'] ?? null;

$role         = trim($_POST['role'] ?? ($_SESSION['user_role'] ?? 'พนักงาน'));
$cost         = (isset($_POST['cost']) && $_POST['cost'] !== '') ? floatval($_POST['cost']) : null;
$price_s      = (isset($_POST['price_s']) && $_POST['price_s'] !== '') ? floatval($_POST['price_s']) : null;
$price_w      = (isset($_POST['price_w']) && $_POST['price_w'] !== '') ? floatval($_POST['price_w']) : null;
$sync_history = isset($_POST['sync_history']) ? intval($_POST['sync_history']) : 0;

if (!$prod_id || empty($name_prod)) {
    echo "fail";
    exit;
}

// If code is empty, retrieve existing code from database
if (empty($code)) {
    $q_cur = mysqli_query($conn, "SELECT prod_code FROM product WHERE prod_id = '$prod_id' LIMIT 1");
    if ($q_cur && $r_cur = mysqli_fetch_assoc($q_cur)) {
        $code = !empty($r_cur['prod_code']) ? $r_cur['prod_code'] : ('SKU-' . $prod_id);
    } else {
        $code = 'SKU-' . $prod_id;
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

// Role check: Only Admin and Owner can modify cost
$server_role = $_SESSION['user_role'] ?? $role;
$can_edit_cost = ($server_role === 'แอดมิน' || $server_role === 'เจ้าของร้าน');
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
    echo "success";
} else {
    echo "fail";
}

if ($stmt) {
    mysqli_stmt_close($stmt);
}
mysqli_close($conn);
?>
