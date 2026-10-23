<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "db.php";

echo "DB connected!\n";

$q_total = mysqli_query($conn, "SELECT COUNT(*) as c FROM product WHERE status != 'normal'");
if (!$q_total) { echo "Error total: " . mysqli_error($conn) . "\n"; exit; }
$r_total = mysqli_fetch_assoc($q_total);
echo "Total unfiltered: " . $r_total['c'] . "\n";

// In MariaDB, let's see how order_item stores date or if order_item has date_time
$q_cols = mysqli_query($conn, "SHOW COLUMNS FROM order_item");
$cols = [];
while ($rc = mysqli_fetch_assoc($q_cols)) {
    $cols[] = $rc['Field'];
}
echo "order_item columns: " . implode(', ', $cols) . "\n";

