<?php
include_once "auth_guard.php";
require_login();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: text/html; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include "db.php";

$output = array();    

// Fallback defaults in case DB is offline
$default_warehouses = [
    ['ware_id' => '1', 'ware_name' => 'โกดังA'],
    ['ware_id' => '2', 'ware_name' => 'โกดังB'],
    ['ware_id' => '3', 'ware_name' => 'โกดังC'],
    ['ware_id' => '4', 'ware_name' => 'โกดังD'],
    ['ware_id' => '5', 'ware_name' => 'พระราม 5'],
];
$default_shops = [
    ['shop_id' => '6', 'shop_name' => 'หน้าร้าน']
];

if (isset($conn) && $conn) {
    mysqli_set_charset($conn, "utf8");

    $query = "SELECT ware_id, ware_name FROM warehouse_detail ORDER BY ware_id ASC";
    $result = @mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {    
        while ($row = mysqli_fetch_assoc($result)) {        
            $output[] = [
                '0'         => (string)$row['ware_id'],
                'ware_id'   => (string)$row['ware_id'],
                '1'         => $row['ware_name'],
                'ware_name' => $row['ware_name'],
                '2'         => 'โกดัง',
                'type'      => 'โกดัง'
            ];
        }   
    }

    $query2 = "SELECT shop_id, shop_name FROM shop_detail ORDER BY shop_id ASC";
    $result2 = @mysqli_query($conn, $query2);
    if ($result2 && mysqli_num_rows($result2) > 0) {    
        while ($row = mysqli_fetch_assoc($result2)) {        
            $output[] = [
                '0'         => (string)$row['shop_id'],
                'shop_id'   => (string)$row['shop_id'],
                '1'         => $row['shop_name'],
                'shop_name' => $row['shop_name'],
                '2'         => 'หน้าร้าน',
                'type'      => 'หน้าร้าน'
            ];
        }   
    }
}

// If DB had no records or query failed, use robust defaults
if (empty($output)) {
    foreach ($default_warehouses as $w) {
        $output[] = [
            '0'         => $w['ware_id'],
            'ware_id'   => $w['ware_id'],
            '1'         => $w['ware_name'],
            'ware_name' => $w['ware_name'],
            '2'         => 'โกดัง',
            'type'      => 'โกดัง'
        ];
    }
    foreach ($default_shops as $s) {
        $output[] = [
            '0'         => $s['shop_id'],
            'shop_id'   => $s['shop_id'],
            '1'         => $s['shop_name'],
            'shop_name' => $s['shop_name'],
            '2'         => 'หน้าร้าน',
            'type'      => 'หน้าร้าน'
        ];
    }
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);

if (isset($conn) && $conn) {
    @mysqli_close($conn);
}
?>