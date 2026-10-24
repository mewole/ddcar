<?php
/**
 * restore_product.php - DEPRECATED & BLOCKED
 * ปิดการใช้งานระบบคืนสินค้าแบบแก้บิลเดิมอย่างถาวรตามคำสั่งเจ้าของร้านและมติ 24/10/2026
 * ให้ใช้ระบบรับคืนสินค้า/เคลมใหม่ (RT26-xxxxx) ผ่านหน้า web_return_claim.html
 */
include_once "auth_guard.php";
require_login();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'error'   => 'ระบบคืนสินค้าแบบแก้บิลเดิมถูกระงับใช้งานอย่างถาวรแล้ว กรุณาใช้ระบบรับคืนสินค้าใหม่ (RT26-xxxxx) ผ่านเมนู "รับคืน / เคลมสินค้า"',
    'code'    => 'USE_RT_MODULE_INSTEAD',
    'redirect' => 'web_return_claim.html'
], JSON_UNESCAPED_UNICODE);
exit;
?>