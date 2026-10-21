<?php
/**
 * migrate_supplier_sku.php
 * Migration script for Supplier Header and Supplier SKU Mapping
 */
header('Content-Type: application/json; charset=utf-8');
include "db.php";

$log = [];

function addColumnIfNotExists($conn, $table, $column, $definition) {
    global $log;
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($res && mysqli_num_rows($res) == 0) {
        $alterSql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        if (mysqli_query($conn, $alterSql)) {
            $log[] = "Added column $column to $table successfully.";
        } else {
            $log[] = "Error adding column $column to $table: " . mysqli_error($conn);
        }
    } else {
        $log[] = "Column $column already exists in $table.";
    }
}

// 1. Alter stock_in table
addColumnIfNotExists($conn, 'stock_in', 'supplier_name', 'VARCHAR(100) NULL DEFAULT NULL AFTER user_id');
addColumnIfNotExists($conn, 'stock_in', 'ref_invoice', 'VARCHAR(100) NULL DEFAULT NULL AFTER supplier_name');

// 2. Alter transfer_in table
addColumnIfNotExists($conn, 'transfer_in', 'supplier_name', 'VARCHAR(100) NULL DEFAULT NULL AFTER user_id');
addColumnIfNotExists($conn, 'transfer_in', 'ref_invoice', 'VARCHAR(100) NULL DEFAULT NULL AFTER supplier_name');

// 3. Alter stock_in_item and transfer_in_item to optionally store supplier_sku
addColumnIfNotExists($conn, 'stock_in_item', 'supplier_sku', 'VARCHAR(100) NULL DEFAULT NULL AFTER source_from');
addColumnIfNotExists($conn, 'transfer_in_item', 'supplier_sku', 'VARCHAR(100) NULL DEFAULT NULL AFTER source_from');

// 4. Create product_supplier_sku table
$sqlTable = "CREATE TABLE IF NOT EXISTS product_supplier_sku (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    prod_id INT(11) NOT NULL,
    supplier_name VARCHAR(100) NOT NULL,
    supplier_sku VARCHAR(100) NOT NULL,
    last_cost DECIMAL(10,2) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_supplier_sku (supplier_name, supplier_sku),
    KEY idx_prod_id (prod_id),
    KEY idx_supplier_name (supplier_name),
    KEY idx_sku_only (supplier_sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

if (mysqli_query($conn, $sqlTable)) {
    $log[] = "Table product_supplier_sku created or already exists.";
} else {
    $log[] = "Error creating table product_supplier_sku: " . mysqli_error($conn);
}

// 5. Backfill historical supplier_name into stock_in / transfer_in from existing items if empty
$backfill_sql = "UPDATE stock_in s
                 JOIN (
                     SELECT stock_id, GROUP_CONCAT(DISTINCT TRIM(source_from) SEPARATOR ', ') AS src
                     FROM stock_in_item
                     WHERE source_from IS NOT NULL AND TRIM(source_from) != ''
                     GROUP BY stock_id
                 ) sub ON s.stock_id = sub.stock_id
                 SET s.supplier_name = sub.src
                 WHERE (s.supplier_name IS NULL OR TRIM(s.supplier_name) = '')";
if (mysqli_query($conn, $backfill_sql)) {
    $log[] = "Backfilled historical supplier_name in stock_in (" . mysqli_affected_rows($conn) . " rows).";
} else {
    $log[] = "Backfill stock_in note: " . mysqli_error($conn);
}

$backfill_tran = "UPDATE transfer_in t
                  JOIN (
                      SELECT tran_id, GROUP_CONCAT(DISTINCT TRIM(source_from) SEPARATOR ', ') AS src
                      FROM transfer_in_item
                      WHERE source_from IS NOT NULL AND TRIM(source_from) != ''
                      GROUP BY tran_id
                  ) sub ON t.tran_id = sub.tran_id
                  SET t.supplier_name = sub.src
                  WHERE (t.supplier_name IS NULL OR TRIM(t.supplier_name) = '')";
if (mysqli_query($conn, $backfill_tran)) {
    $log[] = "Backfilled historical supplier_name in transfer_in (" . mysqli_affected_rows($conn) . " rows).";
} else {
    $log[] = "Backfill transfer_in note: " . mysqli_error($conn);
}

echo json_encode(['status' => 'success', 'log' => $log], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
