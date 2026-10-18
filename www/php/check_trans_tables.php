<?php
include "db.php";
$tables = [
    'sale_order', 'sale_order_item', 
    'stock_in', 'stock_in_item', 
    'stock_out', 'stock_out_item', 
    'transfer_in', 'transfer_in_item', 
    'transfer_record', 'transfer_record_item',
    'credit', 'customer'
];
$info = [];
foreach ($tables as $t) {
    // Indexes
    $res = mysqli_query($conn, "SHOW INDEX FROM `$t`");
    $keys = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $keys[] = $r['Key_name'] . '(' . $r['Column_name'] . ')';
        }
    }
    // Row count
    $rc = mysqli_query($conn, "SELECT COUNT(*) FROM `$t`");
    $cnt = ($rc && $row = mysqli_fetch_row($rc)) ? intval($row[0]) : 0;
    
    // Columns
    $col_res = mysqli_query($conn, "SHOW COLUMNS FROM `$t`");
    $cols = [];
    if ($col_res) {
        while ($c = mysqli_fetch_assoc($col_res)) {
            $cols[] = $c['Field'];
        }
    }
    
    $info[$t] = [
        'rows' => $cnt,
        'indexes' => array_values(array_unique($keys)),
        'cols' => $cols
    ];
}
echo json_encode($info, JSON_PRETTY_PRINT);
?>
