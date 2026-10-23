<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน']);
/**
 * backup_db.php
 * ระบบสำรองฐานข้อมูล MySQL อัตโนมัติ (Automated Backup & Retention Policy)
 * รองรับทั้งเรียกผ่าน Cron/Task Scheduler, API Ajax, และดาวน์โหลดผ่านเบราว์เซอร์
 */
ini_set('memory_limit', '512M');
set_time_limit(600);

include "db.php";
// หากไม่ได้รันผ่าน Command Line (CLI) บนเครื่องเซิร์ฟเวอร์ ให้บังคับตรวจสอบสิทธิ์เจ้าของร้าน/แอดมินเท่านั้น
if (php_sapi_name() !== 'cli') {
    }

$backup_dir = __DIR__ . '/../_backup_db';
if (!is_dir($backup_dir)) {
    @mkdir($backup_dir, 0777, true);
    // สร้าง .htaccess ป้องกันคนภายนอกเข้ามาดาวน์โหลดไฟล์ตรงๆ
    @file_put_contents($backup_dir . '/.htaccess', "Deny from all\n");
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'backup');

// Action 1: ดาวน์โหลดไฟล์สำรอง (เฉพาะแอดมิน/เจ้าของร้าน)
if ($action === 'download') {
    $file = isset($_GET['file']) ? basename($_GET['file']) : '';

    // ตรวจสอบชื่อไฟล์อย่างเข้มงวด (ต้องขึ้นต้นด้วย ddcar_backup_ และลงท้ายด้วย .sql หรือ .sql.gz เท่านั้น)
    if (!preg_match('/^ddcar_backup_[0-9_]+\.sql(\.gz)?$/', $file)) {
        header("HTTP/1.1 400 Bad Request");
        echo "Invalid backup filename format.";
        exit;
    }

    $filepath = $backup_dir . '/' . $file;
    if (file_exists($filepath) && is_file($filepath)) {
        // บันทึก Log การดาวน์โหลดฐานข้อมูลเพื่อความปลอดภัย
        $curr_user = get_current_user_session();
        $log_user = $curr_user ? ($curr_user['user_name'] . ' (ID:' . $curr_user['user_id'] . ')') : 'CLI/System';
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'UNKNOWN';
        @file_put_contents($backup_dir . '/download.log', date('Y-m-d H:i:s') . " | File: $file | By: $log_user | IP: $ip\n", FILE_APPEND);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "File not found.";
        exit;
    }
}

// Action 2: แสดงรายการไฟล์สำรองที่มีในระบบ
if ($action === 'list') {
    header('Content-Type: application/json; charset=utf-8');
    $files = array();
    if (is_dir($backup_dir)) {
        $scan = scandir($backup_dir);
        foreach ($scan as $f) {
            if (strpos($f, 'ddcar_backup_') === 0) {
                $path = $backup_dir . '/' . $f;
                $files[] = array(
                    'filename' => $f,
                    'size_mb' => round(filesize($path) / 1024 / 1024, 2),
                    'created_time' => date('Y-m-d H:i:s', filemtime($path)),
                    'age_days' => round((time() - filemtime($path)) / 86400, 1)
                );
            }
        }
        // เรียงจากไฟล์ใหม่สุดไปเก่าสุด
        usort($files, function($a, $b) {
            return strtotime($b['created_time']) - strtotime($a['created_time']);
        });
    }
    echo json_encode(['success' => true, 'files' => $files]);
    exit;
}

// Action 3: ดำเนินการสำรองฐานข้อมูล (Backup)
header('Content-Type: application/json; charset=utf-8');

$timestamp = date('Ymd_His');
$use_gzip = function_exists('gzopen');
$ext = $use_gzip ? '.sql.gz' : '.sql';
$filename = 'ddcar_backup_' . $timestamp . $ext;
$filepath = $backup_dir . '/' . $filename;

$handle = $use_gzip ? gzopen($filepath, 'w9') : fopen($filepath, 'w');
if (!$handle) {
    echo json_encode(['success' => false, 'error' => 'Cannot create backup file in _backup_db directory.']);
    exit;
}

function write_backup_chunk($handle, $data, $use_gzip) {
    if ($use_gzip) {
        gzwrite($handle, $data);
    } else {
        fwrite($handle, $data);
    }
}

// เขียน Header ข้อมูลการสำรอง
$header_text = "-- ========================================================\n" .
               "-- DD MOTOR SPORT (ddcar) Database Backup\n" .
               "-- Backup Date: " . date('Y-m-d H:i:s') . "\n" .
               "-- PHP Version: " . phpversion() . "\n" .
               "-- MySQL Server: " . mysqli_get_server_info($conn) . "\n" .
               "-- ========================================================\n\n" .
               "SET FOREIGN_KEY_CHECKS=0;\n" .
               "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n" .
               "SET time_zone = \"+07:00\";\n\n";
write_backup_chunk($handle, $header_text, $use_gzip);

// ดึงรายชื่อตารางทั้งหมด
$tables = array();
$result_tables = mysqli_query($conn, "SHOW TABLES");
while ($trow = mysqli_fetch_row($result_tables)) {
    $tables[] = $trow[0];
}

$tables_backed_up = 0;
$total_rows_backed_up = 0;

foreach ($tables as $table) {
    // 1. Structure (CREATE TABLE)
    write_backup_chunk($handle, "\n-- Table structure for `$table`\nDROP TABLE IF EXISTS `$table`;\n", $use_gzip);
    $res_create = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
    if ($res_create && $row_create = mysqli_fetch_row($res_create)) {
        write_backup_chunk($handle, $row_create[1] . ";\n\n", $use_gzip);
    }

    // 2. Data (INSERT INTO)
    $res_count = mysqli_query($conn, "SELECT COUNT(*) FROM `$table`");
    $row_count = $res_count ? mysqli_fetch_row($res_count) : [0];
    $total_table_rows = intval($row_count[0]);

    if ($total_table_rows > 0) {
        write_backup_chunk($handle, "-- Dumping data for `$table` (" . $total_table_rows . " rows)\n", $use_gzip);

        $limit = 500;
        $offset = 0;

        while ($offset < $total_table_rows) {
            $res_rows = mysqli_query($conn, "SELECT * FROM `$table` LIMIT $limit OFFSET $offset");
            if ($res_rows && mysqli_num_rows($res_rows) > 0) {
                $num_fields = mysqli_num_fields($res_rows);

                while ($row_data = mysqli_fetch_row($res_rows)) {
                    $insert = "INSERT INTO `$table` VALUES (";
                    for ($j = 0; $j < $num_fields; $j++) {
                        if (is_null($row_data[$j])) {
                            $insert .= "NULL";
                        } else {
                            $insert .= "'" . mysqli_real_escape_string($conn, $row_data[$j]) . "'";
                        }
                        if ($j < ($num_fields - 1)) {
                            $insert .= ", ";
                        }
                    }
                    $insert .= ");\n";
                    write_backup_chunk($handle, $insert, $use_gzip);
                    $total_rows_backed_up++;
                }
            }
            $offset += $limit;
        }
        write_backup_chunk($handle, "\n", $use_gzip);
    }

    $tables_backed_up++;
}

write_backup_chunk($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n-- Backup Completed Successfully\n", $use_gzip);

if ($use_gzip) {
    gzclose($handle);
} else {
    fclose($handle);
}

// นโยบายลบไฟล์สำรองเก่าเกิน 14 วัน (Prune Retention Policy)
$retention_days = 14;
$pruned_files = array();
$cutoff_time = time() - ($retention_days * 86400);

if (is_dir($backup_dir)) {
    $scan = scandir($backup_dir);
    foreach ($scan as $f) {
        if (strpos($f, 'ddcar_backup_') === 0) {
            $fpath = $backup_dir . '/' . $f;
            if (filemtime($fpath) < $cutoff_time) {
                @unlink($fpath);
                $pruned_files[] = $f;
            }
        }
    }
}

$filesize = filesize($filepath);

echo json_encode([
    'success' => true,
    'filename' => $filename,
    'filesize_mb' => round($filesize / 1024 / 1024, 2),
    'tables_count' => $tables_backed_up,
    'rows_count' => $total_rows_backed_up,
    'compressed' => $use_gzip,
    'download_url' => 'php/backup_db.php?action=download&file=' . urlencode($filename),
    'pruned_old_backups' => $pruned_files,
    'retention_days' => $retention_days,
    'created_at' => date('Y-m-d H:i:s')
]);

mysqli_close($conn);
?>
