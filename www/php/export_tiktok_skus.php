<?php
include_once "auth_guard.php";
require_login();
/**
 * DDCar - Export TikTok SKUs for Batch Editing
 * Exports a CSV file formatted for TikTok Shop Seller Center Batch Edit
 * Includes UTF-8 BOM for seamless Thai display in Excel on Windows.
 */
require_once "db.php";

$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'unmapped';

$where = ["channel_code = 'tiktok_main'"];
if ($filter_status === 'unmapped') {
    $where[] = "status = 'unmapped'";
} elseif ($filter_status === 'mapped') {
    $where[] = "status = 'mapped'";
}

$where_sql = implode(" AND ", $where);
$sql = "SELECT id, platform_sku, platform_name, target_sku, mapping_type, status, image_url, product_url 
        FROM platform_sku_mapping 
        WHERE $where_sql 
        ORDER BY id ASC";
$res = mysqli_query($conn, $sql);

$filename = "tiktok_sku_export_" . date("Ymd_His") . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Output UTF-8 BOM for Microsoft Excel Windows compatibility
echo "\xEF\xBB\xBF";

$fp = fopen('php://output', 'w');

// Header row
fputcsv($fp, [
    'Product ID (รหัสสินค้า TikTok)',
    'Seller SKU (รหัสคลัง DDCar)',
    'ชื่อสินค้าบน TikTok',
    'ตัวเลือกสินค้า (Variation)',
    'สถานะใน DDCar',
    'ลิงก์แก้ไขบน TikTok',
    'ลิงก์รูปภาพสินค้า'
]);

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $sku = $row['platform_sku'] ?? '';
        $name = $row['platform_name'] ?? '';
        $target = $row['target_sku'] ?? '';
        $prod_url = $row['product_url'] ?? '';
        $img_url = $row['image_url'] ?? '';
        
        // Extract TikTok Product ID
        $tiktok_id = '';
        if (preg_match('/ID\s*([0-9]{15,22})/i', $sku, $m)) {
            $tiktok_id = $m[1];
        } elseif (preg_match('/\b([0-9]{15,22})\b/', $sku, $m)) {
            $tiktok_id = $m[1];
        } elseif (preg_match('/edit\/([0-9]{15,22})/i', $prod_url, $m)) {
            $tiktok_id = $m[1];
        }
        
        // Extract variation from [brackets]
        $var_text = '';
        $base_name = $name;
        if (preg_match('/^(.*?)\[(.*?)\]$/', $name, $m)) {
            $base_name = trim($m[1]);
            $var_text = trim($m[2]);
        }
        
        // If image URL is relative, construct full URL
        if (!empty($img_url) && !preg_match('/^https?:\/\//i', $img_url)) {
            $img_url = "http://100.77.57.16:8083/dd-shop/" . ltrim($img_url, '/');
        }

        fputcsv($fp, [
            $tiktok_id ? ("\t" . $tiktok_id) : $sku, // Tab prefix prevents Excel from scientific notation on 19-digit numbers
            $target,
            $base_name,
            $var_text,
            $row['status'] === 'mapped' ? 'จับคู่แล้ว' : 'ยังไม่ได้ลง SKU',
            $prod_url,
            $img_url
        ]);
    }
}

fclose($fp);
exit;
