<?php
include_once "auth_guard.php";
require_login();

include "db.php";

// Kill 226117 if still running
$q = mysqli_query($conn, "SHOW PROCESSLIST");
while ($r = mysqli_fetch_assoc($q)) {
    if ($r['Id'] == 226117) {
        mysqli_query($conn, "KILL 226117");
        echo "Killed 226117\n";
    }
}

$t0 = microtime(true);
$query = "SELECT p.prod_id, p.prod_code, p.prod_name, p.prod_cost, p.status, p.min_amount,
                 COALESCE(ext.is_ordered, 0) AS is_ordered,
                 COALESCE(ext.is_preorder, 0) AS is_preorder
          FROM product p
          LEFT JOIN product_status_ext ext ON p.prod_id = ext.prod_id
          WHERE (p.status != 'normal' OR ext.is_ordered = 1 OR ext.is_preorder = 1)
          ORDER BY 
            CASE WHEN p.status = 'danger' THEN 1 WHEN p.status = 'warning' THEN 2 ELSE 3 END, 
            COALESCE(ext.is_ordered, 0) ASC, 
            p.prod_id DESC 
          LIMIT 0, 50";
$res = mysqli_query($conn, $query);
$t1 = microtime(true);

$count = mysqli_num_rows($res);
echo "Time without img in ORDER BY: " . round(($t1 - $t0) * 1000, 2) . " ms (rows: $count)\n";

$rows = [];
while ($row = mysqli_fetch_assoc($res)) {
    $rows[] = $row['prod_id'];
}

if (count($rows) > 0) {
    $idList = implode("','", $rows);
    $t2 = microtime(true);
    // Now fetch only img for the 50 items
    $res_img = mysqli_query($conn, "SELECT prod_id, img FROM product WHERE prod_id IN ('$idList')");
    $t3 = microtime(true);
    echo "Time to fetch 50 images by ID: " . round(($t3 - $t2) * 1000, 2) . " ms\n";
}
?>
