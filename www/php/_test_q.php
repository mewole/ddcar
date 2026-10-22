<?php
require_once "db.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);

$term = 'Revo';
$limit = 5;
$sql = "SELECT p.prod_id, p.prod_code, p.prod_name, p.prod_cost, p.prod_price,
               CASE WHEN p.img LIKE 'img/%' THEN p.img WHEN LENGTH(p.img) < 200 THEN p.img ELSE '' END as img,
               (SELECT COALESCE(SUM(amount), 0) FROM warehouse WHERE prod_id = p.prod_id) as ware_amount,
               (SELECT COALESCE(SUM(amount), 0) FROM shop WHERE prod_id = p.prod_id) as shop_amount,
               (SELECT COUNT(*) FROM bundle_recipes WHERE bundle_sku COLLATE utf8_unicode_ci = p.prod_code) as bundle_component_count
        FROM product p
        WHERE p.prod_code LIKE '%$term%' OR p.prod_name LIKE '%$term%'
        LIMIT $limit";

$res = mysqli_query($conn, $sql);
if (!$res) {
    echo "SQL ERROR: " . mysqli_error($conn) . "\n";
} else {
    echo "ROWS: " . mysqli_num_rows($res) . "\n";
    while ($r = mysqli_fetch_assoc($res)) {
        echo $r['prod_id'] . " | " . $r['prod_code'] . " | " . $r['prod_name'] . "\n";
    }
}
?>
