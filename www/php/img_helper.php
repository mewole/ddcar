<?php
/**
 * img_helper.php
 * ฟังก์ชันช่วยจัดการรูปภาพสินค้า: บันทึก Base64 ออกมาเป็นไฟล์รูปจริงใน img/products/
 */

if (!function_exists('save_product_image_from_base64')) {
    function save_product_image_from_base64($b64_string, $prod_id) {
        if (empty($b64_string) || !is_string($b64_string)) {
            return false;
        }

        // หากเป็น path ไฟล์รูปภาพอยู่แล้ว ไม่ต้องแปลงซ้ำ
        if (strpos($b64_string, 'img/') === 0 || strpos($b64_string, 'http') === 0) {
            return $b64_string;
        }

        if (strpos($b64_string, 'data:image') !== 0) {
            return false;
        }

        $target_dir = __DIR__ . '/../img/products';
        if (!is_dir($target_dir)) {
            @mkdir($target_dir, 0777, true);
        }

        $filename = $prod_id . '.jpg';
        $filepath = $target_dir . '/' . $filename;

        $parts = explode(',', $b64_string, 2);
        if (count($parts) < 2) {
            return false;
        }

        $binary = base64_decode($parts[1]);
        if ($binary === false || strlen($binary) === 0) {
            return false;
        }

        $saved = (@file_put_contents($filepath, $binary) !== false);

        if ($saved) {
            return 'img/products/' . $filename;
        }

        return false;
    }
}
?>
