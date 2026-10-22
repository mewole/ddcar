<?php
/**
 * login.php
 * ตรวจสอบชื่อผู้ใช้และรหัสผ่าน พร้อมสร้าง Server Session ที่ปลอดภัย
 */
include "db.php";
include_once "auth_guard.php";

$name = isset($_POST['name']) ? trim($_POST['name']) : '';	
$pass = isset($_POST['pass']) ? $_POST['pass'] : '';	


// ตรวจสอบระบบป้องกันการสุ่มรหัสผ่าน (Brute-force lockout 5 ครั้ง ล็อก 15 นาที)
$client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
$lock_dir = __DIR__ . '/../_login_security';
if (!is_dir($lock_dir)) { @mkdir($lock_dir, 0777, true); @file_put_contents($lock_dir . '/.htaccess', "Deny from all\n"); }
$lock_file = $lock_dir . '/ip_' . md5($client_ip) . '.json';

$attempt_data = ['count' => 0, 'lock_until' => 0];
if (file_exists($lock_file)) {
    $attempt_data = json_decode(file_get_contents($lock_file), true) ?: ['count' => 0, 'lock_until' => 0];
}

if ($attempt_data['lock_until'] > time()) {
    $remaining_min = ceil(($attempt_data['lock_until'] - time()) / 60);
    echo "locked:" . $remaining_min;
    exit;
}

if (empty($name) || empty($pass)) {
    echo "fail";
    exit;
}

// ใช้ Prepared Statement ป้องกัน SQL Injection
$stmt = mysqli_prepare($conn, "SELECT user_id, user_name, pass, user_role FROM user WHERE user_name = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $name);
mysqli_stmt_execute($stmt);
$res_pass = mysqli_stmt_get_result($stmt);

if ($res_pass && mysqli_num_rows($res_pass) > 0) {
    $row = mysqli_fetch_assoc($res_pass);
    if (password_verify($pass, $row['pass'])) {
        // สร้าง Session ID ใหม่เพื่อป้องกัน Session Fixation
        session_regenerate_id(true);
        @unlink($lock_file);

        // บันทึกสถานะผู้ใช้บน Server Session
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['user_name'] = $row['user_name'];
        $_SESSION['user_role'] = $row['user_role'];

        // ส่งข้อมูลที่จำเป็นกลับไปยัง Frontend (ไม่ส่ง Hash รหัสผ่านเด็ดขาด)
        $output = array(
            array(
                'user_id'   => $row['user_id'],
                'user_name' => $row['user_name'],
                'user_role' => $row['user_role']
            )
        );
        echo json_encode($output);
    } else {
        echo "fail";
    }
} else {
    echo "fail";
}

mysqli_close($conn);
?>