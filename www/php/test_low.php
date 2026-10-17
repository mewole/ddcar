<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Step 1: db.php... ";
include "db.php";
echo "OK\n";

echo "Step 2: folder_helper.php... ";
include_once "folder_helper.php";
echo "OK\n";

$sql = "SELECT prod_id, prod_code, prod_name, img, prod_cost, prod_price, prod_pricesend, CAST(min_amount AS SIGNED) AS min_amount
        FROM product 
        WHERE min_amount != '' AND min_amount != '0' AND min_amount IS NOT NULL";
echo "Step 3: query product... ";
$res = mysqli_query($conn, $sql);
if (!$res) {
    die("Product query failed: " . mysqli_error($conn));
}
echo "Found " . mysqli_num_rows($res) . " rows\n";

$prod_list = array();
$pids = array();
while ($r = mysqli_fetch_assoc($res)) {
    $prod_list[] = $r;
    $pids[] = intval($r['prod_id']);
}

echo "Step 4: query shop for " . count($pids) . " pids... ";
$pid_str = implode(',', $pids);
$shop_map = array();
$sql_shop = "SELECT prod_id, SUM(amount) AS qty FROM shop WHERE prod_id IN ($pid_str) GROUP BY prod_id";
$res_shop = mysqli_query($conn, $sql_shop);
if (!$res_shop) {
    die("Shop query failed: " . mysqli_error($conn));
}
echo "OK (" . mysqli_num_rows($res_shop) . " rows)\n";

echo "Step 5: query warehouse... ";
$ware_map = array();
$sql_ware = "SELECT prod_id, SUM(amount) AS qty FROM warehouse WHERE prod_id IN ($pid_str) GROUP BY prod_id";
$res_ware = mysqli_query($conn, $sql_ware);
if (!$res_ware) {
    die("Ware query failed: " . mysqli_error($conn));
}
echo "OK (" . mysqli_num_rows($res_ware) . " rows)\n";

echo "Step 6: filtering low items... ";
$items = array();
$low_pids = array();
foreach ($prod_list as $p) {
    $pid = $p['prod_id'];
    $shop_qty = isset($shop_map[$pid]) ? $shop_map[$pid] : 0;
    $ware_qty = isset($ware_map[$pid]) ? $ware_map[$pid] : 0;
    $total_qty = $shop_qty + $ware_qty;
    $min_amt = intval($p['min_amount']);

    if ($min_amt > 0 && $total_qty <= $min_amt) {
        $items[] = array(
            'prod_id' => $pid,
            'prod_code' => $p['prod_code'],
            'prod_name' => $p['prod_name'],
            'min_amount' => $min_amt,
            'total_amount' => $total_qty
        );
        $low_pids[] = $pid;
    }
}
echo "Found " . count($items) . " low stock items\n";

echo "Step 7: folder paths... ";
if (!empty($low_pids)) {
    $folder_map = get_products_folder_paths($conn, $low_pids);
    echo "Mapped " . count($folder_map) . " folders\n";
}

echo "ALL STEPS SUCCESSFUL!\n";
?>
