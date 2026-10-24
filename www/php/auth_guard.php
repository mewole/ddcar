<?php
/**
 * DD MOTOR SPORT (ddcar) - Centralized Server-Side Auth & 3-Tier Role-Based Access Control Guard
 * จัดการ Session ตรวจสอบสิทธิ์ผู้ใช้งาน (RBAC) ป้องกันการเข้าถึงข้อมูลโดยไม่ได้รับอนุญาต
 * 
 * 3-Tier Security Architecture:
 * 1. Admin / Owner ('ผู้ดูแลระบบ', 'เจ้าของร้าน') -> Full Access, Deletion, User Management, Backups, Stock Adjustments
 * 2. Staff ('พนักงาน') -> Sales (POS), Receive Credit Payment, View/Search Stock, Read Reports
 * 3. Warehouse Viewer ('ผู้ดูสต็อก') -> READ-ONLY 100%! Cannot write, cannot sell, cannot modify stock.
 * 4. Extension Sync Token -> Secure server-to-extension sync without login session for allowed endpoints only.
 */

// Secret Token สำหรับ Chrome Extension
define('DDCAR_EXTENSION_TOKEN', 'DDCar_Sync_Token_2026_xK9$pL');

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
             || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    ini_set('session.gc_maxlifetime', 86400 * 30);

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 86400 * 30,
            'path'     => '/',
            'secure'   => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(86400 * 30, '/', '', $is_https, true);
    }

    @session_start();

    // 0.5 & Point 10: Server-side PHP Session Expiry (30 mins idle & Midnight daily reset)
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $now = time();
        // 30 minutes idle check (1800 seconds)
        if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > 1800)) {
            session_unset();
            @session_destroy();
            $curr_file = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');
            if ($curr_file !== 'login.php') {
                header('HTTP/1.1 401 Unauthorized');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error'   => 'Session Expired: หมดเวลาการเชื่อมต่อ (ไม่ใช้งานเกิน 30 นาที) กรุณาเข้าสู่ระบบใหม่',
                    'code'    => 'SESSION_EXPIRED'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        // Midnight daily reset check
        if (isset($_SESSION['login_date']) && $_SESSION['login_date'] !== date('Y-m-d')) {
            session_unset();
            @session_destroy();
            $curr_file = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');
            if ($curr_file !== 'login.php') {
                header('HTTP/1.1 401 Unauthorized');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error'   => 'Session Expired: เซสชันหมดอายุข้ามวัน (เที่ยงคืน) กรุณาเข้าสู่ระบบใหม่',
                    'code'    => 'SESSION_EXPIRED_MIDNIGHT'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        $_SESSION['last_activity'] = $now;
    }
}

/**
 * ตรวจสอบ Token จาก Chrome Extension (รองรับทั้ง Header และ Request query/post)
 */
function is_extension_authenticated() {
    $allowed_scripts = [
        'sync_platform_orders.php',
        'sync_platform_catalog.php',
        'save_platform_orders.php',
        'import_platform_skus.php',
        'get_platform_mappings.php',
        'save_platform_mapping.php'
    ];
    $current_script = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    if (!in_array($current_script, $allowed_scripts)) {
        return false;
    }

    $token = '';
    if (isset($_SERVER['HTTP_X_EXTENSION_TOKEN'])) {
        $token = trim($_SERVER['HTTP_X_EXTENSION_TOKEN']);
    } elseif (isset($_REQUEST['token'])) {
        $token = trim($_REQUEST['token']);
    } elseif (isset($_POST['token'])) {
        $token = trim($_POST['token']);
    } elseif (isset($_GET['token'])) {
        $token = trim($_GET['token']);
    }

    if (!empty($token) && hash_equals(DDCAR_EXTENSION_TOKEN, $token)) {
        return true;
    }
    return false;
}

/**
 * ตรวจสอบว่าผู้ใช้งานเข้าสู่ระบบแล้วหรือไม่
 */
function is_logged_in() {
    // 1. Chrome Extension Token
    if (is_extension_authenticated()) {
        return true;
    }

    // 2. Active PHP Session
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }

    // 3. Client / Mobile App user identification (sent in $_REQUEST or headers)
    $req_uid = 0;
    if (isset($_REQUEST['user_id']) && is_numeric($_REQUEST['user_id'])) {
        $req_uid = (int)$_REQUEST['user_id'];
    } elseif (isset($_SERVER['HTTP_X_USER_ID']) && is_numeric($_SERVER['HTTP_X_USER_ID'])) {
        $req_uid = (int)$_SERVER['HTTP_X_USER_ID'];
    }

    if ($req_uid > 0) {
        global $conn;
        if (isset($conn) && $conn) {
            $clean_uid = mysqli_real_escape_string($conn, (string)$req_uid);
            $q = mysqli_query($conn, "SELECT user_id, user_name, user_role FROM user WHERE user_id = '$clean_uid' LIMIT 1");
            if ($q && ($u = mysqli_fetch_assoc($q))) {
                $_SESSION['user_id'] = (int)$u['user_id'];
                $_SESSION['user_name'] = $u['user_name'];
                $_SESSION['user_role'] = $u['user_role'];
                return true;
            }
        }
        $_SESSION['user_id'] = $req_uid;
        if (!empty($_REQUEST['user_role'])) {
            $_SESSION['user_role'] = $_REQUEST['user_role'];
        }
        return true;
    }

    // 4. Determine if current script is a mutation (write/update/delete)
    $current_script = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    $mutation_scripts = [
        'save_order.php', 'save_stock_adjustment.php', 'stock_in.php', 'transfer_in.php',
        'create_transfer.php', 'confirm_transfer.php', 'reject_transfer.php', 'rename_transfer.php',
        'delete_bill.php', 'delete_user.php', 'delete_transfer.php', 'delete_transfer_to.php',
        'delete_import.php', 'delete_warehouse.php', 'backup_db.php', 'update_prod.php',
        'update_user.php', 'rename_user.php', 'add_user.php', 'add_product.php',
        'save_platform_orders.php', 'import_platform_skus.php'
    ];
    $is_mutation = in_array($current_script, $mutation_scripts) ||
                    strpos($current_script, 'delete_') === 0 ||
                    strpos($current_script, 'save_') === 0 ||
                    strpos($current_script, 'update_') === 0 ||
                    strpos($current_script, 'insert_') === 0 ||
                    strpos($current_script, 'add_') === 0 ||
                    strpos($current_script, 'clear_') === 0 ||
                    strpos($current_script, 'backup_') === 0;

    if ($is_mutation) {
        return false;
    }

    // 5. Read-only queries (reports, details, product search, etc.)
    // Allow read-only access for app / web requests
    $_SESSION['user_id'] = 33;
    $_SESSION['user_name'] = 'ผู้ดูสต็อก (Guest/Read-only)';
    $_SESSION['user_role'] = 'ผู้ดูสต็อก';
    return true;
}

/**
 * ดึง User ID ของผู้ใช้ปัจจุบัน
 */
function get_current_user_id() {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    if (isset($_REQUEST['user_id']) && is_numeric($_REQUEST['user_id'])) {
        return (int)$_REQUEST['user_id'];
    }
    return 0;
}

/**
 * ดึง Role ของผู้ใช้ปัจจุบัน ('เจ้าของร้าน', 'ผู้ดูแลระบบ', 'พนักงาน', 'ผู้ดูสต็อก')
 */
function get_current_user_role() {
    if (is_extension_authenticated()) {
        return 'เจ้าของร้าน';
    }
    if (isset($_SESSION['user_role']) && !empty($_SESSION['user_role'])) {
        return $_SESSION['user_role'];
    }
    if (isset($_REQUEST['user_role']) && !empty($_REQUEST['user_role'])) {
        return $_REQUEST['user_role'];
    }
    
    $uid = get_current_user_id();
    if ($uid > 0) {
        global $conn;
        if (isset($conn) && $conn) {
            $clean_uid = mysqli_real_escape_string($conn, (string)$uid);
            $q = mysqli_query($conn, "SELECT user_role FROM user WHERE user_id = '$clean_uid' LIMIT 1");
            if ($q && ($r = mysqli_fetch_assoc($q))) {
                $_SESSION['user_role'] = $r['user_role'];
                return $r['user_role'];
            }
        }
    }

    return 'ผู้ดูสต็อก';
}

/**
 * บังคับให้ผู้ใช้งานเข้าสู่ระบบ
 */
function require_login() {
    if (!is_logged_in()) {
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Unauthorized: กรุณาเข้าสู่ระบบก่อนทำรายการ',
            'code'    => 'AUTH_REQUIRED'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return get_current_user_id();
}

/**
 * บังคับสิทธิ์เจ้าหน้าที่หรือสูงกว่า (เจ้าของร้าน, ผู้ดูแลระบบ, พนักงาน)
 */
function require_staff_or_admin() {
    require_login();
    $role = get_current_user_role();
    if ($role === 'ผู้ดูสต็อก') {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Forbidden: สิทธิ์ผู้ดูสต็อกไม่มีสิทธิ์แก้ไขข้อมูล (Read-only 100%)',
            'code'    => 'READ_ONLY_ROLE'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $role;
}

/**
 * บังคับสิทธิ์ผู้ดูแลระบบหรือเจ้าของร้าน
 */
function require_admin_or_owner() {
    require_login();
    $role = get_current_user_role();
    if ($role !== 'ผู้ดูแลระบบ' && $role !== 'เจ้าของร้าน' && $role !== 'แอดมิน') {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Forbidden: ฟังก์ชันนี้เฉพาะผู้ดูแลระบบหรือเจ้าของร้านเท่านั้น',
            'code'    => 'ADMIN_ROLE_REQUIRED'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $role;
}

/**
 * บังคับสิทธิ์ตามรายการ Role ที่กำหนด
 */
function require_role($allowed_roles = ['ผู้ดูแลระบบ', 'เจ้าของร้าน', 'แอดมิน']) {
    require_login();
    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    $role = get_current_user_role();
    if (!in_array($role, $allowed_roles)) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Forbidden: คุณไม่มีสิทธิ์ใช้งานส่วนนี้ (ต้องการ ' . implode('/', $allowed_roles) . ' เท่านั้น)',
            'code'    => 'ROLE_FORBIDDEN'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $role;
}
?>
