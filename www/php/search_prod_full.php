<?php
    include "db.php";
include_once "auth_guard.php";
require_login();
    
    $phrase = mysqli_real_escape_string($conn, $_POST['phrase'] ?? '');
    $output = array();

    if (empty($phrase)) {
        echo json_encode([]);
        exit;
    }

    // 1. ค้นหาสินค้า
    $sql_prod = "SELECT * FROM product WHERE prod_name LIKE '%$phrase%' OR prod_code LIKE '%$phrase%' ORDER BY prod_code ASC";
    $res_prod = mysqli_query($conn, $sql_prod);

    if ($res_prod) {
        while ($p = mysqli_fetch_assoc($res_prod)) {
            $pid = $p['prod_id'];
            $p['locations'] = array();

            // ดึงข้อมูลโกดัง + สถานที่เก็บ
            $sql_w = "SELECT d.ware_name AS name, w.amount, p.prod_cost, p.prod_pricesend, p.prod_price,
                             GROUP_CONCAT(wp.place SEPARATOR ',') AS addr
                      FROM warehouse w
                      INNER JOIN warehouse_detail d ON w.ware_id = d.ware_id
                      INNER JOIN product p ON w.prod_id = p.prod_id
                      LEFT JOIN warehouse_place wp ON w.prod_id = wp.prod_id AND w.ware_id = wp.ware_id
                      WHERE w.prod_id = '$pid'
                      GROUP BY w.ware_id";
            $res_w = mysqli_query($conn, $sql_w);
            while ($rw = mysqli_fetch_assoc($res_w)) { $p['locations'][] = $rw; }

            // ดึงข้อมูลหน้าร้าน + สถานที่เก็บ
            $sql_s = "SELECT d.shop_name AS name, s.amount, p.prod_cost, p.prod_pricesend, p.prod_price,
                             GROUP_CONCAT(sp.place SEPARATOR ',') AS addr
                      FROM shop s
                      INNER JOIN shop_detail d ON s.shop_id = d.shop_id
                      INNER JOIN product p ON s.prod_id = p.prod_id
                      LEFT JOIN shop_place sp ON s.prod_id = sp.prod_id AND s.shop_id = sp.shop_id
                      WHERE s.prod_id = '$pid'
                      GROUP BY s.shop_id";
            $res_s = mysqli_query($conn, $sql_s);
            while ($rs = mysqli_fetch_assoc($res_s)) { $p['locations'][] = $rs; }

            $output[] = $p;
        }
        header('Content-Type: application/json');
        echo json_encode($output);
    } else {
        echo "fail";
    }
    mysqli_close($conn);
?>