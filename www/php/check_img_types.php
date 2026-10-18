<?php
include "db.php";
$res = mysqli_query($conn, "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN img LIKE 'data:image%' THEN 1 ELSE 0 END) as base64_cnt,
    SUM(CASE WHEN img LIKE 'img/%' THEN 1 ELSE 0 END) as path_cnt,
    SUM(CASE WHEN img = '' OR img IS NULL THEN 1 ELSE 0 END) as empty_cnt
FROM product");
$row = mysqli_fetch_assoc($res);
echo json_encode($row);
mysqli_close($conn);
?>
