<?php
    include "db.php";
    header("Content-Type: application/json; charset=UTF-8");

    $prod_id = isset($_POST['prod_id']) ? mysqli_real_escape_string($conn, $_POST['prod_id']) : '';

    if (empty($prod_id)) {
        echo json_encode([]);
        exit;
    }

    $output = array();

    // 1. ตรวจสอบว่าคอลัมน์ source_from มีใน stock_in_item หรือไม่ หากยังไม่มีให้สร้างอัตโนมัติ
    $checkCol = mysqli_query($conn, "SHOW COLUMNS FROM stock_in_item LIKE 'source_from'");
    if ($checkCol && mysqli_num_rows($checkCol) == 0) {
        @mysqli_query($conn, "ALTER TABLE stock_in_item ADD COLUMN source_from VARCHAR(50) NULL DEFAULT NULL AFTER amount");
    }

    // 2. ดึงประวัติราคาทุนตามร้าน (จากถูกสุดไปแพงสุด)
    $query = "SELECT 
                si.source_from AS supplier,
                MIN(si.stock_cost) AS min_cost,
                (SELECT s2.stock_cost FROM stock_in_item s2 JOIN stock_in in2 ON s2.stock_id = in2.stock_id WHERE s2.prod_id = '$prod_id' AND s2.source_from = si.source_from ORDER BY in2.date_time DESC, s2.item_id DESC LIMIT 1) AS last_cost,
                MAX(s.date_time) AS last_date,
                COUNT(si.item_id) AS order_count
              FROM stock_in_item si
              JOIN stock_in s ON si.stock_id = s.stock_id
              WHERE si.prod_id = '$prod_id' 
                AND si.source_from IS NOT NULL 
                AND si.source_from != ''
              GROUP BY si.source_from
              ORDER BY min_cost ASC, last_date DESC";

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $rank = 1;
        while ($row = mysqli_fetch_assoc($result)) {
            $row['rank'] = $rank;
            $row['is_best'] = ($rank === 1);
            $output[] = $row;
            $rank++;
        }
    }

    echo json_encode($output, JSON_UNESCAPED_UNICODE);
    mysqli_close($conn);
?>
