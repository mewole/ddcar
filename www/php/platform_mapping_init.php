<?php
require_once "db.php";
include_once "auth_guard.php";
require_login();

// 1. Table: platform_channels
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS platform_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_code VARCHAR(50) NOT NULL UNIQUE,
    channel_name VARCHAR(100) NOT NULL,
    platform_type VARCHAR(30) NOT NULL DEFAULT 'shopee',
    customer_name VARCHAR(100) NOT NULL DEFAULT '',
    badge_color VARCHAR(30) DEFAULT '#f97316',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

// 2. Table: platform_sku_mapping
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS platform_sku_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_code VARCHAR(50) NOT NULL,
    platform_sku VARCHAR(100) NOT NULL,
    platform_name VARCHAR(255) DEFAULT '',
    mapping_type ENUM('single', 'bundle') DEFAULT 'single',
    prod_id INT DEFAULT 0,
    target_sku VARCHAR(100) DEFAULT '',
    bundle_json TEXT NULL,
    vat_type ENUM('vat', 'no_vat') DEFAULT 'no_vat',
    status ENUM('mapped', 'unmapped') DEFAULT 'unmapped',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_channel_sku (channel_code, platform_sku),
    KEY idx_channel (channel_code),
    KEY idx_status (status),
    KEY idx_target_sku (target_sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

// 3. Seed Default Channels if empty
$chk_chan = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM platform_channels");
$c_row = $chk_chan ? mysqli_fetch_assoc($chk_chan) : ['cnt' => 0];
if (intval($c_row['cnt']) == 0) {
    $seed_sql = "INSERT IGNORE INTO platform_channels 
        (channel_code, channel_name, platform_type, customer_name, badge_color) VALUES 
        ('shopee_kingkong', 'Shopee (KingKong)', 'shopee', 'shopee kinglong', '#f97316'),
        ('shopee_taengraberd', 'Shopee (แต่งระเบิด)', 'shopee', 'shoppee jet', '#ea580c'),
        ('lazada_main', 'Lazada', 'lazada', 'lazada', '#0284c7'),
        ('tiktok_main', 'TikTok Shop', 'tiktok', 'tiktok', '#111827')";
    @mysqli_query($conn, $seed_sql);
}

// 4. Migrate old product_platform_sku if mapping table is empty
$chk_map = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM platform_sku_mapping");
$m_row = $chk_map ? mysqli_fetch_assoc($chk_map) : ['cnt' => 0];
if (intval($m_row['cnt']) == 0) {
    $old_q = mysqli_query($conn, "SELECT s.*, p.prod_name 
                                  FROM product_platform_sku s 
                                  LEFT JOIN product p ON s.prod_id = p.prod_id");
    if ($old_q) {
        while ($r = mysqli_fetch_assoc($old_q)) {
            $pid = intval($r['prod_id']);
            $pcode = mysqli_real_escape_string($conn, $r['prod_code']);
            $pname = mysqli_real_escape_string($conn, $r['prod_name'] ?: $pcode);
            $vat_tp = ($r['vat_stock'] > 0 && $r['non_vat_stock'] == 0) ? 'vat' : 'no_vat';

            if (!empty($r['shopee_sku'])) {
                $sku = mysqli_real_escape_string($conn, trim($r['shopee_sku']));
                mysqli_query($conn, "INSERT IGNORE INTO platform_sku_mapping 
                    (channel_code, platform_sku, platform_name, mapping_type, prod_id, target_sku, vat_type, status)
                    VALUES ('shopee_kingkong', '$sku', '$pname', 'single', $pid, '$pcode', '$vat_tp', 'mapped')");
            }
            if (!empty($r['lazada_sku'])) {
                $sku = mysqli_real_escape_string($conn, trim($r['lazada_sku']));
                mysqli_query($conn, "INSERT IGNORE INTO platform_sku_mapping 
                    (channel_code, platform_sku, platform_name, mapping_type, prod_id, target_sku, vat_type, status)
                    VALUES ('lazada_main', '$sku', '$pname', 'single', $pid, '$pcode', '$vat_tp', 'mapped')");
            }
            if (!empty($r['tiktok_sku'])) {
                $sku = mysqli_real_escape_string($conn, trim($r['tiktok_sku']));
                mysqli_query($conn, "INSERT IGNORE INTO platform_sku_mapping 
                    (channel_code, platform_sku, platform_name, mapping_type, prod_id, target_sku, vat_type, status)
                    VALUES ('tiktok_main', '$sku', '$pname', 'single', $pid, '$pcode', '$vat_tp', 'mapped')");
            }
        }
    }
}
?>
