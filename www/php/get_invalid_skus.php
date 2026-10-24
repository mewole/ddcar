<?php
/**
 * get_invalid_skus.php
 * ดึงรายการรหัสสินค้า (SKU) ที่ไม่ถูกต้องตามมาตรฐาน ^[A-Z0-9-]+$ 
 * พร้อมวิเคราะห์สาเหตุและเสนอแนะรหัสที่ถูกต้อง (Suggested SKU)
 */
include_once "auth_guard.php";
require_login();

header('Content-Type: application/json; charset=utf-8');
include "db.php";

// โหลดรายการ prod_code ที่มีอยู่ทั้งหมดเพื่อเช็คการชนกันของรหัสแนะนำ (Collision Detection)
$all_codes_res = mysqli_query($conn, "SELECT prod_id, prod_code FROM product WHERE prod_code IS NOT NULL AND prod_code != ''");
$code_map = [];
if ($all_codes_res) {
    while ($r = mysqli_fetch_assoc($all_codes_res)) {
        $code_map[$r['prod_code']] = intval($r['prod_id']);
    }
}

// ดึงเฉพาะ 382 รายการที่ผิดกติกาโดยตรงจากฐานข้อมูลด้วย REGEXP BINARY ประหยัด Memory 100%
$query = "SELECT prod_id, prod_code, prod_name, img, prod_price, prod_pricesend 
          FROM product 
          WHERE NOT (prod_code REGEXP BINARY '^[A-Z0-9-]+$')
          ORDER BY prod_id ASC";
$result = mysqli_query($conn, $query);

$invalid_items = [];
$auto_clean_count = 0;
$manual_count = 0;

if ($result) {
    while ($item = mysqli_fetch_assoc($result)) {
        $code = $item['prod_code'] ?? '';
        
        $issues = [];
        if (preg_match('/\s/', $code)) {
            $issues[] = 'มีช่องว่าง';
        }
        if (preg_match('/[a-z]/', $code)) {
            $issues[] = 'มีตัวพิมพ์เล็ก';
        }
        if (strpos($code, '.') !== false) {
            $issues[] = 'มีจุด (.)';
        }
        if (strpos($code, '_') !== false) {
            $issues[] = 'มีขีดล่าง (_)';
        }
        if (strpos($code, '/') !== false) {
            $issues[] = 'มีเครื่องหมายทับ (/)';
        }
        if (preg_match('/[\x{0E00}-\x{0E7F}]/u', $code)) {
            $issues[] = 'มีอักษรหรือสระภาษาไทย';
        }
        if (empty($issues)) {
            $issues[] = 'มีอักขระพิเศษ';
        }

        // สร้างรหัสแนะนำ (Suggested SKU)
        $suggested = strtoupper(trim($code));
        $suggested = str_replace(' ', '', $suggested);
        $suggested = preg_replace('/[\x{0E00}-\x{0E7F}]/u', '', $suggested);
        $suggested = str_replace(['.', '_', '/'], '', $suggested);

        $can_auto = false;
        $reason_manual = '';

        if (empty($suggested)) {
            $suggested = 'SKU-' . $item['prod_id'];
            $reason_manual = 'ต้องกำหนดรหัสใหม่ (ลบอักษรผิดแล้วว่างเปล่า)';
        } elseif (!preg_match('/^[A-Z0-9\-]+$/', $suggested)) {
            $reason_manual = 'ยังมีอักขระที่ต้องแก้ไขด้วยตนเอง';
        } elseif (isset($code_map[$suggested]) && $code_map[$suggested] !== intval($item['prod_id'])) {
            $reason_manual = "รหัสแนะนำ ($suggested) ชนกับสินค้าอื่น (#" . $code_map[$suggested] . ")";
        } else {
            $can_auto = true;
        }

        if ($can_auto) {
            $auto_clean_count++;
        } else {
            $manual_count++;
        }

        $invalid_items[] = [
            'prod_id'        => intval($item['prod_id']),
            'prod_code'      => $code,
            'prod_name'      => $item['prod_name'] ?? '',
            'img'            => $item['img'] ?? '',
            'price'          => floatval($item['prod_price'] ?? 0),
            'issues'         => implode(', ', $issues),
            'suggested_sku'  => $suggested,
            'can_auto_clean' => $can_auto,
            'manual_reason'  => $reason_manual
        ];
    }
}

mysqli_close($conn);

echo json_encode([
    'success'        => true,
    'total'          => count($invalid_items),
    'auto_cleanable' => $auto_clean_count,
    'needs_manual'   => $manual_count,
    'data'           => $invalid_items
], JSON_UNESCAPED_UNICODE);
?>
