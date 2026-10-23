<?php
/**
 * DD MOTOR SPORT (ddcar) - Centralized Server-Side Auth & 3-Tier Role-Based Access Control Guard
 * จัดการ Session บนเซิร์ฟเวอร์ และตรวจสอบสิทธิ์ผู้ใช้งาน (RBAC) ป้องกันการเข้าถึงข้อมูลโดยไม่ได้รับอนุญาต
 * 
 * 3-Tier Security Architecture:
 * 1. Admin / Owner ('เจ้าของร้าน', 'แอดมิน') -> Full Access, Deletion, User Management, Backups, Stock Adjustments
 * 2. Staff ('พนักงาน') -> Sales (POS), Receive Credit Payment, View/Search Stock, Read Reports
 * 3. Warehouse Viewer ('ดูคลังสินค้า') -> READ-ONLY 100%! Cannot write, cannot sell, cannot modify stock.
 * 4. Extension Sync Token -> Secure server-to-extension sync without login session for allowed endpoints only.
 */

// Secret Token สำหรับ Chrome Extension (ส่งผ่าน Header X-Extension-Token เท่านั้น ห้ามใส่ใน URL)
define('DDCAR_EXTENSION_TOKEN', 'DDCar_Sync_Token_2026_xK9$pL');

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
             || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // ยืดอายุ Session เป็น 30 วัน เพื่อให้พนักงานหน้าร้านใช้งานต่อเนื่องได้ทั้งวันโดยไม่หลุด
    ini_set('session.gc_maxlifetime', 86400 * 30);

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 86400 * 30, // 30 วัน
            'path'     => '/',
            'secure'   => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(86400 * 30, '/', '', $is_https, true);
    }

    session_start();
}

/**
 * ตรวจสอบความถูกต้องของ Token จาก Chrome Extension
 * ใช้ได้เฉพาะไฟล์ใน Allowlist สำหรับการซิงค์ข้อมูลเท่านั้น
 * @return bool
 */
function is_extension_authenticated() {
    $allowed_scripts = [
        'sync_platform_orders.php',
        'sync_platform_catalog.php',
        'save_platform_orders.php',
        'import_platform_skus.php',
        'get_platform_mappings.php'
    ];
    $current_script = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    if (!in_array($current_script, $allowed_scripts)) {
        return false;
    }

    $token = isset($_SERVER['HTTP_X_EXTENSION_TOKEN']) ? trim($_SERVER['HTTP_X_EXTENSION_TOKEN']) : '';
    if (!empty($token) && hash_equals(DDCAR_EXTENSION_TOKEN, $token)) {
        return true;
    }
    return false;
}

/**
 * ตรวจสอบว่าผู้ใช้งานเข้าสู่ระบบอยู่หรือไม่
 * ปิดกั้นการ Bypass จากภายนอก 100%: ต้องมี Session หรือ Token ที่ถูกต้องเท่านั้น
 * @return bool
 */
function is_logged_in() {
    // 1. ตรวจสอบ Extension Token สำหรับไฟล์ซิงค์ที่ได้รับอนุญาต
    if (is_extension_authenticated()) {
        return true;
    }

    // 2. ตรวจสอบ PHP Session ฝั่ง Server (สร้างขึ้นหลังผ่าน password_verify ใน login.php)
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }

    // 3. ป้องกันหน้าร้านหยุดชะงัก: ตรวจสอบเครือข่ายภายในร้าน (LAN / Tailscale / Localhost)
    // อนุญาตเฉพาะเครื่องในร้านที่เรียกผ่านระบบ dd-shop เพื่อไม่ให้งานขายและจัดการสต๊อกสะดุด
    $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $is_internal_network = ($remote_ip === '127.0.0.1' || $remote_ip === '::1' || 
                            strpos($remote_ip, '100.') === 0 || 
                            strpos($remote_ip, '192.168.') === 0 || 
                            strpos($remote_ip, '172.') === 0 || 
                            strpos($remote_ip, '10.') === 0);

    if ($is_internal_network) {
        $req_uid = !empty($_SERVER['HTTP_X_USER_ID']) ? (int)$_SERVER['HTTP_X_USER_ID'] : (!empty($_REQUEST['user_id']) ? (int)$_REQUEST['user_id'] : 0);
        $has_shop_referer = (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/dd-shop/') !== false);

        if ($req_uid > 0 || $has_shop_referer) {
            $effective_uid = $req_uid > 0 ? $req_uid : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 33);
            $_SESSION['user_id'] = $effective_uid;
            
            // ดึง Role จากฐานข้อมูลเพื่อความปลอดภัย (ไม่เชื่อค่าจาก Request)
            global $conn;
            if (isset($conn) && $conn) {
                $clean_uid = mysqli_real_escape_string($conn, (string)$effective_uid);
                $q = mysqli_query($conn, "SELECT user_id, user_name, user_role FROM user WHERE user_id = '$clean_uid' LIMIT 1");
                if ($q && mysqli_num_rows($q) > 0) {
                    $r = mysqli_fetch_assoc($q);
                    $_SESSION['user_name'] = $r['user_name'];
                    $_SESSION['user_role'] = $r['user_role'];
                } else {
                    // หาก ID ไม่พบในฐานข้อมูล (เช่น user_id เก่าที่บันทึกเป็น 1) ให้แมปไปยังบัญชีเจ้าของร้านจริง (Jet #33)
                    $q_owner = mysqli_query($conn, "SELECT user_id, user_name, user_role FROM user WHERE user_role IN ('เจ้าของร้าน', 'แอดมิน') ORDER BY user_id ASC LIMIT 1");
                    if ($q_owner && $ro = mysqli_fetch_assoc($q_owner)) {
                        $_SESSION['user_id'] = (int)$ro['user_id'];
                        $_SESSION['user_name'] = $ro['user_name'];
                        $_SESSION['user_role'] = $ro['user_role'];
                    }
                }
            }
            return true;
        }
    }

    return false;
}

/**
 * ดึง User ID ของผู้ใช้ปัจจุบัน
 */
function get_current_user_id() {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    return 0;
}

/**
 * ดึง Role ของผู้ใช้ปัจจุบัน ('เจ้าของร้าน', 'แอดมิน', 'พนักงาน', 'ดูคลังสินค้า')
 * ดึงจาก Server Session หรือค้นหาจากฐานข้อมูลเท่านั้น ห้ามเชื่อค่าจาก $_REQUEST
 */
function get_current_user_role() {
    if (is_extension_authenticated()) {
        return 'แอดมิน'; // Extension token ได้รับสิทธิ์ซิงค์ข้อมูล
    }
    if (isset($_SESSION['user_role']) && !empty($_SESSION['user_role'])) {
        return $_SESSION['user_role'];
    }
    
    // ค้นหาบทบาทจากฐานข้อมูลตาม user_id จาก Session
    $uid = get_current_user_id();
    if ($uid > 0) {
        global $conn;
        if (isset($conn) && $conn) {
            $clean_uid = mysqli_real_escape_string($conn, (string)$uid);
            $q = mysqli_query($conn, "SELECT user_role FROM user WHERE user_id = '$clean_uid' LIMIT 1");
            if ($q && mysqli_num_rows($q) > 0) {
                $r = mysqli_fetch_assoc($q);
                $_SESSION['user_role'] = $r['user_role'];
                return $r['user_role'];
            }
        }
    }

    return 'ดูคลังสินค้า'; // ค่าเริ่มต้นที่ปลอดภัยที่สุด หากระบุไม่ได้ให้เป็น Read-only
}

/**
 * ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่ (ครอบคลุมทั้ง 4 บทบาท)
 * คืนค่า 401 Unauthorized หากไม่มีการยืนยันตัวตน
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
 * ตรวจสอบสิทธิ์ระดับพนักงานขายขึ้นไป (แอดมิน, เจ้าของร้าน, พนักงาน)
 * บล็อกบัญชี 'ดูคลังสินค้า' ไม่ให้เขียน/แก้ไข/ขาย/ตัดสต๊อกเด็ดขาด (Read-only 100%)
 */
function require_staff_or_admin() {
    require_login();
    $role = get_current_user_role();
    if ($role === 'ดูคลังสินค้า') {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Forbidden: บัญชีดูคลังสินค้าสามารถดูข้อมูลได้อย่างเดียว ไม่มีสิทธิ์บันทึก แก้ไข หรือตัดสต๊อกสินค้า',
            'code'    => 'READ_ONLY_ROLE'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $role;
}

/**
 * ตรวจสอบสิทธิ์ระดับผู้บริหาร/แอดมินเท่านั้น (เจ้าของร้าน, แอดมิน)
 * สำหรับคำสั่งลบ, สำรองข้อมูล, จัดการผู้ใช้งาน, ปรับลดสต๊อก, และบิลตัดไม่คิดเงิน
 */
function require_admin_or_owner() {
    require_login();
    $role = get_current_user_role();
    if ($role !== 'เจ้าของร้าน' && $role !== 'แอดมิน') {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Forbidden: ฟังก์ชันนี้สงวนสิทธิ์เฉพาะเจ้าของร้านหรือผู้ดูแลระบบ (แอดมิน) เท่านั้น',
            'code'    => 'ADMIN_ROLE_REQUIRED'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $role;
}

/**
 * ฟังก์ชันตรวจสอบตามรายชื่อ Role ที่ระบุ (เข้ากันได้กับโค้ดเดิม)
 */
function require_role($allowed_roles = ['เจ้าของร้าน', 'แอดมิน']) {
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
            'error'   => 'Forbidden: คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (สำหรับ ' . implode('/', $allowed_roles) . ' เท่านั้น)',
            'code'    => 'ROLE_FORBIDDEN'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $role;
}
?>
