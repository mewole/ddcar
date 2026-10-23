<?php
include_once "auth_guard.php";
require_login();

/**
 * get_stock_adjustments.php
 * Retrieve audit history of stock adjustments / write-offs
 */

header("Content-Type: application/json; charset=UTF-8");
include "db.php";

$start      = isset($_POST['start']) ? intval($_POST['start']) : 0;
$limit      = isset($_POST['limit']) ? intval($_POST['limit']) : 30;
$search     = isset($_POST['search']) ? trim(mysqli_real_escape_string($conn, $_POST['search'])) : '';
$date_from  = isset($_POST['date_from']) ? trim(mysqli_real_escape_string($conn, $_POST['date_from'])) : '';
$date_to    = isset($_POST['date_to']) ? trim(mysqli_real_escape_string($conn, $_POST['date_to'])) : '';
$prod_id    = isset($_POST['prod_id']) ? intval($_POST['prod_id']) : 0;

$where = array("1=1");

if (!empty($search)) {
    $where[] = "(a.adj_number LIKE '%$search%' 
             OR p.prod_name LIKE '%$search%' 
             OR p.prod_code LIKE '%$search%' 
             OR a.reason LIKE '%$search%' 
             OR a.user_name LIKE '%$search%' 
             OR a.place_name LIKE '%$search%')";
}

if (!empty($date_from) && !empty($date_to)) {
    $where[] = "DATE(a.date_time) BETWEEN '$date_from' AND '$date_to'";
} else if (!empty($date_from)) {
    $where[] = "DATE(a.date_time) >= '$date_from'";
} else if (!empty($date_to)) {
    $where[] = "DATE(a.date_time) <= '$date_to'";
}

if ($prod_id > 0) {
    $where[] = "a.prod_id = '$prod_id'";
}

$where_clause = implode(" AND ", $where);

// Total count
$count_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM stock_adjustment a INNER JOIN product p ON a.prod_id = p.prod_id WHERE $where_clause");
$total_count = 0;
if ($count_q && $row_c = mysqli_fetch_assoc($count_q)) {
    $total_count = intval($row_c['total']);
}

// Data query
$sql = "SELECT 
            a.adj_id,
            a.adj_number,
            a.prod_id,
            p.prod_name,
            p.prod_code,
            p.img,
            p.prod_cost,
            a.place_type,
            a.place_id,
            a.place_name,
            a.before_qty,
            a.adjust_qty,
            a.after_qty,
            a.reason,
            a.user_id,
            a.user_name,
            DATE_FORMAT(a.date_time, '%Y-%m-%d %H:%i') AS date_time,
            DATE_FORMAT(a.date_time, '%d/%m/%Y %H:%i') AS date_time_th
        FROM stock_adjustment a
        INNER JOIN product p ON a.prod_id = p.prod_id
        WHERE $where_clause
        ORDER BY a.adj_id DESC
        LIMIT $start, $limit";

$result = mysqli_query($conn, $sql);
$data = array();

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Resolve product image path
        if (empty($row['img']) || $row['img'] === 'img/no_img.png') {
            if (file_exists(__DIR__ . '/../img/products/' . $row['prod_id'] . '.jpg')) {
                $row['img'] = 'img/products/' . $row['prod_id'] . '.jpg';
            } else if (file_exists(__DIR__ . '/../img/products/' . $row['prod_id'] . '.png')) {
                $row['img'] = 'img/products/' . $row['prod_id'] . '.png';
            } else {
                $row['img'] = 'img/no_img.png';
            }
        }
        $data[] = $row;
    }
}

echo json_encode(array(
    "status" => "success",
    "total" => $total_count,
    "count" => count($data),
    "data" => $data
), JSON_UNESCAPED_UNICODE);

mysqli_close($conn);
?>
