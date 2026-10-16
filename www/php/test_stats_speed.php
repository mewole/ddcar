<?php
include "db.php";

$t0 = microtime(true);

$stats = [
    'total_low' => 0,
    'danger_count' => 0,
    'warning_count' => 0,
    'not_ordered_count' => 0,
    'ordered_count' => 0,
    'preorder_count' => 0
];

// Group by status on product
$q1 = mysqli_query($conn, "SELECT status, COUNT(*) AS c FROM product WHERE status != 'normal' GROUP BY status");
while ($r = mysqli_fetch_assoc($q1)) {
    if ($r['status'] == 'danger') $stats['danger_count'] = intval($r['c']);
    if ($r['status'] == 'warning') $stats['warning_count'] = intval($r['c']);
}
$stats['total_low'] = $stats['danger_count'] + $stats['warning_count'];

// Counts from product_status_ext
$q2 = mysqli_query($conn, "SELECT 
    COUNT(CASE WHEN is_ordered = 1 THEN 1 END) AS ord, 
    COUNT(CASE WHEN is_preorder = 1 THEN 1 END) AS pre 
FROM product_status_ext");
if ($q2 && $r2 = mysqli_fetch_assoc($q2)) {
    $stats['ordered_count'] = intval($r2['ord']);
    $stats['preorder_count'] = intval($r2['pre']);
}

$stats['not_ordered_count'] = max(0, $stats['total_low'] - $stats['ordered_count'] - $stats['preorder_count']);

$t1 = microtime(true);

echo json_encode([
    'stats_time_ms' => round(($t1 - $t0) * 1000, 2),
    'stats' => $stats
], JSON_PRETTY_PRINT);
?>
