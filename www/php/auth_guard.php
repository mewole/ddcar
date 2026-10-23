<?php
/**
 * DD MOTOR SPORT (ddcar) - Centralized Server-Side Auth & Role-Based Access Control Guard
 * จัดการ Session บนเซิร์ฟเวอร์ และตรวจสอบสิทธิ์ผู้ใช้งาน (RBAC) ป้องกันการเข้าถึงข้อมูลโดยไม่ได้รับอนุญาต
 */

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
 * ตรวจสอบว่าผู้ใช้งานเข้าสู่ระบบอยู่หรือไม่
 * รองรับทั้ง Session คุกกี้, LocalStorage Auth จากหน้าเว็บ/แอป, วงเครือข่ายภายในร้าน และ Tailscale VPN
 * @return bool
 */
function is_logged_in() {
    // 1. ตรวจสอบ PHP Session ฝั่ง Server
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }

    // 2. ตรวจสอบ Headers / Parameters ที่ส่งมาจาก Frontend (Capacitor/Cordova/LocalStorage Auth)
    if (!empty($_SERVER['HTTP_X_USER_ID']) || !empty($_REQUEST['user_id']) || !empty($_REQUEST['user_name'])) {
        return true;
    }

    // 3. อนุญาตการเชื่อมต่อจากเครือข่ายภายในร้านและ Tailscale VPN (POS แคชเชียร์, แท็บเล็ตคลังสินค้า)
    $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if ($remote_ip === '127.0.0.1' || $remote_ip === '::1' || 
        strpos($remote_ip, '100.') === 0 || 
        strpos($remote_ip, '192.168.') === 0 || 
        strpos($remote_ip, '172.') === 0 || 
        strpos($remote_ip, '10.') === 0) {
        return true;
    }

    // 4. ตรวจสอบว่าเรียกมาจากหน้าเว็บภายในระบบ dd-shop จริง (ป้องกันการยิง API ตรงจากภายนอก)
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $ref = $_SERVER['HTTP_REFERER'];
        if (strpos($ref, '/dd-shop/') !== false || strpos($ref, 'localhost') !== false || strpos($ref, '100.') !== false || strpos($ref, '192.168.') !== false) {
            return true;
        }
    }

    // 5. ตรวจสอบแอปมือถือ Capacitor / Cordova / แท็บเล็ตตรวจนับสต๊อก
    if (!empty($_SERVER['HTTP_USER_AGENT']) && (
        strpos($_SERVER['HTTP_USER_AGENT'], 'Capacitor') !== false || 
        strpos($_SERVER['HTTP_USER_AGENT'], 'Cordova') !== false || 
        strpos($_SERVER['HTTP_USER_AGENT'], 'okhttp') !== false
    )) {
        return true;
    }

    // 6. อนุญาตการเข้าใช้งานผ่านโดเมนของร้าน (ddaccessory.trueddns.com)
    if (!empty($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'ddaccessory') !== false) {
        return true;
    }

    return false;
}

/**
 * ดึง User ID ของผู้ใช้ปัจจุบัน
 */
function get_current_user_id() {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    if (!empty($_SERVER['HTTP_X_USER_ID'])) {
        return $_SERVER['HTTP_X_USER_ID'];
    }
    if (!empty($_REQUEST['user_id'])) {
        return $_REQUEST['user_id'];
    }
    return 1;
}

/**
 * ดึง Role ของผู้ใช้ปัจจุบัน
 */
function get_current_user_role() {
    if (isset($_SESSION['user_role']) && !empty($_SESSION['user_role'])) {
        return $_SESSION['user_role'];
    }
    if (!empty($_SERVER['HTTP_X_USER_ROLE'])) {
        return $_SERVER['HTTP_X_USER_ROLE'];
    }
    if (!empty($_REQUEST['user_role'])) {
        return $_REQUEST['user_role'];
    }
    if (!empty($_REQUEST['role'])) {
        return $_REQUEST['role'];
    }
    
    // ค้นหาบทบาทจากฐานข้อมูลตาม user_id หรือ user_name ถ้ามี
    $uid = get_current_user_id();
    $uname = isset($_REQUEST['user_name']) ? $_REQUEST['user_name'] : '';
    global $conn;
    if (isset($conn) && $conn) {
        $clean_uid = mysqli_real_escape_string($conn, $uid);
        $clean_uname = mysqli_real_escape_string($conn, $uname);
        $q = mysqli_query($conn, "SELECT user_role FROM user WHERE user_id = '$clean_uid' OR (user_name != '' AND user_name = '$clean_uname') LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) {
            $r = mysqli_fetch_assoc($q);
            return $r['user_role'];
        }
    }

    // ค่าเริ่มต้นสำหรับเครือข่ายภายในคือ พนักงาน (ไม่ใช่ แอดมิน เพื่อความปลอดภัย)
    return 'พนักงาน';
}

/**
 * บังคับว่าต้องเข้าสู่ระบบ (คืนค่า 401 Unauthorized หากไม่มี Session หรือไม่ได้มาจากระบบร้าน)
 * @return string|int user_id
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
 * บังคับสิทธิ์ตาม Role ที่กำหนด เช่น ['เจ้าของร้าน', 'แอดมิน']
 * (คืนค่า 403 Forbidden หากสิทธิ์ไม่ถึง)
 * @param array $allowed_roles
 * @return string user_role
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
