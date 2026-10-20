<?php
/**
 * get_autoprod.php
 * คืนค่ารายการสินค้าทั้งหมดสำหรับ Autocomplete พร้อม Gzip Compression และ Caching
 */
if (!ob_start("ob_gzhandler")) {
    ob_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=1800'); // Cache 30 นาทีใน Browser

include "db.php";

$query = "SELECT prod_id, prod_name, prod_code, prod_price, prod_pricesend, prod_cost FROM product";     
$result = mysqli_query($conn, $query);

$output = array();
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $output[] = $row;
    }
}

if ($result) {
    echo json_encode($output);		   
} else {
    echo "fail";
}
mysqli_close($conn);
?>