<?php
header("Content-Type: application/json; charset=utf-8");
echo json_encode([
    'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? '',
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? '',
    'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? '',
    'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
