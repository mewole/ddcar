<?php
header("Content-Type: text/plain; charset=UTF-8");
include "db.php";

$indexes = [
    // 1. sale_order & items
    "sale_order" => [
        "idx_so_datetime" => "ADD INDEX idx_so_datetime (date_time)",
        "idx_so_customer" => "ADD INDEX idx_so_customer (customer_id)",
        "idx_so_number" => "ADD INDEX idx_so_number (order_number)"
    ],
    "sale_order_item" => [
        "idx_soi_order_id" => "ADD INDEX idx_soi_order_id (order_id)",
        "idx_soi_prod_id" => "ADD INDEX idx_soi_prod_id (prod_id)",
        "idx_soi_order_prod" => "ADD INDEX idx_soi_order_prod (order_id, prod_id)"
    ],
    // 2. stock_in & items
    "stock_in" => [
        "idx_si_datetime" => "ADD INDEX idx_si_datetime (date_time)",
        "idx_si_number" => "ADD INDEX idx_si_number (stock_number)",
        "idx_si_ware" => "ADD INDEX idx_si_ware (ware_id)"
    ],
    "stock_in_item" => [
        "idx_sii_stock_id" => "ADD INDEX idx_sii_stock_id (stock_id)",
        "idx_sii_prod_id" => "ADD INDEX idx_sii_prod_id (prod_id)",
        "idx_sii_source" => "ADD INDEX idx_sii_source (source_from(50))"
    ],
    // 3. stock_out & items
    "stock_out" => [
        "idx_so_datetime" => "ADD INDEX idx_so_datetime (date_time)",
        "idx_so_number" => "ADD INDEX idx_so_number (stock_out_number)",
        "idx_so_ware" => "ADD INDEX idx_so_ware (ware_id)"
    ],
    "stock_out_item" => [
        "idx_soi_stock_out_id" => "ADD INDEX idx_soi_stock_out_id (stock_out_id)",
        "idx_soi_prod_id" => "ADD INDEX idx_soi_prod_id (prod_id)"
    ],
    // 4. transfer_record & items
    "transfer_record" => [
        "idx_tr_datetime" => "ADD INDEX idx_tr_datetime (date_time)",
        "idx_tr_shop" => "ADD INDEX idx_tr_shop (shop_id)",
        "idx_tr_branch" => "ADD INDEX idx_tr_branch (branch_id)",
        "idx_tr_number" => "ADD INDEX idx_tr_number (tf_number)"
    ],
    "transfer_record_item" => [
        "idx_tri_tf_id" => "ADD INDEX idx_tri_tf_id (tf_id)",
        "idx_tri_prod_id" => "ADD INDEX idx_tri_prod_id (prod_id)"
    ],
    // 5. transfer_in & items
    "transfer_in" => [
        "idx_ti_datetime" => "ADD INDEX idx_ti_datetime (date_time)",
        "idx_ti_shop" => "ADD INDEX idx_ti_shop (shop_id)",
        "idx_ti_number" => "ADD INDEX idx_ti_number (tran_number)"
    ],
    "transfer_in_item" => [
        "idx_tii_tran_id" => "ADD INDEX idx_tii_tran_id (tran_id)",
        "idx_tii_prod_id" => "ADD INDEX idx_tii_prod_id (prod_id)",
        "idx_tii_source" => "ADD INDEX idx_tii_source (source_from(50))"
    ],
    // 6. credit & customer
    "credit" => [
        "idx_cr_datetime" => "ADD INDEX idx_cr_datetime (date_time)",
        "idx_cr_cus_id" => "ADD INDEX idx_cr_cus_id (cus_id)"
    ],
    "customer" => [
        "idx_cus_name" => "ADD INDEX idx_cus_name (cus_name)"
    ],
    // 7. warehouse & shop (Stock lookups)
    "warehouse" => [
        "idx_wh_prod_ware" => "ADD INDEX idx_wh_prod_ware (prod_id, ware_id)"
    ],
    "shop" => [
        "idx_sh_prod_shop" => "ADD INDEX idx_sh_prod_shop (prod_id, shop_id)"
    ],
    "detail_cus" => [
        "idx_dc_prod_cus" => "ADD INDEX idx_dc_prod_cus (prod_id, cus_id)"
    ]
];

$total_added = 0;
foreach ($indexes as $table => $table_idx) {
    // Check existing indexes
    $res = mysqli_query($conn, "SHOW INDEX FROM `$table`");
    $existing = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $existing[] = $r['Key_name'];
        }
    }
    foreach ($table_idx as $key_name => $sql_clause) {
        if (in_array($key_name, $existing)) {
            echo "[$table] $key_name: already exists\n";
            continue;
        }
        $t0 = microtime(true);
        $alter_sql = "ALTER TABLE `$table` $sql_clause";
        $ok = mysqli_query($conn, $alter_sql);
        $ms = round((microtime(true) - $t0) * 1000, 2);
        if ($ok) {
            echo "[$table] $key_name: ADDED in {$ms}ms\n";
            $total_added++;
        } else {
            echo "[$table] $key_name: ERROR - " . mysqli_error($conn) . "\n";
        }
    }
}
echo "\nFinished. Total indexes added: $total_added\n";
?>
