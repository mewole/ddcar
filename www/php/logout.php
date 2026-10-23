<?php
include_once "auth_guard.php";

/**
 * logout.php
 * ทำลาย Session บนเซิร์ฟเวอร์ และล้าง Session Cookie อย่างปลอดภัย
 */
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'message' => 'ออกจากระบบเรียบร้อยแล้ว']);
?>
