<?php
/**
 * migrate_adjustment.php
 * Ensure stock_adjustment table exists in ddcar database
 */
include "db.php";

$sql = "CREATE TABLE IF NOT EXISTS stock_adjustment (
    adj_id INT AUTO_INCREMENT PRIMARY KEY,
    adj_number VARCHAR(50) NOT NULL,
    prod_id INT NOT NULL,
    place_type VARCHAR(20) NOT NULL,
    place_id INT NOT NULL,
    place_name VARCHAR(100) NOT NULL,
    before_qty INT NOT NULL DEFAULT 0,
    adjust_qty INT NOT NULL DEFAULT 0,
    after_qty INT NOT NULL DEFAULT 0,
    reason TEXT NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    date_time DATETIME NOT NULL,
    INDEX idx_prod (prod_id),
    INDEX idx_adj_num (adj_number),
    INDEX idx_date (date_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$result = mysqli_query($conn, $sql);

if ($result) {
    echo json_encode(array("status" => "success", "message" => "Table stock_adjustment created or already exists."));
} else {
    echo json_encode(array("status" => "error", "message" => mysqli_error($conn)));
}

mysqli_close($conn);
?>
