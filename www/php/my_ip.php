<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
header("Content-Type: application/json");
echo json_encode([
    "REMOTE_ADDR" => $_SERVER['REMOTE_ADDR'] ?? '',
    "HTTP_X_FORWARDED_FOR" => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''
]);
?>
