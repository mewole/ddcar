<?php
ini_set('memory_limit', '512M');
set_time_limit(180);

include "db.php";
include_once "auth_guard.php";
require_login();
include_once "img_helper.php";

$chunk = isset($_GET['chunk']) ? intval($_GET['chunk']) : 250;
if ($chunk <= 0) $chunk = 250;

$start_id = isset($_GET['start_id']) ? intval($_GET['start_id']) : 1;
$end_id = $start_id + $chunk - 1;

// ใช้ PRIMARY KEY BETWEEN เพื่อให้เร็วระดับ Milliseconds ไม่ต้อง Full Table Scan
$sql = "SELECT prod_id, img FROM product WHERE prod_id BETWEEN $start_id AND $end_id";
$res = mysqli_query($conn, $sql);

$migrated = 0;
$bytes_saved = 0;

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $img = $row['img'];
        if (!empty($img) && strpos($img, 'data:image') === 0) {
            $pid = intval($row['prod_id']);
            $len = strlen($img);
            $rel = save_product_image_from_base64($img, $pid);
            if ($rel) {
                $stmt = mysqli_prepare($conn, "UPDATE product SET img = ? WHERE prod_id = ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "si", $rel, $pid);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $migrated++;
                    $bytes_saved += $len;
                }
            }
        }
    }
}

$is_done = ($end_id >= 14000);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'done' => $is_done,
    'start_id' => $start_id,
    'end_id' => $end_id,
    'next_id' => $end_id + 1,
    'migrated' => $migrated,
    'bytes_saved_mb' => round($bytes_saved / 1024 / 1024, 2)
]);

mysqli_close($conn);
?>
