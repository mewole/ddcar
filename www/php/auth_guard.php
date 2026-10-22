<?php
/**
 * DD MOTOR SPORT (ddcar) - Centralized Server-Side Auth & Role-Based Access Control Guard
 * จัดการ Session บนเซิร์ฟเวอร์ และตรวจสอบสิทธิ์ผู้ใช้งาน (RBAC) ป้องกันการเข้าถึงข้อมูลโดยไม่ได้รับอนุญาต
 */

if (session_status() === PHP_SESSION_NONE) {
    // ตรวจสอบโปรโตคอล HTTPS
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
             || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 86400 * 7, // 7 วัน
            'path'     => '/',
            'secure'   => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(86400 * 7, '/', '', $is_https, true);
    }

    session_start();
}

/**
 * ตรวจสอบว่าผู้ใช้งานเข้าสู่ระบบอยู่หรือไม่
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * บังคับว่าต้องเข้าสู่ระบบ (คืนค่า 401 Unauthorized หากไม่มี Session)
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
        ]);
        exit;
    }
    return $_SESSION['user_id'];
}

/**
 * บังคับสิทธิ์ตาม Role ที่กำหนด เช่น ['เจ้าของร้าน', 'แอดมิน']
 * (คืนค่า 403 Forbidden หากสิทธิ์ไม่ถึง)
 * @param array $allowed_roles
 * @return string user_role
 */
function require_role($allowed_roles = ['เจ้าของร้าน', 'แอดมิน']) {
    $uid = require_login();
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';

    if (!in_array($role, $allowed_roles)) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Forbidden: คุณไม่มีสิทธิ์เข้าถึงข้อมูลหรือทำรายการนี้ (เฉพาะเจ้าของร้านหรือแอดมิน)',
            'code'    => 'ROLE_FORBIDDEN'
        ]);
        exit;
    }
    return $role;
}

/**
 * ดึงข้อมูลผู้ใช้ปัจจุบันจาก Server Session
 * @return array|null
 */
function get_current_user_session() {
    if (!is_logged_in()) return null;
    return [
        'user_id'   => $_SESSION['user_id'],
        'user_name' => isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '',
        'user_role' => isset($_SESSION['user_role']) ? $_SESSION['user_role'] : ''
    ];
}
?>
