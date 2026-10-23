<?php
include_once "auth_guard.php";
require_staff_or_admin();

require_once "db.php";
@mysqli_report(MYSQLI_REPORT_OFF);
header("Content-Type: application/json; charset=utf-8");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?: $_POST;

if (empty($data)) {
    echo json_encode(["success" => false, "message" => "ไม่มีข้อมูลสำหรับบันทึก"]);
    exit;
}

// Support single object or array of objects
$items = isset($data['items']) ? $data['items'] : [$data];
$saved_count = 0;
$errors = [];

foreach ($items as $entry) {
    $id = isset($entry['id']) ? intval($entry['id']) : 0;
    $channel_code = isset($entry['channel_code']) ? mysqli_real_escape_string($conn, trim($entry['channel_code'])) : '';
    $platform_sku = isset($entry['platform_sku']) ? mysqli_real_escape_string($conn, trim($entry['platform_sku'])) : '';
    $platform_name = isset($entry['platform_name']) ? mysqli_real_escape_string($conn, trim($entry['platform_name'])) : '';
    $mapping_type = (isset($entry['mapping_type']) && $entry['mapping_type'] === 'bundle') ? 'bundle' : 'single';
    $vat_type = (isset($entry['vat_type']) && $entry['vat_type'] === 'vat') ? 'vat' : 'no_vat';
    $mapping_source = isset($entry['mapping_source']) ? mysqli_real_escape_string($conn, trim($entry['mapping_source'])) : 'manual';

    if (empty($channel_code) || empty($platform_sku)) {
        continue;
    }

    $prod_id = 0;
    $target_sku = '';
    $bundle_json = null;
    $status = 'unmapped';

    if ($mapping_type === 'single') {
        $target_sku = isset($entry['target_sku']) ? mysqli_real_escape_string($conn, trim($entry['target_sku'])) : '';
        if (!empty($target_sku)) {
            $pq = mysqli_query($conn, "SELECT prod_id, prod_name FROM product WHERE prod_code = '$target_sku' LIMIT 1");
            if ($pq && mysqli_num_rows($pq) > 0) {
                $p_row = mysqli_fetch_assoc($pq);
                $prod_id = intval($p_row['prod_id']);
                $status = 'mapped';
            } else {
                // SKU might be newly typed without prod_id, still treat as mapped if explicitly set
                $status = 'mapped';
            }
        }
    } else {
        // Bundle Mapping
        $target_sku = isset($entry['bundle_sku']) ? mysqli_real_escape_string($conn, trim($entry['bundle_sku'])) : (isset($entry['target_sku']) ? mysqli_real_escape_string($conn, trim($entry['target_sku'])) : '');
        if (empty($target_sku)) {
            $target_sku = $platform_sku;
        }

        $components = isset($entry['bundle_components']) ? $entry['bundle_components'] : [];
        if (!empty($components) && is_array($components)) {
            $cleaned_comps = [];
            foreach ($components as $c) {
                $c_sku = trim($c['sku'] ?? '');
                $c_qty = max(1, intval($c['qty'] ?? 1));
                $c_name = trim($c['name'] ?? '');
                if (!empty($c_sku)) {
                    $cleaned_comps[] = [
                        'sku' => $c_sku,
                        'name' => $c_name,
                        'qty' => $c_qty
                    ];
                }
            }

            if (!empty($cleaned_comps)) {
                $bundle_json = mysqli_real_escape_string($conn, json_encode($cleaned_comps, JSON_UNESCAPED_UNICODE));
                $status = 'mapped';

                // Sync with bundle_recipes table for auto-deduct support in save_order.php
                @mysqli_query($conn, "DELETE FROM bundle_recipes WHERE bundle_sku = '$target_sku' OR bundle_sku = '$platform_sku'");
                foreach ($cleaned_comps as $comp) {
                    $c_sku_esc = mysqli_real_escape_string($conn, $comp['sku']);
                    $r_qty = intval($comp['qty']);
                    $c_nm_esc = mysqli_real_escape_string($conn, $comp['name']);
                    @mysqli_query($conn, "INSERT INTO bundle_recipes 
                        (bundle_sku, child_sku, ratio, is_hidden_bom, child_name) 
                        VALUES ('$target_sku', '$c_sku_esc', $r_qty, 1, '$c_nm_esc')");
                    if ($target_sku !== $platform_sku) {
                        @mysqli_query($conn, "INSERT INTO bundle_recipes 
                            (bundle_sku, child_sku, ratio, is_hidden_bom, child_name) 
                            VALUES ('$platform_sku', '$c_sku_esc', $r_qty, 1, '$c_nm_esc')");
                    }
                }
            } else if (!empty($target_sku)) {
                $status = 'mapped';
            }
        } else if (!empty($target_sku)) {
            $status = 'mapped';
        }
    }

    $bundle_sql_val = $bundle_json !== null ? "'$bundle_json'" : "NULL";

    if ($id > 0) {
        $sql = "UPDATE platform_sku_mapping SET 
                channel_code = '$channel_code',
                platform_sku = '$platform_sku',
                platform_name = '$platform_name',
                mapping_type = '$mapping_type',
                prod_id = $prod_id,
                target_sku = '$target_sku',
                bundle_json = $bundle_sql_val,
                vat_type = '$vat_type',
                status = '$status',
                mapping_source = '$mapping_source'
                WHERE id = $id";
    } else {
        $sql = "INSERT INTO platform_sku_mapping 
                (channel_code, platform_sku, platform_name, mapping_type, prod_id, target_sku, bundle_json, vat_type, status, mapping_source)
                VALUES 
                ('$channel_code', '$platform_sku', '$platform_name', '$mapping_type', $prod_id, '$target_sku', $bundle_sql_val, '$vat_type', '$status', '$mapping_source')
                ON DUPLICATE KEY UPDATE 
                platform_name = '$platform_name',
                mapping_type = '$mapping_type',
                prod_id = $prod_id,
                target_sku = '$target_sku',
                bundle_json = $bundle_sql_val,
                vat_type = '$vat_type',
                status = '$status',
                mapping_source = '$mapping_source'";
    }

    if (mysqli_query($conn, $sql)) {
        $saved_count++;
        // Also update any matching unmapped platform_order_items
        if ($status === 'mapped') {
            @mysqli_query($conn, "UPDATE platform_order_items 
                                  SET match_status = 'matched', prod_id = $prod_id, target_sku = '$target_sku' 
                                  WHERE channel_code = '$channel_code' AND platform_sku = '$platform_sku'");
        }
    } else {
        $errors[] = mysqli_error($conn);
    }
}

echo json_encode([
    "success" => $saved_count > 0,
    "saved_count" => $saved_count,
    "errors" => $errors,
    "message" => "บันทึกการจับคู่เรียบร้อย $saved_count รายการ"
], JSON_UNESCAPED_UNICODE);
?>
